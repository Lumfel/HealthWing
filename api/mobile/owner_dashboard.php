<?php
/**
 * HealthWing Mobile API - Owner Dashboard
 * GET /api/mobile/owner_dashboard.php
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/receipt_blockchain.php';

hw_mobile_require_method('GET');
$identity = hw_mobile_require_role(['owner']);

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    hw_mobile_json_response(500, [
        'success' => false,
        'error' => 'database_unavailable',
        'message' => 'Database connection failed.',
    ]);
}

try {
    $ownerId = (int)$identity['user_id'];
    $ownerStmt = $conn->prepare('SELECT Owner_ID, First_Name, Last_Name, username, email, Contact FROM owner WHERE Owner_ID = ? AND Deleted_At IS NULL LIMIT 1');
    $ownerStmt->bind_param('i', $ownerId);
    $ownerStmt->execute();
    $owner = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if (!$owner) {
        hw_mobile_json_response(404, [
            'success' => false,
            'error' => 'owner_not_found',
            'message' => 'Owner account not found.',
        ]);
    }

    $petsStmt = $conn->prepare(
        "SELECT Pet_ID, Pet_Name, Pet_Type, Species, Age, Weight_Kg, Photo_Path, TimeAndDateAdded
         FROM pet
         WHERE Owner_ID = ? AND Deleted_At IS NULL
         ORDER BY Pet_ID DESC"
    );
    $petsStmt->bind_param('i', $ownerId);
    $petsStmt->execute();
    $activePets = $petsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $petsStmt->close();

    $schedStmt = $conn->prepare(
        "SELECT s.Schedule_ID, s.Pet_ID, p.Pet_Name, s.Services, s.Appointment_Time, s.End_Time,
                s.Duration_Minutes, s.Status, s.Room_ID, rm.Room_Name, s.Veterinarian_ID,
                CONCAT('Dr. ', v.First_Name, ' ', v.Last_Name) AS Veterinarian_Name,
                v.PRC_License_No AS Veterinarian_PRC, s.Notes, s.Preempted_By_Emergency_ID,
                s.Previous_Appointment_Time
         FROM schedule s
         LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
         LEFT JOIN room rm ON s.Room_ID = rm.Room_ID
         LEFT JOIN veterinarian v ON s.Veterinarian_ID = v.Veterinarian_ID
         WHERE s.Owner_ID = ?
           AND s.Deleted_At IS NULL
           AND s.Status IN ('Pending', 'Confirmed', 'In Progress')
           AND COALESCE(s.End_Time, s.Appointment_Time) >= NOW()
         ORDER BY s.Appointment_Time ASC
         LIMIT 50"
    );
    $schedStmt->bind_param('i', $ownerId);
    $schedStmt->execute();
    $upcomingSchedules = $schedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $schedStmt->close();

    $emStmt = $conn->prepare(
        "SELECT e.Booking_ID, e.Pet_ID, p.Pet_Name, e.Status, e.Severity_Level, e.Priority_Score,
                e.Queue_Position, e.Assigned_Vet_ID,
                CONCAT('Dr. ', v.First_Name, ' ', v.Last_Name) AS Assigned_Vet_Name,
                v.PRC_License_No AS Assigned_Vet_PRC,
                e.Assigned_Room_ID, rm.Room_Name AS Assigned_Room_Name,
                e.Triage_Rationale, e.Created_At, e.Call_Enabled
         FROM emergency_booking e
         LEFT JOIN pet p ON e.Pet_ID = p.Pet_ID
         LEFT JOIN veterinarian v ON e.Assigned_Vet_ID = v.Veterinarian_ID
         LEFT JOIN room rm ON e.Assigned_Room_ID = rm.Room_ID
         WHERE e.Owner_ID = ?
           AND e.Deleted_At IS NULL
           AND e.Status IN ('Pending', 'In Progress')
         ORDER BY COALESCE(e.Queue_Position, 999999) ASC, e.Created_At DESC"
    );
    $emStmt->bind_param('i', $ownerId);
    $emStmt->execute();
    $activeEmergencies = $emStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $emStmt->close();

    $purged = hw_receipt_purge_expired($conn);
    $receiptStmt = $conn->prepare(
        "SELECT Block_ID, Block_Index, Receipt_Code, Reference_Type, Reference_ID, Service_Summary,
                Amount, Currency, Status, Block_Hash, Prev_Hash, Merkle_Root, Issued_By,
                Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Created_At, Expires_At
         FROM receipt_blocks
         WHERE Owner_ID = ?
           AND Status = 'ACTIVE'
           AND Expires_At > NOW()
         ORDER BY Block_Index DESC
         LIMIT 100"
    );
    $receiptStmt->bind_param('i', $ownerId);
    $receiptStmt->execute();
    $activeReceipts = $receiptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $receiptStmt->close();

    hw_mobile_json_response(200, [
        'success' => true,
        'owner' => [
            'id' => (int)$owner['Owner_ID'],
            'username' => (string)$owner['username'],
            'first_name' => (string)$owner['First_Name'],
            'last_name' => (string)$owner['Last_Name'],
            'email' => (string)$owner['email'],
            'contact' => (string)($owner['Contact'] ?? ''),
        ],
        'active_pets' => array_map(static function (array $pet): array {
            return [
                'pet_id' => (int)$pet['Pet_ID'],
                'pet_name' => (string)$pet['Pet_Name'],
                'pet_type' => (string)$pet['Pet_Type'],
                'species' => $pet['Species'] !== null ? (string)$pet['Species'] : null,
                'age' => $pet['Age'] !== null ? (int)$pet['Age'] : null,
                'weight_kg' => $pet['Weight_Kg'] !== null ? (float)$pet['Weight_Kg'] : null,
                'photo_path' => $pet['Photo_Path'] !== null ? (string)$pet['Photo_Path'] : null,
                'created_at' => (string)$pet['TimeAndDateAdded'],
            ];
        }, $activePets),
        'upcoming_schedules' => array_map(static function (array $row): array {
            return [
                'schedule_id' => (int)$row['Schedule_ID'],
                'pet_id' => $row['Pet_ID'] !== null ? (int)$row['Pet_ID'] : null,
                'pet_name' => $row['Pet_Name'] !== null ? (string)$row['Pet_Name'] : null,
                'services' => (string)$row['Services'],
                'appointment_time' => (string)$row['Appointment_Time'],
                'end_time' => $row['End_Time'] !== null ? (string)$row['End_Time'] : null,
                'duration_minutes' => (int)$row['Duration_Minutes'],
                'status' => (string)$row['Status'],
                'room_id' => $row['Room_ID'] !== null ? (int)$row['Room_ID'] : null,
                'room_name' => $row['Room_Name'] !== null ? (string)$row['Room_Name'] : null,
                'veterinarian_id' => $row['Veterinarian_ID'] !== null ? (int)$row['Veterinarian_ID'] : null,
                'veterinarian_name' => $row['Veterinarian_Name'] !== null ? trim((string)$row['Veterinarian_Name']) : null,
                'veterinarian_prc_license_no' => $row['Veterinarian_PRC'] !== null ? (string)$row['Veterinarian_PRC'] : null,
                'notes' => $row['Notes'] !== null ? (string)$row['Notes'] : null,
                'preempted_by_emergency_id' => $row['Preempted_By_Emergency_ID'] !== null ? (int)$row['Preempted_By_Emergency_ID'] : null,
                'previous_appointment_time' => $row['Previous_Appointment_Time'] !== null ? (string)$row['Previous_Appointment_Time'] : null,
            ];
        }, $upcomingSchedules),
        'active_emergency_triage_status' => array_map(static function (array $row): array {
            return [
                'booking_id' => (int)$row['Booking_ID'],
                'pet_id' => $row['Pet_ID'] !== null ? (int)$row['Pet_ID'] : null,
                'pet_name' => $row['Pet_Name'] !== null ? (string)$row['Pet_Name'] : null,
                'status' => (string)$row['Status'],
                'severity_level' => (string)$row['Severity_Level'],
                'priority_score' => (float)$row['Priority_Score'],
                'queue_position' => $row['Queue_Position'] !== null ? (int)$row['Queue_Position'] : null,
                'assigned_vet_id' => $row['Assigned_Vet_ID'] !== null ? (int)$row['Assigned_Vet_ID'] : null,
                'assigned_vet_name' => $row['Assigned_Vet_Name'] !== null ? trim((string)$row['Assigned_Vet_Name']) : null,
                'assigned_vet_prc_license_no' => $row['Assigned_Vet_PRC'] !== null ? (string)$row['Assigned_Vet_PRC'] : null,
                'assigned_room_id' => $row['Assigned_Room_ID'] !== null ? (int)$row['Assigned_Room_ID'] : null,
                'assigned_room_name' => $row['Assigned_Room_Name'] !== null ? (string)$row['Assigned_Room_Name'] : null,
                'triage_rationale' => $row['Triage_Rationale'] !== null ? (string)$row['Triage_Rationale'] : null,
                'call_enabled' => (int)$row['Call_Enabled'] === 1,
                'created_at' => (string)$row['Created_At'],
            ];
        }, $activeEmergencies),
        'active_receipt_blocks' => array_map(static function (array $row): array {
            return [
                'block_id' => (int)$row['Block_ID'],
                'block_index' => (int)$row['Block_Index'],
                'receipt_code' => (string)$row['Receipt_Code'],
                'reference_type' => (string)$row['Reference_Type'],
                'reference_id' => $row['Reference_ID'] !== null ? (int)$row['Reference_ID'] : null,
                'service_summary' => (string)$row['Service_Summary'],
                'amount' => (float)$row['Amount'],
                'currency' => (string)$row['Currency'],
                'status' => (string)$row['Status'],
                'block_hash' => (string)$row['Block_Hash'],
                'prev_hash' => (string)$row['Prev_Hash'],
                'merkle_root' => (string)$row['Merkle_Root'],
                'issued_by' => (string)$row['Issued_By'],
                'attending_vet_id' => (int)$row['Attending_Vet_ID'],
                'attending_vet_name' => (string)$row['Attending_Vet_Name'],
                'attending_clinician_prc' => (string)$row['Attending_Clinician_PRC'],
                'created_at' => (string)$row['Created_At'],
                'expires_at' => (string)$row['Expires_At'],
            ];
        }, $activeReceipts),
        'meta' => [
            'purged_expired_receipts' => $purged,
            'server_time' => date('Y-m-d H:i:s'),
        ],
    ]);
} finally {
    $conn->close();
}
