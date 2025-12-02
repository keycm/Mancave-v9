<?php
session_start();
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

// Ensure logged in
if (!isset($_SESSION['username'])) {
    die("You must be logged in to book.");
}

// Get logged in user's ID
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);
if (!$user) {
    die("Logged-in user not found.");
}
$user_id = (int) $user['id'];

// Collect and validate booking data
// We capture artwork_id here
$artwork_id = isset($_POST['artwork_id']) ? (int)$_POST['artwork_id'] : null;
$service = $_POST['service'] ?? ''; // This might be the artwork title or service name
$preferred_date = $_POST['preferred_date'] ?? null;
$full_name = $_POST['full_name'] ?? '';
$phone_number = $_POST['phone_number'] ?? '';
$special_requests = $_POST['special_requests'] ?? '';

// Optional: Carry over legacy fields if your DB requires them, otherwise leave empty
$vehicle_type = $_POST['vehicle_type'] ?? '';
$vehicle_model = $_POST['vehicle_model'] ?? '';

// Updated Insert Query to include artwork_id
$sql = "INSERT INTO bookings (user_id, artwork_id, service, vehicle_type, vehicle_model, preferred_date, full_name, phone_number, special_requests, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

// Type definition: i = integer, s = string
// user_id (i), artwork_id (i), service (s), vehicle_type (s), vehicle_model (s), preferred_date (s), full_name (s), phone_number (s), special_requests (s)
mysqli_stmt_bind_param($stmt, "iisssssss",
    $user_id, $artwork_id, $service, $vehicle_type, $vehicle_model, $preferred_date,
    $full_name, $phone_number, $special_requests
);

if (!mysqli_stmt_execute($stmt)) {
    die("Insert failed: " . mysqli_stmt_error($stmt));
}

// Redirect back with success message
if ($artwork_id) {
    header("Location: collection.php?success=1");
} else {
    header("Location: index.php?success=1");
}
exit;
?>