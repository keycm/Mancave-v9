<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id       = intval($_POST['id'] ?? 0);
    $subject  = trim($_POST['subject'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    // ✅ Get customer email
    $stmt = $conn->prepare("SELECT email FROM inquiries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inquiry = $result->fetch_assoc();
    $stmt->close();

    if ($inquiry && $subject && $message) {
        $customerEmail = $inquiry['email'];
        $mail = new PHPMailer(true);

        try {
            // Mail server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gallerymancave@gmail.com'; 
            $mail->Password   = 'ptke dpse hjvu uzuf'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Sender & recipient
            $mail->setFrom('gallerymancave@gmail.com', 'TheMancaveGallery');
            $mail->addAddress($customerEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($message);

            $mail->send();

            // ✅ Update inquiry status to replied
            $update = $conn->prepare("UPDATE inquiries SET status='replied' WHERE id=?");
            $update->bind_param("i", $id);
            $update->execute();
            $update->close();

            echo "success";
        } catch (Exception $e) {
            echo "error";
        }
    } else {
        echo "error";
    }
} else {
    http_response_code(405);
    echo "error";
}
?>
