<?php
session_start();
include 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Update General Profile & Avatar
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Handle File Upload
        $imageUpdateSQL = "";
        $params = [$username, $email];
        $types = "ss";

        if (!empty($_FILES['profile_image']['name'])) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
            $targetFilePath = $targetDir . $fileName;
            
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
            $allowTypes = array('jpg','png','jpeg','gif');
            
            if (in_array(strtolower($fileType), $allowTypes)) {
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                    $imageUpdateSQL = ", image_path = ?";
                    $params[] = $fileName;
                    $types .= "s";
                } else {
                    $msg = "Error uploading image.";
                    $msg_type = "error";
                }
            } else {
                $msg = "Invalid file type. Only JPG, PNG & GIF allowed.";
                $msg_type = "error";
            }
        }

        if (empty($msg)) {
            $params[] = $user_id;
            $types .= "i";
            
            $sql = "UPDATE users SET username = ?, email = ? $imageUpdateSQL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $msg = "Profile updated successfully!";
                $msg_type = "success";
            } else {
                $msg = "Error updating database: " . $conn->error;
                $msg_type = "error";
            }
        }
    }

    // 2. Change Password
    if (isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        // Verify Current Password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_data = $res->fetch_assoc();

        if (!password_verify($current_pass, $user_data['password'])) {
            $msg = "Current password is incorrect.";
            $msg_type = "error";
        } elseif (strlen($new_pass) < 8) {
            $msg = "New password must be at least 8 characters.";
            $msg_type = "error";
        } elseif ($new_pass !== $confirm_pass) {
            $msg = "New passwords do not match.";
            $msg_type = "error";
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            if ($stmt->execute()) {
                $msg = "Password changed successfully!";
                $msg_type = "success";
            } else {
                $msg = "Error updating password.";
                $msg_type = "error";
            }
        }
    }
}

