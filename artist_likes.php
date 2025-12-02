<?php
session_start();
include 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$artist_id = intval($_POST['artist_id'] ?? 0);

if ($artist_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Artist ID']);
    exit;
}

try {
    // Check if already liked
    $check = $conn->prepare("SELECT id FROM artist_likes WHERE user_id = ? AND artist_id = ?");
    $check->bind_param("ii", $user_id, $artist_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        // Unlike
        $del = $conn->prepare("DELETE FROM artist_likes WHERE user_id = ? AND artist_id = ?");
        $del->bind_param("ii", $user_id, $artist_id);
        $del->execute();
        $liked = false;
    } else {
        // Like
        $ins = $conn->prepare("INSERT INTO artist_likes (user_id, artist_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $artist_id);
        $ins->execute();
        $liked = true;
    }

    // Get new count
    $count_sql = $conn->prepare("SELECT COUNT(*) FROM artist_likes WHERE artist_id = ?");
    $count_sql->bind_param("i", $artist_id);
    $count_sql->execute();
    $new_count = $count_sql->get_result()->fetch_row()[0];

    echo json_encode(['success' => true, 'liked' => $liked, 'new_count' => $new_count]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>