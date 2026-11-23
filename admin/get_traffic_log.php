<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}

// Auto-expire old traffic log entries every time this page is checked —
// no server task scheduler or MySQL event needed. Allowed (successful)
// requests are kept for 1 day; blocked (rate-limited/failed) requests are
// kept longer, 2 days, since those are the more security-relevant ones.
$conn->query("DELETE FROM traffic_log WHERE Status = 'allowed'AND Created_At < (NOW() - INTERVAL 1 DAY)");
$conn->query("DELETE FROM traffic_log WHERE Status = 'blocked'AND Created_At < (NOW() - INTERVAL 2 DAY)");

// Optional: only return events newer than a given Log_ID, so the admin
// page can poll efficiently and only fetch what's new since last check.
$sinceId = (int)($_GET['sinceId'] ?? 0);

$sql = "SELECT Log_ID, IP_Address, Action, Status, Created_At
        FROM traffic_log
        WHERE Log_ID >?
        ORDER BY Log_ID DESC
        LIMIT 200";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sinceId);
$stmt->execute();
$result = $stmt->get_result();
$events = $result->fetch_all(MYSQLI_ASSOC);

// Summary counts for the last hour
$summarySql = "SELECT Action, Status, COUNT(*) AS total
               FROM traffic_log
               WHERE Created_At >= (NOW() - INTERVAL 1 HOUR)
               GROUP BY Action, Status";
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult ? $summaryResult->fetch_all(MYSQLI_ASSOC) : [];

$blockedCountResult = $conn->query("SELECT COUNT(*) AS c FROM traffic_log WHERE Status = 'blocked'AND Created_At >= (NOW() - INTERVAL 1 HOUR)");
$blockedLastHour = $blockedCountResult ? (int)$blockedCountResult->fetch_assoc()['c'] : 0;

$totalLastHourResult = $conn->query("SELECT COUNT(*) AS c FROM traffic_log WHERE Created_At >= (NOW() - INTERVAL 1 HOUR)");
$totalLastHour = $totalLastHourResult ? (int)$totalLastHourResult->fetch_assoc()['c'] : 0;

echo json_encode([
    'success'=> true,
    'events'=>$events,
    'summary'=>$summary,
    'blocked_last_hour'=>$blockedLastHour,
    'total_last_hour'=>$totalLastHour
]);

$stmt->close();
$conn->close();
