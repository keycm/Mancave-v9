<?php
include 'config.php';
session_start();

// Assuming you store logged-in user's ID in session
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode([]);
    exit;
}

// Fetch notifications for the user
$sql = "SELECT id, service, status, preferred_date 
        FROM bookings 
        WHERE user_id = ? AND status IN ('approved','rejected') 
        ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = [
        'id' => $row['id'],
        'message' => "Your booking for '{$row['service']}' on {$row['preferred_date']} was {$row['status']}.",
        'status' => $row['status']
    ];
}

echo json_encode($notifications);
