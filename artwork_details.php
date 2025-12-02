<?php
session_start();
include 'config.php';
require_once __DIR__ . '/reset_mailer.php'; 

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: collection.php");
    exit();
}

$id = intval($_GET['id']);
$loggedIn = isset($_SESSION['username']);

// =================================================================
// 1. AUTHENTICATION & ACCOUNT LOGIC (Adapted for this page)
// =================================================================

// --- AJAX HANDLER FOR FORGOT PASSWORD FLOW ---
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $response = ['status' => 'error', 'message' => 'An error occurred.'];

    try {
        if ($action === 'send_reset_otp') {
            $email = $_POST['email'] ?? '';
            
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $otp = random_int(100000, 999999);
                $otp_hash = hash("sha256", $otp); 
                $expiry = date("Y-m-d H:i:s", time() + 60 * 15); 

                $update = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?");
                $update->bind_param("ssi", $otp_hash, $expiry, $user['id']);
                
                if ($update->execute()) {
                    $mail = require __DIR__ . '/reset_mailer.php';
                    $mail->setFrom("noreply@example.com", "ManCave Gallery");
                    $mail->addAddress($email);
                    $mail->Subject = "Password Reset OTP";
                    $mail->isHTML(true);
                    $mail->Body = "
                        <h3>Password Reset Request</h3>
                        <p>Hi " . htmlspecialchars($user['username']) . ",</p>
                        <p>Your OTP code to reset your password is:</p>
                        <h2 style='background: #eee; padding: 10px; display: inline-block;'>$otp</h2>
                        <p>This code expires in 15 minutes.</p>
                    ";
                    $mail->send();
                    $response = ['status' => 'success', 'message' => 'OTP sent to your email.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Email not found.'];
            }
        } 
        elseif ($action === 'verify_reset_otp') {
            $email = $_POST['email'] ?? '';
            $otp = $_POST['otp'] ?? '';
            $otp_hash = hash("sha256", $otp);

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token_hash = ? AND reset_token_expires_at > NOW()");
            $stmt->bind_param("ss", $email, $otp_hash);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response = ['status' => 'success', 'message' => 'OTP verified.'];
            } else {
                $response = ['status' => 'error', 'message' => 'Invalid or expired OTP.'];
            }
        }
        elseif ($action === 'reset_password') {
            $email = $_POST['email'] ?? '';
            $otp = $_POST['otp'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';
            $otp_hash = hash("sha256", $otp);

            if ($new_pass !== $confirm_pass) {
                $response = ['status' => 'error', 'message' => 'Passwords do not match.'];
            } elseif (strlen($new_pass) < 8) {
                $response = ['status' => 'error', 'message' => 'Password must be at least 8 characters.'];
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token_hash = ? AND reset_token_expires_at > NOW()");
                $stmt->bind_param("ss", $email, $otp_hash);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE email = ?");
                    $update->bind_param("ss", $new_hash, $email);
                    if ($update->execute()) {
                        $response = ['status' => 'success', 'message' => 'Password reset successfully.'];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Session expired. Please try again.'];
                }
            }
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// --- ACCOUNT VERIFICATION LOGIC (OTP) ---
if (isset($_POST['verify_account'])) {
    $otp_input = trim($_POST['otp']);
    $email = $_SESSION['otp_email'] ?? '';

    if (empty($email)) {
        $_SESSION['error_message'] = "Session expired. Please sign up again.";
    } elseif (empty($otp_input)) {
        $_SESSION['error_message'] = "Please enter the code.";
        $_SESSION['show_verify_modal'] = true; 
    } else {
        $stmt = $conn->prepare("SELECT id, account_activation_hash, reset_token_expires_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user_res = $stmt->get_result();
        $user = $user_res->fetch_assoc();

        if (!$user) {
            $_SESSION['error_message'] = "Account not found.";
        } elseif ($user['account_activation_hash'] == NULL) {
            $_SESSION['success_message'] = "Account already verified. Please Login.";
            unset($_SESSION['otp_email']);
            header("Location: artwork_details.php?id=$id&login=1");
            exit;
        } else {
            $expiry = strtotime($user['reset_token_expires_at']);
            
            if (time() > $expiry) {
                $_SESSION['error_message'] = "Code expired. Please register again.";
            } elseif ($user['account_activation_hash'] !== $otp_input) {
                $_SESSION['error_message'] = "Incorrect code. Try again.";
                $_SESSION['show_verify_modal'] = true; 
            } else {
                $upd = $conn->prepare("UPDATE users SET account_activation_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
                $upd->bind_param("i", $user['id']);
                if ($upd->execute()) {
                    $_SESSION['success_message'] = "Account verified! Please login.";
                    unset($_SESSION['otp_email']);
                    unset($_SESSION['show_verify_modal']);
                    header("Location: artwork_details.php?id=$id&login=1");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Database error.";
                }
            }
        }
    }
    header("Location: artwork_details.php?id=$id");
    exit;
}

// --- LOGIN LOGIC ---
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
        if (!empty($row['account_activation_hash'])) {
            $_SESSION['error_message'] = "Account not activated. Enter the code sent to your email.";
            $_SESSION['otp_email'] = $row['email'];
            $_SESSION['show_verify_modal'] = true; 
            header("Location: artwork_details.php?id=$id");
            exit();
        }
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            header("Location: artwork_details.php?id=$id"); 
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid password!";
        }
    } else {
        $_SESSION['error_message'] = "User not found!";
    }
    if(isset($_SESSION['error_message'])) {
        header("Location: artwork_details.php?id=$id&login=1");
        exit();
    }
} 

// --- SIGNUP LOGIC ---
if (isset($_POST['sign'])) {
    $name = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
  
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error_message'] = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Valid email is required!";
    } elseif (strlen($password) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters!";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Passwords do not match!";
    } else {
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
  
        if ($stmt->num_rows > 0) {
            $_SESSION['error_message'] = "Email already registered!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $otp = random_int(100000, 999999);
            $otp_expiry = date("Y-m-d H:i:s", time() + 60 * 10); 
  
            $sql = "INSERT INTO users (username, email, password, account_activation_hash, reset_token_expires_at) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssss", $name, $email, $password_hash, $otp, $otp_expiry);
                if ($stmt->execute()) {
                    $mail->setFrom("noreply@example.com", "ManCave Gallery");
                    $mail->addAddress($email);
                    $mail->Subject = "Account Activation OTP";
                    $mail->isHTML(true);
                    $mail->Body = "<h3>Welcome to ManCave!</h3><p>Your activation code is: <b style='font-size:1.2em'>$otp</b></p>";
                    try {
                        $mail->send();
                        $_SESSION['otp_email'] = $email;
                        $_SESSION['success_message'] = "Registration successful! Check your email for the code.";
                        $_SESSION['show_verify_modal'] = true; 
                        header("Location: artwork_details.php?id=$id"); 
                        exit;
                    } catch (Exception $e) {
                        $_SESSION['error_message'] = "Mailer Error: " . $mail->ErrorInfo;
                    }
                } else {
                    $_SESSION['error_message'] = "Database Error.";
                }
            }
        }
    }
    if(isset($_SESSION['error_message'])) {
        header("Location: artwork_details.php?id=$id&signup=1");
        exit();
    }
}

