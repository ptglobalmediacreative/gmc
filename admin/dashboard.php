<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user
$user_id = $_SESSION['user_id'];
$query_user = "SELECT * FROM users WHERE id = '$user_id'";
$result_user = mysqli_query($conn, $query_user);
$user = mysqli_fetch_assoc($result_user);

// Hitung total staff
$query_staff = "SELECT COUNT(*) as total FROM users";
$result_staff = mysqli_query($conn, $query_staff);
$total_staff = 0;
if ($result_staff) {
    $data = mysqli_fetch_assoc($result_staff);
    $total_staff = $data['total'];
}

// Sementara untuk Total Client dan Total Project Aktif
$total_client = 0;
$total_project_aktif = 0;

// Data task schedule (sementara, nanti dari database)
$tasks = [
    [
        'title' => 'Briefing Project Website',
        'client' => 'PT Maju Bersama',
        'deadline' => '2025-06-30',
        'priority' => 'High',
        'status' => 'In Progress',
        'assigned_to' => 'Tim Creative'
    ],
    [
        'title' => 'Desain Logo Branding',
        'client' => 'Warung Kopi Nusantara',
        'deadline' => '2025-06-25',
        'priority' => 'Medium',
        'status' => 'Review',
        'assigned_to' => 'Tim Desain'
    ],
    [
        'title' => 'Konten Instagram Campaign',
        'client' => 'Fashion Store ID',
        'deadline' => '2025-06-28',
        'priority' => 'High',
        'status' => 'Pending',
        'assigned_to' => 'Tim Sosmed'
    ],
    [
        'title' => 'Video Company Profile',
        'client' => 'Startup Tech Solution',
        'deadline' => '2025-07-05',
        'priority' => 'Low',
        'status' => 'Planning',
        'assigned_to' => 'Tim Video'
    ],
    [
        'title' => 'SEO Optimization Website',
        'client' => 'E-commerce Jaya Abadi',
        'deadline' => '2025-06-27',
        'priority' => 'Medium',
        'status' => 'In Progress',
        'assigned_to' => 'Tim IT'
    ]
];

// Data notifikasi sementara
$notifications = [
    [
        'id' => 1,
        'title' => 'Task Baru',
        'message' => 'Briefing Project Website telah ditambahkan',
        'time' => '5 menit lalu',
        'icon' => 'fas fa-tasks',
        'color' => '#1e3c72',
        'is_read' => false
    ],
    [
        'id' => 2,
        'title' => 'Deadline Mendekat',
        'message' => 'Task "Desain Logo Branding" deadline 2 hari lagi',
        'time' => '1 jam lalu',
        'icon' => 'fas fa-clock',
        'color' => '#f5365c',
        'is_read' => false
    ],
    [
        'id' => 3,
        'title' => 'Task Completed',
        'message' => 'Task "Konten Instagram Campaign" telah selesai',
        'time' => '3 jam lalu',
        'icon' => 'fas fa-check-circle',
        'color' => '#2dce89',
        'is_read' => true
    ],
    [
        'id' => 4,
        'title' => 'Staff Baru',
        'message' => 'Tim Desain telah menambahkan member baru',
        'time' => 'kemarin',
        'icon' => 'fas fa-user-plus',
        'color' => '#11cdef',
        'is_read' => true
    ]
];

