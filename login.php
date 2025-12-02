<?php  
include 'config.php';  

if (isset($_POST['login'])) {     
    $identifier = $_POST['identifier'];     
    $password = $_POST['password'];      

    $sql = "SELECT * FROM users WHERE username=? OR email=? LIMIT 1";     
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);     

    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        // --- MODIFIED ACTIVATION CHECK ---
        // Check if account is activated (i.e., if OTP is still set)
        if (!empty($row['account_activation_hash'])) {
            $_SESSION['error_message'] = "Your account is not activated. Please enter the OTP sent to your email.";
            // Redirect to OTP page, pre-filling email
            header("Location: verify_otp.php?email=" . urlencode($identifier));
            exit();
        }
        // --- END OF MODIFICATION ---

        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['success_message'] = "Login successful! Welcome, " . $row['username'] . ".";
            unset($_SESSION['old_identifier']); 
            // Redirect based on role
            header("Location: " . ($row['role'] == 'admin' ? 'admin.php' : 'index.php'));
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid password!";
            $_SESSION['old_identifier'] = $identifier;
            header("Location: index.php?login=1");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "No account found with that username or email!";
        $_SESSION['old_identifier'] = $identifier;
        header("Location: index.php?login=1");
        exit();
        
    }
    
} 
?>  

<!DOCTYPE html> 
<html lang="en"> 
<head> 
  <meta charset="UTF-8" /> 
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/> 
  <title>A&F Paint Shop - Car Paint Services</title> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"/>
  <style> 
    .login-modal-content {
      border-radius: 12px;
      overflow: hidden;
    }
    .login-left-panel {
      background-color: rgb(99, 99, 98);
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .login-image {
      max-height: 415px;
      width: 100%;
      object-fit: fill;
    }
    .login-btn {
      background-color: #374151 !important;
      color: white !important;
    }
    .login-btn:hover {
      background-color: #313a49 !important;
    }
    .sign {
      text-decoration: none;
    }
    .modal {
      backdrop-filter: blur(3px);
      background-color: rgba(41, 40, 40, 0.3);
    }
    .modal-backdrop.show {
      backdrop-filter: blur(3px) !important;
      background-color: rgba(41, 40, 40, 0.3) !important;
    }
    .password-wrapper {
      position: relative;
    }
    .password-wrapper input {
      padding-right: 2.5rem;
    }
    .password-toggle {
      position: absolute;
      top: 72%;
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

<div class="modal" id="LoginModal" tabindex="-1" aria-labelledby="LoginModalLabel" aria-hidden="true" data-bs-backdrop="static"> 
  <div class="modal-dialog modal-dialog-centered modal-lg"> 
    <div class="modal-content login-modal-content"> 
      <div class="row g-0 w-100"> 

        <div class="col-md-5 login-left-panel"> 
          <img src="img/ab.png" alt="" class="img-fluid login-image" /> 
        </div> 

        <div class="col-md-7 bg-light p-4"> 
          <div class="modal-header p-0 mb-2" style="border-bottom: none;">
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <h5 class="fw-bold mb-3 text-dark">Welcome Sir/Ma'am</h5> 
          <form method="POST"> 
            <div class="mb-3"> 
              <label class="form-label">Username or Email</label> 
              <?php
                $old_identifier = '';
                if (isset($_SESSION['old_identifier'])) {
                    $old_identifier = htmlspecialchars($_SESSION['old_identifier']);
                    unset($_SESSION['old_identifier']); // clear after use
                }
              ?>
              <input type="text" name="identifier" class="form-control" placeholder="Enter your Username or Email" required 
              value="<?php echo $old_identifier; ?>"> 
            </div> 
            <div class="mb-3 password-wrapper">
              <label class="form-label">Password</label>
              <input type="password" name="password" id="login_password" class="form-control" placeholder="Enter your Password" required>
              <i class="bi bi-eye-slash password-toggle" id="toggleLoginPassword"></i>
            </div>

            <p class="text-muted small text-end">
              <a href="#ForgotModal" class="text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#ForgotModal">Forgot password?</a>
            </p>

            <button type="submit" name="login" class="btn w-100 mb-3 login-btn">Login</button> 
            <p class="text-muted small"> 
              Don't have an account? 
              <a href="#SignupModal" class="sign text-success" data-bs-toggle="modal" data-bs-target="#SignupModal">Sign up</a>
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
        <h3 class="modal-title" id="messageModalLabel">Message</h3> 
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
toggleVisibility('login_password', 'toggleLoginPassword');
</script>

<?php if (isset($_SESSION['error_message'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('messageModalBody').innerHTML =
    '<span class="fs-4 text-dark"><?php echo addslashes($_SESSION['error_message']); ?></span>';
  var msgModal = new bootstrap.Modal(document.getElementById('messageModal'));
  msgModal.show();
});
</script>
<?php unset($_SESSION['error_message']); endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('messageModalBody').innerHTML =
    '<span class="fs-4 text-dark"><?php echo addslashes($_SESSION['success_message']); ?></span>';
  var modalEl = document.getElementById('messageModal');
  var bsModal = new bootstrap.Modal(modalEl);
  bsModal.show();

  modalEl.addEventListener('hidden.bs.modal', function() {
    location.reload(); //  Refresh when success modal is closed
  });
});
</script>
<?php unset($_SESSION['success_message']); endif; ?>

</body> 
</html>