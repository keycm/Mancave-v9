<?php
include 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'add' || $action === 'update') {
    $title = $_POST['title'] ?? '';
    $artist = $_POST['artist'] ?? '';
    $price = $_POST['price'] ?? 0;
    $desc = $_POST['description'] ?? '';
    
    // New Fields
    $category = $_POST['category'] ?? '';
    $medium = $_POST['medium'] ?? '';
    $year = !empty($_POST['year']) ? $_POST['year'] : NULL;
    $size = $_POST['size'] ?? '';
    
    // Status Handling: Default to Available for new, keep existing for update if missing
    $status = $_POST['status'] ?? 'Available'; 
    
    $id = $_POST['id'] ?? null;

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
            $imagePath = $fileName;
        }
    }

    if ($action === 'add') {
        // INSERT with new fields
        $sql = "INSERT INTO artworks (title, artist, category, medium, year, size, description, price, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // s=string, i=int, d=double (price)
        $stmt->bind_param("ssssissdss", $title, $artist, $category, $medium, $year, $size, $desc, $price, $status, $imagePath);
    } else {
        // UPDATE with new fields
        $sql = "UPDATE artworks SET title=?, artist=?, category=?, medium=?, year=?, size=?, description=?, price=?";
        
        // Only update status if it was sent (prevents overwriting 'Sold' with 'Available' unintentionally)
        if(isset($_POST['status'])) {
            $sql .= ", status=?";
        }
        
        if ($imagePath) {
            $sql .= ", image_path=?";
        }
        $sql .= " WHERE id=?";
        
        $stmt = $conn->prepare($sql);
        
        // Dynamic binding based on what fields are present
        if ($imagePath && isset($_POST['status'])) {
            $stmt->bind_param("ssssissdssi", $title, $artist, $category, $medium, $year, $size, $desc, $price, $status, $imagePath, $id);
        } elseif ($imagePath) {
            $stmt->bind_param("ssssissdsi", $title, $artist, $category, $medium, $year, $size, $desc, $price, $imagePath, $id);
        } elseif (isset($_POST['status'])) {
            $stmt->bind_param("ssssissdsi", $title, $artist, $category, $medium, $year, $size, $desc, $price, $status, $id);
        } else {
            $stmt->bind_param("ssssissdi", $title, $artist, $category, $medium, $year, $size, $desc, $price, $id);
        }
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'];
    
    // Fetch data for trash bin
    $res = $conn->query("SELECT * FROM artworks WHERE id=$id");
    if ($res && $row = $res->fetch_assoc()) {
        $name = $row['title'] . "|" . json_encode($row);
        
        // Escape properly for direct query or use prepare (safer to use prepare here too)
        $stmt = $conn->prepare("INSERT INTO trash_bin (item_id, item_name, source, deleted_at) VALUES (?, ?, 'artworks', NOW())");
        $stmt->bind_param("is", $id, $name);
        $stmt->execute();
        $stmt->close();
        
        $conn->query("DELETE FROM artworks WHERE id=$id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Artwork not found']);
    }
    exit;
}
?>