<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$conn->query("DELETE FROM traffic_log WHERE Status = 'allowed' AND Created_At < (NOW() - INTERVAL 1 DAY)");
$conn->query("DELETE FROM traffic_log WHERE Status = 'blocked' AND Created_At < (NOW() - INTERVAL 2 DAY)");

$sinceId = (int)($_GET['sinceId'] ?? 0);

$sql = "SELECT Log_ID, IP_Address, Action, Status, Created_At
        FROM traffic_log
        WHERE Log_ID > ?
        ORDER BY Log_ID DESC
        LIMIT 200";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sinceId);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$summarySql = "SELECT Action, Status, COUNT(*) AS total
               FROM traffic_log
               WHERE Created_At >= (NOW() - INTERVAL 1 HOUR)
               GROUP BY Action, Status";
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult ? $summaryResult->fetch_all(MYSQLI_ASSOC) : [];

$blockedCountResult = $conn->query("SELECT COUNT(*) AS c FROM traffic_log WHERE Status = 'blocked' AND Created_At >= (NOW() - INTERVAL 1 HOUR)");
$blockedLastHour = $blockedCountResult ? (int)$blockedCountResult->fetch_assoc()['c'] : 0;

$totalLastHourResult = $conn->query("SELECT COUNT(*) AS c FROM traffic_log WHERE Created_At >= (NOW() - INTERVAL 1 HOUR)");
$totalLastHour = $totalLastHourResult ? (int)$totalLastHourResult->fetch_assoc()['c'] : 0;

echo json_encode([
    'success' => true,
    'events' => $events,
    'summary' => $summary,
    'blocked_last_hour' => $blockedLastHour,
    'total_last_hour' => $totalLastHour,
]);

$stmt->close();
$conn->close();
