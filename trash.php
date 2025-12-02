<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- FILTERING LOGIC ---
$filterSource = $_GET['source'] ?? 'all';
$sortOrder = $_GET['sort'] ?? 'desc';

$whereSQL = "1";
if ($filterSource !== 'all') {
    $sourceEscaped = mysqli_real_escape_string($conn, $filterSource);
    $whereSQL .= " AND source = '$sourceEscaped'";
}

$orderSQL = ($sortOrder === 'asc') ? "ASC" : "DESC";

$trash_items = [];
$sql = "SELECT * FROM trash_bin WHERE $whereSQL ORDER BY deleted_at $orderSQL";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $trash_items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin | ManCave Admin</title>
    
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

        /* Page Specific Styles */
        .controls-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap; }
        .filter-form { display: flex; gap: 10px; align-items: center;}
        select { padding: 10px 15px; border-radius: 50px; border: 1px solid var(--border); background: white; outline: none; cursor: pointer; color: var(--primary); font-weight: 600; }
        
        .badge-source { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .source-artworks { background: #ede9fe; color: #8b5cf6; }
        .source-bookings { background: #dbeafe; color: #3b82f6; }
        .source-services { background: #ffedd5; color: #f97316; }
        .source-users { background: #fee2e2; color: #ef4444; }
        .source-events { background: #dcfce7; color: #10b981; }
        .source-artists { background: #fce7f3; color: #ec4899; }
        .source-inquiries { background: #f3f4f6; color: #6b7280; }
        .source-ratings { background: #fff7ed; color: #c2410c; }

        /* === MODAL STYLES === */
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
        .restore-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        .modal-card h3 { font-family: var(--font-head); font-size: 1.5rem; margin-bottom: 10px; color: var(--primary); }
        .modal-card p { color: var(--secondary); font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }

        .btn-group { display: flex; gap: 10px; justify-content: center; }
        .btn-friendly {
            padding: 12px 25px; border-radius: 50px; font-weight: 700;
            border: none; cursor: pointer; transition: 0.2s; font-size: 0.9rem;
        }
        .btn-friendly:hover { transform: translateY(-2px); opacity: 0.9; }
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
                <li><a href="feedback.php"><i class="fas fa-comments"></i> <span>Message & Feedback</span></a></li>
                <li class="active"><a href="trash.php"><i class="fas fa-trash-alt"></i> <span>Recycle Bin</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="page-header">
                <h1>Recycle Bin</h1>
                <p>Restore deleted items or remove them permanently.</p>
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

        <div class="controls-bar">
            <form method="GET" class="filter-form">
                <select name="source" onchange="this.form.submit()">
                    <option value="all" <?= $filterSource == 'all' ? 'selected' : '' ?>>All Sources</option>
                    <option value="artworks" <?= $filterSource == 'artworks' ? 'selected' : '' ?>>Artworks</option>
                    <option value="bookings" <?= $filterSource == 'bookings' ? 'selected' : '' ?>>Bookings</option>
                    <option value="services" <?= $filterSource == 'services' ? 'selected' : '' ?>>Services</option>
                    <option value="events" <?= $filterSource == 'events' ? 'selected' : '' ?>>Events</option>
                    <option value="artists" <?= $filterSource == 'artists' ? 'selected' : '' ?>>Artists</option>
                    <option value="users" <?= $filterSource == 'users' ? 'selected' : '' ?>>Users</option>
                    <option value="inquiries" <?= $filterSource == 'inquiries' ? 'selected' : '' ?>>Inquiries</option>
                    <option value="ratings" <?= $filterSource == 'ratings' ? 'selected' : '' ?>>Ratings</option>
                </select>

                <select name="sort" onchange="this.form.submit()">
                    <option value="desc" <?= $sortOrder == 'desc' ? 'selected' : '' ?>>Newest Deleted</option>
                    <option value="asc" <?= $sortOrder == 'asc' ? 'selected' : '' ?>>Oldest Deleted</option>
                </select>
            </form>
        </div>

        <div class="card table-card">
            <div class="table-responsive">
                <table class="styled-table" id="trashTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Details</th>
                            <th>Source</th>
                            <th>Deleted Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trash_items)): ?>
                            <tr><td colspan="5" class="text-center" style="padding:40px; color:#999;">No items found in trash.</td></tr>
                        <?php else: ?>
                            <?php foreach ($trash_items as $item): 
                                $parts = explode('|', $item['item_name'], 2);
                                $displayName = $parts[0];
                                $sourceClass = 'source-' . strtolower($item['source']);
                            ?>
                            <tr>
                                <td><span class="id-badge">#<?= $item['id'] ?></span></td>
                                <td><strong><?= htmlspecialchars($displayName) ?></strong></td>
                                <td><span class="badge-source <?= $sourceClass ?>"><?= ucfirst($item['source']) ?></span></td>
                                <td>
                                    <i class="far fa-clock" style="color:var(--secondary); margin-right:5px;"></i>
                                    <?= date('M d, Y h:i A', strtotime($item['deleted_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="actions" style="justify-content: flex-end;">
                                        <button class="btn-icon edit" onclick="confirmAction('restore', <?= $item['id'] ?>)" title="Restore Item"><i class="fas fa-undo"></i></button>
                                        <button class="btn-icon delete" onclick="confirmAction('delete', <?= $item['id'] ?>)" title="Delete Permanently"><i class="fas fa-times"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="confirmModal">
        <div class="modal-card small">
            <div class="modal-header-icon" id="confirmIcon"></div>
            <h3 id="confirmTitle"></h3>
            <p id="confirmText"></p>
            <div class="btn-group">
                <button class="btn-friendly" style="background:#e5e7eb; color:#374151;" onclick="closeModal('confirmModal')">Cancel</button>
                <button class="btn-friendly" id="confirmBtnAction" style="color:white;"></button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="alertModal">
        <div class="modal-card small">
            <div class="modal-header-icon" id="alertIcon"></div>
            <h3 id="alertTitle"></h3>
            <p id="alertMessage"></p>
            <button class="btn-friendly" style="background:var(--primary); color:white; width:100%;" onclick="closeModal('alertModal'); location.reload();">Okay</button>
        </div>
    </div>

    <script>
        // Modal Logic
        let actionCallback = null;

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function showAlert(title, msg, type) {
            document.getElementById('alertTitle').innerText = title;
            document.getElementById('alertMessage').innerText = msg;
            
            const icon = document.getElementById('alertIcon');
            if (type === 'success') {
                icon.className = 'modal-header-icon restore-icon';
                icon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                icon.className = 'modal-header-icon delete-icon';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            }
            
            document.getElementById('alertModal').classList.add('active');
        }

        function confirmAction(type, id) {
            const icon = document.getElementById('confirmIcon');
            const btn = document.getElementById('confirmBtnAction');
            
            if (type === 'restore') {
                document.getElementById('confirmTitle').innerText = 'Restore Item?';
                document.getElementById('confirmText').innerText = 'This item will be moved back to its original location.';
                icon.className = 'modal-header-icon restore-icon';
                icon.innerHTML = '<i class="fas fa-undo"></i>';
                btn.style.background = '#10b981';
                btn.innerText = 'Restore';
                actionCallback = () => performRestore(id);
            } else {
                document.getElementById('confirmTitle').innerText = 'Delete Permanently?';
                document.getElementById('confirmText').innerText = 'This action cannot be undone. Are you sure?';
                icon.className = 'modal-header-icon delete-icon';
                icon.innerHTML = '<i class="fas fa-trash-alt"></i>';
                btn.style.background = '#ef4444';
                btn.innerText = 'Delete';
                actionCallback = () => performDelete(id);
            }
            
            document.getElementById('confirmModal').classList.add('active');
        }

        document.getElementById('confirmBtnAction').addEventListener('click', () => {
            if (actionCallback) actionCallback();
            closeModal('confirmModal');
        });

        // API Calls
        async function performRestore(id) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'restore');
            try {
                const res = await fetch('restore_item', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.status === 'success') showAlert('Restored!', 'Item restored successfully.', 'success');
                else showAlert('Error', data.message, 'error');
            } catch (err) { showAlert('Error', 'An unexpected error occurred.', 'error'); }
        }

        async function performDelete(id) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'permanent_delete');
            try {
                const res = await fetch('restore_item', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.status === 'success') showAlert('Deleted!', 'Item deleted permanently.', 'success');
                else showAlert('Error', data.message, 'error');
            } catch (err) { showAlert('Error', 'An unexpected error occurred.', 'error'); }
        }

        // Notification Logic
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
    </script>
</body>
</html>