<?php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';
session_start();

function send($arr) { echo json_encode($arr); exit; }

// fallback: resolve user_id from username if needed
if (!isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $u = mysqli_real_escape_string($conn, $_SESSION['username']);
    $res = mysqli_query($conn, "SELECT id FROM users WHERE username='$u' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $_SESSION['user_id'] = $row['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send(['status'=>'error','message'=>'Invalid request method']);
if (!isset($_SESSION['user_id'])) send(['status'=>'error','message'=>'Unauthorized']);

$userId = intval($_SESSION['user_id']);

// update all notifications for this user
$sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) send(['status'=>'error','message'=>'Prepare failed','debug'=>mysqli_error($conn)]);
mysqli_stmt_bind_param($stmt, "i", $userId);
$ok = mysqli_stmt_execute($stmt);
if (!$ok) send(['status'=>'error','message'=>'Execute failed','debug'=>mysqli_stmt_error($stmt)]);
$updated = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

// success even if 0 rows (means nothing to mark)
send(['status'=>'success','message'=>'Marked all as read','rows_updated'=>$updated]);
