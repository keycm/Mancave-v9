<?php
// Start output buffering to prevent header errors
ob_start();

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'config.php';

// --- 1. GET EMAIL ---
// Priority: POST (Form) > GET (Link) > SESSION (Signup flow)
$email = '';
if (isset($_POST['email']) && !empty($_POST['email'])) {
    $email = trim($_POST['email']);
} elseif (isset($_GET['email']) && !empty($_GET['email'])) {
    $email = trim($_GET['email']);
} elseif (isset($_SESSION['otp_email']) && !empty($_SESSION['otp_email'])) {
    $email = trim($_SESSION['otp_email']);
}

$otp_input = '';
$message = '';
$message_type = ''; // 'success' or 'danger'

// --- 2. PROCESS SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otp_input = trim($_POST["otp"] ?? '');

    // Basic Validation
    if (empty($email)) {
        $message = "Error: Email is missing. Please restart the registration process.";
        $message_type = "danger";
    } elseif (empty($otp_input)) {
        $message = "Please enter the 6-digit code.";
        $message_type = "danger";
    } else {
        // Database Check
        $stmt = $conn->prepare("SELECT id, account_activation_hash, reset_token_expires_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            $message = "No account found for email: " . htmlspecialchars($email);
            $message_type = "danger";
        } elseif (empty($user['account_activation_hash'])) {
            // If hash is empty, account is already active
            $message = "Account is already active! Redirecting to login...";
            $message_type = "success";
        } else {
            // Check Expiry
            $expiry = strtotime($user['reset_token_expires_at']);
            
            // Force string comparison
            $db_otp = (string)$user['account_activation_hash'];
            $input_otp = (string)$otp_input;

            if (time() > $expiry) {
                $message = "This OTP has expired. <a href='signup.php' class='alert-link'>Register again</a> to get a new one.";
                $message_type = "danger";
            } elseif ($db_otp !== $input_otp) {
                $message = "Incorrect OTP. Please check your email and try again.";
                $message_type = "danger";
            } else {
                // --- SUCCESS: ACTIVATE ---
                $update = $conn->prepare("UPDATE users SET account_activation_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
                $update->bind_param("i", $user['id']);
                
                if ($update->execute()) {
                    $message = "Account Verified Successfully! Redirecting to login...";
                    $message_type = "success";
                    unset($_SESSION['otp_email']); // Clear session
                } else {
                    $message = "Database Error: " . $conn->error;
                    $message_type = "danger";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f6f9;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .verify-container {
      background: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 450px;
    }
  </style>
</head>
<body>

<div class="verify-container">
    <h3 class="text-center mb-4">Verify Account</h3>

    <!-- ALERT MESSAGE BOX -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> text-center" role="alert">
            <?php echo $message; ?>
        </div>
        
        <!-- Auto Redirect if Success -->
        <?php if ($message_type === 'success'): ?>
            <script>
                setTimeout(function() {
                    window.location.href = "index.php?login=1";
                }, 3000); // 3 seconds delay
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- FORM -->
    <!-- We keep the form visible but disabled on success so the user sees the state -->
    <fieldset <?php echo ($message_type === 'success') ? 'disabled' : ''; ?>>
        <form method="POST" action="">
            
            <?php if (!empty($email)): ?>
                <div class="mb-3 text-center">
                    <span class="text-muted">Code sent to: <strong><?php echo htmlspecialchars($email); ?></strong></span>
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                </div>
            <?php else: ?>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Enter 6-Digit OTP</label>
                <input type="text" name="otp" class="form-control text-center fs-4" 
                       maxlength="6" pattern="\d{6}" required
                       placeholder="000000"
                       value="<?php echo htmlspecialchars($otp_input); ?>"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2">Verify Now</button>
        </form>
    </fieldset>

    <?php if ($message_type !== 'success'): ?>
        <div class="text-center mt-3">
            <small class="text-muted">Didn't receive it? <a href="signup.php">Register again</a> to resend.</small>
        </div>
    <?php endif; ?>

</div>

</body>
</html>