// Fetch User Data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$profileImg = !empty($user['image_path']) 
    ? 'uploads/' . $user['image_path'] 
    : "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=cd853f&color=fff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings | ManCave Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* =========================================
           1. GLOBAL VARIABLES & RESET
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
            background-color: #f9f9f9; /* Slight grey for profile page background */
            line-height: 1.6;
            font-size: 0.95rem;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }

        /* =========================================
           2. NAVBAR (COPIED FROM COLLECTION.PHP)
           ========================================= */
        .navbar {
            position: fixed; top: 0; width: 100%;
            background: rgba(255, 255, 255, 0.98);
            padding: 15px 0;
            z-index: 1000; 
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        .logo {
            text-decoration: none; display: flex; flex-direction: row; gap: 8px;
            align-items: baseline; line-height: 1; white-space: nowrap;
        }
        .logo:hover { transform: scale(1.02); }
        .logo-top { font-family: var(--font-head); font-size: 1rem; font-weight: 700; color: var(--primary); letter-spacing: 1px; margin-bottom: 0; }
        .logo-main { font-family: var(--font-script); font-size: 1.8rem; font-weight: 400; transform: rotate(-2deg); margin: 0; padding: 0; }
        .logo-red { color: #ff4d4d; }
        .logo-text { color: var(--primary); }
        .logo-bottom { font-family: var(--font-main); font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: 2px; text-transform: uppercase; margin: 0; }

        .nav-links { display: flex; gap: 30px; }
        .nav-links a { font-weight: 700; color: var(--primary); font-size: 1rem; position: relative; transition: color 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-orange); }

        .btn-nav { background: var(--primary); color: #fff; padding: 10px 25px; border-radius: 50px; border: none; cursor: pointer; font-weight: 700; margin-left: 15px; font-size: 0.9rem; transition: 0.3s; }
        .btn-nav-outline { background: transparent; color: var(--primary); border: 2px solid var(--primary); padding: 8px 20px; border-radius: 50px; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: 0.3s; }

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
        
        .notif-header { padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; font-weight: 700; background: #fafafa; font-size: 0.9rem; }
        .notif-list { max-height: 300px; overflow-y: auto; list-style: none; padding: 0; margin: 0; }
        
        /* Notification Item */
        .notif-item { padding: 15px 35px 15px 20px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer; position: relative; }
        .notif-item:hover { background: #fdfbf7; }
        .btn-notif-close { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #aaa; font-size: 1.2rem; line-height: 1; cursor: pointer; padding: 0; transition: color 0.2s; }
        .btn-notif-close:hover { color: #ff4d4d; }
        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        
        .mobile-menu-icon { display: none; font-size: 1.8rem; cursor: pointer; color: var(--primary); }

        /* =========================================
           3. PROFILE PAGE LAYOUT
           ========================================= */
        .settings-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 30px;
            max-width: 1100px;
            margin: 120px auto 40px;
            padding: 0 20px;
            align-items: start;
        }

        /* Sidebar */
        .settings-nav { background: #fff; padding: 15px; border-radius: 12px; border: 1px solid #eee; position: sticky; top: 100px; }
        .settings-nav a { display: flex; align-items: center; gap: 10px; padding: 12px; color: #666; font-weight: 700; border-radius: 8px; margin-bottom: 5px; transition: 0.2s; font-size: 0.9rem; }
        .settings-nav a:hover { background: #f5f5f5; color: var(--primary); }
        .settings-nav a.active { background: var(--accent-orange); color: #fff; }
        .settings-nav i { width: 20px; text-align: center; }

        /* Content Box */
        .settings-content { background: #fff; border: 1px solid #eee; padding: 35px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        
        .settings-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .settings-header h2 { font-family: var(--font-head); margin: 0 0 5px; font-size: 1.8rem; color: var(--primary); }
        .settings-header p { color: #888; margin: 0; font-size: 0.9rem; }

        /* Profile Info Top Section */
        .profile-header-card {
            display: flex; align-items: center; gap: 25px; margin-bottom: 35px;
            background: #fcfcfc; padding: 20px; border-radius: 12px; border: 1px solid #f0f0f0;
        }
        .avatar-preview { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .user-meta h4 { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--primary); }
        .user-meta span { color: #999; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .btn-upload { margin-top: 8px; border: 1px solid #ddd; background: #fff; padding: 6px 18px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; cursor: pointer; color: #555; transition: 0.2s; }
        .btn-upload:hover { border-color: var(--accent-orange); color: var(--accent-orange); }

        /* --- SPLIT LAYOUT FOR FORMS --- */
        .forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Side by Side */
            gap: 40px;
        }

        .form-section h4 { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 15px; border-left: 3px solid var(--accent-orange); padding-left: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 700; color: #555; font-size: 0.85rem; }
        .form-group input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; color: #333; transition: 0.3s; font-family: var(--font-main); }
        .form-group input:focus { border-color: var(--accent-orange); outline: none; box-shadow: 0 0 0 3px rgba(243, 108, 33, 0.1); }

        .btn-save { background: var(--primary); color: #fff; padding: 10px 30px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; border: none; cursor: pointer; transition: 0.3s; width: 100%; margin-top: 10px; }
        .btn-save:hover { background: var(--accent-orange); }

        /* Alert */
        .alert-box { padding: 12px 15px; border-radius: 6px; margin-bottom: 25px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        
        @media (max-width: 768px) {
            .settings-layout { grid-template-columns: 1fr; margin-top: 100px; }
            .forms-grid { grid-template-columns: 1fr; gap: 30px; } /* Stack on mobile */
            .navbar { padding: 10px 0; }
            .nav-links { display: none; }
            .mobile-menu-icon { display: block; color: var(--primary); font-size: 1.5rem; }
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
                <li><a href="collection.php">Collection</a></li>
                <li><a href="index.php#artists">Artists</a></li>
                <li><a href="index.php#services">Services</a></li>
                <li><a href="index.php#contact-form">Visit</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="favorites.php" class="header-icon-btn" title="My Favorites">
                    <i class="far fa-heart"></i>
                </a>

                <div class="notification-wrapper">
                    <button class="header-icon-btn" id="notifBtn">
                        <i class="far fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span>Notifications</span>
                            <button id="markAllRead" style="border:none; background:none; font-size:0.7rem; font-weight:700; color:var(--accent-orange); cursor:pointer;">MARK ALL READ</button>
                        </div>
                        <ul class="notif-list" id="notifList">
                            <li class="no-notif">Loading...</li>
                        </ul>
                    </div>
                </div>

                <div class="user-dropdown">
                    <div class="profile-pill">
                        <img src="<?php echo htmlspecialchars($profileImg); ?>" class="profile-img">
                        <span class="profile-name"><?php echo htmlspecialchars($user['username']); ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: var(--secondary);"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if($user['role'] === 'admin'): ?>
                            <a href="admin.php"><i class="fas fa-cog"></i> Dashboard</a>
                        <?php endif; ?>
                        <a href="profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
            <div class="mobile-menu-icon"><i class="fas fa-bars"></i></div>
        </div>
    </nav>

    <div class="container settings-layout">
        <aside class="settings-nav">
            <a href="#" class="active"><i class="fas fa-user-circle"></i> General Info</a>
            <a href="favorites.php"><i class="far fa-heart"></i> My Favorites</a>
            <a href="logout.php" style="color:#dc2626; margin-top:15px; border-top:1px solid #eee; border-radius:0; padding-top:15px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="settings-content">
            <div class="settings-header">
                <h2>Profile Settings</h2>
                <p>Manage your account details and security preferences.</p>
            </div>

            <?php if($msg): ?>
                <div class="alert-box <?php echo ($msg_type == 'success') ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="profile-header-card">
                    <img src="<?php echo htmlspecialchars($profileImg); ?>" class="avatar-preview" id="avatarPreview">
                    <div class="user-meta">
                        <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                        <span><?php echo htmlspecialchars($user['role'] === 'admin' ? 'Administrator' : 'Collector'); ?></span>
                        <div style="position:relative;">
                            <button class="btn-upload" type="button" onclick="document.getElementById('fileInput').click()">Change Photo</button>
                            <input type="file" id="fileInput" name="profile_image" style="display:none;" onchange="previewImage(event)" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="forms-grid">
                    <div class="form-section">
                        <h4>Personal Information</h4>
                        <div class="form-group">
                            <label>Display Name</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn-save">Save Profile</button>
                    </div>

                    <div class="form-section">
                        <h4>Security</h4>
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" placeholder="Required to change password">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" placeholder="Min. 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Retype new password">
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="background:#666;">Update Password</button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Image Preview Logic
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function(){
                const output = document.getElementById('avatarPreview');
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        // Dropdown & Notification Logic
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const markAllBtn = document.getElementById('markAllRead');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');

            // Profile Toggle
            if(profilePill) {
                profilePill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    if(notifDropdown) notifDropdown.classList.remove('active');
                });
            }

            // Notification Toggle
            if(notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                    if(userDropdown) userDropdown.classList.remove('active');
                });

                // Fetch Notifications
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
                                            <div>${notif.message}</div>
                                            <button class="btn-notif-close">×</button>
                                        `;
                                        
                                        // Mark read logic
                                        item.addEventListener('click', (e) => {
                                            if(e.target.classList.contains('btn-notif-close')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: formData })
                                                .then(() => fetchNotifications());
                                        });

                                        // Delete logic
                                        item.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(confirm('Delete notification?')) {
                                                const fd = new FormData();
                                                fd.append('id', notif.id);
                                                fetch('delete_notifications.php', { method:'POST', body:fd })
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
                        fetch('mark_all_as_read.php', { method: 'POST' })
                            .then(() => fetchNotifications());
                    });
                }

                fetchNotifications();
                setInterval(fetchNotifications, 30000);
            }

            window.addEventListener('click', () => {
                if(notifDropdown) notifDropdown.classList.remove('active');
                if(userDropdown) userDropdown.classList.remove('active');
            });
        });
    </script>
</body>
</html>