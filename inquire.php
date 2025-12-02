<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get logged-in username
    $username = $_SESSION['username'] ?? 'Guest';

    $email    = filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL);
    $mobile   = htmlspecialchars($_POST["mobile"] ?? '');
    $message  = htmlspecialchars($_POST["message"] ?? '');

    // File upload handling
    $attachmentPath = null;
    if (!empty($_FILES['attachment']['tmp_name'])) {
        $uploadDir = "uploads/inquiries/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $attachmentPath = $uploadDir . basename($_FILES['attachment']['name']);
        move_uploaded_file($_FILES['attachment']['tmp_name'], $attachmentPath);
    }

    if ($email && $message) {
        $mail = new PHPMailer(true);

        try {
            // Email settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gallerymancave@gmail.com';
            $mail->Password   = 'ptke dpse hjvu uzuf';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($email, $username);
            $mail->addAddress('gallerymancave@gmail.com', 'TheMancaveGallery');

            $mail->isHTML(false);
            $mail->Subject = 'New Inquiry';
            $mail->Body    = "Username: $username\nEmail: $email\nMobile: $mobile\n\nMessage:\n$message";

            if ($attachmentPath) {
                $mail->addAttachment($attachmentPath);
            }

            $mail->send();

            // Save inquiry in DB
            $stmt = $conn->prepare("INSERT INTO inquiries (username, email, mobile, message, attachment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $mobile, $message, $attachmentPath);
            $stmt->execute();
            $stmt->close();

            echo 'success';
        } catch (Exception $e) {
            echo 'error';
        }
    } else {
        echo 'error';
    }
} else {
    http_response_code(405);
    echo 'error';
}
?>
