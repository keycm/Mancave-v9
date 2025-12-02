<?php
include 'config.php';
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';

    // =========================================================
    // SAVE ARTIST (ADD or UPDATE)
    // =========================================================
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? ''); // Trim removes extra spaces
        $style = $_POST['style'] ?? '';
        $quote = $_POST['quote'] ?? '';
        $bio = $_POST['bio'] ?? '';
        
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = time() . '_artist_' . basename($_FILES['image']['name']);
            if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                $imagePath = $fileName;
            }
        }

        // --- STEP 1: If updating, fetch the OLD NAME first ---
        $oldName = "";
        if ($id) {
            $fetchStmt = $conn->prepare("SELECT name FROM artists WHERE id = ?");
            $fetchStmt->bind_param("i", $id);
            $fetchStmt->execute();
            $fetchStmt->bind_result($oldName);
            $fetchStmt->fetch();
            $fetchStmt->close(); // Important: Close connection before next query
        }

        // --- STEP 2: Update the Artist Table ---
        if ($id) {
            $sql = "UPDATE artists SET name=?, style=?, quote=?, bio=?";
            if ($imagePath) $sql .= ", image_path=?";
            $sql .= " WHERE id=?";
            
            $stmt = $conn->prepare($sql);
            if ($imagePath) $stmt->bind_param("sssssi", $name, $style, $quote, $bio, $imagePath, $id);
            else $stmt->bind_param("ssssi", $name, $style, $quote, $bio, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO artists (name, style, quote, bio, image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $style, $quote, $bio, $imagePath);
        }

        if ($stmt->execute()) {
            $stmt->close(); // Close artist update query

            // --- STEP 3: LOGIC TO UPDATE INVENTORY (ARTWORKS) ---
            // If we have an ID, an Old Name, and the New Name is different...
            if ($id && !empty($oldName) && $oldName !== $name) {
                
                // Update all artworks that currently have the Old Name
                $updateArt = $conn->prepare("UPDATE artworks SET artist = ? WHERE artist = ?");
                $updateArt->bind_param("ss", $name, $oldName);
                $updateArt->execute();
                $updateArt->close();
            }

            echo json_encode(['success' => true]);
        } else {
            throw new Exception($stmt->error);
        }
        exit;
    }

    // =========================================================
    // DELETE ARTIST
    // =========================================================
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        $res = $conn->query("SELECT * FROM artists WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            $trashName = $row['name'] . '|' . json_encode($row);
            
            $stmt = $conn->prepare("INSERT INTO trash_bin (item_id, item_name, source, deleted_at) VALUES (?, ?, 'artists', NOW())");
            $stmt->bind_param("is", $id, $trashName);
            $stmt->execute();
            $stmt->close();
            
            $conn->query("DELETE FROM artists WHERE id=$id");
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Artist not found");
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>