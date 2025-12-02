<?php
session_start();
include 'config.php';

// Check login status
$loggedIn = isset($_SESSION['username']);

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

// Get Artist Name from URL
$artistName = isset($_GET['artist']) ? urldecode($_GET['artist']) : '';

if (empty($artistName)) {
    header("Location: index.php#artists");
    exit();
}

// --- 1. FETCH ARTIST DETAILS FROM DATABASE ---
$stmt = $conn->prepare("SELECT * FROM artists WHERE name = ? LIMIT 1");
$stmt->bind_param("s", $artistName);
$stmt->execute();
$artist_result = $stmt->get_result();
$artist_data = $artist_result->fetch_assoc();

if (!$artist_data) {
    echo "Artist profile not found.";
    exit();
}

$artist_id = $artist_data['id'];

// --- 2. FETCH ARTIST LIKES COUNT & STATE ---
$like_count = 0;
$user_has_liked = false;

// Get total likes
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM artist_likes WHERE artist_id = ?");
$count_stmt->bind_param("i", $artist_id);
$count_stmt->execute();
$like_count = $count_stmt->get_result()->fetch_row()[0];

// Check if current user liked
if ($loggedIn) {
    $uid = $_SESSION['user_id'];
    $check_stmt = $conn->prepare("SELECT id FROM artist_likes WHERE user_id = ? AND artist_id = ?");
    $check_stmt->bind_param("ii", $uid, $artist_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $user_has_liked = true;
    }
}

// Prepare data for display
$data = [
    "image" => !empty($artist_data['image_path']) ? 'uploads/' . $artist_data['image_path'] : "https://ui-avatars.com/api/?name=" . urlencode($artistName) . "&size=500",
    "style" => !empty($artist_data['style']) ? $artist_data['style'] : "Fine Art",
    "quote" => !empty($artist_data['quote']) ? $artist_data['quote'] : "Art is the journey of a free soul.",
    "bio"   => !empty($artist_data['bio']) ? $artist_data['bio'] : "Biography not available yet."
];

// --- 3. FETCH ARTWORKS BY THIS ARTIST ---
$artworks = [];
$stmt_art = $conn->prepare("SELECT * FROM artworks WHERE artist = ? ORDER BY id DESC");
$stmt_art->bind_param("s", $artistName);
$stmt_art->execute();
$result_art = $stmt_art->get_result();
while ($row = $result_art->fetch_assoc()) {
    $artworks[] = $row;
}

