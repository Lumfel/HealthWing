<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'Veterinarian') {
    http_response_code(401);
    echo json_encode(['success'=> false, 'message'=>'Not logged in as a veterinarian.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
    exit();
}

// Combines two very different record types — checkups (tied to a specific
// pet) and emergency bookings (tied to an owner or a guest, not a pet) —
// into one unified timeline so a vet can see everything about a client
// and their pets in one place, instead of checking two separate tables.
$sql = "
    SELECT 'Checkup'AS Type, o.First_Name AS OwnerFirst, o.Last_Name AS OwnerLast,
           p.Pet_Name AS PetName, CONCAT(v.First_Name, '', v.Last_Name) AS VetName,
           c.Checkup_Date AS EventDate, c.Notes AS Details
    FROM pet_checkup c
    JOIN pet p ON c.Pet_ID = p.Pet_ID
    JOIN owner o ON p.Owner_ID = o.Owner_ID
    LEFT JOIN veterinarian v ON c.Veterinarian_ID = v.Veterinarian_ID

    UNION ALL

    SELECT 'Emergency'AS Type,
           COALESCE(o.First_Name, e.Name) AS OwnerFirst,
           COALESCE(o.Last_Name, '') AS OwnerLast,
           '—'AS PetName, '—'AS VetName,
           e.Created_At AS EventDate, e.Additional_Details AS Details
    FROM emergency_booking e
    LEFT JOIN owner o ON e.Owner_ID = o.Owner_ID

    ORDER BY EventDate DESC
    LIMIT 200
";

$result = $conn->query($sql);
$history = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

echo json_encode(['success'=> true, 'history'=>$history]);
