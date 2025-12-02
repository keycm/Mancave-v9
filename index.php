<?php
session_start();
include 'config.php';
require_once __DIR__ . '/reset_mailer.php'; 

// =================================================================
// 1. AJAX HANDLER FOR OTP VERIFICATION (NEW)
// =================================================================
if (isset($_POST['ajax_verify'])) {
    header('Content-Type: application/json');
    $otp_input = trim($_POST['otp']);
    $email = $_SESSION['otp_email'] ?? '';

    if (empty($email) || empty($otp_input)) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired or missing input.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, account_activation_hash, reset_token_expires_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    } elseif ($user['account_activation_hash'] !== $otp_input) {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect OTP code.']);
    } elseif (strtotime($user['reset_token_expires_at']) < time()) {
        echo json_encode(['status' => 'error', 'message' => 'OTP has expired.']);
    } else {
        // Activate Account
        $upd = $conn->prepare("UPDATE users SET account_activation_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $upd->bind_param("i", $user['id']);
        if ($upd->execute()) {
            unset($_SESSION['otp_email']);
            echo json_encode(['status' => 'success', 'message' => 'Account successfully verified! You may now log in.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
    }
    exit; // Stop further execution for AJAX
}

// === FETCH USER DATA (Profile Pic & Favorites) ===
$user_favorites = [];
$user_profile_pic = ""; 

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    
    // 1. Fetch Favorites
    $fav_sql = "SELECT artwork_id FROM favorites WHERE user_id = $uid";
    if ($fav_res = mysqli_query($conn, $fav_sql)) {
        while($r = mysqli_fetch_assoc($fav_res)){
            $user_favorites[] = $r['artwork_id'];
        }
    }

    // 2. Fetch Profile Image
    $user_sql = "SELECT username, email, image_path FROM users WHERE id = $uid"; 
    $user_res = mysqli_query($conn, $user_sql);
    
    if ($user_data = mysqli_fetch_assoc($user_res)) {
        if (!empty($user_data['image_path'])) {
            $user_profile_pic = 'uploads/' . $user_data['image_path'];
        } else {
            $user_profile_pic = "https://ui-avatars.com/api/?name=" . urlencode($user_data['username']) . "&background=cd853f&color=fff&rounded=true&bold=true";
        }
    }
}

// === AJAX HANDLER FOR ARTIST DATA ===
if (isset($_GET['get_artist_data'])) {
    header('Content-Type: application/json');
    $artistName = mysqli_real_escape_string($conn, $_GET['get_artist_data']);
    $sql = "SELECT * FROM artworks WHERE artist = '$artistName' LIMIT 4";
    $result = mysqli_query($conn, $sql);
    $works = [];
    while($row = mysqli_fetch_assoc($result)) { $works[] = $row; }
    echo json_encode(['success' => true, 'works' => $works]);
    exit;
}

// === LOGIN LOGIC ===
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
            $_SESSION['show_verify_modal'] = true; // Trigger Modal
            header("Location: index.php");
            exit();
        }
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            header("Location: " . ($row['role'] == 'admin' ? 'admin.php' : 'index.php'));
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid password!";
        }
    } else {
        $_SESSION['error_message'] = "User not found!";
    }
    if(isset($_SESSION['error_message'])) {
        header("Location: index.php?login=1");
        exit();
    }
} 

// === SIGNUP LOGIC ===
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
                    $mail->Body = "<h3>Welcome to ManCave!</h3><p>Your activation code is: <b>$otp</b></p>";
                    try {
                        $mail->send();
                        $_SESSION['otp_email'] = $email;
                        // UPDATED: Instead of redirecting to separate page, show modal on index
                        $_SESSION['show_verify_modal'] = true; 
                        header("Location: index.php"); 
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
        header("Location: index.php?signup=1");
        exit();
    }
}

// === DATA FETCHING ===
$loggedIn = isset($_SESSION['username']);

// 1. Fetch Artworks (Latest Arrivals - LIMIT 3)
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$artworks = [];

