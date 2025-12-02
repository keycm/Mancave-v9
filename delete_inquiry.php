<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request method']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['status'=>'error','message'=>'Missing inquiry ID']);
    exit;
}

$id = intval($_POST['id']);
$conn->begin_transaction();

try {
    // Fetch the inquiry
    $stmt = $conn->prepare("SELECT * FROM inquiries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new Exception("Inquiry not found");
    $inquiry = $res->fetch_assoc();
    $stmt->close();

    // Encode full inquiry data as JSON (Added 'mobile')
    $jsonData = json_encode([
        'username'   => $inquiry['username'],
        'email'      => $inquiry['email'],
        'mobile'     => $inquiry['mobile'], // <--- ADDED THIS
        'message'    => $inquiry['message'],
        'attachment' => $inquiry['attachment'],
        'status'     => $inquiry['status'],
        'created_at' => $inquiry['created_at']
    ]);

    // Combine username + JSON for restore
    $combined = $inquiry['username'] . '|' . $jsonData;

    // Insert into trash_bin
    $stmt = $conn->prepare("
        INSERT INTO trash_bin (item_id, item_name, source, deleted_at)
        VALUES (?, ?, 'inquiries', NOW())
    ");
    $stmt->bind_param("is", $id, $combined);
    if (!$stmt->execute()) throw new Exception("Insert into trash_bin failed: " . $stmt->error);
    $stmt->close();

    // Delete from inquiries
    $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) throw new Exception("Delete from inquiries failed: " . $stmt->error);
    $stmt->close();

    $conn->commit();
    echo json_encode(['status'=>'success']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>