// --- FETCH USER DATA (Profile Pic) ---
$user_profile_pic = ""; 
if ($loggedIn && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $user_sql = "SELECT username, image_path FROM users WHERE id = $uid"; 
    $user_res = mysqli_query($conn, $user_sql);
    
    if ($user_data = mysqli_fetch_assoc($user_res)) {
        if (!empty($user_data['image_path'])) {
            $user_profile_pic = 'uploads/' . $user_data['image_path'];
        } else {
            $user_profile_pic = "https://ui-avatars.com/api/?name=" . urlencode($user_data['username']) . "&background=cd853f&color=fff&rounded=true&bold=true";
        }
    }
}

// --- FETCH USER FAVORITES ---
$isFav = false;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $fav_sql = "SELECT id FROM favorites WHERE user_id = $uid AND artwork_id = $id";
    if ($fav_res = mysqli_query($conn, $fav_sql)) {
        if (mysqli_num_rows($fav_res) > 0) {
            $isFav = true;
        }
    }
}

// 1. Fetch Current Artwork Details
$sql = "SELECT * FROM artworks WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$artwork = $result->fetch_assoc();

if (!$artwork) {
    echo "Artwork not found.";
    exit();
}

// 2. Fetch "Other Works"
$other_works = [];
$section_title = "";

$sql_artist = "SELECT * FROM artworks WHERE artist = ? AND id != ? LIMIT 4";
$stmt_artist = $conn->prepare($sql_artist);
$stmt_artist->bind_param("si", $artwork['artist'], $id);
$stmt_artist->execute();
$res_artist = $stmt_artist->get_result();

while($row = $res_artist->fetch_assoc()) {
    $other_works[] = $row;
}

