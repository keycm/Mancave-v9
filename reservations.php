<?php
session_start();
include 'config.php';

// Security Check
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 1. Fetch Reservations
$reservations = [];
$sql = "SELECT b.*, 
               u.email as user_email, 
               u.username as user_username,
               a.image_path 
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN artworks a ON b.artwork_id = a.id
        WHERE b.deleted_at IS NULL
        ORDER BY b.created_at DESC";

if ($res = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $reservations[] = $row;
    }
}

// 2. Fetch Copy Requests
$copy_requests = [];
$sql_copy = "SELECT * FROM inquiries WHERE message LIKE '%requesting a copy%' ORDER BY created_at DESC";
if ($res_copy = mysqli_query($conn, $sql_copy)) {
    while ($row = mysqli_fetch_assoc($res_copy)) {
        $copy_requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | ManCave Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_new_style.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

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

        /* Page Specific Styles */
        .tabs-container { display: flex; gap: 15px; margin-bottom: 25px; border-bottom: 2px solid var(--border); padding-bottom: 0; }
        .tab-btn {
            background: none; border: none; padding: 10px 20px;
            font-size: 1rem; font-weight: 700; color: var(--secondary);
            cursor: pointer; position: relative; transition: 0.3s;
            border-bottom: 3px solid transparent; margin-bottom: -2px;
        }
        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }

        .view-pane { display: none; animation: fadeIn 0.3s ease; }
        .view-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Controls */
        .controls-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .search-wrapper { position: relative; width: 100%; max-width: 350px; }
        .search-wrapper input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border); outline: none; }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--secondary); }

        .filter-group { background: var(--white); padding: 4px; border-radius: 50px; border: 1px solid var(--border); display: flex; }
        .filter-btn { border: none; background: none; padding: 6px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; color: var(--secondary); cursor: pointer; transition: 0.3s; }
        .filter-btn.active { background: var(--primary); color: var(--white); }

        /* Table Styling */
        .art-cell { display: flex; align-items: center; gap: 15px; }
        .art-thumb { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border); }
        .info-sub { font-size: 0.85rem; color: var(--secondary); display: block; margin-top: 2px; cursor: pointer; transition: 0.2s; }
        .info-sub:hover { color: var(--accent); text-decoration: underline; }
        
        /* Calendar */
        .fc { background: var(--white); padding: 20px; border-radius: 16px; box-shadow: var(--shadow-sm); }
        .fc-toolbar-title { font-family: var(--font-head); font-size: 1.5rem !important; }
        .fc-button-primary { background-color: var(--primary) !important; border: none !important; }

        @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        /* === FRIENDLY MODAL STYLES === */
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
        .question-icon { background: rgba(205, 133, 63, 0.1); color: #cd853f; }
        
        .modal-card h3 { font-family: var(--font-head); font-size: 1.5rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card p { color: var(--secondary); font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }

        .btn-group { display: flex; gap: 10px; justify-content: center; }
        .btn-friendly {
            padding: 12px 25px; border-radius: 50px; font-weight: 700;
            border: none; cursor: pointer; transition: 0.2s; font-size: 0.9rem;
        }
        .btn-friendly:hover { transform: translateY(-2px); opacity: 0.9; }

        /* Reply Modal */
        .modal-card.large {
            background: white; width: 600px; max-width: 95%;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: 0.3s;
            display: flex; flex-direction: column;
        }
        .modal-overlay.active .modal-card.large { transform: translateY(0); }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
        .modal-body { padding: 25px; }
        .reply-section label { display:block; font-weight:700; font-size:0.85rem; color:#555; margin-bottom:5px; }
        .reply-section input, .reply-section textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; outline: none; margin-bottom: 15px; font-family: 'Nunito Sans', sans-serif;}
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
                <li class="active"><a href="reservations.php"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a></li>
                <li><a href="content.php"><i class="fas fa-layer-group"></i> <span>Website Content</span></a></li>
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
                <h1>Appointments & Requests</h1>
                <p>Manage viewings, schedule, and artwork requests.</p>
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

        <div class="tabs-container">
            <button class="tab-btn active" onclick="switchView('list')"><i class="fas fa-list-ul"></i> List View</button>
            <button class="tab-btn" onclick="switchView('copyRequests')"><i class="fas fa-clone"></i> Requests</button>
            <button class="tab-btn" onclick="switchView('calendar')"><i class="far fa-calendar-alt"></i> Calendar</button>
        </div>

        <div id="listView" class="view-pane active">
            <div class="controls-bar">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="reqSearch" placeholder="Search by name, artwork, or date...">
                </div>
                <div class="filter-group">
                    <button class="filter-btn active" onclick="filterTable('all', this)">All</button>
                    <button class="filter-btn" onclick="filterTable('pending', this)">Pending</button>
                    <button class="filter-btn" onclick="filterTable('approved', this)">Approved</button>
                    <button class="filter-btn" onclick="filterTable('completed', this)">Completed</button>
                </div>
            </div>

            <div class="card table-card">
                <div class="table-responsive">
                    <table class="styled-table" id="resTable">
                        <thead>
                            <tr>
                                <th>Booking Detail</th>
                                <th>Customer Info</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr><td colspan="5" class="text-center" style="padding:40px; color:#999;">No appointments found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $r): 
                                    $status = strtolower($r['status']); 
                                    $img = !empty($r['image_path']) ? 'uploads/'.$r['image_path'] : 'https://placehold.co/50x50/eee/999?text=Art';
                                    $title = !empty($r['service']) ? $r['service'] : 'General Appointment';
                                    $name = !empty($r['full_name']) ? $r['full_name'] : ($r['user_username'] ?? 'Guest');
                                    $contact = !empty($r['phone_number']) ? $r['phone_number'] : 'N/A';
                                    $email = !empty($r['user_email']) ? $r['user_email'] : 'N/A';
                                    
                                    $jsonData = htmlspecialchars(json_encode([
                                        'title' => $title,
                                        'name' => $name,
                                        'contact' => $contact,
                                        'email' => $email,
                                        'date' => date('F d, Y', strtotime($r['preferred_date'])),
                                        'status' => ucfirst($status),
                                        'request' => $r['special_requests']
                                    ]));

                                    // Highlight completed row slightly
                                    $rowStyle = ($status === 'completed') ? 'background-color: #f0fdf4;' : ''; 
                                ?>
                                <tr data-status="<?= $status ?>" style="<?= $rowStyle ?>">
                                    <td>
                                        <div class="art-cell">
                                            <img src="<?= htmlspecialchars($img) ?>" class="art-thumb" alt="Thumb">
                                            <div>
                                                <strong><?= htmlspecialchars($title) ?></strong>
                                                <span class="info-sub" onclick='viewBooking(<?= $jsonData ?>)' title="Click to view full details">
                                                    <?= htmlspecialchars(substr($r['special_requests'], 0, 30)) . (strlen($r['special_requests']) > 30 ? '...' : '') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($name) ?></strong>
                                        <span class="info-sub"><i class="fas fa-phone" style="font-size:0.7rem;"></i> <?= htmlspecialchars($contact) ?></span>
                                        <span class="info-sub" style="font-size:0.75rem;"><?= htmlspecialchars($email) ?></span>
                                    </td>
                                    <td>
                                        <i class="far fa-calendar" style="color:var(--secondary); margin-right:5px;"></i>
                                        <?= date('M d, Y', strtotime($r['preferred_date'])) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $status ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="actions" style="justify-content: flex-end;">
                                            <button class="btn-icon" style="background:#e0e7ff; color:#4338ca;" onclick='viewBooking(<?= $jsonData ?>)' title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <?php if ($status == 'pending'): ?>
                                                <button class="btn-icon edit" onclick="updateStatus(<?= $r['id'] ?>, 'approved', this)" title="Approve"><i class="fas fa-check"></i></button>
                                                <button class="btn-icon delete" onclick="updateStatus(<?= $r['id'] ?>, 'rejected', this)" title="Reject"><i class="fas fa-times"></i></button>
                                            
                                            <?php elseif ($status == 'approved'): ?>
                                                <button class="btn-icon" style="background:var(--blue-light); color:var(--blue);" onclick="updateStatus(<?= $r['id'] ?>, 'completed', this)" title="Mark Completed"><i class="fas fa-flag-checkered"></i></button>
                                            
                                            <?php elseif ($status == 'completed'): ?>
                                                <span style="font-size:0.8rem; color:var(--green); font-weight:700; margin-right:5px; display:flex; align-items:center; gap:5px;">
                                                    <i class="fas fa-check-circle"></i> Done
                                                </span>
                                            <?php endif; ?>

                                            <button class="btn-icon delete" onclick="updateStatus(<?= $r['id'] ?>, 'delete', this)" title="Move to Trash"><i class="far fa-trash-alt"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="copyRequestsView" class="view-pane">
            <div class="controls-bar">
                <h3><i class="fas fa-clone" style="color:var(--accent); margin-right:10px;"></i> User Requests</h3>
            </div>
            <div class="card table-card">
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Message / Request</th>
                                <th>Date Sent</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($copy_requests)): ?>
                                <tr><td colspan="5" class="text-center" style="padding:40px; color:#999;">No copy requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($copy_requests as $req): 
                                    $msg = $req['message'];
                                    $reqData = htmlspecialchars(json_encode([
                                        'title' => 'Copy Request',
                                        'name' => $req['username'],
                                        'contact' => $req['mobile'],
                                        'email' => $req['email'],
                                        'date' => date('F d, Y', strtotime($req['created_at'])),
                                        'status' => 'Inquiry',
                                        'request' => $msg
                                    ]));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($req['username']) ?></strong>
                                        <div class="info-sub"><?= htmlspecialchars($req['email']) ?></div>
                                    </td>
                                    <td>
                                        <i class="fas fa-phone" style="font-size:0.75rem; color:var(--secondary);"></i> 
                                        <?= htmlspecialchars($req['mobile']) ?>
                                    </td>
                                    <td>
                                        <div style="max-width:350px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#555;">
                                            <?= htmlspecialchars($msg) ?>
                                        </div>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($req['created_at'])) ?></td>
                                    <td style="text-align:right;">
                                        <div class="actions" style="justify-content: flex-end;">
                                            <button class="btn-icon" style="background:#e0e7ff; color:#4338ca;" onclick='viewBooking(<?= $reqData ?>)' title="Read Full Message">
                                                <i class="fas fa-envelope-open-text"></i>
                                            </button>
                                            
                                            <button class="btn-icon edit" onclick="openReplyModal(<?= $req['id'] ?>, '<?= htmlspecialchars($req['email']) ?>')" title="Reply via Email">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="calendarView" class="view-pane">
            <div id="calendar"></div>
        </div>

    </main>

    <div id="viewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; transition: opacity 0.3s;">
        <div style="background: #fff; width: 550px; max-width: 95%; border-radius: 16px; padding: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); position: relative; animation: modalPop 0.3s ease-out; max-height: 90vh; overflow-y: auto;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px;">
                <h3 style="margin: 0; font-family: 'Playfair Display', serif; color: #2c3e50; font-size: 1.6rem;">Details</h3>
                <button onclick="closeViewModal()" style="background: none; border: none; font-size: 2rem; line-height: 1; cursor: pointer; color: #999; transition: 0.2s;">&times;</button>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                    <strong id="view-title" style="color: #cd853f; font-size: 1.1rem;"></strong>
                    <span id="view-status" style="padding: 5px 12px; border-radius: 30px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;"></span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">Customer Name</span>
                        <div id="view-name" style="font-weight: 600; color: #333;"></div>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">Date</span>
                        <div id="view-date" style="font-weight: 600; color: #333;"></div>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">Contact</span>
                        <div id="view-contact" style="font-weight: 600; color: #333;"></div>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">Email</span>
                        <div id="view-email" style="font-weight: 600; color: #333;"></div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Message / Request</span>
                    <div id="view-request" style="background: #fff; border: 1px solid #eee; padding: 15px; border-radius: 8px; font-size: 0.95rem; line-height: 1.6; color: #555; min-height: 80px; word-wrap: break-word; white-space: pre-wrap;"></div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 25px;">
                <button onclick="closeViewModal()" style="background: #2c3e50; color: white; padding: 12px 30px; border: none; border-radius: 50px; font-weight: 700; cursor: pointer; transition: 0.3s;">Close Details</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="replyModal">
        <div class="modal-card large">
            <div class="modal-header">
                <h3>Reply to Inquiry</h3>
                <button onclick="closeModal('replyModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#999;">&times;</button>
            </div>
            <div class="modal-body">
                <div class="reply-section">
                    <p style="color:#666; font-size:0.9rem; margin-bottom:15px;">Replying to: <strong id="replyToEmail"></strong></p>
                    <form id="replyForm">
                        <input type="hidden" id="replyId" name="id">
                        <div style="margin-bottom: 15px;">
                            <label>Subject</label>
                            <input type="text" name="subject" value="Re: Your Inquiry - ManCave Gallery" required>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label>Message</label>
                            <textarea name="message" rows="5" placeholder="Type your reply here..." required></textarea>
                        </div>
                        <button type="submit" class="btn-friendly" style="width:100%; background:#cd853f; color:white;">Send Reply <i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="confirmModal">
        <div class="modal-card small">
            <div class="modal-header-icon question-icon" id="confirmIcon">
                <i class="fas fa-question"></i>
            </div>
            <h3 id="confirmTitle">Confirm Action</h3>
            <p id="confirmText">Are you sure you want to proceed?</p>
            <div class="btn-group">
                <button class="btn-friendly" style="background:#e5e7eb; color:#374151;" onclick="closeModal('confirmModal')">Cancel</button>
                <button class="btn-friendly" id="confirmBtnAction" style="background:#cd853f; color:white;">Confirm</button>
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
            <button class="btn-friendly" style="background:#2c3e50; color:white; width:100%;" onclick="closeModal('alertModal'); location.reload();">Okay</button>
        </div>
    </div>

    <script>
        // --- VIEW SWITCHER ---
        function switchView(viewName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.querySelectorAll('.view-pane').forEach(pane => pane.classList.remove('active'));
            document.getElementById(viewName + 'View').classList.add('active');
            if (viewName === 'calendar') setTimeout(renderCalendar, 100);
        }

        // --- FILTER & SEARCH ---
        function filterTable(status, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('#resTable tbody tr').forEach(row => {
                row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
            });
        }

        document.getElementById('reqSearch').addEventListener('keyup', function() {
            const val = this.value.toLowerCase();
            document.querySelectorAll('#resTable tbody tr').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(val) ? '' : 'none';
            });
        });

        // --- MODAL HELPERS ---
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Friendly Alert
        function showAlert(title, msg, type) {
            document.getElementById('alertTitle').innerText = title;
            document.getElementById('alertMessage').innerText = msg;
            
            const icon = document.getElementById('alertIcon');
            if (type === 'success') {
                icon.className = 'modal-header-icon success-icon';
                icon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                icon.className = 'modal-header-icon delete-icon';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            }
            document.getElementById('alertModal').classList.add('active');
        }

        // Friendly Confirm
        let confirmCallback = null;
        function showConfirm(title, msg, btnText, btnColor, callback) {
            document.getElementById('confirmTitle').innerText = title;
            document.getElementById('confirmText').innerText = msg;
            
            const btn = document.getElementById('confirmBtnAction');
            btn.innerText = btnText;
            btn.style.background = btnColor;
            
            // Set Icon
            const icon = document.getElementById('confirmIcon');
            if(btnText === 'Delete' || btnText === 'Reject') {
                icon.className = 'modal-header-icon delete-icon';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            } else if (btnText === 'Approve' || btnText === 'Mark Completed') {
                icon.className = 'modal-header-icon success-icon';
                icon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                icon.className = 'modal-header-icon question-icon';
                icon.innerHTML = '<i class="fas fa-question"></i>';
            }

            confirmCallback = callback;
            document.getElementById('confirmModal').classList.add('active');
        }

        document.getElementById('confirmBtnAction').addEventListener('click', () => {
            if(confirmCallback) confirmCallback();
            closeModal('confirmModal');
        });

        // --- VIEW BOOKING DETAILS ---
        const viewModal = document.getElementById('viewModal');

        function viewBooking(data) {
            document.getElementById('view-title').innerText = data.title;
            document.getElementById('view-name').innerText = data.name;
            document.getElementById('view-contact').innerText = data.contact;
            document.getElementById('view-email').innerText = data.email;
            document.getElementById('view-date').innerText = data.date;
            document.getElementById('view-request').innerText = data.request || "No details provided.";
            
            const statusEl = document.getElementById('view-status');
            statusEl.innerText = data.status;
            let bg = '#f0f0f0', col = '#555';
            
            if(data.status === 'Pending') { bg = '#fff3cd'; col = '#856404'; }
            else if(data.status === 'Approved') { bg = '#d4edda'; col = '#155724'; }
            else if(data.status === 'Completed') { bg = '#cce5ff'; col = '#004085'; }
            else if(data.status === 'Rejected') { bg = '#f8d7da'; col = '#721c24'; }
            else if(data.status === 'Inquiry') { bg = '#e0f2fe'; col = '#0284c7'; }
            
            statusEl.style.backgroundColor = bg;
            statusEl.style.color = col;
            viewModal.style.display = 'flex';
        }

        function closeViewModal() { viewModal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target === viewModal) closeViewModal(); }

        // --- REPLY MODAL LOGIC ---
        function openReplyModal(id, email) {
            document.getElementById('replyId').value = id;
            document.getElementById('replyToEmail').innerText = email;
            document.getElementById('replyModal').classList.add('active');
        }

        document.getElementById('replyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = e.target.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = 'Sending...';

            try {
                const res = await fetch('reply_inquiry.php', { method: 'POST', body: formData });
                const text = await res.text();
                if(text.trim() === 'success') {
                    closeModal('replyModal');
                    showAlert('Sent!', 'Reply sent successfully!', 'success');
                } else {
                    showAlert('Error', 'Error sending email. Please try again.', 'error');
                }
            } catch(err) { 
                showAlert('Error', 'Request failed.', 'error'); 
            } finally { 
                btn.disabled = false; btn.innerHTML = originalText; 
            }
        });

        // --- UPDATE STATUS (With Friendly Confirm) ---
        function updateStatus(id, action, btn) {
            let title = 'Confirm Action';
            let msg = `Are you sure you want to ${action} this appointment?`;
            let btnText = 'Confirm';
            let btnColor = '#cd853f'; // Default accent

            if(action === 'completed') {
                title = 'Mark Completed';
                msg = "Mark this appointment as Completed?";
                btnText = 'Mark Completed';
                btnColor = '#3b82f6'; // Blue
            } else if (action === 'delete') {
                title = 'Move to Trash';
                msg = "Are you sure you want to move this to the trash?";
                btnText = 'Delete';
                btnColor = '#ef4444'; // Red
            } else if (action === 'approved') {
                title = 'Approve Appointment';
                btnText = 'Approve';
                btnColor = '#10b981'; // Green
            } else if (action === 'rejected') {
                title = 'Reject Appointment';
                btnText = 'Reject';
                btnColor = '#ef4444'; // Red
            }

            showConfirm(title, msg, btnText, btnColor, async () => {
                // The actual logic executes after confirmation
                let originalContent = '';
                if(btn) {
                    btn.disabled = true;
                    originalContent = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
                
                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', action);
                try {
                    await fetch('update_booking.php', { method: 'POST', body: formData });
                    showAlert('Success', 'Status updated successfully.', 'success');
                } catch (error) {
                    showAlert('Error', 'An error occurred.', 'error');
                    if(btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                }
            });
        }

        // --- NOTIFICATION LOGIC ---
        document.addEventListener('DOMContentLoaded', () => {
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
                                            if (e.target.classList.contains('btn-notif-close')) return;
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
                        });
                }

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        fetch('mark_all_as_read.php', { method: 'POST' })
                            .then(() => fetchNotifications());
                    });
                }

                window.addEventListener('click', () => {
                    if (notifDropdown.classList.contains('active')) {
                        notifDropdown.classList.remove('active');
                    }
                });
                notifDropdown.addEventListener('click', (e) => e.stopPropagation());

                fetchNotifications();
                setInterval(fetchNotifications, 30000);
            }
        });

        // --- CALENDAR ---
        let calendarInstance;
        function renderCalendar() {
            if (calendarInstance) { calendarInstance.render(); return; }
            const calendarEl = document.getElementById('calendar');
            calendarInstance = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
                events: 'booking_events.php',
                eventColor: '#cd853f'
            });
            calendarInstance.render();
        }
    </script>
</body>
</html>