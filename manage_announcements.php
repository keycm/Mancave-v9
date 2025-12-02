<?php
session_start();
include 'config.php';
header('Content-Type: application/json');

// --- SECURITY CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';

    if (empty($title) || empty($content)) {
        throw new Exception('Please fill in both title and content.');
    }

    // Insert the new announcement. The homepage will automatically show the newest one.
    $stmt = $conn->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
    $stmt->bind_param("ss", $title, $content);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save announcement: ' . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>