$sql_art = "SELECT a.*, 
            (
                SELECT status 
                FROM bookings b 
                WHERE (b.artwork_id = a.id OR b.service = a.title) 
                AND b.status IN ('approved', 'completed') 
                ORDER BY b.id DESC LIMIT 1
            ) as active_booking_status
            FROM artworks a
            WHERE (
                SELECT COUNT(*) 
                FROM bookings b2
                WHERE (b2.artwork_id = a.id OR b2.service = a.title)
                AND b2.status = 'completed' 
                AND b2.preferred_date < '$seven_days_ago'
            ) = 0
            ORDER BY active_booking_status DESC, a.status ASC, a.id DESC 
            LIMIT 3";

$res_art = mysqli_query($conn, $sql_art);
if ($res_art) { while ($row = mysqli_fetch_assoc($res_art)) { $artworks[] = $row; } }

// 2. Fetch Artists
$artists_list = [];
$sql_artists = "SELECT * FROM artists ORDER BY id DESC LIMIT 6";
if ($res_artists = mysqli_query($conn, $sql_artists)) { 
    while ($row = mysqli_fetch_assoc($res_artists)) { $artists_list[] = $row; } 
}

// 3. Fetch Services
$services_list = [];
$sql_services = "SELECT * FROM services ORDER BY id ASC LIMIT 3";
if ($res_services = mysqli_query($conn, $sql_services)) { 
    while ($row = mysqli_fetch_assoc($res_services)) { $services_list[] = $row; } 
}

// 4. Fetch Events
$events_list = [];
$sql_events = "SELECT * FROM events ORDER BY event_date ASC LIMIT 2";
if ($res_events = mysqli_query($conn, $sql_events)) { 
    while ($row = mysqli_fetch_assoc($res_events)) { $events_list[] = $row; } 
}

// 5. Fetch Client Stories
$reviews_list = [];
$sql_review = "SELECT r.*, u.username, s.name as service_name 
               FROM ratings r 
               JOIN users u ON r.user_id = u.id 
               LEFT JOIN services s ON r.service_id = s.id
               WHERE r.rating >= 4 
               ORDER BY RAND() LIMIT 3";
