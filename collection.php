<?php
session_start();
include 'config.php';
require_once __DIR__ . '/reset_mailer.php'; 

// =================================================================
// 1. AUTHENTICATION & ACCOUNT LOGIC
// =================================================================

// --- AJAX HANDLER FOR FORGOT PASSWORD FLOW ---
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $response = ['status' => 'error', 'message' => 'An error occurred.'];

    try {
        if ($action === 'send_reset_otp') {
            $email = $_POST['email'] ?? '';
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $otp = random_int(100000, 999999);
                $otp_hash = hash("sha256", $otp); // Hash OTP for security
                $expiry = date("Y-m-d H:i:s", time() + 60 * 15); // 15 mins

                // Update DB
                $update = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?");
                $update->bind_param("ssi", $otp_hash, $expiry, $user['id']);
                
                if ($update->execute()) {
                    // Send Email
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
                // Verify OTP again before updating
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
            header("Location: collection.php?login=1");
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
                    header("Location: collection.php?login=1");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Database error.";
                }
            }
        }
    }
    header("Location: collection.php");
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
            header("Location: collection.php");
            exit();
        }
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            header("Location: " . ($row['role'] == 'admin' ? 'admin.php' : 'collection.php')); 
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid password!";
        }
    } else {
        $_SESSION['error_message'] = "User not found!";
    }
    if(isset($_SESSION['error_message'])) {
        header("Location: collection.php?login=1");
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
                        header("Location: collection.php"); 
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
        header("Location: collection.php?signup=1");
        exit();
    }
}

// =================================================================
// 2. COLLECTION LOGIC
// =================================================================

// --- FETCH USER FAVORITES ---
$user_favorites = [];
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $fav_sql = "SELECT artwork_id FROM favorites WHERE user_id = $uid";
    if ($fav_res = mysqli_query($conn, $fav_sql)) {
        while($r = mysqli_fetch_assoc($fav_res)){
            $user_favorites[] = $r['artwork_id'];
        }
    }
}

// --- FILTER & SORT LOGIC ---
$filter_artist = $_GET['artist'] ?? '';
$sort_option = $_GET['sort'] ?? 'newest';

// Build Query Parts
$where_clause = "1";
if (!empty($filter_artist)) {
    $clean_artist = mysqli_real_escape_string($conn, $filter_artist);
    $where_clause .= " AND a.artist = '$clean_artist'";
}

$order_clause = "a.id DESC"; // Default
switch ($sort_option) {
    case 'price_low': $order_clause = "a.price ASC"; break;
    case 'price_high': $order_clause = "a.price DESC"; break;
    case 'oldest': $order_clause = "a.id ASC"; break;
    case 'newest': default: $order_clause = "a.id DESC"; break;
}

// Fetch Artists for Dropdown
$artists_list = [];
$res_artists = mysqli_query($conn, "SELECT DISTINCT artist FROM artworks ORDER BY artist ASC");
if ($res_artists) {
    while($row = mysqli_fetch_assoc($res_artists)) {
        $artists_list[] = $row['artist'];
    }
}

// --- PAGINATION & 7-DAY LOGIC ---
$limit = 9; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));

// Count total with filters (Modified for 7-day rule)
$count_sql = "SELECT COUNT(*) 
              FROM artworks a 
              WHERE ($where_clause) 
              AND (
                  SELECT COUNT(*) 
                  FROM bookings b2
                  WHERE (b2.artwork_id = a.id OR b2.service = a.title)
                  AND b2.status = 'completed' 
                  AND b2.preferred_date < '$seven_days_ago'
              ) = 0";

$total_result = mysqli_query($conn, $count_sql);
$total_rows = ($total_result) ? mysqli_fetch_array($total_result)[0] : 0;
$total_pages = ceil($total_rows / $limit);

// --- FETCH ARTWORKS WITH ROBUST BOOKING CHECK ---
// UPDATED: Prioritize user Sort By over status
$artworks = [];
$sql_art = "SELECT a.*, 
            (
                SELECT status 
                FROM bookings b 
                WHERE (b.artwork_id = a.id OR b.service = a.title) 
                AND b.status IN ('approved', 'completed') 
                ORDER BY b.id DESC LIMIT 1
            ) as active_booking_status,
            (
                SELECT COUNT(*) 
                FROM favorites f 
                WHERE f.artwork_id = a.id
            ) as fav_count
            FROM artworks a
            WHERE ($where_clause)
            AND (
                SELECT COUNT(*) 
                FROM bookings b2
                WHERE (b2.artwork_id = a.id OR b2.service = a.title)
                AND b2.status = 'completed' 
                AND b2.preferred_date < '$seven_days_ago'
            ) = 0
            ORDER BY $order_clause 
            LIMIT $limit OFFSET $offset";