$unread_count = 0;
foreach ($notifications as $notif) {
    if (!$notif['is_read']) {
        $unread_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Global Media Creative</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            display: flex;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 280px;
            background: #ffffff;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            left: 0;
            top: 0;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid #eef2f7;
        }

        .sidebar-header h2 {
            font-size: 20px;
            color: #1e3c72;
        }

        .sidebar-header p {
            font-size: 12px;
            color: #8898aa;
            margin-top: 5px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: #525f7f;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }

        .sidebar-menu a i {
            width: 20px;
            font-size: 16px;
        }

        .sidebar-menu a:hover {
            background: #f0f4f8;
            color: #1e3c72;
        }

        .sidebar-menu a.active {
            background: #1e3c72;
            color: white;
            border-radius: 0 10px 10px 0;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px 30px;
        }

        /* Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .top-header h1 {
            font-size: 24px;
            color: #1e3c72;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* ========== NOTIFICATION ========== */
        .notification-container {
            position: relative;
        }

        .notification-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #525f7f;
            cursor: pointer;
            position: relative;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .notification-btn:hover {
            background: #f0f4f8;
            color: #1e3c72;
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #f5365c;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 50%;
            font-weight: bold;
        }

        .notification-dropdown {
            position: absolute;
            top: 45px;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h4 {
            font-size: 16px;
            color: #1e3c72;
        }

        .notification-header a {
            font-size: 12px;
            color: #1e3c72;
            text-decoration: none;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            padding: 15px 20px;
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
            transition: background 0.3s;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #f0f4f8;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .notification-icon i {
            font-size: 18px;
            color: white;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 12px;
            color: #8898aa;
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 11px;
            color: #c0c5d0;
        }

        .notification-footer {
            padding: 12px 20px;
            text-align: center;
            border-top: 1px solid #eef2f7;
        }

        .notification-footer a {
            font-size: 13px;
            color: #1e3c72;
            text-decoration: none;
        }

        .role-badge {
            background: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        /* Stats Cards - 3 cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 40px;
            color: #1e3c72;
            margin-bottom: 15px;
        }

        .stat-card h4 {
            color: #8898aa;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #1e3c72;
        }

        /* ========== TASK SCHEDULE ========== */
        .task-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 20px;
            overflow: hidden;
        }

        .task-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-header h3 {
            font-size: 18px;
            color: #1e3c72;
        }

        .task-header h3 i {
            margin-right: 10px;
        }

        .btn-add {
            background: #1e3c72;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.3s;
        }

        .btn-add:hover {
            background: #2a5298;
        }

        .task-table {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 20px;
            background: #f8f9fa;
            color: #525f7f;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid #eef2f7;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #eef2f7;
            color: #3a3f4b;
            font-size: 14px;
        }

        .priority-high {
            background: #fde8e8;
            color: #f5365c;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .priority-medium {
            background: #fff3e0;
            color: #fb6340;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .priority-low {
            background: #e3f5ec;
            color: #2dce89;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .status-progress {
            background: #e3f2fd;
            color: #11cdef;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .status-review {
            background: #fff3e0;
            color: #fb6340;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending {
            background: #fde8e8;
            color: #f5365c;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .status-planning {
            background: #e3f5ec;
            color: #2dce89;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .deadline {
            font-size: 13px;
        }

        .deadline.urgent {
            color: #f5365c;
            font-weight: bold;
        }

        .task-actions i {
            margin: 0 5px;
            cursor: pointer;
            color: #8898aa;
            transition: color 0.3s;
        }

        .task-actions i:hover {
            color: #1e3c72;
        }

        .empty-task {
            text-align: center;
            padding: 50px;
            color: #8898aa;
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Global Media Creative</h2>
            <p>Dashboard Admin</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="#">
                <i class="fas fa-users"></i> Staff
            </a>
            <a href="#">
                <i class="fas fa-umbrella-beach"></i> Wisata
            </a>
            <a href="#">
                <i class="fas fa-star"></i> Ulasan
            </a>
            <a href="#">
                <i class="fas fa-chart-line"></i> Analisis
            </a>
            <a href="#">
                <i class="fas fa-calendar"></i> Kalender
            </a>
            <a href="#">
                <i class="fas fa-envelope"></i> Pesan
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <h1>Dashboard</h1>
            <div class="user-info">
                <!-- NOTIFICATION MENU -->
                <div class="notification-container">
                    <button class="notification-btn" onclick="toggleNotification()">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4>Notifikasi</h4>
                            <a href="#">Tandai semua sudah dibaca</a>
                        </div>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notification-icon" style="background: <?php echo $notif['color']; ?>;">
                                        <i class="<?php echo $notif['icon']; ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?php echo $notif['title']; ?></div>
                                        <div class="notification-message"><?php echo $notif['message']; ?></div>
                                        <div class="notification-time"><?php echo $notif['time']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="#">Lihat semua notifikasi</a>
                        </div>
                    </div>
                </div>
                
                <span>Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Stats Cards - 3 cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h4>Total Client</h4>
                <div class="value"><?php echo $total_client; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-project-diagram"></i>
                <h4>Total Project Aktif</h4>
                <div class="value"><?php echo $total_project_aktif; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-tie"></i>
                <h4>Total Staff</h4>
                <div class="value"><?php echo $total_staff; ?></div>
            </div>
        </div>

        <!-- TASK SCHEDULE SECTION -->
        <div class="task-section">
            <div class="task-table">
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Client</th>
                            <th>Deadline</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo $task['title']; ?></strong></td>
                                    <td><?php echo $task['client']; ?></td>
                                    <td>
                                        <span class="deadline <?php echo (strtotime($task['deadline']) < strtotime('+3 days') ? 'urgent' : ''); ?>">
                                            <?php echo date('d M Y', strtotime($task['deadline'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-<?php echo strtolower($task['priority']); ?>">
                                            <?php echo $task['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo strtolower(str_replace(' ', '', $task['status'])); ?>">
                                            <?php echo $task['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $task['assigned_to']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-task">
                                    <i class="fas fa-calendar-alt" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                                    Belum ada task schedule. Klik "Tambah Task" untuk membuat task baru.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleNotification() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        // Tutup dropdown saat klik di luar
        document.addEventListener('click', function(event) {
            const container = document.querySelector('.notification-container');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (container && !container.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>