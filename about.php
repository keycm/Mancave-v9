<?php
session_start();
include 'config.php';

// Check login status for Navbar logic
$loggedIn = isset($_SESSION['username']);

// === FETCH USER DATA (Profile Pic) ===
$user_profile_pic = ""; 
if ($loggedIn && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $user_sql = "SELECT username, image_path FROM users WHERE id = $uid"; 
    $user_res = mysqli_query($conn, $user_sql);
    
    if ($user_data = mysqli_fetch_assoc($user_res)) {
        // Logic: Use uploaded image if exists, else fallback to UI Avatar
        if (!empty($user_data['image_path'])) {
            $user_profile_pic = 'uploads/' . $user_data['image_path'];
        } else {
            $user_profile_pic = "https://ui-avatars.com/api/?name=" . urlencode($user_data['username']) . "&background=cd853f&color=fff&rounded=true&bold=true";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | ManCave Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* === HEADER ICONS & PROFILE STYLES (MATCHING INDEX.PHP) === */
        .header-icon-btn {
            background: #f8f8f8;
            border: 1px solid #eee;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .header-icon-btn:hover {
            background: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            color: var(--accent);
        }

        .notif-badge { 
            position: absolute; top: -2px; right: -2px; 
            background: var(--brand-red); color: white; 
            font-size: 0.65rem; font-weight: bold; 
            padding: 2px 5px; border-radius: 50%; 
            min-width: 18px; text-align: center; 
            border: 2px solid #fff; 
        }

        .profile-pill {
            display: flex; align-items: center; gap: 10px;
            background: #f8f8f8;
            padding: 4px 15px 4px 4px;
            border-radius: 50px;
            border: 1px solid #eee;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-pill:hover {
            background: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .profile-img {
            width: 32px; height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent);
        }

        .profile-name {
            font-weight: 700; font-size: 0.9rem;
            color: var(--primary); padding-right: 5px;
        }

        /* Dropdown Styles */
        .user-dropdown { position: relative; }
        .dropdown-content { 
            display: none; position: absolute; top: 140%; right: 0; 
            background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            border-radius: 8px; padding: 10px 0; min-width: 180px; z-index: 1001; 
        }
        .user-dropdown.active .dropdown-content { display: block; animation: fadeIn 0.2s ease-out; }
        
        .notification-wrapper { position: relative; }
        .notif-dropdown { 
            display: none; position: absolute; right: -10px; top: 160%; 
            width: 320px; background: white; border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: 1px solid #eee; 
            z-index: 1100; overflow: hidden; 
        }
        .notif-dropdown.active { display: block; animation: fadeIn 0.2s ease-out; }
        
        .notif-header { padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; font-weight: 700; background: #fafafa; font-size: 0.9rem; }
        .notif-list { max-height: 300px; overflow-y: auto; list-style: none; padding: 0; margin: 0; }
        
        /* UPDATED NOTIFICATION ITEM STYLES */
        .notif-item { 
            padding: 15px 35px 15px 15px; /* Right padding for close button */
            border-bottom: 1px solid #f9f9f9; 
            font-size: 0.9rem; cursor: pointer; 
            position: relative; /* For absolute positioning of close btn */
            display: flex; flex-direction: column; gap: 5px;
        }
        .notif-item:hover { background: #fdfbf7; }
        .notif-item.unread { background: #fff8f0; border-left: 4px solid var(--accent); }
        
        .btn-notif-close {
            position: absolute; top: 10px; right: 10px;
            background: none; border: none; color: #aaa;
            font-size: 1.2rem; line-height: 1; cursor: pointer;
            padding: 0; transition: color 0.2s;
        }
        .btn-notif-close:hover { color: #ff4d4d; }

        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .nav-actions { display: flex; align-items: center; gap: 15px; }

        /* === PAGE SPECIFIC STYLES === */
        .page-header {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('Grand Opening - Man Cave Gallery/img-12.jpg');
            background-size: cover;
            background-position: center;
            height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            margin-bottom: 80px;
        }
        
        .page-header h1 { font-size: 3.5rem; color: white; margin-bottom: 10px; font-family: var(--font-head); }
        .page-header p { font-size: 1.2rem; max-width: 600px; margin: 0 auto; opacity: 0.9; }

        .story-text { font-size: 1.1rem; line-height: 1.8; color: #555; margin-bottom: 20px; }
        
        .stats-row {
            display: flex; justify-content: space-between;
            margin-top: 50px; text-align: center;
            border-top: 1px solid #eee; padding-top: 40px;
        }
        .stat-item h3 { font-size: 2.5rem; color: var(--accent); margin-bottom: 5px; font-family: var(--font-head); }
        .stat-item p { font-weight: 700; color: var(--primary); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

        .team-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px; margin-top: 50px;
        }
        .team-member { text-align: center; }
        .team-img { width: 100%; height: 350px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; filter: grayscale(100%); transition: 0.3s; }
        .team-member:hover .team-img { filter: grayscale(0%); transform: translateY(-5px); }
        .team-name { font-weight: 700; font-size: 1.2rem; color: var(--primary); margin-bottom: 5px; }
        .team-role { color: var(--accent); font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
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
                    
                    <a href="favorites.php" class="header-icon-btn" title="My Favorites">
                        <i class="far fa-heart"></i>
                    </a>

                    <div class="notification-wrapper">
                        <button class="header-icon-btn" id="notifBtn" title="Notifications">
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
        </div>
    </nav>

    <header class="page-header">
        <div class="container" data-aos="fade-up">
            <h1>Our Story</h1>
            <p>From a passion project to a premier destination for contemporary art.</p>
        </div>
    </header>

    <section class="section-padding">
        <div class="container">
            <div class="row">
                <div class="col-6 content-padding" data-aos="fade-right">
                    <h4 class="section-tag">Who We Are</h4>
                    <h2 class="section-title">More Than Just a Gallery</h2>
                    <p class="story-text">
                        Founded in 2020, ManCave Gallery began with a simple idea: art should be experienced, not just viewed. We set out to create a sanctuary where the raw energy of modern masculinity meets the refined elegance of fine art.
                    </p>
                    <p class="story-text">
                        Located in the heart of Pampanga, our space is designed to be an escape from the ordinary. We specialize in contemporary realism, abstract expressionism, and modern sculpture, curating pieces that tell bold stories and evoke powerful emotions.
                    </p>
                    <div class="stats-row">
                        <div class="stat-item">
                            <h3>500+</h3>
                            <p>Artworks Sold</p>
                        </div>
                        <div class="stat-item">
                            <h3>50+</h3>
                            <p>Artists Represented</p>
                        </div>
                        <div class="stat-item">
                            <h3>4</h3>
                            <p>Years of Excellence</p>
                        </div>
                    </div>
                </div>
                <div class="col-6" data-aos="fade-left">
                    <div class="image-stack" style="height: 550px;">
                        <img src="https://images.unsplash.com/photo-1577720580479-7d839d829c73?q=80&w=800&auto=format&fit=crop" class="img-back" style="height: 100%; width: 90%;">
                        <img src="Grand Opening - Man Cave Gallery/img-10.jpg" class="img-front" style="height: 300px; bottom: -40px; right: -20px;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding bg-light">
        <div class="container text-center" data-aos="fade-up">
            <h4 class="section-tag">Our Vision</h4>
            <h2 class="section-title mb-5">Curating the Future</h2>
            <p style="max-width: 800px; margin: 0 auto; font-size: 1.2rem; color: #555;">
                "We envision a world where art is accessible, personal, and transformative. Our mission is to connect collectors with pieces that do more than decorate a room—they define it."
            </p>
        </div>
    </section>

    <section class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h4 class="section-tag">The Team</h4>
                <h2 class="section-title">Meet the Curators</h2>
            </div>
            <div class="team-grid">
                <div class="team-member" data-aos="fade-up" data-aos-delay="100">
                    <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=400&auto=format&fit=crop" alt="Founder" class="team-img">
                    <div class="team-name">Alexander Ford</div>
                    <div class="team-role">Founder & Lead Curator</div>
                </div>
                <div class="team-member" data-aos="fade-up" data-aos-delay="200">
                    <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=400&auto=format&fit=crop" alt="Director" class="team-img">
                    <div class="team-name">Sarah Jenkins</div>
                    <div class="team-role">Art Director</div>
                </div>
                <div class="team-member" data-aos="fade-up" data-aos-delay="300">
                    <img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=400&auto=format&fit=crop" alt="Manager" class="team-img">
                    <div class="team-name">James Liu</div>
                    <div class="team-role">Client Relations</div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <a class="footer-logo">
                        <span class="logo-top">THE</span>
                        <span class="logo-main">
                        <span class="logo-red">M</span><span class="logo-text">an</span><span class="logo-red">C</span><span class="logo-text">ave</span>
                        </span>
                        <span class="logo-bottom">GALLERY</span>
                    </a>
                    <p>Where passion meets preservation. Located in Pampanga.</p>
                    <div class="socials">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
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

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, offset: 50 });
        
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const markAllReadBtn = document.getElementById('markAllRead');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');

            // Toggle Profile Dropdown
            if (profilePill && userDropdown) {
                profilePill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    if (notifDropdown) notifDropdown.classList.remove('active');
                });
                userDropdown.addEventListener('click', (e) => e.stopPropagation());
            }

            // Toggle Notification Dropdown
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
                                            <div class="notif-time">${notif.created_at}</div>
                                            <button class="btn-notif-close" title="Delete">×</button>
                                        `;
                                        
                                        // Mark Read
                                        item.addEventListener('click', (e) => {
                                            if (e.target.classList.contains('btn-notif-close')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: formData })
                                                .then(() => fetchNotifications());
                                        });

                                        // Delete Logic
                                        item.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(confirm('Delete this notification?')) {
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
                        })
                        .catch(err => console.error('Error:', err));
                }

                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', () => {
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
    </script>
</body>
</html>