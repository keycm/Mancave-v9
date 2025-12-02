<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(403);
    exit("Forbidden");
}

$token = $_POST["token"] ?? '';

if (!$token) {
    die("Invalid or missing token.");
}

$token_hash = hash("sha256", $token);

$conn = require __DIR__ . "/config.php";

function redirectWithError($error, $token) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&error=" . urlencode($error));
    exit;
}

// Step 1: Validate token
$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    redirectWithError("Invalid or expired token.", $token);
}

if (strtotime($user["reset_token_expires_at"]) <= time()) {
    redirectWithError("Token has expired.", $token);
}

// Step 2: Validate new password
$password = $_POST["password"] ?? '';
$password_confirmation = $_POST["password_confirmation"] ?? '';

if (strlen($password) < 8) {
    redirectWithError("Password must be at least 8 characters.", $token);
}

if (!preg_match("/[a-z]/i", $password)) {
    redirectWithError("Password must contain at least one letter.", $token);
}

if (!preg_match("/[0-9]/", $password)) {
    redirectWithError("Password must contain at least one number.", $token);
}

if ($password !== $password_confirmation) {
    redirectWithError("Passwords do not match.", $token);
}

// Step 3: Hash and update password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users
        SET password = ?,
            reset_token_hash = NULL,
            reset_token_expires_at = NULL
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $password_hash, $user["id"]);
$stmt->execute();

echo "Password updated successfully. You can now <a href='index.php'>log in</a>.";
