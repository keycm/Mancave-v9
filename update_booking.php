<?php
include 'config.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") { 
    header('Location: admin.php'); 
    exit; 
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

if ($id <= 0) exit("Invalid ID");

// Function to add internal notification
function notifyUser($conn, $id, $msg) {
    $q = mysqli_query($conn, "SELECT user_id FROM bookings WHERE id=$id");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $uid = $r['user_id'];
        $msg = mysqli_real_escape_string($conn, $msg);
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ($uid, '$msg')");
    }
}

// Function to send email notification
function sendEmailNotification($conn, $booking_id, $status) {
    // Only send email if status is approved
    if ($status !== 'approved') return;

    // Fetch booking and user details
    $sql = "SELECT b.service, b.preferred_date, b.full_name, u.email 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data && !empty($data['email'])) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gallerymancave@gmail.com'; // Your Gmail
            $mail->Password   = 'ptke dpse hjvu uzuf';    // Your App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('gallerymancave@gmail.com', 'ManCave Gallery');
            $mail->addAddress($data['email'], $data['full_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Reservation Approved - ManCave Gallery';
            
            // 7 Days Collection Logic
            $collectionDeadline = date('F d, Y', strtotime('+7 days'));
            $reservationDate = date('F d, Y', strtotime($data['preferred_date']));

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2>Reservation Approved</h2>
                    <p>Dear {$data['full_name']},</p>
                    <p>We are pleased to inform you that your reservation for <strong>'{$data['service']}'</strong> on <strong>{$reservationDate}</strong> has been approved.</p>
                    
                    <div style='background: #fdfdfd; border-left: 4px solid #cd853f; padding: 15px; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #cd853f;'>Important Notice</h3>
                        <p><strong>You have 7 days to collect your reservation via walk-in.</strong></p>
                        <p>Please visit the gallery before <strong>{$collectionDeadline}</strong> to finalize your acquisition.</p>
                    </div>

                    <p>We look forward to seeing you at the gallery.</p>
                    <p>Best regards,<br>The ManCave Team</p>
                </div>
            ";

            $mail->send();
        } catch (Exception $e) {
            // Log error if needed, but don't stop the script
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
}

switch ($action) {
    case 'approved':
        if (mysqli_query($conn, "UPDATE bookings SET status='approved' WHERE id=$id")) {
            notifyUser($conn, $id, "Your booking has been approved. Please check your email for collection details.");
            sendEmailNotification($conn, $id, 'approved');
        }
        break;
        
    case 'rejected':
        mysqli_query($conn, "UPDATE bookings SET status='rejected' WHERE id=$id");
        notifyUser($conn, $id, "Your booking was rejected.");
        break;
        
    case 'completed':
        // Force lowercase 'completed'
        mysqli_query($conn, "UPDATE bookings SET status='completed' WHERE id=$id");
        notifyUser($conn, $id, "Your booking has been marked as completed. Thank you!");
        break;
        
    case 'delete':
        // Move to Trash Logic
        $query = mysqli_query($conn, "SELECT * FROM bookings WHERE id=$id");
        if ($row = mysqli_fetch_assoc($query)) {
            $displayName = "Booking: " . $row['service'] . " - " . $row['full_name'];
            $trashData = $displayName . '|' . json_encode($row);
            $trashDataSafe = mysqli_real_escape_string($conn, $trashData);
            
            $insertTrash = "INSERT INTO trash_bin (item_id, item_name, source, deleted_at) 
                            VALUES ($id, '$trashDataSafe', 'bookings', NOW())";
            
            if (mysqli_query($conn, $insertTrash)) {
                mysqli_query($conn, "UPDATE bookings SET deleted_at=NOW() WHERE id=$id");
            }
        }
        break;
}
?>