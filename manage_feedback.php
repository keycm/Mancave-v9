<?php
include 'config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'delete_rating') {
    $id = intval($_POST['id']);
    
    // 1. Fetch Rating Data
    $res = $conn->query("SELECT * FROM ratings WHERE id=$id");
    if ($row = $res->fetch_assoc()) {
        
        // 2. Prepare Data for Restore (JSON)
        // We use the review text as the display title, followed by the JSON data
        $displayTitle = "Rating ID: $id";
        $jsonData = json_encode($row);
        $trashName = $displayTitle . '|' . $jsonData;

        // 3. Insert into Trash Bin
        $stmt = $conn->prepare("INSERT INTO trash_bin (item_id, item_name, source, deleted_at) VALUES (?, ?, 'ratings', NOW())");
        $stmt->bind_param("is", $id, $trashName);
        $stmt->execute();
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Rating not found']);
        exit;
    }

    // 4. Delete Actual Rating
    if ($conn->query("DELETE FROM ratings WHERE id=$id")) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>