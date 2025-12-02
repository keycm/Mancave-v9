<?php
include 'config.php';
// --- UPDATED CONNECTION ---
require_once __DIR__ . '/reset_mailer.php'; // We use the same mailer setup
// --- END OF UPDATE ---

if (isset($_POST['sign'])) {
  $name = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  // Store temporary input values
  $_SESSION['signup_old'] = [
    'username' => $name,
    'email' => $email
  ];

  if (empty($name)) {
    $_SESSION['error_message'] = "Name is required!";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "Valid email is required!";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
  } elseif (strlen($password) < 8) {
    $_SESSION['error_message'] = "Password must be at least 8 characters!";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
  } elseif (!preg_match("/[a-z]/i", $password)) {
    $_SESSION['error_message'] = "Password must contain at least one letter!";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
  } elseif (!preg_match("/[0-9]/", $password)) {
    $_SESSION['error_message'] = "Password must contain at least one number!";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
  } elseif ($password !== $confirm_password) {
    $_SESSION['error_message'] = "Passwords do not match!";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
  } else {
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $_SESSION['error_message'] = "This email address is already registered!";
      echo "<script>window.location.href = 'index.php';</script>";
      exit;
    } else {
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      
      // --- OTP Generation ---
      $otp = random_int(100000, 999999);
      $otp_expiry = date("Y-m-d H:i:s", time() + 60 * 10); // 10 minutes expiry
      // We will store the OTP in 'account_activation_hash' and expiry in 'reset_token_expires_at'
      // ---

      $sql = "INSERT INTO users (username, email, password, account_activation_hash, reset_token_expires_at) VALUES (?, ?, ?, ?, ?)";
      $stmt = $conn->prepare($sql);

      if ($stmt) {
        // Bind the new OTP and expiry
        $stmt->bind_param("sssss", $name, $email, $password_hash, $otp, $otp_expiry);
        
        if ($stmt->execute()) {
          $mail->setFrom("noreply@example.com", "A&F Paint Shop");
          $mail->addAddress($email);
          $mail->Subject = "Your Account Activation OTP";
          $mail->isHTML(true);
          // --- New Email Body ---
          $mail->Body = <<<END
          <p>Thank you for registering at A&F Paint Shop.</p>
          <p>Your account activation OTP is:</p>
          <h2 style="text-align:center; letter-spacing: 2px;"><b>$otp</b></h2>
          <p>This code will expire in 10 minutes.</p>
END;
          // ---

          try {
            $mail->send();
            
            // --- Redirect to OTP verification page ---
            $_SESSION['otp_email'] = $email; // To pre-fill the form
            $_SESSION['success_message'] = "Registered Successfully! Please check your email for the OTP to activate your account.";
            unset($_SESSION['signup_old']);
            echo "<script>window.location.href = 'verify_otp.php';</script>";
            exit;
            // ---
            
          } catch (Exception $e) {
            $_SESSION['error_message'] = "Mailer error: {$mail->ErrorInfo}";
          }
        } else {
          $_SESSION['error_message'] = "Error: " . $conn->error;
        }
      } else {
        $_SESSION['error_message'] = "SQL prepare error: " . $conn->error;
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign-up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .signup-modal-content {
      border-radius: 12px;
      overflow: hidden;
    }

    .signup-left-panel {
      background-color: rgb(99, 99, 98);
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .signup-image {
      height: 34rem !important;
      width: 100%;
      object-fit: fill !important;
      aspect-ratio: 4/3;
    }

    .signup-btn {
      background-color: #374151 !important;
      color: white !important;
    }

    .signup-btn:hover {
      background-color: #313a49 !important;
    }

    .log {
      text-decoration: none;
    }

    @media (max-width: 768px) {
      .signup-left-panel {
        display: none;
      }

      .modal-dialog {
        max-width: 100% !important;
        margin: 0;
      }

      .modal-content {
        height: 100vh;
        border-radius: 0;
      }

      .col-md-7 {
        width: 100% !important;
      }
    }

    .password-wrapper {
      position: relative;
    }

    .password-wrapper input {
      padding-right: 2.5rem;
    }

    .password-toggle {
      position: absolute;
      top: 70%;
      right: 12px;
      transform: translateY(-50%);
      cursor: pointer;
      color: #6c757d;
      font-size: 1.2rem;
    }

    .password-toggle:hover {
      color: #000;
    }
  </style>
</head>

<body>
  <div class="modal" id="SignupModal" tabindex="-1" aria-labelledby="SignupModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content signup-modal-content">
        <div class="row g-0 w-100">
          <div class="col-md-5 signup-left-panel">
            <img src="img/ab.png" alt="" class="img-fluid signup-image" />
          </div>

          <div class="col-md-7 bg-light p-4">
            <div class="modal-header p-0 mb-2" style="border-bottom: none;">
              <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <h5 class="fw-bold mb-3 text-dark">Create Your Account</h5>
            <form method="POST">
              <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your Username" required
                  value="<?php
                          if (isset($_SESSION['signup_old']['username'])) {
                            echo htmlspecialchars($_SESSION['signup_old']['username']);
                            unset($_SESSION['signup_old']['username']);
                          }
                          ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your Email" required
                  value="<?php
                          if (isset($_SESSION['signup_old']['email'])) {
                            echo htmlspecialchars($_SESSION['signup_old']['email']);
                            unset($_SESSION['signup_old']['email']);
                          }
                          ?>">
              </div>
              <div class="mb-3 password-wrapper">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your Password" required>
                <i class="bi bi-eye-slash password-toggle" id="togglePassword"></i>
              </div>
              <div class="mb-3 password-wrapper">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required>
                <i class="bi bi-eye-slash password-toggle" id="toggleConfirmPassword"></i>
              </div>
              <button type="submit" name="sign" class="btn w-100 text-white mb-3 signup-btn">Sign Up</button>
              <p class="text-muted small">
                Already have an account?
                <a href="#LoginModal" class="log text-success" data-bs-toggle="modal" data-bs-target="#LoginModal">Login</a>
              </p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="messageModalLabel">Message</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="messageModalBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <?php if (isset($_SESSION['error_message'])): ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const signupModalEl = document.getElementById('SignupModal');
        const messageModalEl = document.getElementById('messageModal');
        const messageBody = document.getElementById('messageModalBody');

        const signupModal = bootstrap.Modal.getOrCreateInstance(signupModalEl);
        const messageModal = bootstrap.Modal.getOrCreateInstance(messageModalEl);

        messageBody.textContent = "<?php echo addslashes($_SESSION['error_message']); ?>";

        signupModal.hide();

        setTimeout(() => {
          messageModal.show();
        }, 200);

        messageModalEl.addEventListener('hidden.bs.modal', function() {
          signupModal.show();
        }, {
          once: true
        });
      });
    </script>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['success_message'])): ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const messageModalEl = document.getElementById('messageModal');
        const messageBody = document.getElementById('messageModalBody');
        const messageModal = bootstrap.Modal.getOrCreateInstance(messageModalEl);

        messageBody.textContent = "<?php echo addslashes($_SESSION['success_message']); ?>";
        messageModal.show();
      });
    </script>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <script>
    function toggleVisibility(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(iconId);

      icon.addEventListener('click', () => {
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
      });
    }

    toggleVisibility('password', 'togglePassword');
    toggleVisibility('confirm_password', 'toggleConfirmPassword');

    // Skip refresh
    document.addEventListener("DOMContentLoaded", function () {
  const signupModal = document.getElementById('SignupModal');
  if (!signupModal) return;

  let skipRefresh = false;

  document.querySelectorAll('[data-bs-target="#LoginModal"]').forEach(el => {
    el.addEventListener('pointerdown', () => { skipRefresh = true; });
    el.addEventListener('mousedown', () => { skipRefresh = true; });
    el.addEventListener('touchstart', () => { skipRefresh = true; });
  });

  signupModal.addEventListener('hidden.bs.modal', () => {
    if (!skipRefresh) {
      location.reload();
    }
    // reset for next time (short delay to allow modal switching flow)
    setTimeout(() => { skipRefresh = false; }, 20);
  });
});
  </script>



</body>

</html>