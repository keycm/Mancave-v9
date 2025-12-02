<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$stats = [
    'total_art' => 0,
    'pending_req' => 0,
    'approved_req' => 0,
    'completed_app' => 0,
    'cancelled_app' => 0,
    'unread_inquiries' => 0,
    'users' => 0
];

// 1. Artworks
$res = mysqli_query($conn, "SELECT COUNT(*) as total FROM artworks");
if ($res) $stats['total_art'] = mysqli_fetch_assoc($res)['total'];

// 2. Reservation Stats
$stats['pending_req'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE status = 'pending' AND deleted_at IS NULL"))['total'];
$stats['approved_req'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE status = 'approved' AND deleted_at IS NULL"))['total'];
$stats['completed_app'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE status = 'completed' AND deleted_at IS NULL"))['total'];
$stats['cancelled_app'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE (status = 'rejected' OR status = 'cancelled') AND deleted_at IS NULL"))['total'];

// 3. Inquiries (New Stat)
$res = mysqli_query($conn, "SELECT COUNT(*) as total FROM inquiries WHERE status != 'replied'");
if ($res) $stats['unread_inquiries'] = mysqli_fetch_assoc($res)['total'];

// 4. Users
$res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
if ($res) $stats['users'] = mysqli_fetch_assoc($res)['total'];

// --- RECENT BOOKINGS ---
$recent_bookings = [];
$sql_recent = "SELECT b.*, a.title as art_title, u.username as collector_name
               FROM bookings b 
               LEFT JOIN artworks a ON b.artwork_id = a.id 
               LEFT JOIN users u ON b.user_id = u.id
               WHERE b.deleted_at IS NULL 
               ORDER BY b.created_at DESC LIMIT 5";
$result_recent = mysqli_query($conn, $sql_recent);
while ($row = mysqli_fetch_assoc($result_recent)) { $recent_bookings[] = $row; }

// --- CHART DATA ---
$selectedYear = date('Y');
$months = [];
$bookingsData = array_fill(0, 12, 0);
for ($m = 1; $m <= 12; $m++) { $months[] = date("M", mktime(0, 0, 0, $m, 1)); }

$query_chart = "SELECT MONTH(created_at) AS month, COUNT(*) AS total FROM bookings WHERE deleted_at IS NULL AND YEAR(created_at) = $selectedYear GROUP BY MONTH(created_at)";
$result_chart = mysqli_query($conn, $query_chart);
while ($row = mysqli_fetch_assoc($result_chart)) { $bookingsData[$row['month'] - 1] = (int) $row['total']; }

// Time-based greeting
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ManCave Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_new_style.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    
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
        
        /* NEW: Notification Item with Close Button */
        .notif-item { 
            padding: 15px 35px 15px 20px; /* Extra right padding for button */
            border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; 
            cursor: pointer; transition: 0.2s; position: relative; 
        }
        .notif-item:hover { background: #fdfbf7; }
        .notif-item.unread { background: #fff8f0; border-left: 4px solid var(--accent); }
        .notif-msg { color: #444; line-height: 1.4; margin-bottom: 4px; }
        .notif-time { font-size: 0.75rem; color: #999; font-weight: 600; }

        /* NEW: Close Button Style */
        .btn-notif-close {
            position: absolute; top: 10px; right: 10px;
            background: none; border: none; color: #aaa;
            font-size: 1.2rem; line-height: 1; cursor: pointer;
            padding: 0; transition: color 0.2s;
        }
        .btn-notif-close:hover { color: #ff4d4d; }
        
        .no-notif { padding: 20px; text-align: center; color: #999; font-style: italic; }
        
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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
                <li class="active"><a href="admin.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a></li>
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
                <h1><?php echo $greeting; ?>, Admin! 👋</h1>
                <p>Overview of gallery activities and requests.</p>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-image"></i></div>
                <div class="stat-details">
                    <h3><?php echo $stats['total_art']; ?></h3>
                    <span>Total Artworks</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-details">
                    <h3><?php echo $stats['pending_req']; ?></h3>
                    <span>Pending Bookings</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2; color:#ef4444;"><i class="fas fa-envelope"></i></div>
                <div class="stat-details">
                    <h3><?php echo $stats['unread_inquiries']; ?></h3>
                    <span>New Inquiries</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-friends"></i></div>
                <div class="stat-details">
                    <h3><?php echo $stats['users']; ?></h3>
                    <span>Active Customers</span>
                </div>
            </div>
        </div>

        <div class="dashboard-layout">
            
            <div class="col-chart">
                <div class="card chart-card">
                    <div class="card-header">
                        <h3>Reservation Analytics</h3>
                        <select class="chart-filter">
                            <option>This Year</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyBookingsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-side">
                <div class="card summary-card">
                    <h3>Booking Summary</h3>
                    <div class="summary-item">
                        <span class="label"><span class="dot approved"></span> Approved</span>
                        <span class="value"><?php echo $stats['approved_req']; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="label"><span class="dot completed"></span> Completed</span>
                        <span class="value"><?php echo $stats['completed_app']; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="label"><span class="dot cancelled"></span> Cancelled</span>
                        <span class="value"><?php echo $stats['cancelled_app']; ?></span>
                    </div>
                </div>
            </div>

            <div class="col-full">
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Recent Appointments</h3>
                        <a href="reservations.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Service / Artwork</th>
                                    <th>Scheduled Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_bookings)): ?>
                                    <tr><td colspan="5" class="text-center" style="padding:20px;">No recent activity found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_bookings as $row): 
                                        $statusClass = strtolower($row['status']);
                                        if($statusClass == 'rejected') $statusClass = 'cancelled';
                                    ?>
                                    <tr>
                                        <td><span class="id-badge">#<?php echo $row['id']; ?></span></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm"><?php echo strtoupper(substr($row['collector_name'] ?? 'G', 0, 1)); ?></div>
                                                <span><?php echo htmlspecialchars($row['collector_name'] ?? 'Guest'); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['service'] ?: $row['art_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['preferred_date'])); ?></td>
                                        <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Chart.js Configuration
        const ctx = document.getElementById('monthlyBookingsChart').getContext('2d');
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(205, 133, 63, 0.2)');
        gradient.addColorStop(1, 'rgba(205, 133, 63, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Reservations',
                    data: <?php echo json_encode($bookingsData); ?>,
                    backgroundColor: gradient,
                    borderColor: '#cd853f',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#cd853f',
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f0f0f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // === NOTIFICATION LOGIC (UPDATED) ===
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('adminNotifBtn');
            const notifDropdown = document.getElementById('adminNotifDropdown');
            const notifBadge = document.getElementById('adminNotifBadge');
            const notifList = document.getElementById('adminNotifList');
            const markAllBtn = document.getElementById('adminMarkAllRead');

            if(notifBtn) {
                // Toggle Dropdown
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                });

                // Fetch Notifications
                function fetchNotifications() {
                    fetch('fetch_notifications.php')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                // Update Badge
                                if (data.unread_count > 0) {
                                    notifBadge.innerText = data.unread_count;
                                    notifBadge.style.display = 'flex';
                                } else {
                                    notifBadge.style.display = 'none';
                                }

                                // Render List
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

                                        // Mark as read on item click
                                        li.addEventListener('click', (e) => {
                                            if(e.target.classList.contains('btn-notif-close')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: formData })
                                                .then(() => fetchNotifications());
                                        });

                                        // Delete button click
                                        li.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(!confirm('Delete this notification?')) return;
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

                // Mark All as Read
                if (markAllBtn) {
                    markAllBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        fetch('mark_all_as_read.php', { method: 'POST' })
                            .then(() => fetchNotifications());
                    });
                }

                // Close dropdown on outside click
                window.addEventListener('click', () => {
                    if (notifDropdown.classList.contains('active')) {
                        notifDropdown.classList.remove('active');
                    }
                });
                notifDropdown.addEventListener('click', (e) => e.stopPropagation());

                // Initial load & poll
                fetchNotifications();
                setInterval(fetchNotifications, 30000);
            }
        });
    </script>
</body>
</html>