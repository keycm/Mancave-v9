<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$id = intval($_POST['id']);
$action = $_POST['action'] ?? 'restore';

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$conn->begin_transaction();

try {
    // --- PERMANENT DELETE ---
    if ($action === 'permanent_delete') {
        $stmt = $conn->prepare("DELETE FROM trash_bin WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- RESTORE LOGIC ---
    // 1. Fetch from Trash
    $stmt = $conn->prepare("SELECT * FROM trash_bin WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $trash = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trash) throw new Exception("Item not found in trash");

    // 2. Parse Data
    $parts = explode('|', $trash['item_name'], 2);
    $jsonData = $parts[1] ?? null;
    $data = $jsonData ? json_decode($jsonData, true) : [];

    // Smart Detection if source is empty
    if (empty($trash['source'])) {
        if (isset($data['username']) && isset($data['role'])) {
            $trash['source'] = 'users';
        } elseif (isset($data['artist']) && isset($data['price'])) {
            $trash['source'] = 'artworks';
        } elseif (isset($data['event_date'])) {
            $trash['source'] = 'events';
        } elseif (isset($data['bio'])) {
            $trash['source'] = 'artists';
        } elseif (isset($data['duration'])) {
            $trash['source'] = 'services';
        } elseif (isset($data['message']) && isset($data['mobile'])) {
            $trash['source'] = 'inquiries';
        } elseif (isset($data['vehicle_type']) || isset($data['service']) || strpos($trash['item_name'], 'Booking:') !== false) {
            $trash['source'] = 'bookings';
        }
    }

    // --- 3. HANDLE RESTORE BASED ON TYPE ---
    switch ($trash['source']) {
        case 'bookings':
            // Bookings are "Soft Deleted", so we just need the ID to restore them.
            // We DON'T need to check if $data is empty here.
            $booking_id = intval($trash['item_id']);
            $update = $conn->query("UPDATE bookings SET deleted_at=NULL WHERE id=$booking_id");
            if (!$update) throw new Exception("Failed to restore booking (ID: $booking_id). It might be permanently deleted.");
            break;

        // For all other types, we need the data to recreate the item.
        case 'users':
        case 'artworks':
        case 'services':
        case 'events':
        case 'artists':
        case 'inquiries':
        case 'ratings':
            // Check data integrity for these types
            if (empty($data) && $action === 'restore') {
                throw new Exception("Cannot restore: Data is corrupted or missing.");
            }

            // ... (Continue with specific restore logic) ...
            if ($trash['source'] === 'users') {
                $email = $data['email'];
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $check->close();
                    throw new Exception("Cannot restore: Email '$email' is already active.");
                }
                $check->close();

                $role = $data['role'] ?? 'user';
                $stmt = $conn->prepare("INSERT INTO users (id, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $data['id'], $data['username'], $data['email'], $data['password'], $role);
                
                if (!$stmt->execute()) {
                    if ($stmt->errno == 1062) {
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $data['username'], $data['email'], $data['password'], $role);
                        if (!$stmt->execute()) throw new Exception("Restore failed: " . $stmt->error);
                    } else {
                        throw new Exception("Restore failed: " . $stmt->error);
                    }
                }
            }
            elseif ($trash['source'] === 'artworks') {
                $stmt = $conn->prepare("INSERT INTO artworks (title, artist, price, description, image_path, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdsss", $data['title'], $data['artist'], $data['price'], $data['description'], $data['image_path'], $data['status']);
                $stmt->execute();
            }
            elseif ($trash['source'] === 'services') {
                $stmt = $conn->prepare("INSERT INTO services (name, description, price, duration, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdss", $data['name'], $data['description'], $data['price'], $data['duration'], $data['image']);
                $stmt->execute();
            }
            elseif ($trash['source'] === 'events') {
                $stmt = $conn->prepare("INSERT INTO events (title, event_date, event_time, location) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $data['title'], $data['event_date'], $data['event_time'], $data['location']);
                $stmt->execute();
            }
            elseif ($trash['source'] === 'artists') {
                $stmt = $conn->prepare("INSERT INTO artists (name, style, quote, bio, image_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $data['name'], $data['style'], $data['quote'], $data['bio'], $data['image_path']);
                $stmt->execute();
            }
            elseif ($trash['source'] === 'inquiries') {
                $mobile = $data['mobile'] ?? ''; 
                $stmt = $conn->prepare("INSERT INTO inquiries (username, email, mobile, message, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $data['username'], $data['email'], $mobile, $data['message'], $data['attachment'], $data['status'], $data['created_at']);
                $stmt->execute();
            }
            elseif ($trash['source'] === 'ratings') {
                $stmt = $conn->prepare("INSERT INTO ratings (user_id, service_id, rating, review, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiss", $data['user_id'], $data['service_id'], $data['rating'], $data['review'], $data['created_at']);
                $stmt->execute();
            }
            break;

        default:
            throw new Exception("Unknown source type: " . $trash['source']);
    }

    // 4. Cleanup Trash
    $del = $conn->prepare("DELETE FROM trash_bin WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    $conn->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>