// --- FETCH USER FAVORITES (For Artwork Cards) ---
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($artistName); ?> | Artist Profile</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* === PAGE SPECIFIC STYLES === */
        body { background-color: #fcfcfc; }
        
        /* === HEADER UI (MATCHING HOMEPAGE) === */
        .navbar {
            position: fixed; top: 0; width: 100%;
            background: rgba(255, 255, 255, 0.98);
            padding: 15px 0;
            z-index: 1000; 
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        .logo { text-decoration: none; display: flex; gap: 8px; align-items: baseline; white-space: nowrap; }
        .logo-top { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 700; color: var(--primary); letter-spacing: 1px; margin: 0; }
        .logo-main { font-family: 'Pacifico', cursive; font-size: 1.8rem; transform: rotate(-2deg); margin: 0; padding: 0; color: var(--primary); }
        .logo-red { color: #ff4d4d; }
        .logo-bottom { font-family: 'Nunito Sans', sans-serif; font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: 2px; text-transform: uppercase; margin: 0; }

        .nav-links { display: flex; gap: 30px; }
        .nav-links a { font-weight: 700; color: var(--primary); font-size: 1rem; position: relative; transition: color 0.3s; }
        .nav-links a:hover { color: var(--accent); }

        /* Icons & Profile */
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
        
        /* Notification Item */
        .notif-item { padding: 15px 35px 15px 20px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer; position: relative; }
        .notif-item:hover { background: #fdfbf7; }
        .btn-notif-close { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #aaa; font-size: 1.2rem; line-height: 1; cursor: pointer; padding: 0; transition: color 0.2s; }
        .btn-notif-close:hover { color: #ff4d4d; }
        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        
        .mobile-menu-icon { display: none; font-size: 1.8rem; cursor: pointer; color: var(--primary); }

        /* === ARTIST PROFILE LAYOUT === */
        .artist-header-section { padding-top: 130px; padding-bottom: 60px; background: #fff; border-bottom: 1px solid #eaeaea; }
        .back-btn-wrapper { margin-bottom: 30px; }
        .btn-back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--secondary); font-weight: 700; font-size: 0.95rem; transition: 0.3s; }
        .btn-back-link:hover { color: var(--accent); transform: translateX(-5px); }

        .profile-layout { display: grid; grid-template-columns: 350px 1fr; gap: 60px; align-items: start; }
        .artist-img-container { position: relative; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); aspect-ratio: 1/1.2; }
        .artist-img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .artist-img-container:hover img { transform: scale(1.03); }

        .artist-details { padding-top: 10px; }
        .name-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }
        .artist-name-lg { font-family: 'Playfair Display', serif; font-size: 3rem; color: var(--primary); margin: 0; line-height: 1; }

        /* FOLLOW BUTTON WITH COUNT */
        .btn-follow {
            height: 50px; border-radius: 30px; padding: 0 25px;
            border: 2px solid #eee; background: #fff;
            color: #ccc; font-size: 1.2rem; 
            display: flex; align-items: center; gap: 10px; justify-content: center;
            cursor: pointer; transition: 0.3s;
        }
        .btn-follow:hover { border-color: var(--brand-red); color: var(--brand-red); transform: scale(1.05); }
        .btn-follow.active { background: var(--brand-red); border-color: var(--brand-red); color: #fff; }
        .btn-follow span { font-weight: 700; font-size: 1rem; font-family: var(--font-main); }

        .artist-quote-lg { font-size: 1.4rem; font-style: italic; color: var(--accent); margin-bottom: 30px; font-family: 'Playfair Display', serif; position: relative; padding-left: 20px; border-left: 4px solid var(--accent); }
        .bio-section h4 { text-transform: uppercase; letter-spacing: 2px; font-size: 0.9rem; color: #999; margin-bottom: 10px; }
        .bio-text { font-size: 1.05rem; line-height: 1.8; color: #555; margin-bottom: 30px; white-space: pre-line; }

        /* FEATURED WORKS */
        .featured-section { padding: 80px 0; background-color: #f8f9fa; }
        .section-header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
        .section-title-sm { font-size: 2rem; margin: 0; font-family: 'Playfair Display', serif; color: var(--primary); }
        .artist-works-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }

        .work-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s ease, box-shadow 0.3s ease; display: flex; flex-direction: column; }
        .work-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); }
        .work-img-wrapper { position: relative; width: 100%; aspect-ratio: 4/5; overflow: hidden; background: #eee; }
        .work-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .work-card:hover .work-img-wrapper img { transform: scale(1.05); }
        .work-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .work-card:hover .work-overlay { opacity: 1; }
        .btn-view-work { background: #fff; color: var(--primary); padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; transform: translateY(10px); transition: transform 0.3s ease; }
        .work-card:hover .btn-view-work { transform: translateY(0); }
        .work-content { padding: 15px 20px; border-top: 1px solid #f0f0f0; }
        .work-title { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .work-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .work-price { font-weight: 600; color: #666; font-size: 1rem; }
        .badge-sm { font-size: 0.65rem; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; font-weight: 700; color: #fff; }
        .bg-available { background: #27ae60; }
        .bg-reserved { background: #f39c12; }
        .bg-sold { background: #c0392b; }
        
        .btn-circle { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--accent); background: transparent; color: var(--accent); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; font-size: 0.85rem; }
        .btn-circle:hover { background: var(--accent); color: #fff; }

        /* Modal Overlay */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: var(--white); padding: 50px; border-radius: 20px; width: 500px; max-width: 90%; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.3); transform: translateY(20px); transition: 0.3s; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888; transition: 0.3s; }
        .btn-full { width: 100%; background: var(--primary); color: var(--white); padding: 16px; border-radius: 10px; border: none; font-weight: 800; cursor: pointer; font-size: 1.1rem; transition: 0.3s; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }

        @media (max-width: 900px) {
            .profile-layout { grid-template-columns: 1fr; gap: 30px; }
            .artist-img-container { max-width: 400px; margin: 0 auto; }
            .artist-name-lg { font-size: 2.2rem; }
        }
        @media (max-width: 768px) {
            .navbar { background: var(--white); padding: 15px 0; border-bottom: 1px solid var(--border-color); }
            .nav-links { display: none; } 
            .mobile-menu-icon { display: block; color: var(--primary); font-size: 1.8rem; }
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
                    <a href="index.php?login=1" class="btn-nav">Sign In</a>
                <?php endif; ?>
            </div>
            <div class="mobile-menu-icon"><i class="fas fa-bars"></i></div>
        </div>
    </nav>

    <header class="artist-header-section">
        <div class="container">
            <div class="back-btn-wrapper">
                <a href="index.php#artists" class="btn-back-link"><i class="fas fa-arrow-left"></i> Back to Artists</a>
            </div>

            <div class="profile-layout">
                <div class="artist-img-container" data-aos="fade-right">
                    <img src="<?php echo htmlspecialchars($data['image']); ?>" alt="<?php echo htmlspecialchars($artistName); ?>">
                </div>

                <div class="artist-details" data-aos="fade-left">
                    <div class="name-row">
                        <h1 class="artist-name-lg"><?php echo htmlspecialchars($artistName); ?></h1>
                        
                        <button class="btn-follow <?php echo $user_has_liked ? 'active' : ''; ?>" 
                                onclick="toggleArtistLike(this, <?php echo $artist_id; ?>)" 
                                title="Follow Artist">
                            <i class="<?php echo $user_has_liked ? 'fas' : 'far'; ?> fa-heart"></i>
                            <span id="likeCount"><?php echo $like_count; ?></span>
                        </button>
                    </div>

                    <div class="artist-quote-lg">"<?php echo htmlspecialchars($data['quote']); ?>"</div>

                    <div class="bio-section">
                        <h4>Professional Art Style</h4>
                        <p class="bio-text" style="font-weight:700; color:var(--primary); font-size:1.2rem;">
                            <?php echo htmlspecialchars($data['style']); ?>
                        </p>
                        
                        <h4>About the Artist</h4>
                        <p class="bio-text"><?php echo nl2br(htmlspecialchars($data['bio'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="featured-section">
        <div class="container">
            <div class="section-header-row">
                <h2 class="section-title-sm">Featured Works</h2>
                <a href="collection.php?artist=<?php echo urlencode($artistName); ?>" class="btn-nav-outline" style="color:#333; border-color:#333;">View All</a>
            </div>

            <div class="artist-works-grid">
                <?php if (empty($artworks)): ?>
                    <div class="col-12 text-center" style="grid-column: 1/-1; padding:40px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                        <p style="color:#888;">No artworks found for this artist in our current inventory.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($artworks as $art): 
                        $imgSrc = !empty($art['image_path']) ? 'uploads/'.$art['image_path'] : 'img-21.jpg';
                        $statusClass = 'bg-' . strtolower($art['status']);
                        $isFav = in_array($art['id'], $user_favorites);
                        $heartIcon = $isFav ? 'fas fa-heart' : 'far fa-heart'; 
                        $heartColor = $isFav ? '#ff4d4d' : '#ccc';
                    ?>
                    <div class="work-card" data-aos="fade-up">
                        <div class="work-img-wrapper">
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>">
                            <div class="work-overlay">
                                <a href="artwork_details.php?id=<?php echo $art['id']; ?>" class="btn-view-work">View Details</a>
                            </div>
                            <div style="position:absolute; top:10px; left:10px;">
                                <span class="badge-sm <?php echo $statusClass; ?>"><?php echo $art['status']; ?></span>
                            </div>
                        </div>
                        <div class="work-content">
                            <div class="work-title"><?php echo htmlspecialchars($art['title']); ?></div>
                            <div class="work-footer">
                                <span class="work-price">Php <?php echo number_format($art['price']); ?></span>
                                <button class="btn-circle" style="border:none; background:none; color:<?php echo $heartColor; ?>; font-size:1.1rem; cursor:pointer;" 
                                        onclick="toggleFavorite(this, <?php echo $art['id']; ?>)">
                                    <i class="<?php echo $heartIcon; ?>"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-card small">
            <button class="modal-close">×</button>
            <h3>Member Login</h3>
            <p>Please log in to follow artists.</p>
            <form action="index.php" method="POST">
                <div class="form-group">
                    <input type="text" name="identifier" placeholder="Username or Email" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" name="login" class="btn-full">Log In</button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, offset: 50 });
        
        // --- HEADER JS ---
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const markAllBtn = document.getElementById('markAllRead');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');

            if (profilePill && userDropdown) {
                profilePill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    if (notifDropdown) notifDropdown.classList.remove('active');
                });
                userDropdown.addEventListener('click', (e) => e.stopPropagation());
            }

            if (notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                    if (userDropdown) userDropdown.classList.remove('active');
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
                                            <div class="notif-msg">${notif.message}</div>
                                            <button class="btn-notif-close">×</button>
                                        `;
                                        
                                        // Mark Read
                                        item.addEventListener('click', (e) => {
                                            if(e.target.classList.contains('btn-notif-close')) return;
                                            const fd = new FormData(); fd.append('id', notif.id);
                                            fetch('mark_as_read.php', { method:'POST', body:fd }).then(() => fetchNotifications());
                                        });

                                        // Delete
                                        item.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(confirm('Delete notification?')) {
                                                const fd = new FormData(); fd.append('id', notif.id);
                                                fetch('delete_notifications.php', { method:'POST', body:fd })
                                                    .then(r=>r.json()).then(d=>{ if(d.status==='success') fetchNotifications(); });
                                            }
                                        });
                                        notifList.appendChild(item);
                                    });
                                }
                            }
                        })
                        .catch(err => console.error('Error:', err));
                }

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', () => {
                        fetch('mark_all_as_read.php', { method: 'POST' })
                            .then(() => fetchNotifications());
                    });
                }

                fetchNotifications();
                setInterval(fetchNotifications, 30000);
            }

            window.addEventListener('click', () => {
                if (notifDropdown) notifDropdown.classList.remove('active');
                if (userDropdown) userDropdown.classList.remove('active');
            });
            if (notifDropdown) notifDropdown.addEventListener('click', (e) => e.stopPropagation());
        });

        // --- ARTIST LIKE LOGIC ---
        const isLoggedIn = <?php echo $loggedIn ? 'true' : 'false'; ?>;
        const loginModal = document.getElementById('loginModal');
        const closeBtns = document.querySelectorAll('.modal-close');

        function closeModal() { document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active')); }
        closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
        window.addEventListener('click', (e) => { if (e.target.classList.contains('modal-overlay')) closeModal(); });

        function toggleArtistLike(btn, artistId) {
            if (!isLoggedIn) {
                loginModal.classList.add('active');
                return;
            }

            // Optimistic UI
            const icon = btn.querySelector('i');
            const countSpan = btn.querySelector('span');
            let count = parseInt(countSpan.innerText);
            const isLiked = btn.classList.contains('active');

            btn.classList.toggle('active');
            
            if (isLiked) {
                icon.classList.remove('fas'); icon.classList.add('far');
                count--;
            } else {
                icon.classList.remove('far'); icon.classList.add('fas');
                count++;
            }
            countSpan.innerText = count;

            // Send Request
            const formData = new FormData();
            formData.append('artist_id', artistId);
            
            fetch('artist_likes.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(!data.success) {
                        alert('Error updating like. Please try again.');
                        location.reload();
                    } else {
                        countSpan.innerText = data.new_count; // Sync with server count
                    }
                })
                .catch(err => console.error(err));
        }

        // --- ARTWORK FAVORITE LOGIC ---
        window.toggleFavorite = function(btn, id) {
            if(!isLoggedIn) { loginModal.classList.add('active'); return; }
            
            const icon = btn.querySelector('i');
            const isLiked = icon.classList.contains('fas');
            const action = isLiked ? 'remove_id' : 'add_id';

            if(isLiked) {
                icon.classList.remove('fas'); icon.classList.add('far');
                btn.style.color = '#ccc';
            } else {
                icon.classList.remove('far'); icon.classList.add('fas');
                btn.style.color = '#ff4d4d';
            }

            const formData = new FormData();
            formData.append(action, id);
            fetch('favorites.php', { method: 'POST', body: formData });
        }
    </script>
</body>
</html>