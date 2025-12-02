<?php
include 'config.php';

$token = $_GET["token"] ?? null;
$error = $_GET["error"] ?? null;

if (!$token) {
    die("Invalid token.");
}

$token_hash = hash("sha256", $token);

$conn = require __DIR__ . "/config.php";

$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("Token not found or invalid.");
}

if (strtotime($user["reset_token_expires_at"]) <= time()) {
    die("Token has expired.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      background-color: #e5e5e5;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    .reset-container {
      background-color: white;
      padding: 2.5rem;
      border-radius: 1rem;
      box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 420px;
    }

    h1 {
      text-align: center;
      font-weight: 700;
      margin-bottom: 2rem;
    }

    .password-wrapper {
      position: relative;
      margin-bottom: 1rem;
    }

    .password-wrapper input {
      padding-right: 3rem;
    }

    .form-control.is-invalid {
      border-color: #dc3545;
    }

    .icon-group {
      position: absolute;
      top: 71.5%;
      right: 33px;
      transform: translateY(-50%);
      display: flex;
      align-items: center;
     
    }

    .icon-group i {
      font-size: 1.1rem;
      color: #666262ff;
      cursor: pointer;
    }

    .form-text-error {
      color: #dc3545;
      font-size: 0.875rem;
      margin-bottom: 20px;
      padding-left: 0.25rem;
    }
  </style>
</head>
<body>
  <div class="reset-container">
    <h1>Reset Your Password</h1>
    <form method="post" action="process_reset_password.php">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="password-wrapper">
        <label for="password" class="form-label">New Password</label>
        <input type="password" class="form-control <?= $error ? 'is-invalid' : '' ?>" id="password" name="password" required placeholder="Enter new password">
        <div class="icon-group">
          <i class="bi bi-eye-slash toggle-password" data-target="password"></i>

        </div>
      </div>

      <div class="password-wrapper">
        <label for="password_confirmation" class="form-label">Repeat Password</label>
        <input type="password" class="form-control <?= $error ? 'is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" required placeholder="Repeat new password">
        <div class="icon-group">
          <i class="bi bi-eye-slash toggle-password" data-target="password_confirmation"></i>

        </div>
        
      </div>
<?php if ($error): ?>
          <div class="form-text-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      <button type="submit" class="btn btn-dark w-100">Reset Password</button>
    </form>
  </div>

  <script>
    document.querySelectorAll(".toggle-password").forEach(icon => {
      icon.addEventListener("click", () => {
        const targetId = icon.dataset.target;
        const input = document.getElementById(targetId);
        const type = input.type === "password" ? "text" : "password";
        input.type = type;
        icon.classList.toggle("bi-eye");
        icon.classList.toggle("bi-eye-slash");
      });
    });
  </script>
</body>
</html>
