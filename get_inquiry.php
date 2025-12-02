<?php
header('Content-Type: application/json');
require 'config.php';

try {
    if (!isset($_GET['id'])) {
        throw new Exception("No ID provided");
    }

    $id = intval($_GET['id']);

    // Prepare query with LEFT JOIN in case user is missing
    $stmt = $conn->prepare("
        SELECT i.*, u.username 
        FROM inquiries i 
        LEFT JOIN users u ON i.username = u.username
        WHERE i.id = ?
    ");
    
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        throw new Exception("Query failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Mark as read
        $update = $conn->prepare("UPDATE inquiries SET status='read' WHERE id=?");
        $update->bind_param("i", $id);
        $update->execute();

        echo json_encode([
            "id" => $row['id'],
            "username" => $row['username'] ?? 'Unknown',
            "email" => $row['email'] ?? '',
            "mobile" => $row['mobile'] ?? '',
            "message" => $row['message'] ?? '',
            "attachment" => $row['attachment'] ?? '',
            "status" => $row['status'] ?? '',
            "created_at" => $row['created_at'] ?? ''
        ]);
    } else {
        throw new Exception("Inquiry not found");
    }
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
