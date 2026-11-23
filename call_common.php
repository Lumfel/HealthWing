<?php
/**
 * Shared helpers for the direct call / video call feature.
 * Included by every call_*.php and toggle_call_feature.php endpoint.
 */

require_once __DIR__ . '/rate_limit.php'; // gives us rate_limit_log_event() for the Network Monitor

if (session_status() === PHP_SESSION_NONE) {
    // If we're being served over https (either directly, or via the
    // Cloudflare Tunnel / reverse proxy header), make sure the session
    // cookie is only ever sent back over https too.
    $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($onHttps) {
        session_set_cookie_params(['secure'=> true, 'httponly'=> true, 'samesite'=>'Lax']);
    }
    require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
}

/**
 * Returns 'owner', 'vet', or null based on the current session.
 */
function call_current_role(): ?string
{
    $role = $_SESSION['role'] ?? '';
    if ($role === 'owner') return 'owner';
    if ($role === 'Veterinarian') return 'vet';
    return null;
}

/** Sends a JSON error response and stops execution. */
function call_fail(string $message, int $httpCode = 400): void
{
    http_response_code($httpCode);
    echo json_encode(['success'=> false, 'message'=>$message]);
    exit();
}

/** Looks up the logged-in owner's Owner_ID, or null. */
function call_lookup_owner_id(mysqli $conn, string $username): ?int
{
    $stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['Owner_ID'] : null;
}

/** Looks up the logged-in vet's Veterinarian_ID, or null. */
function call_lookup_vet_id(mysqli $conn, string $username): ?int
{
    $stmt = $conn->prepare('SELECT Veterinarian_ID FROM veterinarian WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['Veterinarian_ID'] : null;
}

/**
 * Loads an emergency_booking row and checks the current user is allowed
 * to touch it: a vet can act on any booking, an owner only their own.
 * Returns the row (with Call_Enabled etc.) or null if not allowed / not found.
 */
function call_load_authorized_booking(mysqli $conn, int $bookingId, string $role, ?int $ownerId): ?array
{
    $stmt = $conn->prepare(
        "SELECT Booking_ID, Owner_ID, Status, Call_Enabled FROM emergency_booking WHERE Booking_ID = ? LIMIT 1"
    );
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;
    if ($role === 'owner'&& (int)$row['Owner_ID'] !== (int)$ownerId) return null;
    return $row;
}

/**
 * Loads a call_session row and verifies the current session (owner or vet)
 * is one of the two participants. Returns the row, or null if not allowed.
 */
function call_load_authorized_session(mysqli $conn, int $callId, string $role, ?int $ownerId, ?int $vetId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM call_session WHERE Call_ID = ? LIMIT 1");
    $stmt->bind_param('i', $callId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;
    if ($role === 'owner'&& (int)$row['Owner_ID'] !== (int)$ownerId) return null;
    if ($role === 'vet'&& $row['Veterinarian_ID'] !== null && (int)$row['Veterinarian_ID'] !== (int)$vetId) return null;
    return $row;
}
