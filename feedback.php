<?php
session_start();
include 'config.php';

// Security Check
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 1. Fetch Inquiries (EXCLUDING Copy Requests)
$inquiries = [];
$sql_inq = "SELECT * FROM inquiries WHERE message NOT LIKE '%requesting a copy%' ORDER BY created_at DESC";
if ($res_inq = mysqli_query($conn, $sql_inq)) {
    while ($row = mysqli_fetch_assoc($res_inq)) {
        $inquiries[] = $row;
    }
}

// 2. Fetch Ratings
$ratings = [];
$sql_rate = "SELECT r.*, u.username, s.name as service_name 
             FROM ratings r 
             LEFT JOIN users u ON r.user_id = u.id 
             LEFT JOIN services s ON r.service_id = s.id 
             ORDER BY r.created_at DESC";
if ($res_rate = mysqli_query($conn, $sql_rate)) {
    while ($row = mysqli_fetch_assoc($res_rate)) {
        $ratings[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message & Feedback | ManCave Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_new_style.css">
    
    <style>
        /* Logo Styles */
        .sidebar-logo { display: flex; flex-direction: column; align-items: center; gap: 0; line-height: 1; text-decoration: none; }
        .logo-top { font-family: 'Playfair Display', serif; font-size: 0.7rem; font-weight: 700; color: #ccc; letter-spacing: 2px; }
        .logo-main { font-family: 'Pacifico', cursive; font-size: 1.8rem; transform: rotate(-4deg); margin: 5px 0; color: #fff; }
        .logo-red { color: #ff4d4d; }
        .logo-bottom { font-family: 'Nunito Sans', sans-serif; font-size: 0.6rem; font-weight: 800; color: #ccc; letter-spacing: 3px; text-transform: uppercase; }

        /* Notification Styles */
        .header-actions { display: flex; align-items: center; gap: 25px; }
        .notif-wrapper { position: relative; }
        .notif-bell { background: #fff; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--secondary); cursor: pointer; box-shadow: var(--shadow-sm); border: 1px solid var(--border); transition: 0.3s; }
        .notif-bell:hover { color: var(--accent); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .notif-bell .dot { position: absolute; top: -2px; right: -2px; background: var(--red); color: white; font-size: 0.65rem; font-weight: 700; border-radius: 50%; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
        .notif-dropdown { display: none; position: absolute; right: -10px; top: 55px; width: 320px; background: var(--white); border-radius: 16px; box-shadow: var(--shadow-lg); border: 1px solid var(--border); z-index: 1100; overflow: hidden; transform-origin: top right; animation: slideDown 0.2s ease-out; }
        .notif-dropdown.active { display: block; }
        
        .notif-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa; font-weight: 700; font-size: 0.9rem; color: var(--primary); }
        .small-btn { border: none; background: none; font-size: 0.75rem; cursor: pointer; font-weight: 700; color: var(--accent); text-transform: uppercase; }
        .notif-list { max-height: 300px; overflow-y: auto; list-style: none; margin: 0; padding: 0; }
        .notif-item { padding: 15px 35px 15px 20px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer; transition: 0.2s; position: relative; }
        .notif-item:hover { background: #fdfbf7; }
        .notif-item.unread { background: #fff8f0; border-left: 4px solid var(--accent); }
        .notif-msg { color: #444; line-height: 1.4; margin-bottom: 4px; }
        .notif-time { font-size: 0.75rem; color: #999; font-weight: 600; }
        .btn-notif-close { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #aaa; font-size: 1.2rem; line-height: 1; cursor: pointer; padding: 0; transition: color 0.2s; }
        .btn-notif-close:hover { color: #ff4d4d; }
        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Page Specific Styles */
        .tabs { margin-bottom: 30px; display: flex; gap: 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 0; }
        .tab-btn { background: none; border: none; padding: 12px 25px; font-size: 1rem; font-weight: 700; color: var(--secondary); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.3s; }
        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-pane { display: none; animation: fadeIn 0.3s ease; }
        .tab-pane.active { display: block; }

        .user-cell strong { display: block; font-size: 0.95rem; color: var(--primary); }
        .user-cell small { color: var(--secondary); }
        .msg-preview { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #555; }
        .unread-row { background-color: #fffaf0; }
        .unread-row td { font-weight: 600; color: var(--primary); }
        .stars { color: #f39c12; font-size: 0.85rem; letter-spacing: 2px; }
        .star-empty { color: #e0e0e0; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* === MODAL STYLES (MATCHING TRASH.PHP) === */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        /* Small Modal (Confirm/Alert) */
        .modal-card.small {
            background: white; width: 400px; max-width: 90%;
            border-radius: 20px; padding: 40px 30px; text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: 0.3s;
        }
        .modal-overlay.active .modal-card.small { transform: translateY(0); }

        /* Large Modal (Inquiry Details) */
        .modal-card.large {
            background: white; width: 650px; max-width: 95%;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: 0.3s;
            display: flex; flex-direction: column;
        }
        .modal-overlay.active .modal-card.large { transform: translateY(0); }

        /* Modal Icons */
        .modal-header-icon {
            width: 70px; height: 70px; border-radius: 50%;
            margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
        }
        .delete-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .success-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .info-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        
        .modal-card h3 { font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card p { color: var(--secondary); font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }

        .btn-group { display: flex; gap: 10px; justify-content: center; }
        .btn-friendly {
            padding: 12px 25px; border-radius: 50px; font-weight: 700;
            border: none; cursor: pointer; transition: 0.2s; font-size: 0.9rem;
        }
        .btn-friendly:hover { transform: translateY(-2px); opacity: 0.9; }
        
        /* Inquiry Modal Specifics */
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
        .inquiry-box { background: #f8f9fa; border: 1px solid #eee; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
        .reply-section label { display:block; font-weight:700; font-size:0.85rem; color:#555; margin-bottom:5px; }
        .reply-section input, .reply-section textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; outline: none; margin-bottom: 15px; }
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
                <li><a href="content.php"><i class="fas fa-layer-group"></i> <span>Website Content</span></a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> <span>Customers & Staff</span></a></li>
                <li class="active"><a href="feedback.php"><i class="fas fa-comments"></i> <span>Message & Feedback</span></a></li>
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
                <h1>Message & Feedback Center</h1>
                <p>Manage customer inquiries and service reviews.</p>
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
            <button class="tab-btn active" onclick="switchTab('inquiries', this)"><i class="fas fa-envelope"></i> Message Inquiries</button>
            <button class="tab-btn" onclick="switchTab('ratings', this)"><i class="fas fa-star"></i> Service Ratings</button>
        </div>

        <div id="inquiries" class="tab-pane active">
            <div class="card table-card">
                <div class="card-header"><h3>Customer Messages</h3></div>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Username</th>
                                <th style="width: 20%;">Email Address</th>
                                <th style="width: 20%;">Message Preview</th>
                                <th style="width: 15%;">Status</th>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 10%; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($inquiries)): ?>
                                <tr><td colspan="5" class="text-center" style="padding:50px; color:#999;">No general inquiries found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($inquiries as $inq): 
                                    $isUnread = ($inq['status'] !== 'read' && $inq['status'] !== 'replied');
                                    $statusClass = 'status-' . ($inq['status'] == 'replied' ? 'completed' : ($isUnread ? 'pending' : 'approved'));
                                    $statusLabel = ($inq['status'] == 'replied') ? 'Replied' : ($isUnread ? 'Unread' : 'Read');
                                ?>
                                <tr class="<?= $isUnread ? 'unread-row' : '' ?>">
                                    <td>
                                        <div class="user-cell">
                                            <strong><?= htmlspecialchars($inq['username']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-cell">
                                            <small><?= htmlspecialchars($inq['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="msg-preview"><?= htmlspecialchars($inq['message']) ?></div>
                                    </td>
                                    <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td><?= date('M d, Y', strtotime($inq['created_at'])) ?></td>
                                    <td style="text-align:right;">
                                        <div class="actions" style="justify-content: flex-end;">
                                            <button class="btn-icon edit" onclick="viewInquiry(<?= $inq['id'] ?>)" title="View & Reply">
                                                <i class="fas fa-envelope-open-text"></i>
                                            </button>
                                            <button class="btn-icon delete" onclick="confirmAction('inquiry', <?= $inq['id'] ?>)" title="Move to Trash">
                                                <i class="fas fa-trash"></i>
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

        <div id="ratings" class="tab-pane">
            <div class="card table-card">
                <div class="card-header"><h3>Service Reviews</h3></div>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Service Rated</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th style="text-align:left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($ratings)): ?>
                                <tr><td colspan="5" class="text-center" style="padding:50px; color:#999;">No ratings yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ratings as $rate): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($rate['username'] ?? 'Guest') ?></strong></td>
                                    <td><?= htmlspecialchars($rate['service_name'] ?? 'General/Artwork') ?></td>
                                    <td>
                                        <div class="stars">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $rate['rating'] ? '' : 'star-empty' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><div class="msg-preview"><?= htmlspecialchars($rate['review']) ?></div></td>
                                    <td style="text-align:left;">
                                        <button class="btn-icon delete" onclick="confirmAction('rating', <?= $rate['id'] ?>)" title="Delete Review">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <div class="modal-overlay" id="inquiryModal">
        <div class="modal-card large">
            <div class="modal-header">
                <h3>Inquiry Details</h3>
                <button onclick="closeModal('inquiryModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#999;">&times;</button>
            </div>
            <div class="modal-body">
                <div id="inquiryContent" class="inquiry-box">
                    <p style="text-align:center; color:#888;">Loading details...</p>
                </div>
                <div class="reply-section">
                    <h4 style="margin: 0 0 15px 0; color: #cd853f; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px;">Reply to Customer</h4>
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
        // --- TABS ---
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // --- MODAL LOGIC ---
        let actionCallback = null;

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
                icon.className = 'modal-header-icon delete-icon'; // Reuse red for error
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            }
            
            // Set OK button behavior
            const btn = document.querySelector('#alertModal button');
            btn.onclick = function() {
                closeModal('alertModal');
                if (reload) location.reload();
            };
            
            document.getElementById('alertModal').classList.add('active');
        }

        function confirmAction(type, id) {
            const btn = document.getElementById('confirmBtnAction');
            
            if (type === 'inquiry') {
                document.getElementById('confirmTitle').innerText = 'Trash Inquiry?';
                document.getElementById('confirmText').innerText = 'This message will be moved to the recycle bin.';
                actionCallback = () => deleteInquiry(id);
            } else if (type === 'rating') {
                document.getElementById('confirmTitle').innerText = 'Delete Review?';
                document.getElementById('confirmText').innerText = 'This will move the review to the recycle bin.';
                actionCallback = () => deleteRating(id);
            }
            
            document.getElementById('confirmModal').classList.add('active');
        }

        document.getElementById('confirmBtnAction').addEventListener('click', () => {
            if (actionCallback) actionCallback();
            closeModal('confirmModal');
        });

        // --- NOTIFICATIONS ---
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('adminNotifBtn');
            const notifDropdown = document.getElementById('adminNotifDropdown');
            const notifBadge = document.getElementById('adminNotifBadge');
            const notifList = document.getElementById('adminNotifList');
            const markAllBtn = document.getElementById('adminMarkAllRead');

            if (notifBtn) {
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

        // --- INQUIRY LOGIC ---
        function viewInquiry(id) {
            document.getElementById('inquiryModal').classList.add('active');
            document.getElementById('inquiryContent').innerHTML = '<p style="text-align:center; color:#888;">Loading...</p>';
            document.getElementById('replyId').value = id;

            fetch(`get_inquiry.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if(data.error) {
                        document.getElementById('inquiryContent').innerHTML = `<p style="color:red;">${data.error}</p>`;
                    } else {
                        document.getElementById('inquiryContent').innerHTML = `
                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:10px;">
                                <div>
                                    <span style="display:block; font-size:0.85rem; color:#888; text-transform:uppercase; letter-spacing:0.5px;">From</span>
                                    <strong style="font-size:1.1rem; color:#2c3e50;">${data.username}</strong>
                                    <div style="color:#666; font-size:0.9rem;">${data.email}</div>
                                </div>
                                <div style="text-align:right;">
                                    <span style="display:block; font-size:0.85rem; color:#888; text-transform:uppercase; letter-spacing:0.5px;">Date</span>
                                    <div style="font-weight:600; color:#555;">${data.created_at}</div>
                                </div>
                            </div>
                            <div style="margin-bottom:10px;">
                                <span style="font-size:0.85rem; color:#888; font-weight:700;">Contact:</span> 
                                <span style="color:#333;">${data.mobile}</span>
                            </div>
                            <div style="margin-top:15px;">
                                <span style="display:block; font-size:0.85rem; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:5px;">Message</span>
                                <p style="line-height:1.6; color:#333; white-space: pre-wrap; margin:0;">${data.message}</p>
                            </div>
                        `;
                    }
                });
        }

        // Reply Form
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
                    closeModal('inquiryModal');
                    showAlert('Sent!', 'Reply sent successfully!', 'success', true);
                } else {
                    showAlert('Error', 'Error sending email. Please try again.', 'error');
                }
            } catch(err) { 
                showAlert('Error', 'Request failed.', 'error'); 
            } finally { 
                btn.disabled = false; btn.innerHTML = originalText; 
            }
        });

        // Delete Inquiry
        function deleteInquiry(id) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('delete_inquiry.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') showAlert('Deleted!', 'Inquiry moved to trash.', 'success', true);
                    else showAlert('Error', data.message, 'error');
                });
        }

        // Delete Rating
        function deleteRating(id) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_rating');
            fetch('manage_feedback.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) showAlert('Deleted!', 'Review moved to trash.', 'success', true);
                    else showAlert('Error', 'Error deleting rating.', 'error');
                });
        }
    </script>
</body>
</html>
}