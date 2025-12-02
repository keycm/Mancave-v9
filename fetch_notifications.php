<?php
session_start();
include 'config.php';

// Ensure user_id is set
if (!isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $u = mysqli_real_escape_string($conn, $_SESSION['username']);
    $res = mysqli_query($conn, "SELECT id FROM users WHERE username='$u' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $_SESSION['user_id'] = $row['id'];
    }
}

$uid = $_SESSION['user_id'] ?? 0;

// Fetch last 10 notifications (read + unread)
$sql = "SELECT id, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = $uid 
        ORDER BY created_at DESC 
        LIMIT 10";
$result = mysqli_query($conn, $sql);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

// Count unread notifications
$countSql = "SELECT COUNT(*) as unread_count 
             FROM notifications 
             WHERE user_id = $uid AND is_read = 0";
$countRes = mysqli_query($conn, $countSql);
$countRow = mysqli_fetch_assoc($countRes);
$unreadCount = $countRow['unread_count'] ?? 0;

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);
