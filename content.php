<?php
session_start();
include 'config.php';

// Security Check
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 1. Fetch Artworks
$artworks = [];
$res_art = mysqli_query($conn, "SELECT * FROM artworks ORDER BY id DESC");
if ($res_art) while ($row = mysqli_fetch_assoc($res_art)) $artworks[] = $row;

// 2. Fetch Services
$services = [];
$res_serv = mysqli_query($conn, "SELECT * FROM services ORDER BY id DESC");
if ($res_serv) while ($row = mysqli_fetch_assoc($res_serv)) $services[] = $row;

// 3. Fetch Events
$events = [];
if($res_evt = mysqli_query($conn, "SELECT * FROM events ORDER BY event_date ASC")) {
    while ($row = mysqli_fetch_assoc($res_evt)) $events[] = $row;
}

// 4. Fetch Artists
$artists = [];
if($res_artist = mysqli_query($conn, "SELECT * FROM artists ORDER BY name ASC")) {
    while ($row = mysqli_fetch_assoc($res_artist)) $artists[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | ManCave Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_new_style.css">
    
    <style>
        /* --- LOGO STYLES --- */
        .sidebar-logo { display: flex; flex-direction: column; align-items: center; gap: 0; line-height: 1; text-decoration: none; }
        .logo-top { font-family: 'Playfair Display', serif; font-size: 0.7rem; font-weight: 700; color: #ccc; letter-spacing: 2px; }
        .logo-main { font-family: 'Pacifico', cursive; font-size: 1.8rem; transform: rotate(-4deg); margin: 5px 0; color: #fff; }
        .logo-red { color: #ff4d4d; }
        .logo-bottom { font-family: 'Nunito Sans', sans-serif; font-size: 0.6rem; font-weight: 800; color: #ccc; letter-spacing: 3px; text-transform: uppercase; }

        /* --- NOTIFICATION STYLES --- */
        .header-actions { display: flex; align-items: center; gap: 25px; }
        .notif-wrapper { position: relative; }
        
        .notif-bell { 
            background: #fff; width: 45px; height: 45px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.2rem; color: var(--secondary); cursor: pointer; 
            box-shadow: var(--shadow-sm); border: 1px solid var(--border); transition: 0.3s; 
        }
        .notif-bell:hover { color: var(--accent); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .notif-bell .dot { 
            position: absolute; top: -2px; right: -2px; background: var(--red); 
            color: white; font-size: 0.65rem; font-weight: 700; border-radius: 50%; 
            min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; 
        }
        
        .notif-dropdown { 
            display: none; position: absolute; right: -10px; top: 55px; 
            width: 320px; background: white; border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: 1px solid var(--border); 
            z-index: 1100; overflow: hidden; transform-origin: top right; animation: slideDown 0.2s ease-out; 
        }
        .notif-dropdown.active { display: block; }
        
        .notif-header { 
            padding: 15px 20px; border-bottom: 1px solid #f0f0f0; 
            display: flex; justify-content: space-between; align-items: center; 
            background: #fafafa; font-weight: 700; font-size: 0.9rem; color: var(--primary); 
        }
        .small-btn { border: none; background: none; font-size: 0.75rem; cursor: pointer; font-weight: 700; color: var(--accent); text-transform: uppercase; }
        .small-btn:hover { color: #b07236; }
        
        .notif-list { max-height: 300px; overflow-y: auto; list-style: none; margin: 0; padding: 0; }
        
        .notif-item { 
            padding: 15px 35px 15px 20px; 
            border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; 
            cursor: pointer; transition: 0.2s; position: relative; 
        }
        .notif-item:hover { background: #fdfbf7; }
        .notif-item.unread { background: #fff8f0; border-left: 4px solid var(--accent); }
        .notif-msg { color: #444; line-height: 1.4; margin-bottom: 4px; }
        .notif-time { font-size: 0.75rem; color: #999; font-weight: 600; }
        
        .btn-notif-close {
            position: absolute; top: 10px; right: 10px;
            background: none; border: none; color: #aaa;
            font-size: 1.2rem; line-height: 1; cursor: pointer;
            padding: 0; transition: color 0.2s;
        }
        .btn-notif-close:hover { color: #ff4d4d; }
        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }
        
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Page Specific Tweaks */
        .tabs { margin-bottom: 30px; display: flex; gap: 10px; flex-wrap: wrap; }
        .tab-btn { background: var(--white); border: 1px solid var(--border); padding: 10px 25px; border-radius: 50px; font-weight: 700; color: var(--secondary); cursor: pointer; transition: 0.3s; }
        .tab-btn:hover { background: #f8fafc; color: var(--primary); }
        .tab-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }
        
        .tab-pane { display: none; animation: fadeIn 0.3s ease; }
        .tab-pane.active { display: block; }
        
        /* Grid Layout */
        .grid-layout { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        
        /* Thumbnails */
        .thumb-img { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        .artist-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        
        .item-meta { font-size: 0.85rem; color: var(--secondary); display: block; margin-top: 4px; }
        .item-title { font-weight: 700; color: var(--primary); font-size: 1rem; }
        
        .form-card { position: sticky; top: 20px; }

        /* === MODAL STYLES (Consolidated) === */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        .modal-card.small {
            background: white; width: 400px; max-width: 90%;
            border-radius: 20px; padding: 40px 30px; text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: 0.3s;
        }
        .modal-overlay.active .modal-card.small { transform: translateY(0); }

        .modal-header-icon {
            width: 70px; height: 70px; border-radius: 50%;
            margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
        }
        .delete-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .success-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .error-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        .modal-card h3 { font-family: var(--font-head); font-size: 1.5rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card p { color: var(--secondary); font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }

        .btn-group { display: flex; gap: 10px; justify-content: center; }
        .btn-friendly {
            padding: 12px 25px; border-radius: 50px; font-weight: 700;
            border: none; cursor: pointer; transition: 0.2s; font-size: 0.9rem;
        }
        .btn-friendly:hover { transform: translateY(-2px); opacity: 0.9; }
        
        @media (max-width: 1024px) {
            .grid-layout { grid-template-columns: 1fr; }
            .form-card { position: static; margin-bottom: 30px; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo">
                <span class="logo-top">THE</span>
                <span class="logo-main"><span class="logo-red">M</span>an<span class="logo-red">C</span>ave</span>
                <span class="logo-bottom">GALLERY</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="admin.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a></li>
                <li class="active"><a href="content.php"><i class="fas fa-layer-group"></i> <span>Website Content</span></a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> <span>Customers & Staff</span></a></li>
                <li><a href="feedback.php"><i class="fas fa-comments"></i> <span>Message & Feedback</span></a></li>
                <li><a href="trash.php"><i class="fas fa-trash-alt"></i> <span>Recycle Bin</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="page-header">
                <h1>Website Content</h1>
                <p>Manage Artworks, Services, Events, and Artists.</p>
            </div>
            
            <div class="header-actions">
                <div class="notif-wrapper">
                    <div class="notif-bell" id="adminNotifBtn">
                        <i class="far fa-bell"></i>
                        <span class="dot" id="adminNotifBadge" style="display:none;">0</span>
                    </div>
                    
                    <div class="notif-dropdown" id="adminNotifDropdown">
                        <div class="notif-header">
                            <span>Notifications</span>
                            <button id="adminMarkAllRead" class="small-btn">Mark all read</button>
                        </div>
                        <ul class="notif-list" id="adminNotifList">
                            <li class="no-notif">Loading...</li>
                        </ul>
                    </div>
                </div>

                <div class="user-profile">
                    <div class="profile-info">
                        <span class="name">Administrator</span>
                        <span class="role">Super Admin</span>
                    </div>
                    <div class="avatar"><img src="https://ui-avatars.com/api/?name=Admin&background=cd853f&color=fff" alt="Admin"></div>
                </div>
            </div>
        </header>

        <div class="tabs">
            <button class="tab-btn active" id="tab-artworks" onclick="switchTab('artworks', this)"><i class="fas fa-paint-brush"></i> Artworks</button>
            <button class="tab-btn" id="tab-services" onclick="switchTab('services', this)"><i class="fas fa-concierge-bell"></i> Services</button>
            <button class="tab-btn" id="tab-events" onclick="switchTab('events', this)"><i class="fas fa-calendar-alt"></i> Upcoming Events</button>
            <button class="tab-btn" id="tab-artists" onclick="switchTab('artists', this)"><i class="fas fa-user-friends"></i> Meet Artists</button>
        </div>

        <div id="artworks" class="tab-pane active">
            <div class="grid-layout">
                <div class="card form-card">
                    <div class="card-header"><h3 id="artFormTitle">Add New Artwork</h3></div>
                    <form id="artworkForm" enctype="multipart/form-data">
                        <input type="hidden" id="art-id" name="id">
                        
                        <div class="form-group">
                            <label>Artwork Title</label>
                            <input type="text" id="art-title" name="title" required placeholder="e.g., Starry Night">
                        </div>
                        
                        <div class="form-group">
                            <label>Artist</label>
                            <select id="art-artist" name="artist" required>
                                <option value="" disabled selected>Select an Artist</option>
                                <?php foreach ($artists as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['name']); ?>">
                                        <?php echo htmlspecialchars($a['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:var(--secondary); font-size:0.75rem;">Can't find the artist? Add them in the "Meet Artists" tab first.</small>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" id="art-category" name="category" placeholder="e.g. Oil Painting, Abstract">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Medium</label>
                                <input type="text" id="art-medium" name="medium" placeholder="e.g. Canvas, Paper">
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <input type="number" id="art-year" name="year" placeholder="e.g. 2024">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Size</label>
                                <input type="text" id="art-size" name="size" placeholder="e.g. 24x36 inches">
                            </div>
                            <div class="form-group">
                                <label>Price (PHP)</label>
                                <input type="number" id="art-price" name="price" step="0.01" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea id="art-desc" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Upload Image</label>
                            <input type="file" id="art-img" name="image" accept="image/*">
                        </div>
                        <button type="submit" class="btn-primary" id="art-btn-text">Save Artwork</button>
                        <button type="button" id="cancelArtEdit" class="btn-text" style="display:none;" onclick="resetArtForm()">Cancel Edit</button>
                    </form>
                </div>

                <div class="card list-card">
                    <div class="card-header"><h3>Current Inventory</h3></div>
                    <div class="table-responsive">
                        <table class="styled-table">
                            <thead><tr><th>Image</th><th>Details</th><th>Size</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($artworks as $art): $img = !empty($art['image_path']) ? 'uploads/'.$art['image_path'] : 'https://placehold.co/50'; ?>
                                <tr id="art-row-<?= $art['id'] ?>">
                                    <td><img src="<?= htmlspecialchars($img) ?>" class="thumb-img"></td>
                                    <td><div class="item-title"><?= htmlspecialchars($art['title']) ?></div><span class="item-meta">By <?= htmlspecialchars($art['artist']) ?></span></td>
                                    <td>
                                        <span style="font-weight:700; color:var(--secondary); font-size:0.9rem;">
                                            <?= htmlspecialchars($art['size'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon edit" onclick='editArtwork(<?= json_encode($art) ?>)'><i class="fas fa-pen"></i></button>
                                            <button class="btn-icon delete" onclick="confirmDelete('artwork', <?= $art['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="services" class="tab-pane">
            <div class="grid-layout">
                <div class="card form-card">
                    <div class="card-header"><h3 id="serviceFormTitle">Manage Service</h3></div>
                    <form id="serviceForm" enctype="multipart/form-data">
                        <input type="hidden" id="service-id" name="id">
                        
                        <div class="form-group">
                            <label>Service Name</label>
                            <input type="text" id="service-name" name="name" required placeholder="e.g., Art Appraisal">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Price (PHP)</label>
                                <input type="number" id="service-price" name="price" required>
                            </div>
                            <div class="form-group">
                                <label>Duration</label>
                                <input type="text" id="service-duration" name="duration" placeholder="e.g., 2 hours">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea id="service-desc" name="description" rows="3" placeholder="Brief details about the service..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Service Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit" class="btn-primary" id="service-btn-text">Save Service</button>
                        <button type="button" id="cancelServiceEdit" class="btn-text" style="display:none;" onclick="resetServiceForm()">Cancel</button>
                    </form>
                </div>

                <div class="card list-card">
                    <div class="card-header"><h3>Gallery Services</h3></div>
                    <div class="table-responsive">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Service Info</th>
                                    <th>Price</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $srv): 
                                    $srvImg = !empty($srv['image']) ? 'uploads/'.$srv['image'] : 'https://placehold.co/60?text=Service';
                                ?>
                                <tr id="srv-row-<?= $srv['id'] ?>">
                                    <td><img src="<?= htmlspecialchars($srvImg) ?>" class="thumb-img" alt="Service"></td>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($srv['name']) ?></div>
                                        <span class="item-meta"><?= htmlspecialchars(substr($srv['description'], 0, 50)) . (strlen($srv['description']) > 50 ? '...' : '') ?></span>
                                    </td>
                                    <td style="color:var(--accent); font-weight:700;">₱<?= number_format($srv['price']) ?></td>
                                    <td><?= htmlspecialchars($srv['duration']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon edit" onclick='editService(<?= json_encode($srv) ?>)'><i class="fas fa-pen"></i></button>
                                            <button class="btn-icon delete" onclick="confirmDelete('service', <?= $srv['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="events" class="tab-pane">
            <div class="grid-layout">
                <div class="card form-card">
                    <div class="card-header"><h3 id="eventFormTitle">Add Event</h3></div>
                    <form id="eventForm">
                        <input type="hidden" id="event-id" name="id">
                        <input type="hidden" name="action" value="save">
                        
                        <div class="form-group">
                            <label>Event Title</label>
                            <input type="text" id="event-title" name="title" required placeholder="e.g. Modern Abstract Night">
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" id="event-date" name="event_date" required>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="text" id="event-time" name="event_time" placeholder="e.g. 6:00 PM - 9:00 PM">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" id="event-location" name="location" placeholder="e.g. Main Gallery Hall">
                        </div>
                        <button type="submit" class="btn-primary" id="event-btn-text">Publish Event</button>
                        <button type="button" id="cancelEventEdit" class="btn-text" style="display:none;" onclick="resetEventForm()">Cancel</button>
                    </form>
                </div>

                <div class="card list-card">
                    <div class="card-header"><h3>Upcoming Events</h3></div>
                    <div class="table-responsive">
                        <table class="styled-table">
                            <thead><tr><th>Date</th><th>Event Details</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($events as $evt): ?>
                                <tr id="evt-row-<?= $evt['id'] ?>">
                                    <td>
                                        <div class="item-title" style="color:var(--accent);"><?= date('M d', strtotime($evt['event_date'])) ?></div>
                                        <span class="item-meta"><?= date('Y', strtotime($evt['event_date'])) ?></span>
                                    </td>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($evt['title']) ?></div>
                                        <span class="item-meta"><i class="far fa-clock"></i> <?= htmlspecialchars($evt['event_time']) ?> | <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($evt['location']) ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon edit" onclick='editEvent(<?= json_encode($evt) ?>)'><i class="fas fa-pen"></i></button>
                                            <button class="btn-icon delete" onclick="confirmDelete('event', <?= $evt['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="artists" class="tab-pane">
            <div class="grid-layout">
                <div class="card form-card">
                    <div class="card-header"><h3 id="artistFormTitle">Add Artist</h3></div>
                    <form id="artistForm" enctype="multipart/form-data">
                        <input type="hidden" id="artist-id" name="id">
                        <input type="hidden" name="action" value="save">
                        
                        <div class="form-group">
                            <label>Artist Name</label>
                            <input type="text" id="artist-name" name="name" required placeholder="e.g. Elena Vance">
                        </div>
                        <div class="form-group">
                            <label>Art Style</label>
                            <input type="text" id="artist-style" name="style" placeholder="e.g. Abstract Expressionism">
                        </div>
                        <div class="form-group">
                            <label>Quote</label>
                            <textarea id="artist-quote" name="quote" rows="2" placeholder="Inspiring quote..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Biography</label>
                            <textarea id="artist-bio" name="bio" rows="4" placeholder="Artist background..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Profile Image</label>
                            <input type="file" id="artist-img" name="image" accept="image/*">
                        </div>
                        <button type="submit" class="btn-primary" id="artist-btn-text">Save Artist</button>
                        <button type="button" id="cancelArtistEdit" class="btn-text" style="display:none;" onclick="resetArtistForm()">Cancel</button>
                    </form>
                </div>

                <div class="card list-card">
                    <div class="card-header"><h3>Gallery Artists</h3></div>
                    <div class="table-responsive">
                        <table class="styled-table">
                            <thead><tr><th>Profile</th><th>Info</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($artists as $artist): 
                                    $avatar = !empty($artist['image_path']) ? 'uploads/'.$artist['image_path'] : 'https://ui-avatars.com/api/?name='.urlencode($artist['name']);
                                ?>
                                <tr id="artist-row-<?= $artist['id'] ?>">
                                    <td><img src="<?= htmlspecialchars($avatar) ?>" class="artist-avatar"></td>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($artist['name']) ?></div>
                                        <span class="item-meta"><?= htmlspecialchars($artist['style']) ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon edit" onclick='editArtist(<?= json_encode($artist) ?>)'><i class="fas fa-pen"></i></button>
                                            <button class="btn-icon delete" onclick="confirmDelete('artist', <?= $artist['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <div class="modal-overlay" id="confirmModal">
        <div class="modal-card small">
            <div class="modal-header-icon delete-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 id="confirmTitle">Delete Item?</h3>
            <p id="confirmText">Are you sure you want to move this to the recycle bin?</p>
            <div class="btn-group">
                <button class="btn-friendly" style="background:#e5e7eb; color:#374151;" onclick="closeModal('confirmModal')">Cancel</button>
                <button class="btn-friendly" id="confirmBtnAction" style="background:#ef4444; color:white;">Delete</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="alertModal">
        <div class="modal-card small">
            <div class="modal-header-icon success-icon" id="alertIcon">
                <i class="fas fa-check"></i>
            </div>
            <h3 id="alertTitle">Success!</h3>
            <p id="alertMessage">Action completed successfully.</p>
            <button class="btn-friendly" style="background:#2c3e50; color:white; width:100%;" onclick="closeModal('alertModal');">Okay</button>
        </div>
    </div>

    <script>
        // --- 1. TAB STATE MEMORY ---
        document.addEventListener('DOMContentLoaded', () => {
            const activeTabId = localStorage.getItem('adminActiveTab') || 'artworks';
            const btn = document.getElementById('tab-' + activeTabId);
            if(btn) switchTab(activeTabId, btn);

            // NOTIFICATIONS
            const notifBtn = document.getElementById('adminNotifBtn');
            const notifDropdown = document.getElementById('adminNotifDropdown');
            const notifBadge = document.getElementById('adminNotifBadge');
            const notifList = document.getElementById('adminNotifList');
            const markAllBtn = document.getElementById('adminMarkAllRead');

            if(notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                });

                function fetchNotifications() {
                    fetch('fetch_notifications.php')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                if (data.unread_count > 0) {
                                    notifBadge.innerText = data.unread_count;
                                    notifBadge.style.display = 'flex';
                                } else {
                                    notifBadge.style.display = 'none';
                                }
                                notifList.innerHTML = '';
                                if (data.notifications.length === 0) {
                                    notifList.innerHTML = '<li class="no-notif">No new notifications</li>';
                                } else {
                                    data.notifications.forEach(notif => {
                                        const li = document.createElement('li');
                                        li.className = `notif-item ${notif.is_read == 0 ? 'unread' : ''}`;
                                        li.innerHTML = `
                                            <div class="notif-msg">${notif.message}</div>
                                            <div class="notif-time">${notif.created_at}</div>
                                            <button class="btn-notif-close" title="Delete">&times;</button>
                                        `;
                                        
                                        li.addEventListener('click', (e) => {
                                            if(e.target.classList.contains('btn-notif-close')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: formData })
                                                .then(() => fetchNotifications());
                                        });

                                        li.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(!confirm('Delete notification?')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('delete_notifications.php', { method: 'POST', body: formData })
                                                .then(res => res.json())
                                                .then(d => { if(d.status === 'success') fetchNotifications(); });
                                        });
                                        notifList.appendChild(li);
                                    });
                                }
                            }
                        })
                        .catch(err => console.error(err));
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
                if (notifDropdown && notifDropdown.classList.contains('active')) {
                    notifDropdown.classList.remove('active');
                }
            });
        });

        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            localStorage.setItem('adminActiveTab', tabId);
        }

        // --- MODAL & ALERT LOGIC ---
        let deleteCallback = null;

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function showAlert(title, msg, type, reload = false) {
            document.getElementById('alertTitle').innerText = title;
            document.getElementById('alertMessage').innerText = msg;
            
            const icon = document.getElementById('alertIcon');
            if (type === 'success') {
                icon.className = 'modal-header-icon success-icon';
                icon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                icon.className = 'modal-header-icon error-icon';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            }
            
            const btn = document.querySelector('#alertModal button');
            btn.onclick = function() {
                closeModal('alertModal');
                if (reload) location.reload();
            };
            
            document.getElementById('alertModal').classList.add('active');
        }

        function confirmDelete(type, id) {
            document.getElementById('confirmTitle').innerText = 'Delete Item?';
            document.getElementById('confirmText').innerText = 'Are you sure you want to move this to the recycle bin?';
            
            if (type === 'artwork') deleteCallback = () => performDelete('artworks.php?action=delete', id);
            else if (type === 'service') deleteCallback = () => performDelete('services.php?action=delete', id);
            else if (type === 'event') deleteCallback = () => performDelete('manage_events.php', id, true);
            else if (type === 'artist') deleteCallback = () => performDelete('manage_artists.php', id, true);
            
            document.getElementById('confirmModal').classList.add('active');
        }

        document.getElementById('confirmBtnAction').addEventListener('click', () => {
            if (deleteCallback) deleteCallback();
            closeModal('confirmModal');
        });

        async function performDelete(url, id, isPostAction = false) {
            const formData = new FormData();
            formData.append('id', id);
            if (isPostAction) formData.append('action', 'delete');

            try {
                const res = await fetch(url, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showAlert('Deleted!', 'Item moved to trash.', 'success', true);
                } else {
                    showAlert('Error', data.message || 'Could not delete item.', 'error');
                }
            } catch (err) {
                showAlert('Error', 'Request failed.', 'error');
            }
        }

        // --- 2. ARTWORK LOGIC ---
        const artForm = document.getElementById('artworkForm');
        artForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(artForm);
            const action = document.getElementById('art-id').value ? 'update' : 'add';
            if(action === 'update') formData.append('id', document.getElementById('art-id').value);
            
            try {
                const res = await fetch(`artworks.php?action=${action}`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) showAlert('Success!', 'Artwork saved successfully.', 'success', true);
                else showAlert('Error', data.message, 'error');
            } catch(err) { showAlert('Error', 'Request failed.', 'error'); }
        });

        function editArtwork(art) {
            document.getElementById('art-id').value = art.id;
            document.getElementById('art-title').value = art.title;
            document.getElementById('art-artist').value = art.artist; 
            document.getElementById('art-price').value = art.price;
            document.getElementById('art-desc').value = art.description;
            
            // UPDATED: Populate new fields
            if(art.category) document.getElementById('art-category').value = art.category;
            if(art.medium) document.getElementById('art-medium').value = art.medium;
            if(art.year) document.getElementById('art-year').value = art.year;
            if(art.size) document.getElementById('art-size').value = art.size;
            // Note: art.status removed as requested
            
            document.getElementById('artFormTitle').textContent = 'Edit Artwork';
            document.getElementById('art-btn-text').textContent = 'Update Artwork';
            document.getElementById('cancelArtEdit').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetArtForm() {
            artForm.reset();
            document.getElementById('art-id').value = '';
            document.getElementById('artFormTitle').textContent = 'Add New Artwork';
            document.getElementById('art-btn-text').textContent = 'Save Artwork';
            document.getElementById('cancelArtEdit').style.display = 'none';
        }

        // --- 3. SERVICE LOGIC ---
        const serviceForm = document.getElementById('serviceForm');
        serviceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(serviceForm);
            const action = document.getElementById('service-id').value ? 'update' : 'add';
            try {
                const res = await fetch(`services.php?action=${action}`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) showAlert('Success!', 'Service saved.', 'success', true);
                else showAlert('Error', data.message, 'error');
            } catch(err) { showAlert('Error', 'Request failed.', 'error'); }
        });

        function editService(srv) {
            document.getElementById('service-id').value = srv.id;
            document.getElementById('service-name').value = srv.name;
            document.getElementById('service-price').value = srv.price;
            document.getElementById('service-duration').value = srv.duration;
            document.getElementById('service-desc').value = srv.description;
            document.getElementById('serviceFormTitle').textContent = 'Edit Service';
            document.getElementById('service-btn-text').textContent = 'Update Service';
            document.getElementById('cancelServiceEdit').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetServiceForm() {
            serviceForm.reset();
            document.getElementById('service-id').value = '';
            document.getElementById('serviceFormTitle').textContent = 'Manage Service';
            document.getElementById('service-btn-text').textContent = 'Save Service';
            document.getElementById('cancelServiceEdit').style.display = 'none';
        }

        // --- 4. EVENT LOGIC ---
        const eventForm = document.getElementById('eventForm');
        eventForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(eventForm);
            try {
                const res = await fetch('manage_events.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) showAlert('Success!', 'Event saved.', 'success', true);
                else showAlert('Error', 'Error saving event', 'error');
            } catch(err) { showAlert('Error', 'Request failed', 'error'); }
        });

        function editEvent(evt) {
            document.getElementById('event-id').value = evt.id;
            document.getElementById('event-title').value = evt.title;
            document.getElementById('event-date').value = evt.event_date;
            document.getElementById('event-time').value = evt.event_time;
            document.getElementById('event-location').value = evt.location;
            document.getElementById('eventFormTitle').textContent = 'Edit Event';
            document.getElementById('event-btn-text').textContent = 'Update Event';
            document.getElementById('cancelEventEdit').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetEventForm() {
            eventForm.reset();
            document.getElementById('event-id').value = '';
            document.getElementById('eventFormTitle').textContent = 'Add Event';
            document.getElementById('event-btn-text').textContent = 'Publish Event';
            document.getElementById('cancelEventEdit').style.display = 'none';
        }

        // --- 5. ARTIST LOGIC ---
        const artistForm = document.getElementById('artistForm');
        artistForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(artistForm);
            try {
                const res = await fetch('manage_artists.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) showAlert('Success!', 'Artist saved.', 'success', true);
                else showAlert('Error', 'Error saving artist', 'error');
            } catch(err) { showAlert('Error', 'Request failed', 'error'); }
        });

        function editArtist(artist) {
            document.getElementById('artist-id').value = artist.id;
            document.getElementById('artist-name').value = artist.name;
            document.getElementById('artist-style').value = artist.style;
            document.getElementById('artist-quote').value = artist.quote;
            document.getElementById('artist-bio').value = artist.bio;
            document.getElementById('artistFormTitle').textContent = 'Edit Artist';
            document.getElementById('artist-btn-text').textContent = 'Update Artist';
            document.getElementById('cancelArtistEdit').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetArtistForm() {
            artistForm.reset();
            document.getElementById('artist-id').value = '';
            document.getElementById('artistFormTitle').textContent = 'Add Artist';
            document.getElementById('artist-btn-text').textContent = 'Save Artist';
            document.getElementById('cancelArtistEdit').style.display = 'none';
        }
    </script>
</body>
</html>