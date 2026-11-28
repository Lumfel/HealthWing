<?php
/**
 * HealthWing Mobile API - Authentication
 * POST ?action=login
 * POST ?action=register
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../rate_limit.php';

hw_mobile_require_method('POST');

$action = strtolower(trim((string)($_GET['action'] ?? '')));
if (!in_array($action, ['login', 'register'], true)) {
    hw_mobile_json_response(400, [
        'success' => false,
        'error' => 'invalid_action',
        'message' => 'Valid action query parameter is required: login or register.',
    ]);
}

if ($action === 'login') {
    rate_limit_check('mobile_login', 15, 60);
    $data = hw_mobile_read_json();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'missing_credentials',
            'message' => 'username and password are required.',
        ]);
    }

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
        $stmt = $conn->prepare("SELECT Owner_ID, First_Name, Last_Name, email, Contact, username, password FROM owner WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND Deleted_At IS NULL LIMIT 1");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($owner) {
            if (!password_verify($password, (string)$owner['password'])) {
                hw_mobile_json_response(401, [
                    'success' => false,
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid username/email or password.',
                ]);
            }

            $userId = (int)$owner['Owner_ID'];
            $name = trim((string)$owner['First_Name'] . ' ' . (string)$owner['Last_Name']);
            $role = 'owner';
            $token = hw_mobile_issue_token($userId, (string)$owner['username'], $role, null);
            $wsToken = hw_generate_ws_token($role, $userId, $name !== '' ? $name : (string)$owner['username'], [], 600);

            hw_mobile_json_response(200, [
                'success' => true,
                'token' => $token,
                'ws_token' => $wsToken,
                'user' => [
                    'id' => $userId,
                    'username' => (string)$owner['username'],
                    'role' => $role,
                    'first_name' => (string)$owner['First_Name'],
                    'last_name' => (string)$owner['Last_Name'],
                    'email' => (string)$owner['email'],
                    'contact' => (string)($owner['Contact'] ?? ''),
                    'prc_license_no' => null,
                ],
            ]);
        }

        $stmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name, email, Contact, username, password, Status, Role_Level, PRC_License_No, Specialties FROM veterinarian WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND Deleted_At IS NULL LIMIT 1");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $vet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($vet) {
            if (!password_verify($password, (string)$vet['password'])) {
                hw_mobile_json_response(401, [
                    'success' => false,
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid username/email or password.',
                ]);
            }

            $vetStatus = (string)($vet['Status'] ?? '');
            if ($vetStatus !== 'Approved') {
                if ($vetStatus === 'Pending') {
                    hw_mobile_json_response(403, [
                        'success' => false,
                        'error' => 'account_pending',
                        'message' => 'Your veterinarian account is awaiting Head Veterinarian approval.',
                    ]);
                }
                hw_mobile_json_response(403, [
                    'success' => false,
                    'error' => 'account_not_approved',
                    'message' => 'Your veterinarian account is not approved for login. Please contact the clinic.',
                ]);
            }

            $role = hw_normalize_role((string)($vet['Role_Level'] ?? 'vet_staff'));
            if (!in_array($role, ['vet_staff', 'head_vet'], true)) {
                $role = 'vet_staff';
            }

            $userId = (int)$vet['Veterinarian_ID'];
            $name = trim('Dr. ' . (string)$vet['First_Name'] . ' ' . (string)$vet['Last_Name']);
            $prc = (string)($vet['PRC_License_No'] ?? '');
            $token = hw_mobile_issue_token($userId, (string)$vet['username'], $role, $prc);
            $wsToken = hw_generate_ws_token($role, $userId, $name, [], 600);

            hw_mobile_json_response(200, [
                'success' => true,
                'token' => $token,
                'ws_token' => $wsToken,
                'user' => [
                    'id' => $userId,
                    'username' => (string)$vet['username'],
                    'role' => $role,
                    'first_name' => (string)$vet['First_Name'],
                    'last_name' => (string)$vet['Last_Name'],
                    'email' => (string)$vet['email'],
                    'contact' => (string)($vet['Contact'] ?? ''),
                    'prc_license_no' => $prc,
                    'specialties' => (string)($vet['Specialties'] ?? ''),
                ],
            ]);
        }

        hw_mobile_json_response(401, [
            'success' => false,
            'error' => 'invalid_credentials',
            'message' => 'Invalid username/email or password.',
        ]);
    } finally {
        $conn->close();
    }
}

if ($action === 'register') {
    rate_limit_check('mobile_register', 10, 300);
    $data = hw_mobile_read_json();

    $privacyConsent = array_key_exists('privacy_consent', $data) ? $data['privacy_consent'] === true : false;
    if (!$privacyConsent) {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'privacy_consent_required',
            'message' => 'Informed consent under the Data Privacy Act (RA 10173) is required to register. Set privacy_consent to true.',
        ]);
    }

    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $username = trim((string)($data['username'] ?? ''));
    $rawPassword = (string)($data['password'] ?? '');
    $requestedRole = hw_normalize_role((string)($data['role'] ?? 'owner'));
    $role = ($requestedRole === 'owner') ? 'owner' : 'vet_staff';
    if (!in_array($requestedRole, ['owner', 'vet_staff'], true)) {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'invalid_role',
            'message' => 'Registration role must be owner or vet_staff.',
        ]);
    }

    $age = (isset($data['age']) && $data['age'] !== '' && $data['age'] !== null) ? (int)$data['age'] : null;
    $address = trim((string)($data['address'] ?? ''));
    $contact = trim((string)($data['contact'] ?? ''));
    $specialties = trim((string)($data['specialties'] ?? ''));
    $prcLicenseNo = strtoupper(trim((string)($data['prc_license_no'] ?? '')));

    if ($firstName === '' || $lastName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'invalid_input',
            'message' => 'Valid first_name, last_name, username, and email are required.',
        ]);
    }
    if (strlen($rawPassword) < 8) {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'weak_password',
            'message' => 'Password must be at least 8 characters.',
        ]);
    }
    if ($role === 'vet_staff' && $prcLicenseNo === '') {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'prc_license_required',
            'message' => 'PRC License Number is required for veterinarian registration.',
        ]);
    }

    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
    $timeNow = date('Y-m-d H:i:s');

    try {
        $conn = hw_get_db();
    } catch (Throwable $e) {
        hw_mobile_json_response(500, [
            'success' => false,
            'error' => 'database_unavailable',
            'message' => 'Database connection failed.',
        ]);
    }

    $lockAcquired = false;
    $txStarted = false;
    try {
        $lockAcquired = hw_db_acquire_lock($conn, 'hw_identity_lock', 5);
        if (!$lockAcquired) {
            hw_mobile_json_response(503, [
                'success' => false,
                'error' => 'registration_busy',
                'message' => 'Registration service busy. Please retry.',
            ]);
        }

        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Unable to start registration transaction.');
        }
        $txStarted = true;

        $stmt = $conn->prepare("
            SELECT username FROM owner WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
            UNION
            SELECT username FROM veterinarian WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
        ");
        $stmt->bind_param('ssss', $username, $email, $username, $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $conn->rollback();
            $txStarted = false;
            hw_mobile_json_response(409, [
                'success' => false,
                'error' => 'account_exists',
                'message' => 'Username or email is already taken.',
            ]);
        }

        if ($role === 'vet_staff') {
            $prcStmt = $conn->prepare('SELECT Veterinarian_ID FROM veterinarian WHERE UPPER(PRC_License_No) = UPPER(?) LIMIT 1');
            $prcStmt->bind_param('s', $prcLicenseNo);
            $prcStmt->execute();
            $prcExists = $prcStmt->get_result()->num_rows > 0;
            $prcStmt->close();
            if ($prcExists) {
                $conn->rollback();
                $txStarted = false;
                hw_mobile_json_response(409, [
                    'success' => false,
                    'error' => 'prc_license_exists',
                    'message' => 'PRC License Number is already registered.',
                ]);
            }
        }

        if ($role === 'owner') {
            $stmt = $conn->prepare('INSERT INTO owner (First_Name, Last_Name, Age, Address, Contact, username, email, password, TimeAndDateAdded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssissssss', $firstName, $lastName, $age, $address, $contact, $username, $email, $passwordHash, $timeNow);
        } else {
            $stmt = $conn->prepare("INSERT INTO veterinarian (First_Name, Last_Name, Age, Address, Contact, Specialties, Role_Level, PRC_License_No, username, email, password, TimeAndDateAdded, Status) VALUES (?, ?, ?, ?, ?, ?, 'vet_staff', ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param('ssissssssss', $firstName, $lastName, $age, $address, $contact, $specialties, $prcLicenseNo, $username, $email, $passwordHash, $timeNow);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Could not create account.');
        }

        $insertId = (int)$stmt->insert_id;
        $stmt->close();
        $conn->commit();
        $txStarted = false;

        hw_log_consent(
            $conn,
            $insertId,
            $role,
            'registration_privacy_consent_ra10173',
            $role === 'owner' ? 'owner_registration' : 'vet_registration',
            (string)$insertId
        );

        hw_mobile_json_response(201, [
            'success' => true,
            'message' => $role === 'owner'
                ? 'Owner account created successfully.'
                : 'Veterinarian account created and pending Head Veterinarian approval.',
            'user' => [
                'id' => $insertId,
                'username' => $username,
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'contact' => $contact,
                'prc_license_no' => $role === 'vet_staff' ? $prcLicenseNo : null,
                'status' => $role === 'owner' ? 'Active' : 'Pending',
            ],
        ]);
    } catch (Throwable $e) {
        if ($txStarted) {
            @$conn->rollback();
        }
        hw_mobile_json_response(500, [
            'success' => false,
            'error' => 'registration_failed',
            'message' => 'Could not create account.',
        ]);
    } finally {
        if ($lockAcquired) {
            hw_db_release_lock($conn, 'hw_identity_lock');
        }
        $conn->close();
    }
}