if (empty($other_works)) {
    $section_title = "You Might Also Like";
    $sql_random = "SELECT * FROM artworks WHERE id != ? ORDER BY RAND() LIMIT 4";
    $stmt_random = $conn->prepare($sql_random);
    $stmt_random->bind_param("i", $id);
    $stmt_random->execute();
    $res_random = $stmt_random->get_result();
    while($row = $res_random->fetch_assoc()) {
        $other_works[] = $row;
    }
} else {
    $section_title = "Other Works By " . htmlspecialchars($artwork['artist']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($artwork['title']); ?> | ManCave Gallery</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* =========================================
           GLOBAL STYLES
           ========================================= */
        :root {
            --primary: #1a1a1a;       
            --secondary: #555555;     
            --accent: #cd853f;        
            --accent-hover: #b07236;  
            --brand-red: #d63031;     
            --bg-light: #fafafa;      
            --white: #ffffff;
            --border-color: #e5e5e5;
            --radius: 4px;           
            --font-main: 'Nunito Sans', sans-serif;       
            --font-head: 'Playfair Display', serif; 
            --font-script: 'Pacifico', cursive;
            --shadow-soft: 0 10px 40px -10px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 40px -5px rgba(0,0,0,0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-main);
            color: var(--secondary);
            background-color: #fff;
            line-height: 1.6;
            font-size: 1rem;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }

        /* =========================================
           HEADER UI (MATCHING INDEX.PHP)
           ========================================= */
        .navbar {
            position: fixed; top: 0; width: 100%;
            background: rgba(255, 255, 255, 0.98); 
            padding: 15px 0;
            z-index: 1000; 
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }

        /* Logo Styling */
        .logo { text-decoration: none; display: flex; gap: 8px; align-items: baseline; white-space: nowrap; }
        .logo:hover { transform: scale(1.02); }
        .logo-top { font-family: var(--font-head); font-weight: 700; color: var(--primary); letter-spacing: 1px; font-size: 1rem; margin-bottom: 0; }
        .logo-main { font-family: var(--font-script); font-size: 1.8rem; transform: rotate(-2deg); margin: 0; padding:0; }
        .logo-red { color: #8B0000; } 
        .logo-text { color: var(--primary); }
        .logo-bottom { font-family: var(--font-main); font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: 2px; text-transform: uppercase; margin:0; }

        .nav-links { display: flex; gap: 30px; }
        .nav-links a { font-weight: 700; color: var(--primary); font-size: 1rem; position: relative; transition: color 0.3s; }
        .nav-links a:hover { color: var(--accent); }

        /* Buttons */
        .btn-nav { background: var(--primary); color: #fff; padding: 10px 25px; border-radius: 50px; border: none; cursor: pointer; font-weight: 700; margin-left: 15px; font-size: 0.9rem; transition: 0.3s; box-shadow: 0 3px 8px rgba(0,0,0,0.15); }
        .btn-nav:hover { background: var(--accent); color: #fff; transform: translateY(-2px); }
        .btn-nav-outline { background: transparent; color: var(--primary); border: 2px solid var(--primary); padding: 8px 20px; border-radius: 50px; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: 0.3s; }
        .btn-nav-outline:hover { background: var(--primary); color: #fff; }

        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .header-icon-btn {
            background: #f8f8f8; border: 1px solid #eee; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; position: relative;
        }
        .header-icon-btn:hover { background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); color: var(--accent); }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--brand-red); color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 5px; border-radius: 50%; min-width: 18px; text-align: center; border: 2px solid #fff; }

        .profile-pill { display: flex; align-items: center; gap: 10px; background: #f8f8f8; padding: 4px 15px 4px 4px; border-radius: 50px; border: 1px solid #eee; cursor: pointer; transition: all 0.3s ease; }
        .profile-pill:hover { background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        .profile-name { font-weight: 700; font-size: 0.9rem; color: var(--primary); padding-right: 5px; }

        /* Dropdowns */
        .user-dropdown, .notification-wrapper { position: relative; }
        .dropdown-content, .notif-dropdown { display: none; position: absolute; top: 140%; right: 0; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; z-index: 1001; }
        .dropdown-content { min-width: 180px; padding: 10px 0; }
        .notif-dropdown { width: 320px; right: -10px; top: 160%; }
        .user-dropdown.active .dropdown-content, .notif-dropdown.active { display: block; animation: fadeIn 0.2s ease-out; }
        .dropdown-content a { display: block; padding: 10px 20px; color: var(--primary); font-size: 0.9rem; }
        .dropdown-content a:hover { background: #f9f9f9; color: var(--accent); }
        .notif-header { padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; font-weight: 700; background: #fafafa; font-size: 0.9rem; }
        .notif-list { max-height: 300px; overflow-y: auto; list-style: none; padding: 0; margin: 0; }
        .notif-item { padding: 15px 35px 15px 20px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer; position: relative; }
        .notif-item:hover { background: #fdfbf7; }
        .btn-notif-close { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #aaa; font-size: 1.2rem; line-height: 1; cursor: pointer; padding: 0; transition: color 0.2s; }
        .btn-notif-close:hover { color: #ff4d4d; }
        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .mobile-menu-icon { display: none; font-size: 1.8rem; cursor: pointer; color: var(--primary); }


        /* =========================================
           ARTWORK DETAILS LAYOUT
           ========================================= */
        .details-page-wrapper { 
            padding-top: 130px; 
            padding-bottom: 80px; 
            max-width: 1300px;
            margin: 0 auto;
            padding-left: 30px;
            padding-right: 30px;
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            margin-bottom: 40px; font-weight: 700; color: #999; 
            font-size: 0.8rem; letter-spacing: 1px;
            text-transform: uppercase;
            transition: 0.3s;
        }
        .back-link:hover { color: var(--primary); padding-left: 5px; }

        .product-grid { 
            display: grid; 
            grid-template-columns: 1.2fr 0.8fr; /* Image wider than details */
            gap: 60px; 
            align-items: start; 
            position: relative;
        }

        /* --- Left Column: Gallery Display --- */
        .gallery-side { 
            position: relative; 
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .main-image-frame {
            background-color: #fff; 
            border-radius: 2px;
            box-shadow: var(--shadow-soft);
            width: 100%; 
            height: auto; 
            min-height: 500px;
            display: flex; align-items: center; justify-content: center;
            cursor: zoom-in;
            padding: 20px;
            border: 1px solid #f0f0f0;
            overflow: hidden;
            position: relative;
        }
        
        .main-image-frame img { 
            max-width: 100%; max-height: 700px; object-fit: contain; 
            display: block; transition: transform 0.2s ease-out; 
        }

        .thumbnail-strip { 
            display: flex; gap: 15px; 
            justify-content: center; 
            padding-top: 10px;
        }
        
        .thumb-item {
            width: 70px; height: 70px; 
            border-radius: 4px; overflow: hidden;
            cursor: pointer; border: 1px solid #eee; 
            opacity: 0.6; transition: 0.3s;
            background: #fff;
        }
        .thumb-item img { width: 100%; height: 100%; object-fit: cover; }
        .thumb-item:hover, .thumb-item.active { opacity: 1; border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        /* --- Right Column: Sticky Info --- */
        .info-side { 
            position: sticky;
            top: 110px; /* Sticks below navbar */
            padding: 30px;
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }

        .status-badge-lg {
            display: inline-block; padding: 6px 14px; border-radius: 4px;
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            color: white; margin-bottom: 20px; letter-spacing: 1px;
        }
        .status-available { background: var(--primary); }
        .status-reserved { background: #f39c12; }
        .status-sold { background: #c0392b; }

        .art-header-row { 
            display: flex; justify-content: space-between; 
            align-items: flex-start; gap: 20px; margin-bottom: 10px;
        }
        
        .art-title-lg { 
            font-family: var(--font-head); 
            font-size: 2.8rem; 
            color: var(--primary); 
            margin: 0; 
            line-height: 1.1; 
            font-weight: 500;
        }
        
        .btn-heart-lg {
            width: 50px; height: 50px; 
            border-radius: 50%; border: 1px solid #eee;
            background: #fff; color: #ccc; 
            font-size: 1.3rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center; 
            transition: all 0.3s ease; flex-shrink: 0;
            margin-top: 5px;
        }
        .btn-heart-lg:hover { border-color: var(--brand-red); color: var(--brand-red); transform: scale(1.05); }
        .btn-heart-lg.active { background: var(--brand-red); border-color: var(--brand-red); color: white; box-shadow: 0 5px 15px rgba(214, 48, 49, 0.3); }
        @keyframes heartPump { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }
        .btn-heart-lg.animating i { animation: heartPump 0.4s ease; }

        .art-artist-lg { 
            font-size: 1.1rem; color: #777; margin-bottom: 30px; display: block; 
            font-family: var(--font-head); font-style: italic;
        }
        .art-artist-lg span { font-weight: 600; color: var(--primary); font-style: normal; }
        .art-artist-lg a { border-bottom: 1px dotted #ccc; padding-bottom: 1px; }
        .art-artist-lg a:hover { color: var(--accent); border-bottom-color: var(--accent); }

        .price-wrapper {
            display: flex; align-items: baseline; gap: 15px; margin-bottom: 30px;
            padding-bottom: 25px; border-bottom: 1px solid #eee;
        }
        .art-price-lg { font-family: var(--font-main); font-size: 2rem; font-weight: 300; color: var(--primary); }
        .price-label { text-transform: uppercase; font-size: 0.8rem; color: #999; font-weight: 700; letter-spacing: 1px; }

        /* NEW: Specs Grid Style */
        .specs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #f0f0f0;
        }
        .specs-item { display: flex; flex-direction: column; }
        .specs-label { font-size: 0.75rem; text-transform: uppercase; color: #999; font-weight: 700; letter-spacing: 1px; margin-bottom: 4px; }
        .specs-value { font-size: 1rem; color: var(--primary); font-weight: 600; }

        .desc-section { margin-bottom: 40px; }
        .desc-label { font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1.5px; color: var(--accent); display: block; margin-bottom: 12px; }
        .desc-text { font-size: 1rem; line-height: 1.7; color: #555; font-weight: 300; }

        .action-area { display: flex; flex-direction: column; gap: 15px; }

        .btn-reserve-block {
            width: 100%; padding: 18px; 
            background: var(--primary); color: white;
            border: 1px solid var(--primary); border-radius: 4px; 
            font-weight: 700; font-size: 0.9rem;
            text-transform: uppercase; letter-spacing: 2px; 
            cursor: pointer; transition: 0.3s;
            display: flex; justify-content: center; align-items: center; gap: 15px;
        }
        .btn-reserve-block:hover { background: #333; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        .btn-reserve-block.outline {
            background: transparent; color: var(--primary); border: 1px solid #ddd;
        }
        .btn-reserve-block.outline:hover {
            border-color: var(--primary); background: #fff;
        }

        /* --- OTHER WORKS GRID (MODERN) --- */
        .other-section { padding: 80px 0; background: #fafafa; border-top: 1px solid #eee; }
        .section-head { margin-bottom: 40px; text-align: center; position: relative; }
        .section-head h2 { font-size: 2rem; margin: 0; font-family: var(--font-head); color: var(--primary); }
        .section-head::after { content: ''; display: block; width: 60px; height: 3px; background: var(--accent); margin: 15px auto 0; }
        .btn-view-all { display: block; margin-top: 10px; font-size: 0.85rem; color: #888; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .btn-view-all:hover { color: var(--accent); }

        .latest-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
            gap: 40px; 
        }

        .art-card-new { 
            background: transparent; 
            transition: all 0.4s ease; 
            display: flex;
            flex-direction: column;
            group: hover;
        }
        
        .art-img-wrapper-new { 
            position: relative; 
            width: 100%; 
            aspect-ratio: 4/5; 
            overflow: hidden; 
            background: #fff; 
            margin-bottom: 20px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.4s;
        }
        .art-img-wrapper-new img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            transition: transform 0.6s ease;
        }
        
        /* Hover Effects */
        .art-card-new:hover .art-img-wrapper-new { transform: translateY(-5px); box-shadow: var(--shadow-hover); }
        .art-card-new:hover img { transform: scale(1.05); }

        .badge-new { position: absolute; top: 15px; left: 15px; padding: 5px 12px; background: rgba(255,255,255,0.9); border-radius: 2px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--primary); z-index: 2; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .art-content-new { text-align: center; }
        .art-title-new { font-size: 1.1rem; font-family: var(--font-head); color: var(--primary); margin-bottom: 5px; }
        .price-new { font-weight: 400; color: #888; font-size: 0.95rem; font-family: var(--font-main); }
        
        /* Modals (Updated Styles) */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000; padding: 20px; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        .modal-card { 
            background: var(--white); 
            padding: 35px; /* Slightly more padding */
            border-radius: 12px; /* Smoother corners */
            width: 550px; /* Slightly wider */
            max-width: 95%; 
            position: relative; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.2); 
            transform: translateY(20px); 
            transition: 0.3s; 
            max-height: 90vh; /* Prevent overflow */
            overflow-y: auto;
        }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        
        .modal-card::-webkit-scrollbar { width: 6px; }
        .modal-card::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
        .modal-card::-webkit-scrollbar-track { background: #f9f9f9; }

        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #999; transition: 0.3s; line-height: 1; }
        .modal-close:hover { color: var(--brand-red); }
        
        /* Auth Modal Specifics */
        .modal-card.small { width: 420px; max-width: 95%; padding: 45px 35px; text-align: center; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .modal-header-icon { font-size: 3rem; color: var(--accent); margin-bottom: 20px; background: rgba(205, 133, 63, 0.1); width: 80px; height: 80px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .modal-card.small h3 { font-family: var(--font-head); font-size: 1.8rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card.small p { color: #666; margin-bottom: 30px; font-size: 0.95rem; }
        
        .friendly-input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .friendly-input-group i { position: absolute; top: 50%; left: 20px; transform: translateY(-50%); color: #bbb; font-size: 1.1rem; pointer-events: none; transition: 0.3s; }
        .friendly-input-group input { width: 100%; padding: 14px 14px 14px 55px; border-radius: 50px; background: #f8f9fa; border: 1px solid #e9ecef; font-size: 0.95rem; transition: all 0.3s ease; outline: none; }
        .friendly-input-group input:focus { background: #fff; border-color: var(--accent); box-shadow: 0 4px 15px rgba(243, 108, 33, 0.15); }
        .friendly-input-group input:focus + i { color: var(--accent); }
        
        .btn-friendly { width: 100%; padding: 15px; border-radius: 50px; border: none; background: linear-gradient(135deg, var(--accent), #ff8c42); color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(243, 108, 33, 0.3); }
        .btn-friendly:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(243, 108, 33, 0.4); }
        
        .modal-footer-link { margin-top: 25px; font-size: 0.9rem; color: #777; }
        .modal-footer-link a { color: var(--accent); font-weight: 700; }
        .alert-error { background: #ffe6e6; color: #d63031; padding: 12px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 20px; border: 1px solid #fab1a0; text-align: left; display: flex; align-items: center; gap: 10px; }
        .forgot-pass-link { text-decoration: none; color: #888; font-size: 0.85rem; font-weight: 600; transition: color 0.3s ease; }
        .forgot-pass-link:hover { color: var(--accent); }

        .btn-full { width: 100%; background: var(--primary); color: var(--white); padding: 16px; border-radius: 4px; border: none; font-weight: 700; cursor: pointer; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; margin-top: 10px; }
        .btn-full:hover { background: #333; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; font-size: 0.85rem; color: #444; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input:not(.friendly-input-group input), .form-group textarea { width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; background: #fafafa; transition: 0.3s; }
        .form-group input:focus, .form-group textarea:focus { background: #fff; border-color: var(--primary); outline: none; }

        @media (max-width: 900px) {
            .product-grid { grid-template-columns: 1fr; gap: 40px; }
            .info-side { position: static; box-shadow: none; border: none; padding: 0; }
            .art-title-lg { font-size: 2.2rem; }
        }
        @media (max-width: 768px) {
            .navbar { background: var(--white); padding: 15px 0; border-bottom: 1px solid var(--border-color); }
            .nav-links { display: none; } 
            .mobile-menu-icon { display: block; color: var(--primary); font-size: 1.8rem; }
            .details-page-wrapper { padding-top: 100px; padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>
<body>

    <nav class="navbar scrolled">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <span class="logo-top">THE</span>
                <span class="logo-main">
                    <span class="logo-red">M</span><span class="logo-text">an</span><span class="logo-red">C</span><span class="logo-text">ave</span>
                </span>
                <span class="logo-bottom">GALLERY</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="collection.php">Collection</a></li>
                <li><a href="index.php#artists">Artists</a></li>
                <li><a href="index.php#services">Services</a></li>
                <li><a href="index.php#contact-form">Visit</a></li>
            </ul>
            <div class="nav-actions">
                <?php if ($loggedIn): ?>
                    <a href="favorites.php" class="header-icon-btn" title="My Favorites"><i class="far fa-heart"></i></a>
                    
                    <div class="notification-wrapper">
                        <button class="header-icon-btn" id="notifBtn">
                            <i class="far fa-bell"></i>
                            <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <span>Notifications</span>
                                <button id="markAllRead" style="border:none; background:none; color:var(--accent); cursor:pointer; font-size:0.8rem; font-weight:700;">Mark all read</button>
                            </div>
                            <ul class="notif-list" id="notifList">
                                <li class="no-notif">Loading...</li>
                            </ul>
                        </div>
                    </div>

                    <div class="user-dropdown">
                        <div class="profile-pill">
                            <img src="<?php echo htmlspecialchars($user_profile_pic); ?>" alt="Profile" class="profile-img">
                            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: var(--secondary);"></i>
                        </div>
                        <div class="dropdown-content">
                            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="admin.php"><i class="fas fa-cog"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="openSignupBtn" class="btn-nav-outline">Sign Up</button>
                    <button id="openLoginBtn" class="btn-nav">Sign In</button>
                <?php endif; ?>
            </div>
            <div class="mobile-menu-icon"><i class="fas fa-bars"></i></div>
        </div>
    </nav>

    <div class="details-page-wrapper">
        <a href="collection.php" class="back-link">
            <i class="fas fa-long-arrow-alt-left"></i> Back to Collection
        </a>

        <div class="product-grid">
            <div class="gallery-side">
                <?php $imgSrc = !empty($artwork['image_path']) ? 'uploads/'.$artwork['image_path'] : 'img-21.jpg'; ?>
                
                <div class="main-image-frame" id="zoomFrame">
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" id="mainImage" alt="<?php echo htmlspecialchars($artwork['title']); ?>">
                </div>

                <div class="thumbnail-strip">
                    <div class="thumb-item active" onclick="switchImage('<?php echo $imgSrc; ?>', this)"><img src="<?php echo $imgSrc; ?>"></div>
                    <div class="thumb-item" onclick="switchImage('<?php echo $imgSrc; ?>', this)"><img src="<?php echo $imgSrc; ?>" style="filter: brightness(0.8);"></div>
                    <div class="thumb-item" onclick="switchImage('<?php echo $imgSrc; ?>', this)"><img src="<?php echo $imgSrc; ?>" style="filter: sepia(0.3);"></div>
                </div>
            </div>

            <div class="info-side">
                <?php 
                    $statusClass = strtolower($artwork['status']); 
                    $isAvailable = ($artwork['status'] === 'Available');
                ?>
                <div class="art-header-row">
                    <span class="status-badge-lg status-<?php echo $statusClass; ?>"><?php echo $artwork['status']; ?></span>
                    
                    <button class="btn-heart-lg <?php echo $isFav ? 'active' : ''; ?>" 
                            onclick="toggleFavorite(this, <?php echo $artwork['id']; ?>)" 
                            title="Toggle Favorite">
                        <i class="<?php echo $isFav ? 'fas' : 'far'; ?> fa-heart"></i>
                    </button>
                </div>

                <h1 class="art-title-lg"><?php echo htmlspecialchars($artwork['title']); ?></h1>
                
                <div class="art-artist-lg">
                    by <a href="artist_profile.php?artist=<?php echo urlencode($artwork['artist']); ?>">
                        <span><?php echo htmlspecialchars($artwork['artist']); ?></span>
                    </a>
                </div>

                <div class="price-wrapper">
                    <span class="price-label">Price</span>
                    <div class="art-price-lg">Php <?php echo number_format($artwork['price']); ?></div>
                </div>

                <?php if(!empty($artwork['category']) || !empty($artwork['medium']) || !empty($artwork['year']) || !empty($artwork['size'])): ?>
                <div class="specs-grid">
                    <?php if(!empty($artwork['category'])): ?>
                    <div class="specs-item">
                        <span class="specs-label">Category</span>
                        <span class="specs-value"><?php echo htmlspecialchars($artwork['category']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($artwork['medium'])): ?>
                    <div class="specs-item">
                        <span class="specs-label">Medium</span>
                        <span class="specs-value"><?php echo htmlspecialchars($artwork['medium']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($artwork['year'])): ?>
                    <div class="specs-item">
                        <span class="specs-label">Year</span>
                        <span class="specs-value"><?php echo htmlspecialchars($artwork['year']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($artwork['size'])): ?>
                    <div class="specs-item">
                        <span class="specs-label">Dimensions</span>
                        <span class="specs-value"><?php echo htmlspecialchars($artwork['size']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="desc-section">
                    <span class="desc-label">About the Artwork</span>
                    <p class="desc-text">
                        <?php echo !empty($artwork['description']) ? nl2br(htmlspecialchars($artwork['description'])) : 'This original piece captures a unique moment in time, blending modern technique with classical emotion. Perfect for collectors seeking depth and character.'; ?>
                    </p>
                </div>

                <div class="action-area">
                    <?php if($isAvailable): ?>
                        <button class="btn-reserve-block" onclick="openReserveModal(<?php echo $artwork['id']; ?>, '<?php echo addslashes($artwork['title']); ?>')">
                            Reserve Artwork
                        </button>
                    <?php else: ?>
                        <button class="btn-reserve-block outline" onclick="openCopyModal('<?php echo addslashes($artwork['title']); ?>')">
                            Request Commission
                        </button>
                    <?php endif; ?>
                    
                    <div style="font-size:0.8rem; color:#999; text-align:center; margin-top:5px;">
                        <i class="fas fa-shield-alt"></i> Authenticity Guaranteed
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(!empty($other_works)): ?>
    <section class="other-section">
        <div class="container" style="max-width:1300px; margin:0 auto; padding:0 30px;">
            <div class="section-head">
                <h2><?php echo $section_title; ?></h2>
                <a href="collection.php" class="btn-view-all">Explore Collection</a>
            </div>
            
            <div class="latest-grid">
                <?php foreach($other_works as $work): 
                     $wImg = !empty($work['image_path']) ? 'uploads/'.$work['image_path'] : 'img-21.jpg';
                     $wStatus = strtolower($work['status']);
                ?>
                <div class="art-card-new" data-aos="fade-up">
                    <a href="artwork_details.php?id=<?php echo $work['id']; ?>" class="art-link-wrapper" style="color:inherit; text-decoration:none;">
                        <div class="art-img-wrapper-new">
                            <span class="badge-new"><?php echo $work['status']; ?></span>
                            <img src="<?php echo $wImg; ?>">
                        </div>
                        <div class="art-content-new">
                            <div class="art-title-new"><?php echo htmlspecialchars($work['title']); ?></div>
                            <span class="price-new">Php <?php echo number_format($work['price']); ?></span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-user-circle"></i></div>
            <h3>Welcome Back</h3>
            <p>Sign in to continue to your account</p>
            <?php if(isset($_SESSION['error_message']) && isset($_GET['login'])): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <form action="" method="POST"> 
                <div class="friendly-input-group">
                    <input type="text" name="identifier" required placeholder="Username or Email">
                    <i class="fas fa-user"></i>
                </div>
                <div class="friendly-input-group" style="margin-bottom:10px;">
                    <input type="password" name="password" required placeholder="Password">
                    <i class="fas fa-lock"></i>
                </div>
                <div style="text-align: right; margin-bottom: 20px;">
                    <a href="#" class="forgot-pass-link" id="openForgotBtn">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn-friendly">Sign In</button>
                <div class="modal-footer-link">
                    Don't have an account? <a href="#" id="switchRegister">Sign Up</a>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="signupModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-rocket"></i></div>
            <h3>Join The Club</h3>
            <p>Create an account to reserve unique art</p>
            <?php if(isset($_SESSION['error_message']) && isset($_GET['signup'])): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <form action="" method="POST"> 
                <div class="friendly-input-group">
                    <input type="text" name="username" required placeholder="Username">
                    <i class="fas fa-user"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="email" name="email" required placeholder="Email Address">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="password" name="password" required placeholder="Password (Min 8 chars)">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="password" name="confirm_password" required placeholder="Confirm Password">
                    <i class="fas fa-check-circle"></i>
                </div>
                <button type="submit" name="sign" class="btn-friendly">Create Account</button>
                <div class="modal-footer-link">
                    Already a member? <a href="#" id="switchLogin">Log In</a>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="forgotModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-key"></i></div>
            <h3>Forgot Password</h3>
            <p>Enter your email to receive an OTP code.</p>
            <form id="forgotForm">
                <div class="friendly-input-group">
                    <input type="email" id="resetEmail" name="email" required placeholder="Email Address">
                    <i class="fas fa-envelope"></i>
                </div>
                <button type="submit" class="btn-friendly">Send OTP</button>
                <div class="modal-footer-link">Remembered it? <a href="#" class="switchBackToLogin">Log In</a></div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="resetOtpModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-shield-alt"></i></div>
            <h3>Verify Code</h3>
            <p>Enter the 6-digit code sent to your email.</p>
            <form id="resetOtpForm">
                <div class="friendly-input-group">
                    <input type="text" id="otpCode" name="otp" required placeholder="123456" maxlength="6" style="letter-spacing:5px; text-align:center; font-weight:700; font-size:1.2rem;">
                    <i class="fas fa-key"></i>
                </div>
                <button type="submit" class="btn-friendly">Verify Code</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="newPasswordModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-lock-open"></i></div>
            <h3>New Password</h3>
            <p>Create a secure new password for your account.</p>
            <form id="newPasswordForm">
                <div class="friendly-input-group">
                    <input type="password" id="newPass" name="new_password" required placeholder="New Password">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="password" id="confirmPass" name="confirm_password" required placeholder="Confirm Password">
                    <i class="fas fa-check"></i>
                </div>
                <button type="submit" class="btn-friendly">Reset Password</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="verifyAccountModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-check-circle"></i></div>
            <h3>Verify Account</h3>
            <p>Enter the 6-digit code sent to <?php echo htmlspecialchars($_SESSION['otp_email'] ?? 'your email'); ?>.</p>
            
            <?php if(isset($_SESSION['error_message']) && isset($_SESSION['show_verify_modal'])): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="friendly-input-group">
                    <input type="text" name="otp" required placeholder="000000" maxlength="6" style="text-align:center; letter-spacing:5px; font-weight:700; font-size:1.2rem;">
                    <i class="fas fa-key"></i>
                </div>
                <button type="submit" name="verify_account" class="btn-friendly">Verify Now</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="reserveModal">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3 style="margin-bottom:5px; font-family:var(--font-head); font-size:1.8rem;">Secure Reservation</h3>
            <p style="color:#666; margin-bottom:20px; font-size:0.9rem;">Complete your details to secure this piece.</p>
            
            <form action="submit_booking.php" method="POST">
                <input type="hidden" id="res_art_id" name="artwork_id">
                
                <div class="form-group">
                    <label>Selected Artwork</label>
                    <input type="text" id="res_art_title" name="service" readonly style="background:#f9f9f9; color:#555; border-color:#eee;">
                </div>

                <div class="form-group">
                    <label>Preferred Viewing Date</label>
                    <input type="date" name="preferred_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div style="background:#fdfdfd; border:1px solid #eee; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <h4 style="font-size:0.9rem; color:var(--primary); margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:5px;">Security Verification</h4>
                    <div class="form-group">
                        <label>Full Legal Name</label>
                        <input type="text" name="full_name" required placeholder="e.g. Juan dela Cruz">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="phone_number" required placeholder="0912 345 6789" pattern="[0-9]{11}">
                    </div>
                    <p style="font-size:0.75rem; color:#888; margin-top:10px;">
                        <i class="fas fa-shield-alt"></i> Identity verification will be required upon viewing.
                    </p>
                </div>

                <div class="form-group">
                    <label>Special Requests</label>
                    <textarea name="special_requests" rows="2" placeholder="Any specific requirements?"></textarea>
                </div>

                <?php if($loggedIn): ?>
                    <button type="submit" name="submit_reservation" class="btn-full">Confirm Reservation</button>
                <?php else: ?>
                    <button type="button" class="btn-full" onclick="closeModal(); document.getElementById('loginModal').classList.add('active');">Log In to Reserve</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="copyModal">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3 style="margin-bottom:5px; font-family:var(--font-head); font-size:1.8rem;">Request Commission</h3>
            <p style="color:#666; margin-bottom:20px; font-size:0.9rem;">This piece is unavailable, but you can request a commission that is similar to the artwork or art style from the artist.</p>
            <form action="inquire.php" method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="Your email">
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="text" name="mobile" required placeholder="09..." maxlength="11">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" id="copyMessage" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn-full">Send Request</button>
            </form>
        </div>
    </div>

    <script>const isLoggedIn = <?php echo $loggedIn ? 'true' : 'false'; ?>;</script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800 });

        // 1. Zoom Logic
        const zoomFrame = document.getElementById('zoomFrame');
        const mainImage = document.getElementById('mainImage');
        if (zoomFrame && mainImage) {
            zoomFrame.addEventListener('mousemove', function(e) {
                const { left, top, width, height } = zoomFrame.getBoundingClientRect();
                const x = (e.clientX - left) / width * 100;
                const y = (e.clientY - top) / height * 100;
                mainImage.style.transformOrigin = `${x}% ${y}%`;
                mainImage.style.transform = "scale(2)";
            });
            zoomFrame.addEventListener('mouseleave', function() { 
                mainImage.style.transform = "scale(1)"; 
                setTimeout(() => { mainImage.style.transformOrigin = 'center center'; }, 100);
            });
        }

        // 2. Switch Image
        function switchImage(src, element) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
        }

        // 3. Heart Logic
        function toggleFavorite(btn, id) {
            if (!isLoggedIn) { 
                document.getElementById('loginModal').classList.add('active'); 
                return; 
            }
            
            const icon = btn.querySelector('i');
            const isLiked = btn.classList.contains('active');
            const action = isLiked ? 'remove_id' : 'add_id';

            btn.classList.add('animating');
            btn.classList.toggle('active');
            
            if (isLiked) { icon.classList.remove('fas'); icon.classList.add('far'); }
            else { icon.classList.remove('far'); icon.classList.add('fas'); }

            const formData = new FormData();
            formData.append(action, id);
            fetch('favorites.php', { method: 'POST', body: formData });

            setTimeout(() => btn.classList.remove('animating'), 400);
        }

        // 4. Modal Logic
        const reserveModal = document.getElementById('reserveModal');
        const copyModal = document.getElementById('copyModal');
        const loginModal = document.getElementById('loginModal');
        const signupModal = document.getElementById('signupModal');
        const forgotModal = document.getElementById('forgotModal');
        const resetOtpModal = document.getElementById('resetOtpModal');
        const newPasswordModal = document.getElementById('newPasswordModal');
        const verifyAccountModal = document.getElementById('verifyAccountModal');
        const closeBtns = document.querySelectorAll('.modal-close');

        function closeModal() { 
            document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active'));
        }
        closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
        window.addEventListener('click', (e) => { if (e.target.classList.contains('modal-overlay')) closeModal(); });

        document.getElementById('openLoginBtn')?.addEventListener('click', () => { closeModal(); loginModal.classList.add('active'); });
        document.getElementById('openSignupBtn')?.addEventListener('click', () => { closeModal(); signupModal.classList.add('active'); });
        document.getElementById('switchRegister')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); signupModal.classList.add('active'); });
        document.getElementById('switchLogin')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); loginModal.classList.add('active'); });
        document.getElementById('openForgotBtn')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); forgotModal.classList.add('active'); });
        document.querySelectorAll('.switchBackToLogin').forEach(btn => {
            btn.addEventListener('click', (e) => { e.preventDefault(); closeModal(); loginModal.classList.add('active'); });
        });

        // Trigger Modals from PHP logic
        <?php if(isset($_GET['login'])): ?> loginModal.classList.add('active'); <?php endif; ?>
        <?php if(isset($_GET['signup'])): ?> signupModal.classList.add('active'); <?php endif; ?>
        <?php if(isset($_SESSION['show_verify_modal'])): ?>
            verifyAccountModal.classList.add('active');
            <?php unset($_SESSION['show_verify_modal']); ?>
        <?php endif; ?>

        window.openReserveModal = function(id, title) {
            if (!isLoggedIn) {
                closeModal();
                loginModal.classList.add('active');
                return;
            }
            document.getElementById('res_art_id').value = id;
            document.getElementById('res_art_title').value = title;
            reserveModal.classList.add('active');
        }

        window.openCopyModal = function(title) {
            if (!isLoggedIn) {
                closeModal();
                loginModal.classList.add('active');
                return;
            }
            document.getElementById('copyMessage').value = "Hello, I am interested in this kind of art style similar to the artwork \"" + title + "\" and want to request a commission.";
            copyModal.classList.add('active');
        }

        // --- FORGOT PASSWORD AJAX ---
        let resetEmail = '';
        document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            resetEmail = document.getElementById('resetEmail').value;
            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = 'Sending...';

            const formData = new FormData();
            formData.append('ajax_action', 'send_reset_otp');
            formData.append('email', resetEmail);

            fetch('', { method: 'POST', body: formData }) 
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.innerHTML = originalText;
                if(data.status === 'success') {
                    forgotModal.classList.remove('active');
                    resetOtpModal.classList.add('active');
                } else {
                    alert(data.message);
                }
            }).catch(() => { btn.disabled = false; btn.innerHTML = originalText; alert('Error sending request.'); });
        });

        document.getElementById('resetOtpForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const otp = document.getElementById('otpCode').value;
            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = 'Verifying...';

            const formData = new FormData();
            formData.append('ajax_action', 'verify_reset_otp');
            formData.append('email', resetEmail);
            formData.append('otp', otp);

            fetch('', { method: 'POST', body: formData }) 
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.innerHTML = originalText;
                if(data.status === 'success') {
                    resetOtpModal.classList.remove('active');
                    newPasswordModal.classList.add('active');
                } else {
                    alert(data.message);
                }
            });
        });

        document.getElementById('newPasswordForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const newPass = document.getElementById('newPass').value;
            const confirmPass = document.getElementById('confirmPass').value;
            const otp = document.getElementById('otpCode').value; 
            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = 'Resetting...';

            const formData = new FormData();
            formData.append('ajax_action', 'reset_password');
            formData.append('email', resetEmail);
            formData.append('otp', otp);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confirmPass);

            fetch('', { method: 'POST', body: formData }) 
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.innerHTML = originalText;
                if(data.status === 'success') {
                    alert('Password reset successful! You can now log in.');
                    closeModal();
                    loginModal.classList.add('active');
                } else {
                    alert(data.message);
                }
            });
        });

        // 5. Header Logic
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const markAllBtn = document.getElementById('markAllRead');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');

            if (profilePill) {
                profilePill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    if (notifDropdown) notifDropdown.classList.remove('active');
                });
            }
            if (notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                    if (userDropdown) userDropdown.classList.remove('active');
                });
                
                fetch('fetch_notifications.php').then(r=>r.json()).then(d=>{
                    if(d.status==='success' && d.unread_count > 0) {
                        if(notifBadge) {
                            notifBadge.style.display='block';
                            notifBadge.innerText=d.unread_count;
                        }
                        if(notifList) {
                            notifList.innerHTML='';
                            d.notifications.forEach(n=>{
                                const li = document.createElement('li');
                                li.className = `notif-item ${n.is_read == 0 ? 'unread' : ''}`;
                                li.innerHTML = `<div class="notif-msg">${n.message}</div><button class="btn-notif-close">×</button>`;
                                li.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                    e.stopPropagation();
                                    if(confirm('Delete notification?')) {
                                        const fd = new FormData(); fd.append('id', n.id);
                                        fetch('delete_notifications.php', {method:'POST', body:fd})
                                            .then(r=>r.json()).then(d=>{ if(d.status==='success') location.reload(); });
                                    }
                                });
                                li.addEventListener('click', (e) => {
                                    if (e.target.classList.contains('btn-notif-close')) return;
                                    const fd = new FormData(); fd.append('id', n.id);
                                    fetch('mark_as_read.php', { method:'POST', body:fd }).then(() => location.reload());
                                });
                                notifList.appendChild(li);
                            });
                        }
                    }
                });
            }
            window.addEventListener('click', () => {
                if(notifDropdown) notifDropdown.classList.remove('active');
                if(userDropdown) userDropdown.classList.remove('active');
            });
        });
    </script>
</body>
</html>