if ($res_review = mysqli_query($conn, $sql_review)) {
    while ($row = mysqli_fetch_assoc($res_review)) { $reviews_list[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The ManCave Art Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="style.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        /* === PREVENT OVERFLOW === */
        html, body { width: 100%; max-width: 100vw; overflow-x: hidden !important; margin: 0; padding: 0; }
        section { overflow-x: hidden; width: 100%; }
        .row { margin-right: 0; margin-left: 0; }
        
        /* === NOTIFICATION STYLES === */
        .notification-wrapper { position: relative; display: inline-block; }
        .notif-dropdown { display: none; position: absolute; right: -50px; top: 160%; width: 300px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: 1px solid #eee; z-index: 1100; animation: fadeIn 0.2s ease-out; }
        .notif-dropdown.active { display: block; }
        .notif-list { max-height: 350px; overflow-y: auto; list-style: none; margin: 0; padding: 0; }
        .notif-item { padding: 15px 35px 15px 15px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; transition: 0.2s; cursor: pointer; display: flex; flex-direction: column; gap: 5px; position: relative; }
        .notif-item:hover { background: #fdfbf7; }
        .btn-notif-close { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #aaa; font-size: 1.2rem; cursor: pointer; padding: 0; }
        .btn-notif-close:hover { color: #ff4d4d; }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--brand-red); color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 5px; border-radius: 50%; min-width: 18px; text-align: center; border: 2px solid #fff; }

        /* === MODALS === */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000; padding: 20px; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #fff; padding: 30px; border-radius: 12px; width: 550px; max-width: 100%; max-height: 90vh; overflow-y: auto; position: relative; transform: translateY(20px); transition: 0.3s; box-shadow: 0 15px 50px rgba(0,0,0,0.4); }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        .modal-close { position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; transform: rotate(90deg); }
        
        /* Small Modal (Success/Auth) */
        .modal-card.small { width: 420px; max-width: 95%; padding: 45px 35px; text-align: center; }
        .modal-header-icon { font-size: 3rem; margin-bottom: 20px; width: 80px; height: 80px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .modal-header-icon.success { background: rgba(39, 174, 96, 0.1); color: #27ae60; }
        .modal-header-icon.auth { background: rgba(205, 133, 63, 0.1); color: var(--accent); }
        .modal-card.small h3 { font-family: var(--font-head); font-size: 1.8rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card.small p { color: #666; margin-bottom: 30px; font-size: 0.95rem; }
        
        .btn-friendly { width: 100%; padding: 15px; border-radius: 50px; border: none; background: linear-gradient(135deg, var(--accent-orange), #ff8c42); color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(243, 108, 33, 0.3); }
        .btn-friendly:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(243, 108, 33, 0.4); }
        
        /* Forms */
        .friendly-input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .friendly-input-group i { position: absolute; top: 50%; left: 20px; transform: translateY(-50%); color: #bbb; font-size: 1.1rem; pointer-events: none; }
        .friendly-input-group input { width: 100%; padding: 14px 14px 14px 55px; border-radius: 50px; background: #f8f9fa; border: 1px solid #e9ecef; outline: none; }
        .friendly-input-group input:focus { background: #fff; border-color: var(--accent-orange); }
        .friendly-input-group input:focus + i { color: var(--accent-orange); }
        
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #555; }
        .form-group input:not(.friendly-input-group input), .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container nav-container">
            <a href="./" class="logo"> <span class="logo-top">THE</span>
                <span class="logo-main"><span class="logo-red">M</span>an<span class="logo-red">C</span>ave</span>
                <span class="logo-bottom">GALLERY</span>
            </a>
            <ul class="nav-links" id="navLinks">
                <li><a href="#home">Home</a></li>
                <li><a href="#gallery">Collection</a></li>
                <li><a href="#artists">Artists</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact-form">Visit</a></li>
            </ul>
            <div class="nav-actions">
                <?php if ($loggedIn): ?>
                    <a href="favorites.php" class="header-icon-btn" title="My Favorites"> <i class="far fa-heart"></i></a>
                    <div class="notification-wrapper">
                        <button class="header-icon-btn" id="notifBtn" title="Notifications">
                            <i class="far fa-bell"></i>
                            <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <span>Notifications</span>
                                <button id="markAllRead" class="small-btn" style="border:none; background:none; color:var(--accent); cursor:pointer;">Mark all read</button>
                            </div>
                            <ul class="notif-list" id="notifList"><li class="no-notif">Loading...</li></ul>
                        </div>
                    </div>
                    <div class="user-dropdown">
                        <div class="profile-pill">
                            <img src="<?php echo htmlspecialchars($user_profile_pic); ?>" alt="Profile" class="profile-img">
                            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: rgba(255,255,255,0.7);"></i>
                        </div>
                        <div class="dropdown-content">
                            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="admin.php"><i class="fas fa-cog"></i> Dashboard</a> <?php endif; ?>
                            <a href="profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a> 
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a> 
                        </div>
                    </div>
                <?php else: ?>
                    <button id="openSignupBtn" class="btn-nav-outline">Sign Up</button>
                    <button id="openLoginBtn" class="btn-nav">Sign In</button>
                <?php endif; ?>
                <div class="mobile-menu-icon" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></div>
            </div>
        </div>
    </nav>

    <header id="home" class="hero">
        <div class="hero-slider">
            <div class="slide" style="background-image: url('Grand Opening - Man Cave Gallery/img-21.jpg');"></div>
            <div class="slide" style="background-image: url('Grand Opening - Man Cave Gallery/img-10.jpg');"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="container hero-content text-center" data-aos="fade-up">
            <p class="hero-subtitle">Welcome to the Sanctuary</p>
            <h1>Art That Tells Your Story</h1>
            <p class="hero-description">Explore a curated collection of contemporary masterpieces.</p>
            <div class="hero-btns">
                <a href="#gallery" class="btn-primary">Browse Collection</a>
                <a href="#about" class="btn-outline">Our Story</a>
            </div>
        </div>
    </header>
    
    <section class="section-padding"><div class="container"><h2 class="section-title text-center">Gallery Highlights</h2></div></section>

    <section id="contact-form" class="section-padding bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h4 class="section-tag">Get In Touch</h4>
                <h2 class="section-title">Send Us an Inquiry</h2>
            </div>
            <div class="row" style="align-items: stretch;">
                <div class="col-6">
                    <div style="background: var(--white); padding: 40px; border-radius: var(--radius); box-shadow: var(--shadow-soft); height: 100%;">
                        <form id="inquiryForm" action="inquire.php" method="POST"> 
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" required placeholder="name@example.com">
                            </div>
                            <div class="form-group">
                                <label>Mobile Number</label>
                                <input type="text" name="mobile" required placeholder="09..." maxlength="11">
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="message" id="contactMessage" rows="5" required placeholder="Tell us about your needs..."></textarea>
                            </div>
                            <button type="submit" class="btn-full" style="margin-top: 20px;">Send Message</button>
                        </form>
                    </div>
                </div>
                <div class="col-6">
                    <div style="height: 100%; min-height: 450px; width: 100%; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-soft);">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3854.4242495197054!2d120.60586927493148!3d14.969136585562088!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33965fd81593ac7b%3A0x548fb1cae8b5e942!2sThe%20ManCave%20Gallery!5e0!3m2!1sen!2sph!4v1764527302397!5m2!1sen!2sph" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="footer-bottom">
                © 2025 Man Cave Art Gallery. All Rights Reserved.
            </div>
        </div>
    </footer>

    <div class="modal-overlay" id="messageModal">
        <div class="modal-card small">
            <button class="modal-close" onclick="closeModal('messageModal')">×</button>
            <div class="modal-header-icon success"><i class="fas fa-check-circle"></i></div>
            <h3 id="msgTitle">Success!</h3>
            <p id="msgBody">Operation completed.</p>
            <button class="btn-friendly" onclick="closeModal('messageModal')">Okay</button>
        </div>
    </div>

    <div class="modal-overlay" id="verifyAccountModal">
        <div class="modal-card small">
            <button class="modal-close" onclick="closeModal('verifyAccountModal')">×</button>
            <div class="modal-header-icon auth"><i class="fas fa-shield-alt"></i></div>
            <h3>Verify Account</h3>
            <p>Enter the code sent to <strong><?php echo htmlspecialchars($_SESSION['otp_email'] ?? 'your email'); ?></strong></p>
            
            <form id="verifyForm">
                <div class="friendly-input-group">
                    <input type="text" name="otp" required placeholder="000000" maxlength="6" style="text-align:center; letter-spacing:5px; font-weight:700; font-size:1.2rem;">
                    <i class="fas fa-key"></i>
                </div>
                <button type="submit" class="btn-friendly">Verify Now</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon auth"><i class="fas fa-user-circle"></i></div>
            <h3>Welcome Back</h3>
            <p>Sign in to continue to your account</p>
            <?php if(isset($_GET['login']) && isset($_SESSION['error_message'])): ?>
                <div class="alert-error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            <form action="index.php" method="POST"> 
                <div class="friendly-input-group">
                    <input type="text" name="identifier" required placeholder="Username or Email">
                    <i class="fas fa-user"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="password" name="password" required placeholder="Password">
                    <i class="fas fa-lock"></i>
                </div>
                <button type="submit" name="login" class="btn-friendly">Sign In</button>
                <div class="modal-footer-link">Don't have an account? <a href="#" id="switchRegister">Sign Up</a></div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="signupModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <div class="modal-header-icon auth"><i class="fas fa-rocket"></i></div>
            <h3>Join The Club</h3>
            <p>Create an account to reserve unique art</p>
            <form action="index.php" method="POST"> 
                <div class="friendly-input-group">
                    <input type="text" name="username" required placeholder="Username">
                    <i class="fas fa-user"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="email" name="email" required placeholder="Email Address">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="password" name="password" required placeholder="Password">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="friendly-input-group">
                    <input type="password" name="confirm_password" required placeholder="Confirm Password">
                    <i class="fas fa-check-circle"></i>
                </div>
                <button type="submit" name="sign" class="btn-friendly">Create Account</button>
                <div class="modal-footer-link">Already a member? <a href="#" id="switchLogin">Log In</a></div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, offset: 50 });

        // --- HELPER: Show Generic Modal Message ---
        function showModalMessage(title, message) {
            document.getElementById('msgTitle').innerText = title;
            document.getElementById('msgBody').innerText = message;
            document.getElementById('messageModal').classList.add('active');
        }

        // --- MODAL CONTROL ---
        const loginModal = document.getElementById('loginModal');
        const signupModal = document.getElementById('signupModal');
        const verifyModal = document.getElementById('verifyAccountModal');
        const messageModal = document.getElementById('messageModal');

        function closeModal(id) {
            if(id) document.getElementById(id).classList.remove('active');
            else document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active'));
        }

        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.modal-overlay').classList.remove('active');
            });
        });

        // Open Auth Modals
        document.getElementById('openLoginBtn')?.addEventListener('click', () => { closeModal(); loginModal.classList.add('active'); });
        document.getElementById('openSignupBtn')?.addEventListener('click', () => { closeModal(); signupModal.classList.add('active'); });
        document.getElementById('switchRegister')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); signupModal.classList.add('active'); });
        document.getElementById('switchLogin')?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); loginModal.classList.add('active'); });

        // Auto Open Modals from PHP Session Flags
        <?php if(isset($_GET['login'])): ?> loginModal.classList.add('active'); <?php endif; ?>
        <?php if(isset($_GET['signup'])): ?> signupModal.classList.add('active'); <?php endif; ?>
        <?php if(isset($_SESSION['show_verify_modal'])): ?>
            verifyModal.classList.add('active');
            <?php unset($_SESSION['show_verify_modal']); ?>
        <?php endif; ?>

        // ===================================================
        // 1. SEND US AN INQUIRY - SEND MESSAGE MODAL ALERT
        // ===================================================
        const inquiryForm = document.getElementById('inquiryForm');
        if(inquiryForm) {
            inquiryForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                const formData = new FormData(this);
                
                fetch('inquire.php', { method: 'POST', body: formData })
                .then(response => response.text())
                .then(result => {
                    if(result.trim() === 'success') {
                        // SUCCESS: Close form (if it was modal) and show Success Modal
                        inquiryForm.reset();
                        showModalMessage('Sent!', 'Your message has been sent successfully. We will get back to you soon.');
                    } else {
                        alert('There was an error sending your message. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An unexpected error occurred.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        }

        // ===================================================
        // 2. SYSTEM VERIFICATION - SUCCESS MODAL ALERT
        // ===================================================
        const verifyForm = document.getElementById('verifyForm');
        if(verifyForm) {
            verifyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const btn = this.querySelector('button');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Verifying...';

                const formData = new FormData(this);
                formData.append('ajax_verify', '1'); // Trigger PHP AJAX block

                fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    
                    if (data.status === 'success') {
                        // Close verify modal
                        verifyModal.classList.remove('active');
                        // Show Success Modal
                        showModalMessage('Verified!', data.message);
                        
                        // Optional: When they close the success message, reload or open login
                        // document.getElementById('messageModal').querySelector('.btn-friendly').onclick = function() {
                        //    window.location.href = 'index.php?login=1';
                        // };
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    alert("Verification failed. Please try again.");
                });
            });
        }
        
        // Header & Navbar Logic
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if(window.scrollY > 50) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        });
        
        function toggleMobileMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }

        // Notification Logic (Standard)
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            
            if (notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                });
                
                fetch('fetch_notifications.php').then(r=>r.json()).then(data => {
                    if(data.status === 'success' && data.unread_count > 0) {
                        notifBadge.innerText = data.unread_count;
                        notifBadge.style.display = 'block';
                    }
                    if(data.notifications && data.notifications.length > 0) {
                        notifList.innerHTML = '';
                        data.notifications.forEach(n => {
                            notifList.innerHTML += `<li class="notif-item"><div class="notif-msg">${n.message}</div><button class="btn-notif-close">×</button></li>`;
                        });
                    }
                });
                
                window.addEventListener('click', () => notifDropdown.classList.remove('active'));
            }
        });
    </script>
</body>
</html>
