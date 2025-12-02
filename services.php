<?php
include 'config.php';
$action = $_GET['action'] ?? '';

if ($action === 'list') {
    header('Content-Type: application/json');

    // Pagination setup
    $servicesPerPage = 5; // change if needed
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $servicesPerPage;

    $totalResult = $conn->query("SELECT COUNT(*) as total FROM services");
    $totalServices = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalServices / $servicesPerPage);

    $stmt = $conn->prepare("SELECT * FROM services ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $servicesPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    echo json_encode([
        'services' => $services,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
    exit;
}

if ($action === 'add') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $imagePath = '';

    if (!$name || !$description || !$price || !$duration || !isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $uploadDir = 'uploads/';
    $filename = time() . '_' . basename($_FILES['image']['name']);
    $targetFile = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        $imagePath = $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Image upload failed.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO services (name, description, price, duration, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $name, $description, $price, $duration, $imagePath);
    $result = $stmt->execute();

    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'update') {
    header('Content-Type: application/json');
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];

    if (!$id || !$name || !$description || !$price || !$duration) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = 'uploads/' . $image;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Image upload failed']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE services SET name=?, description=?, price=?, duration=?, image=? WHERE id=?");
        $stmt->bind_param("ssdssi", $name, $description, $price, $duration, $image, $id);
    } else {
        $stmt = $conn->prepare("UPDATE services SET name=?, description=?, price=?, duration=? WHERE id=?");
        $stmt->bind_param("ssdsi", $name, $description, $price, $duration, $id);
    }

    if ($stmt && $stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

if ($action === 'delete') {
    header('Content-Type: application/json');

    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing ID']);
        exit;
    }

    // Get service data before deleting
    $stmt = $conn->prepare("SELECT * FROM services WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit;
    }

    // Store service info as JSON in trash_bin
    $itemName = $service['name'] . '|' . json_encode([
        'description' => $service['description'],
        'price' => $service['price'],
        'duration' => $service['duration'],
        'image' => $service['image']
    ], JSON_UNESCAPED_UNICODE);

    $trashStmt = $conn->prepare("
        INSERT INTO trash_bin (item_id, item_name, source, deleted_at)
        VALUES (?, ?, 'services', NOW())
    ");
    $trashStmt->bind_param("is", $service['id'], $itemName);
    $trashStmt->execute();
    $trashStmt->close();

    // Then delete from services
    $delStmt = $conn->prepare("DELETE FROM services WHERE id=?");
    $delStmt->bind_param("i", $id);
    $success = $delStmt->execute();
    $delStmt->close();

    echo json_encode(['success' => $success]);
    exit;
}

?>
