<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(403);
    exit("Forbidden");
}

$email = $_POST["email"] ?? '';

if (empty($email)) {
    $_SESSION['error_message'] = "Email is required.";
    $_SESSION['show_forgot_modal'] = true;
    header("Location: index.php");
    exit();
}

$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60 * 30);

$sql = "UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $token_hash, $expiry, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $mail = require __DIR__ . "/reset_mailer.php";
    $mail->setFrom("noreply@example.com", "YourApp Support");
    $mail->addAddress($email);
    $mail->Subject = "Password Reset Request";

    $resetUrl = "http://localhost/Capstone_AF/reset_password.php?token=$token";

    $mail->isHTML(true);
    $mail->Body = <<<HTML
        <p>Hello,</p>
        <p>We received a request to reset your password.</p>
        <p>Click the link below to reset your password:</p>
        <p><a href="$resetUrl">Reset Password</a></p>
        <p>This link will expire in 30 minutes.</p>
    HTML;

    try {
        $mail->send();
        $_SESSION['success_message'] = "Message sent, please check your inbox.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Message could not be sent. Mailer error: {$mail->ErrorInfo}";
    }
} else {
    $_SESSION['error_message'] = "No account found with that email.";
}

// $_SESSION['show_forgot_modal'] = true;  TO MAKE RE OPEN THE MODAL
header("Location: index.php");
exit();
