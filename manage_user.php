<?php
session_start();
include 'config.php';
header('Content-Type: application/json');

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    // --- 2. DELETE USER LOGIC ---
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $current_user_id = $_SESSION['user_id'] ?? 0;

        if ($id <= 0) throw new Exception("Invalid User ID.");
        if ($id == $current_user_id) throw new Exception("You cannot delete your own account while logged in.");

        // Fetch User Data for Trash Bin
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) throw new Exception("User not found.");

        // Add to Trash Bin (Recycle Bin)
        // We save the username and full data to restore it later if needed
        $trashName = $user['username'] . '|' . json_encode($user);
        $trashStmt = $conn->prepare("INSERT INTO trash_bin (item_id, item_name, source, deleted_at) VALUES (?, ?, 'users', NOW())");
        $trashStmt->bind_param("is", $id, $trashName);
        
        if (!$trashStmt->execute()) {
            throw new Exception("Failed to move user to Recycle Bin.");
        }
        $trashStmt->close();

        // Delete from Users Table
        $delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delStmt->bind_param("i", $id);
        
        if ($delStmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Database error: Could not delete user.");
        }
        $delStmt->close();
        exit;
    }

    // --- 3. ADD / UPDATE USER LOGIC ---
    if ($action === 'add' || $action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        if (empty($username) || empty($email)) {
            throw new Exception("Username and Email are required.");
        }

        // ADD NEW USER
        if ($action === 'add') {
            // Check if email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) throw new Exception("This email is already registered.");
            $check->close();

            if (empty($password)) throw new Exception("Password is required for new users.");
            if (strlen($password) < 8) throw new Exception("Password must be at least 8 characters.");

            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hash, $role);
            
            if ($stmt->execute()) echo json_encode(['success' => true]);
            else throw new Exception($stmt->error);
            $stmt->close();
        } 
        
        // UPDATE EXISTING USER
        elseif ($action === 'update') {
            if ($id <= 0) throw new Exception("Invalid User ID.");

            // Prepare Update Query
            $sql = "UPDATE users SET username=?, email=?, role=?";
            $types = "sss";
            $params = [$username, $email, $role];

            // Only update password if user typed something new
            if (!empty($password)) {
                if (strlen($password) < 8) throw new Exception("Password must be at least 8 characters.");
                $sql .= ", password=?";
                $types .= "s";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id=?";
            $types .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) echo json_encode(['success' => true]);
            else throw new Exception($stmt->error);
            $stmt->close();
        }
        exit;
    }

    throw new Exception("Invalid Request Action.");

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>