<?php
require 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $notifId = intval($_POST['id']);
    $userId  = $_SESSION['user_id'];

    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $notifId, $userId);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
?>