$res_art = mysqli_query($conn, $sql_art);
if ($res_art) {
    while ($row = mysqli_fetch_assoc($res_art)) {
        $artworks[] = $row;
    }
}

$loggedIn = isset($_SESSION['username']);
// Fetch Profile Image for Header
$user_profile_pic = "";
if ($loggedIn) {
    $uid = $_SESSION['user_id'];
    $u_res = mysqli_query($conn, "SELECT username, image_path FROM users WHERE id=$uid");
    if($u_data = mysqli_fetch_assoc($u_res)) {
         if (!empty($u_data['image_path'])) {
            $user_profile_pic = 'uploads/' . $u_data['image_path'];
        } else {
            $user_profile_pic = "https://ui-avatars.com/api/?name=" . urlencode($u_data['username']) . "&background=cd853f&color=fff&rounded=true&bold=true";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Collection | ManCave Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* =========================================
           UI VARIABLES & RESET
           ========================================= */
        :root {
            --primary: #333333;       
            --secondary: #666666;     
            --accent-orange: #f36c21;
            --brand-red: #ff4d4d;
            --bg-light: #ffffff;      
            --font-main: 'Nunito Sans', sans-serif;       
            --font-head: 'Playfair Display', serif; 
            --font-script: 'Pacifico', cursive;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-main);
            color: var(--secondary);
            background-color: var(--bg-light);
            line-height: 1.6;
            font-size: 0.95rem;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }

        /* =========================================
           NAVBAR (UPDATED FROM INDEX.PHP)
           ========================================= */
        .navbar {
            position: fixed; top: 0; width: 100%;
            background: rgba(255, 255, 255, 0.98);
            padding: 15px 0;
            z-index: 1000; 
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1140px; margin: 0 auto; padding: 0 20px; }

        .logo {
            text-decoration: none; display: flex; gap: 8px;
            align-items: baseline; line-height: 1; white-space: nowrap;
        }
        .logo:hover { transform: scale(1.02); }
        .logo-top { font-family: var(--font-head); font-size: 1rem; font-weight: 700; color: var(--primary); letter-spacing: 1px; margin-bottom: 0; }
        .logo-main { font-family: var(--font-script); font-size: 1.8rem; font-weight: 400; transform: rotate(-2deg); margin: 0; padding: 0; }
        .logo-red { color: #8B0000; }
        .logo-text { color: var(--primary); }
        .logo-bottom { font-family: var(--font-main); font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: 2px; text-transform: uppercase; margin: 0; }

        .nav-links { display: flex; gap: 30px; }
        .nav-links a { font-weight: 700; color: var(--primary); font-size: 1rem; position: relative; transition: color 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-orange); }

        .btn-nav { background: var(--primary); color: #fff; padding: 10px 25px; border-radius: 50px; border: none; cursor: pointer; font-weight: 700; margin-left: 15px; font-size: 0.9rem; transition: 0.3s; box-shadow: 0 3px 8px rgba(0,0,0,0.15); }
        .btn-nav:hover { background: var(--accent-orange); }
        .btn-nav-outline { background: transparent; color: var(--primary); border: 2px solid var(--primary); padding: 8px 20px; border-radius: 50px; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: 0.3s; }
        .btn-nav-outline:hover { background: var(--primary); color: #fff; }

        /* Header Icons & Profile */
        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .header-icon-btn {
            background: #f8f8f8; border: 1px solid #eee; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; position: relative;
        }
        .header-icon-btn:hover { background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); color: var(--accent-orange); }
        
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--brand-red); color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 5px; border-radius: 50%; min-width: 18px; text-align: center; border: 2px solid #fff; }
        
        .profile-pill { display: flex; align-items: center; gap: 10px; background: #f8f8f8; padding: 4px 15px 4px 4px; border-radius: 50px; border: 1px solid #eee; cursor: pointer; transition: all 0.3s ease; }
        .profile-pill:hover { background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-orange); }
        .profile-name { font-weight: 700; font-size: 0.9rem; color: var(--primary); padding-right: 5px; }

        /* Dropdowns */
        .user-dropdown, .notification-wrapper { position: relative; }
        .dropdown-content, .notif-dropdown { display: none; position: absolute; top: 140%; right: 0; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; z-index: 1001; }
        .dropdown-content { min-width: 180px; padding: 10px 0; }
        .notif-dropdown { width: 320px; right: -10px; top: 160%; }
        .user-dropdown.active .dropdown-content, .notif-dropdown.active { display: block; animation: fadeIn 0.2s ease-out; }
        
        .dropdown-content a { display: block; padding: 10px 20px; color: var(--primary); font-size: 0.9rem; }
        .dropdown-content a:hover { background: #f9f9f9; color: var(--accent-orange); }
        
        .notif-header { padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; font-weight: 700; background: #fafafa; font-size: 0.9rem; color: var(--primary); }
        .notif-list { max-height: 300px; overflow-y: auto; list-style: none; padding: 0; margin: 0; }
        .notif-item { padding: 15px 35px 15px 15px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer; position: relative; display: flex; flex-direction: column; gap: 5px; }
        .notif-item:hover { background: #fdfbf7; }
        .notif-item.unread { background: #fff8f0; border-left: 4px solid var(--accent-orange); }
        .notif-msg { color: #444; line-height: 1.4; }
        .notif-time { font-size: 0.75rem; color: #999; font-weight: 600; }
        .btn-notif-close { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #aaa; font-size: 1.2rem; line-height: 1; cursor: pointer; padding: 0; transition: color 0.2s; }
        .btn-notif-close:hover { color: #ff4d4d; }
        .no-notif { padding: 30px; text-align: center; color: #999; font-style: italic; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .mobile-menu-icon { display: none; font-size: 1.8rem; cursor: pointer; color: var(--primary); }

        /* =========================================
           COLLECTION HEADER & GRID
           ========================================= */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .section-padding { padding: 20px 0 80px; }

        .collection-header { padding-top: 140px; padding-bottom: 25px; margin-bottom: 40px; border-bottom: 1px solid #eaeaea; }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .collection-header h1 { font-size: 2.2rem; font-weight: 700; color: var(--primary); margin: 0; font-family: var(--font-head); letter-spacing: -0.5px; }
        .filter-actions { display: flex; align-items: center; gap: 15px; }
        
        .select-container { position: relative; min-width: 200px; }
        .styled-select { appearance: none; width: 100%; padding: 10px 40px 10px 20px; font-family: var(--font-main); font-weight: 700; font-size: 0.85rem; color: var(--primary); border: 1px solid #e0e0e0; border-radius: 50px; background: #fff; cursor: pointer; transition: 0.3s; outline: none; text-transform: uppercase; }
        .styled-select:hover, .styled-select:focus { border-color: var(--accent-orange); color: var(--accent-orange); box-shadow: 0 4px 12px rgba(243, 108, 33, 0.1); }
        .select-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--secondary); pointer-events: none; font-size: 0.8rem; }

        .collection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 40px 30px; padding-bottom: 40px; }
        .art-card { background: transparent; transition: transform 0.3s ease; }
        .art-card:hover { transform: translateY(-5px); }
        .art-image-wrapper { position: relative; width: 100%; aspect-ratio: 4/5; overflow: hidden; background: #f4f4f4; margin-bottom: 15px; border-radius: 8px; }
        .art-link-wrapper { display: block; width: 100%; height: 100%; }
        .art-image-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .art-card:hover .art-image-wrapper img { transform: scale(1.05); }
        .explore-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; transition: 0.4s; z-index: 3; }
        .art-card:hover .explore-overlay { opacity: 1; }
        .explore-icon { width: 60px; height: 60px; border: 2px solid rgba(255,255,255,0.8); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; margin-bottom: 15px; }
        .explore-text { color: white; font-size: 0.85rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
        .badge { position: absolute; top: 10px; left: 10px; padding: 5px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #fff; z-index: 2; }
        .available { background: #27ae60; }
        .reserved { background: #f39c12; }
        .sold { background: #c0392b; }
        .art-content { text-align: left; }
        .art-title { font-size: 1.1rem; font-weight: 800; color: var(--accent-orange); text-transform: uppercase; margin-bottom: 2px; }
        .art-meta-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
        .artist-name { font-size: 0.95rem; color: #555; font-style: italic; font-weight: 600; }
        .art-dims { font-size: 0.9rem; color: #999; }
        .art-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 5px; padding-top: 10px; border-top: 1px solid #f9f9f9; }
        .price { font-weight: 600; color: #888; font-size: 1.1rem; }
        .action-buttons { display: flex; gap: 10px; }
        .btn-circle { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #ddd; background: transparent; color: var(--accent-orange); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; font-size: 0.95rem; }
        .btn-circle:hover { border-color: var(--accent-orange); background: var(--accent-orange); color: #fff; }
        .btn-circle.disabled { border-color: #eee; color: #ccc; cursor: not-allowed; }
        .btn-circle.disabled:hover { background: transparent; color: #ccc; }
        
        @keyframes heartPump { 0% { transform: scale(1); } 50% { transform: scale(1.4); } 100% { transform: scale(1); } }
        @keyframes popBtn { 0% { transform: scale(1); } 50% { transform: scale(0.9); } 100% { transform: scale(1); } }
        .btn-heart.animating i { animation: heartPump 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .btn-heart.active i { color: #ff4d4d; font-weight: 900; } 
        .btn-cart.animating { animation: popBtn 0.3s ease; }

        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 60px; }
        .page-link { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border: 1px solid #ddd; border-radius: 50%; color: #333; font-weight: 600; transition: 0.3s; background: #fff; }
        .page-link:hover, .page-link.active { background: #333; border-color: #333; color: #fff; }

        /* =========================================
           FOOTER (UPDATED FROM INDEX.PHP)
           ========================================= */
        footer { background: #1a1a1a; color: #bbb; padding: 80px 0 30px; margin-top: auto; font-size: 0.95rem; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 50px; margin-bottom: 50px; }
        .footer-about h3 { color: #fff; margin-bottom: 20px; font-family: var(--font-head); font-size: 1.6rem; }
        .footer-logo { display: flex; flex-direction: row; gap: 8px; align-items: center; margin-bottom: 15px; }
        .footer-logo .logo-top { font-size: 1.8rem; margin-bottom: 0; }
        .footer-logo .logo-main { font-size: 2.4rem; margin: 0; padding: 0; }
        .footer-logo .logo-bottom { font-size: 1.8rem; margin-top: 0; letter-spacing: 1px; }
        .socials a { display: inline-flex; width: 40px; height: 40px; background: #333; color: #fff; align-items: center; justify-content: center; border-radius: 50%; margin-right: 10px; transition: 0.3s; font-size: 1.1rem; }
        .socials a:hover { background: var(--accent-orange); transform: translateY(-3px); }
        .footer-links h4, .footer-contact h4 { color: #fff; margin-bottom: 20px; font-size: 1.1rem; }
        .footer-links ul li { margin-bottom: 12px; }
        .footer-links a:hover { color: var(--accent-orange); padding-left: 5px; }
        .footer-contact p { margin-bottom: 12px; display: flex; gap: 12px; align-items: flex-start; }
        .footer-contact i { color: var(--accent-orange); margin-top: 4px; }
        .footer-bottom { border-top: 1px solid #333; padding-top: 25px; text-align: center; font-size: 0.85rem; }

        /* =========================================
           MODAL STYLES (NEW - FROM INDEX.PHP)
           ========================================= */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000; padding: 20px; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #fff; padding: 30px; border-radius: 12px; width: 550px; max-width: 100%; max-height: 90vh; overflow-y: auto; position: relative; transform: translateY(20px); transition: 0.3s; box-shadow: 0 15px 50px rgba(0,0,0,0.4); display: flex; flex-direction: column; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        .modal-card::-webkit-scrollbar { width: 6px; }
        .modal-card::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
        .modal-card::-webkit-scrollbar-track { background: #f9f9f9; }
        .modal-close { position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #999; transition: 0.2s; z-index: 10; }
        .modal-close:hover { color: #333; transform: rotate(90deg); }
        
        /* Small Auth Modal Styles */
        .modal-card.small { width: 420px; max-width: 95%; padding: 45px 35px; text-align: center; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .modal-header-icon { font-size: 3rem; color: var(--accent-orange); margin-bottom: 20px; background: rgba(243, 108, 33, 0.1); width: 80px; height: 80px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .modal-card.small h3 { font-family: var(--font-head); font-size: 1.8rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card.small p { color: #666; margin-bottom: 30px; font-size: 0.95rem; }
        
        .friendly-input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .friendly-input-group i { position: absolute; top: 50%; left: 20px; transform: translateY(-50%); color: #bbb; font-size: 1.1rem; pointer-events: none; transition: 0.3s; }
        .friendly-input-group input { width: 100%; padding: 14px 14px 14px 55px; border-radius: 50px; background: #f8f9fa; border: 1px solid #e9ecef; font-size: 0.95rem; transition: all 0.3s ease; outline: none; }
        .friendly-input-group input:focus { background: #fff; border-color: var(--accent-orange); box-shadow: 0 4px 15px rgba(243, 108, 33, 0.15); }
        .friendly-input-group input:focus + i { color: var(--accent-orange); }
        
        .btn-friendly { width: 100%; padding: 15px; border-radius: 50px; border: none; background: linear-gradient(135deg, var(--accent-orange), #ff8c42); color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(243, 108, 33, 0.3); }
        .btn-friendly:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(243, 108, 33, 0.4); }
        
        .modal-footer-link { margin-top: 25px; font-size: 0.9rem; color: #777; }
        .modal-footer-link a { color: var(--accent-orange); font-weight: 700; }
        .alert-error { background: #ffe6e6; color: #d63031; padding: 12px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 20px; border: 1px solid #fab1a0; }
        
        .forgot-pass-link { text-decoration: none; color: #888; font-size: 0.85rem; font-weight: 600; transition: color 0.3s ease; }
        .forgot-pass-link:hover { color: var(--accent-orange); }

        /* Generic Full Button (For Reserve Modal) */
        .btn-full { width: 100%; background: var(--primary); color: #fff; padding: 14px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; font-size: 1rem; transition: 0.3s; margin-top: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #555; }
        .form-group input:not(.friendly-input-group input), .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; }

        @media (max-width: 768px) {
            .navbar { background: #fff; padding: 10px 0; border-bottom: 1px solid #eee; }
            .nav-links { display: none; } 
            .mobile-menu-icon { display: block; color: var(--primary); font-size: 1.8rem; }
            .collection-header h1 { font-size: 1.8rem; }
            .collection-grid { grid-template-columns: 1fr; gap: 30px; }
            .header-content { flex-direction: column; align-items: flex-start; }
            .filter-actions { width: 100%; flex-direction: column; align-items: stretch; }
            .select-container { width: 100%; }
            .modal-card { width: 95%; padding: 30px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
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
                <li><a href="collection.php" class="active">Collection</a></li>
                <li><a href="index.php#artists">Artists</a></li>
                <li><a href="index.php#services">Services</a></li>
                <li><a href="index.php#contact-form">Visit</a></li>
            </ul>
            <div class="nav-actions">
                <?php if ($loggedIn): ?>
                    <a href="favorites.php" class="header-icon-btn" title="My Favorites"> <i class="far fa-heart"></i>
                    </a>
                    <div class="notification-wrapper">
                        <button class="header-icon-btn" id="notifBtn" title="Notifications">
                            <i class="far fa-bell"></i>
                            <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <span>Notifications</span>
                                <button id="markAllRead" style="border:none; background:none; color:var(--accent-orange); cursor:pointer; font-size:0.8rem; font-weight:700;">Mark all read</button>
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
                                <a href="admin.php"><i class="fas fa-cog"></i> Dashboard</a> <?php endif; ?>
                            <a href="profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a> <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a> </div>
                    </div>
                <?php else: ?>
                    <button id="openSignupBtn" class="btn-nav-outline">Sign Up</button>
                    <button id="openLoginBtn" class="btn-nav">Sign In</button>
                <?php endif; ?>
            </div>
            <div class="mobile-menu-icon"><i class="fas fa-bars"></i></div>
        </div>
    </nav>

    <div class="container">
        <header class="collection-header">
            <div class="header-content">
                <h1>All Artworks</h1>
                
                <form method="GET" class="filter-actions">
                    
                    <div class="select-container">
                        <select name="artist" class="styled-select" onchange="this.form.submit()">
                            <option value="">All Artists</option>
                            <?php foreach($artists_list as $artist_name): ?>
                                <option value="<?php echo htmlspecialchars($artist_name); ?>" <?php echo ($filter_artist == $artist_name) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($artist_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-icon"></i>
                    </div>

                    <div class="select-container">
                        <select name="sort" class="styled-select" onchange="this.form.submit()">
                            <option value="newest" <?php echo ($sort_option == 'newest') ? 'selected' : ''; ?>>Sort By: Newest</option>
                            <option value="oldest" <?php echo ($sort_option == 'oldest') ? 'selected' : ''; ?>>Sort By: Oldest</option>
                            <option value="price_low" <?php echo ($sort_option == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo ($sort_option == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                        <i class="fas fa-chevron-down select-icon"></i>
                    </div>

                </form>
            </div>
        </header>
    </div>

    <section class="section-padding">
        <div class="container">
            
            <div class="collection-grid">
                <?php if (empty($artworks)): ?>
                    <div class="col-12 text-center" style="grid-column: 1/-1; padding: 50px;">
                        <h3>No artworks found.</h3>
                        <p>Try adjusting your filters or check back later.</p>
                        <a href="collection.php" class="btn-nav-outline" style="color:#333; border-color:#333; margin-top:20px; display:inline-block;">Reset Filters</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($artworks as $art): 
                        $bookingStatus = $art['active_booking_status'] ?? null;
                        $favCount = $art['fav_count'] ?? 0;
                        
                        if ($bookingStatus === 'completed' || $art['status'] === 'Sold') {
                            $displayStatus = 'Sold';
                            $statusClass = 'sold';
                            $isSold = true;
                            $isReserved = false;
                            $isAvailable = false;
                        } elseif ($bookingStatus === 'approved' || $art['status'] === 'Reserved') {
                            $displayStatus = 'Reserved';
                            $statusClass = 'reserved';
                            $isSold = false;
                            $isReserved = true;
                            $isAvailable = false;
                        } else {
                            $displayStatus = 'Available';
                            $statusClass = 'available';
                            $isSold = false;
                            $isReserved = false;
                            $isAvailable = true;
                        }

                        $imgSrc = !empty($art['image_path']) ? 'uploads/'.$art['image_path'] : 'https://placehold.co/600x800?text=Art';
                        
                        $isFav = in_array($art['id'], $user_favorites);
                        $heartIcon = $isFav ? 'fas fa-heart' : 'far fa-heart'; 
                    ?>
                    
                    <div class="art-card" data-aos="fade-up">
                        <div class="art-image-wrapper">
                            <?php if(!$isAvailable): ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $displayStatus; ?></span>
                            <?php endif; ?>
                            
                            <a href="artwork_details.php?id=<?php echo $art['id']; ?>" class="art-link-wrapper">
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>">
                                <div class="explore-overlay">
                                    <div class="explore-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <p class="explore-text">CLICK TO VIEW</p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="art-content">
                            <div class="art-title"><?php echo htmlspecialchars($art['title']); ?></div>
                            
                            <div class="art-meta-row">
                                <span class="artist-name"><?php echo htmlspecialchars($art['artist']); ?></span>
                                <?php if(!empty($art['year'])): ?>
                                    <span class="art-year" style="font-size:0.9rem; color:#999;">, <?php echo htmlspecialchars($art['year']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:0.85rem; color:#777; margin-bottom:5px; display:flex; justify-content:space-between;">
                                <span><?php echo !empty($art['medium']) ? htmlspecialchars($art['medium']) : ''; ?></span>
                                <span><?php echo !empty($art['size']) ? htmlspecialchars($art['size']) : ''; ?></span>
                            </div>
                            
                            <div class="art-footer">
                                <span class="price">Php <?php echo number_format($art['price']); ?></span>
                                
                                <div class="action-buttons">
                                    <button class="btn-circle btn-heart <?php echo $isFav ? 'active' : ''; ?>" 
                                            onclick="toggleFavorite(this, <?php echo $art['id']; ?>)" 
                                            title="Toggle Favorite"
                                            style="width:auto; padding:0 12px; border-radius:50px; display:flex; align-items:center; gap:5px;">
                                        <i class="<?php echo $heartIcon; ?>"></i>
                                        <span class="fav-count" style="font-size:0.8rem; font-weight:700;"><?php echo $favCount; ?></span>
                                    </button>
                                    
                                    <?php if($isSold || $isReserved): ?>
                                        <button class="btn-circle" 
                                                onclick="openCopyModal('<?php echo addslashes($art['title']); ?>')" 
                                                title="Request a Copy">
                                            <i class="fas fa-clone"></i>
                                        </button>
                                    <?php elseif($isAvailable): ?>
                                        <button class="btn-circle btn-cart" 
                                                onclick="animateCart(this); openReserveModal(<?php echo $art['id']; ?>, '<?php echo addslashes($art['title']); ?>')" 
                                                title="Reserve Artwork">
                                            <i class="fas fa-shopping-cart"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-circle disabled" title="Unavailable">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                    $queryParams = $_GET; 
                    unset($queryParams['page']); 
                    $queryString = http_build_query($queryParams);
                    $linkPrefix = "?{$queryString}&page=";
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?php echo $linkPrefix . ($page-1); ?>" class="page-link">&laquo;</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?php echo $linkPrefix . $i; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $linkPrefix . ($page+1); ?>" class="page-link">&raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <a class="footer-logo" style="text-decoration:none; display:flex; flex-direction:row; gap:8px; align-items:center; margin-bottom:15px;">
                        <span style="font-family:'Playfair Display', serif; font-size:1.8rem; font-weight:700; color:#fff; letter-spacing:1px; margin-bottom:0;">THE</span>
                        <span style="font-family:'Pacifico', cursive; font-size:2.4rem; transform:rotate(-4deg); margin:0; padding:0; color:#fff;"><span style="color:#ff4d4d;">M</span>an<span style="color:#ff4d4d;">C</span>ave</span>
                        <span style="font-family:'Nunito Sans', sans-serif; font-size:1.8rem; font-weight:800; color:#fff; letter-spacing:1px; margin-top:0;">GALLERY</span>
                    </a>
                    <p>Where passion meets preservation. Located in Pampanga.</p>
                    <div class="socials">
                        <a href="https://web.facebook.com/profile.php?id=61581718054821&_rdc=1&_rdr#" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/the_mancave_gallery_ph?igsh=MW9wczBzcWpka3E3Nw%3D%3D" target="_blank"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-links">
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="collection.php">Collection</a></li>
                        <li><a href="index.php#artists">Artists</a></li>
                        <li><a href="index.php#services">Services</a></li>
                        <li><a href="index.php#contact-form">Visit</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact</h4>
                    <p><i class="fas fa-envelope"></i> info@mancave.gallery</p>
                    <p><i class="fas fa-phone"></i> +63 912 345 6789</p>
                    <p><i class="fas fa-map-marker-alt"></i> San Antonio, Guagua, Pampanga</p>
                </div>
            </div>
            <div class="footer-bottom">
                © 2025 Man Cave Art Gallery. All Rights Reserved.
            </div>
        </div>
    </footer>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon"><i class="fas fa-user-circle"></i></div>
            <h3>Welcome Back</h3>
            <p>Sign in to continue to your account</p>
            <?php if(isset($_SESSION['error_message']) && isset($_GET['login'])): ?>
                <div class="alert-error" style="text-align:left;">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <form action="collection.php" method="POST"> 
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
                <div class="alert-error" style="text-align:left;">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <form action="collection.php" method="POST"> 
                <div class="friendly-input-group">
                    <input type="text" name="username" required placeholder="Full Name">
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
                <div class="alert-error" style="text-align:left;">
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
            <button class="modal-close">×</button>
            <h3 style="margin-bottom:5px;">Secure Reservation</h3>
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

                <button type="submit" name="submit_reservation" class="btn-full">Confirm Reservation</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="copyModal">
        <div class="modal-card">
            <button class="modal-close">×</button>
            <h3>Request Commission</h3>
            <p style="color:#666; margin-bottom:20px; font-size:0.9rem;">This piece is unavailable. Request a similar commission.</p>
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
        AOS.init({ duration: 800, offset: 50 });

        // --- GLOBAL VARIABLES & MODALS ---
        const loginModal = document.getElementById('loginModal');
        const signupModal = document.getElementById('signupModal');
        const forgotModal = document.getElementById('forgotModal');
        const resetOtpModal = document.getElementById('resetOtpModal');
        const newPasswordModal = document.getElementById('newPasswordModal');
        const verifyAccountModal = document.getElementById('verifyAccountModal');
        const reserveModal = document.getElementById('reserveModal');
        const copyModal = document.getElementById('copyModal');
        const closeBtns = document.querySelectorAll('.modal-close');

        function closeModal() { document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active')); }
        closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
        window.addEventListener('click', (e) => { if (e.target.classList.contains('modal-overlay')) closeModal(); });

        document.getElementById('openLoginBtn')?.addEventListener('click', () => { closeModal(); loginModal.classList.add('active'); });
        document.getElementById('openSignupBtn')?.addEventListener('click', () => { closeModal(); signupModal.classList.add('active'); });
        document.getElementById('switchRegister')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); signupModal.classList.add('active'); });
        document.getElementById('switchLogin')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); loginModal.classList.add('active'); });

        // Forgot Password Logic
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

        // --- FORGOT PASSWORD FLOW (AJAX) ---
        let resetEmail = '';

        // Step 1: Send OTP
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            e.preventDefault();
            resetEmail = document.getElementById('resetEmail').value;
            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            const formData = new FormData();
            formData.append('ajax_action', 'send_reset_otp');
            formData.append('email', resetEmail);

            fetch('collection.php', { method: 'POST', body: formData }) 
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

        // Step 2: Verify OTP
        document.getElementById('resetOtpForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const otp = document.getElementById('otpCode').value;
            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = 'Verifying...';

            const formData = new FormData();
            formData.append('ajax_action', 'verify_reset_otp');
            formData.append('email', resetEmail);
            formData.append('otp', otp);

            fetch('collection.php', { method: 'POST', body: formData }) 
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

        // Step 3: Reset Password
        document.getElementById('newPasswordForm').addEventListener('submit', function(e) {
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

            fetch('collection.php', { method: 'POST', body: formData }) 
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

        window.openReserveModal = function(id, title) {
            if(!isLoggedIn) { loginModal.classList.add('active'); return; }
            document.getElementById('res_art_id').value = id;
            document.getElementById('res_art_title').value = title;
            reserveModal.classList.add('active');
        }

        window.openCopyModal = function(title) {
            document.getElementById('copyMessage').value = "Hello, I am interested in this kind of art style similar to the artwork \"" + title + "\" and want to request a commission.";
            copyModal.classList.add('active');
        }

        // --- NOTIFICATION LOGIC ---
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');
            const markAllBtn = document.getElementById('markAllRead');

            if(profilePill) {
                profilePill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    if(notifDropdown) notifDropdown.classList.remove('active');
                });
            }

            if (notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                    if (userDropdown) userDropdown.classList.remove('active');
                });

                function fetchNotifications() {
                    fetch('fetch_notifications.php')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                if (data.unread_count > 0) {
                                    notifBadge.innerText = data.unread_count;
                                    notifBadge.style.display = 'block';
                                } else {
                                    notifBadge.style.display = 'none';
                                }
                                notifList.innerHTML = '';
                                if (data.notifications.length === 0) {
                                    notifList.innerHTML = '<li class="no-notif">No new notifications</li>';
                                } else {
                                    data.notifications.forEach(notif => {
                                        const item = document.createElement('li');
                                        item.className = `notif-item ${notif.is_read == 0 ? 'unread' : ''}`;
                                        item.innerHTML = `
                                            <div class="notif-msg">${notif.message}</div>
                                            <div class="notif-time">${notif.created_at}</div>
                                            <button class="btn-notif-close" title="Delete">×</button>
                                        `;
                                        
                                        item.addEventListener('click', (e) => {
                                            if(e.target.classList.contains('btn-notif-close')) return;
                                            const fd = new FormData(); fd.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: fd }).then(() => fetchNotifications());
                                        });

                                        item.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(confirm('Delete notification?')) {
                                                const fd = new FormData(); fd.append('id', notif.id);
                                                fetch('delete_notifications.php', { method: 'POST', body: fd })
                                                    .then(r=>r.json()).then(d=>{ if(d.status==='success') fetchNotifications(); });
                                            }
                                        });
                                        notifList.appendChild(item);
                                    });
                                }
                            }
                        });
                }

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        fetch('mark_all_as_read.php', { method: 'POST' }).then(() => fetchNotifications());
                    });
                }

                fetchNotifications();
            }

            window.addEventListener('click', () => {
                if(notifDropdown) notifDropdown.classList.remove('active');
                if(userDropdown) userDropdown.classList.remove('active');
            });
        });

        // --- FAVORITES & CART ANIMATION ---
        window.toggleFavorite = function(btn, id) {
            if(!isLoggedIn) { loginModal.classList.add('active'); return; }
            
            const icon = btn.querySelector('i');
            const countSpan = btn.querySelector('.fav-count');
            const isLiked = btn.classList.contains('active');
            const action = isLiked ? 'remove_id' : 'add_id';

            btn.classList.add('animating');
            
            let count = parseInt(countSpan.innerText);
            if(isLiked) {
                btn.classList.remove('active');
                icon.classList.remove('fas'); icon.classList.add('far');
                count = Math.max(0, count - 1);
            } else {
                btn.classList.add('active');
                icon.classList.remove('far'); icon.classList.add('fas');
                count++;
            }
            countSpan.innerText = count;

            const formData = new FormData();
            formData.append(action, id);
            fetch('favorites.php', { method: 'POST', body: formData }); 

            setTimeout(() => btn.classList.remove('animating'), 400);
        }

        window.animateCart = function(btn) {
            btn.classList.add('animating');
            setTimeout(() => btn.classList.remove('animating'), 300);
        }
    </script>
</body>
</html>