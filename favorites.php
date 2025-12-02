<?php
session_start();
include 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    // If accessed directly via URL, redirect. 
    // For AJAX calls, this might return the login page HTML, which is fine (handled by frontend check).
    header("Location: index.php?login=1");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- 1. ADD FAVORITE LOGIC (THIS WAS MISSING) ---
if (isset($_POST['add_id'])) {
    $art_id = intval($_POST['add_id']);
    
    // Check if it already exists to prevent duplicates
    $check_sql = "SELECT id FROM favorites WHERE user_id = ? AND artwork_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $art_id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();

    if ($check_res->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, artwork_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $art_id);
        $stmt->execute();
    }
    // Exit to prevent loading the HTML below during an AJAX call
    exit('success');
}

// --- 2. REMOVE FAVORITE LOGIC ---
if (isset($_POST['remove_id'])) {
    $art_id = intval($_POST['remove_id']);
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND artwork_id = ?");
    $stmt->bind_param("ii", $user_id, $art_id);
    $stmt->execute();
    
    // If this was a standard form submit (from favorites page), refresh.
    // If AJAX, this redirect is ignored by the JS but the action still happens.
    if (!isset($_POST['ajax'])) {
        header("Location: favorites.php");
        exit();
    }
    exit('success');
}

// --- 3. FETCH FAVORITES FOR DISPLAY ---
$favorites = [];
$sql = "SELECT a.* FROM favorites f 
        JOIN artworks a ON f.artwork_id = a.id 
        WHERE f.user_id = ? 
        ORDER BY f.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $favorites[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites | ManCave Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* Reusing consistent variables from Index */
        :root {
            --primary: #333333;       
            --secondary: #666666;     
            --accent-orange: #f36c21;
            --brand-red: #ff4d4d;
            --bg-light: #ffffff;      
            --font-main: 'Nunito Sans', sans-serif;       
            --font-head: 'Playfair Display', serif; 
        }

        body { font-family: var(--font-main); color: var(--secondary); background: #fcfcfc; }

        /* HEADER STYLES */
        .navbar {
            position: fixed; top: 0; width: 100%;
            background: rgba(255, 255, 255, 0.98); padding: 15px 0;
            z-index: 1000; border-bottom: 1px solid #eee;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        
        /* Logo & Links */
        .logo { text-decoration: none; display: flex; gap: 8px; align-items: baseline; white-space: nowrap; }
        .logo-top { font-family: var(--font-head); font-weight: 700; color: var(--primary); letter-spacing: 1px; }
        .logo-main { font-family: 'Pacifico', cursive; font-size: 1.8rem; transform: rotate(-2deg); margin: 0; }
        .logo-red { color: #ff4d4d; } .logo-text { color: var(--primary); }
        .logo-bottom { font-family: var(--font-main); font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: 2px; text-transform: uppercase; }
        
        .nav-links { display: flex; gap: 30px; }
        .nav-links a { font-weight: 700; color: var(--primary); position: relative; transition: 0.3s; }
        .nav-links a:hover { color: var(--accent-orange); }

        /* Header Icons & Profile */
        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .header-icon-btn {
            background: #f8f8f8; border: 1px solid #eee; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; position: relative;
        }
        .header-icon-btn:hover { background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); color: var(--accent-orange); }
        
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
        .notif-item { padding: 15px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer; }
        .notif-item:hover { background: #fdfbf7; }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--brand-red); color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 5px; border-radius: 50%; min-width: 18px; text-align: center; border: 2px solid #fff; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* PAGE CONTENT */
        .page-header-min { padding-top: 130px; padding-bottom: 40px; border-bottom: 1px solid #eee; margin-bottom: 40px; }
        .page-title { font-family: var(--font-head); font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
        .breadcrumb { font-size: 0.9rem; color: #888; }
        .breadcrumb a { font-weight: 700; color: var(--primary); }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Grid & Card */
        .collection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 40px 30px; padding-bottom: 60px; }
        
        .art-card-new { background: transparent; transition: transform 0.3s ease; }
        .art-card-new:hover { transform: translateY(-5px); }
        .art-img-wrapper-new { position: relative; width: 100%; aspect-ratio: 4/5; overflow: hidden; background: #f4f4f4; margin-bottom: 15px; border-radius: 8px; }
        .art-link-wrapper { display: block; width: 100%; height: 100%; }
        .art-img-wrapper-new img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .art-card-new:hover .art-img-wrapper-new img { transform: scale(1.05); }
        
        .art-title-new { font-size: 1.1rem; font-weight: 800; color: var(--accent-orange); margin-bottom: 2px; }
        .art-meta-new { font-size: 0.95rem; color: #555; font-style: italic; margin-bottom: 8px; }
        .art-footer-new { display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid #f9f9f9; }
        .price-new { font-weight: 600; color: #888; font-size: 1.1rem; }

        /* Remove Button */
        .btn-remove-float {
            position: absolute; top: 10px; right: 10px;
            background: rgba(255,255,255,0.9); border: none;
            width: 35px; height: 35px; border-radius: 50%;
            color: #ff4d4d; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center;
            transition: 0.3s;
        }
        .btn-remove-float:hover { background: #ff4d4d; color: white; transform: scale(1.1); }

        .btn-circle {
            width: 38px; height: 38px; border-radius: 50%; border: 1px solid #ddd;
            background: transparent; color: var(--accent-orange); display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.3s; font-size: 0.95rem;
        }
        .btn-circle:hover { border-color: var(--accent-orange); background: var(--accent-orange); color: #fff; }

        /* Empty State */
        .empty-state { text-align: center; padding: 100px 0; grid-column: 1/-1; }
        .empty-icon { font-size: 4rem; color: #ddd; margin-bottom: 20px; }
        .btn-outline-dark { border: 2px solid var(--primary); color: var(--primary); padding: 10px 30px; border-radius: 50px; font-weight: 700; transition: 0.3s; }
        .btn-outline-dark:hover { background: var(--primary); color: #fff; }
    </style>
</head>
<body>

    <nav class="navbar scrolled">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <span class="logo-top">THE</span>
                <span class="logo-main"><span class="logo-red">M</span><span class="logo-text">an</span><span class="logo-red">C</span><span class="logo-text">ave</span></span>
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
                <a href="favorites.php" class="header-icon-btn" style="color:var(--accent-orange); border-color:var(--accent-orange); background:#fff;">
                    <i class="fas fa-heart"></i>
                </a>

                <div class="notification-wrapper">
                    <button class="header-icon-btn" id="notifBtn">
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
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=cd853f&color=fff&rounded=true&bold=true" alt="Profile" class="profile-img">
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
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header-min">
            <div class="breadcrumb"><a href="index.php">Home</a> / Favorites</div>
            <h1 class="page-title">My Collection</h1>
            <p>Your personally curated selection of artworks.</p>
        </div>

        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="far fa-heart"></i></div>
                <h3>No favorites yet.</h3>
                <p style="margin-bottom:30px; color:#888;">Go explore the collection and save what inspires you.</p>
                <a href="collection.php" class="btn-outline-dark">Browse Collection</a>
            </div>
        <?php else: ?>
            <div class="collection-grid">
                <?php foreach ($favorites as $art): 
                    $imgSrc = !empty($art['image_path']) ? 'uploads/'.$art['image_path'] : 'https://placehold.co/600x800?text=Art';
                ?>
                <div class="art-card-new" data-aos="fade-up">
                    <div class="art-img-wrapper-new">
                        
                        <form method="POST" onsubmit="return confirm('Remove this artwork from your favorites?');">
                            <input type="hidden" name="remove_id" value="<?php echo $art['id']; ?>">
                            <button type="submit" class="btn-remove-float" title="Remove Favorite">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>

                        <a href="artwork_details.php?id=<?php echo $art['id']; ?>" class="art-link-wrapper">
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>">
                        </a>
                    </div>
                    
                    <div class="art-content-new">
                        <div class="art-title-new"><?php echo htmlspecialchars($art['title']); ?></div>
                        <div class="art-meta-new">
                            <span class="artist-name-new"><?php echo htmlspecialchars($art['artist']); ?></span>
                        </div>
                        <div class="art-footer-new">
                            <span class="price-new">Php <?php echo number_format($art['price']); ?></span>
                            <a href="artwork_details.php?id=<?php echo $art['id']; ?>" class="btn-circle" title="View Details">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, offset: 50 });

        // Header Logic (Same as Index)
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');

            if (profilePill && userDropdown) {
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
                
                // Fetch Notifications
                fetch('fetch_notifications.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success' && data.unread_count > 0) {
                            document.getElementById('notifBadge').innerText = data.unread_count;
                            document.getElementById('notifBadge').style.display = 'block';
                        }
                    });
            }

            window.addEventListener('click', () => {
                if (notifDropdown) notifDropdown.classList.remove('active');
                if (userDropdown) userDropdown.classList.remove('active');
            });
        });
    </script>
</body>
</html>