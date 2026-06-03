<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";
session_start();

// Set zona waktu Jakarta
date_default_timezone_set('Asia/Jakarta');

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'];

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? mysqli_real_escape_string($conn, $_GET['priority']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build where clause
$where = "WHERE 1=1";
if (!empty($status_filter)) {
    $where .= " AND t.status = '$status_filter'";
}
if (!empty($priority_filter)) {
    $where .= " AND t.priority = '$priority_filter'";
}
if (!empty($search)) {
    $where .= " AND (t.task_name LIKE '%$search%' OR p.client_name LIKE '%$search%' OR p.kode LIKE '%$search%')";
}

// Query ambil semua task dari semua project
$tasks_query = "SELECT t.*, 
                p.kode as project_kode, 
                p.client_name,
                GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') as assigned_staff
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN task_assignments ta ON t.id = ta.task_id
                LEFT JOIN users u ON ta.user_id = u.id
                $where
                GROUP BY t.id
                ORDER BY 
                    CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END ASC,
                    CASE t.priority 
                        WHEN 'Urgent' THEN 1 
                        WHEN 'High' THEN 2 
                        WHEN 'Medium' THEN 3 
                        WHEN 'Low' THEN 4 
                        WHEN 'Done' THEN 5
                        ELSE 6 
                    END ASC,
                    t.due_date ASC
                LIMIT $offset, $limit";
$tasks_result = mysqli_query($conn, $tasks_query);

// Hitung total data untuk pagination
$total_query = "SELECT COUNT(DISTINCT t.id) as total FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                $where";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Ambil statistik
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done,
    SUM(CASE WHEN priority = 'Urgent' AND status != 'Done' THEN 1 ELSE 0 END) as urgent,
    SUM(CASE WHEN priority = 'High' AND status != 'Done' THEN 1 ELSE 0 END) as high,
    SUM(CASE WHEN priority = 'Medium' AND status != 'Done' THEN 1 ELSE 0 END) as medium
FROM tasks";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Tasks - Global Media Creative</title>
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

        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px 30px;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
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
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card h4 {
            font-size: 12px;
            color: #8898aa;
            margin-bottom: 5px;
        }

        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
        }

        .stat-card.total .number { color: #1e3c72; }
        .stat-card.progress .number { color: #fb6340; }
        .stat-card.done .number { color: #2dce89; }
        .stat-card.urgent .number { color: #f5365c; }
        .stat-card.high .number { color: #fb6340; }
        .stat-card.medium .number { color: #11cdef; }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-group select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            cursor: pointer;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 250px;
            font-size: 13px;
        }

        .btn-search {
            background: #1e3c72;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .btn-search:hover {
            background: #2a5298;
        }

        .btn-reset {
            background: #8898aa;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .btn-reset:hover {
            background: #6c757d;
        }

        /* Task Table */
        .task-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

        tr:hover {
            background: #f8f9fa;
            cursor: pointer;
        }

        .task-link {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
        }

        .task-link:hover {
            text-decoration: underline;
        }

        .client-name {
            font-weight: 500;
            color: #1e3c72;
        }

        .deadline-date {
            font-size: 13px;
        }

        .deadline-overdue {
            color: #f5365c;
            font-weight: 500;
        }

        .deadline-soon {
            color: #fb6340;
        }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        .priority-urgent { background: #fde8e8; color: #f5365c; }
        .priority-high { background: #fff3e0; color: #fb6340; }
        .priority-medium { background: #e3f2fd; color: #11cdef; }
        .priority-low { background: #e3f5ec; color: #2dce89; }
        .priority-done { background: #e3f5ec; color: #2dce89; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        .status-ToDo { background: #eef2f7; color: #8898aa; }
        .status-InProgress { background: #fff3e0; color: #fb6340; }
        .status-Review { background: #e3f2fd; color: #11cdef; }
        .status-Done { background: #e3f5ec; color: #2dce89; }

        .assigned-staff {
            font-size: 12px;
            color: #525f7f;
        }

        .assigned-staff i {
            margin-right: 5px;
            color: #11cdef;
        }

        .pagination {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #525f7f;
            font-size: 14px;
        }

        .pagination a:hover {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }

        .pagination .active {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #8898aa;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <div class="main-content">
        <div class="top-header">
            <h1><i class="fas fa-tasks"></i> All Tasks</h1>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h4>Total Task</h4>
                <div class="number"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-card progress">
                <h4>In Progress</h4>
                <div class="number"><?php echo $stats['in_progress'] ?? 0; ?></div>
            </div>
            <div class="stat-card done">
                <h4>Done</h4>
                <div class="number"><?php echo $stats['done'] ?? 0; ?></div>
            </div>
            <div class="stat-card urgent">
                <h4>Urgent</h4>
                <div class="number"><?php echo $stats['urgent'] ?? 0; ?></div>
            </div>
            <div class="stat-card high">
                <h4>High Priority</h4>
                <div class="number"><?php echo $stats['high'] ?? 0; ?></div>
            </div>
            <div class="stat-card medium">
                <h4>Medium Priority</h4>
                <div class="number"><?php echo $stats['medium'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-group">
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="">Semua Status</option>
                    <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Done" <?php echo $status_filter == 'Done' ? 'selected' : ''; ?>>Done</option>
                    <option value="To Do" <?php echo $status_filter == 'To Do' ? 'selected' : ''; ?>>To Do</option>
                    <option value="Review" <?php echo $status_filter == 'Review' ? 'selected' : ''; ?>>Review</option>
                </select>
                <select id="priorityFilter" onchange="applyFilters()">
                    <option value="">Semua Priority</option>
                    <option value="Urgent" <?php echo $priority_filter == 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                </select>
                <button onclick="resetFilters()" class="btn-reset">Reset Filter</button>
            </div>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Cari task/client..." value="<?php echo htmlspecialchars($search); ?>">
                <button onclick="applyFilters()" class="btn-search"><i class="fas fa-search"></i> Cari</button>
            </div>
        </div>

        <!-- Task Table -->
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
                    <?php if (mysqli_num_rows($tasks_result) > 0): ?>
                        <?php while ($task = mysqli_fetch_assoc($tasks_result)): 
                            $due_date = $task['due_date'];
                            $today = new DateTime();
                            $deadline = new DateTime($due_date);
                            $days_left = $today->diff($deadline)->days;
                            $is_overdue = ($due_date && strtotime($due_date) < time() && $task['status'] != 'Done');
                            $is_soon = ($days_left <= 3 && $days_left > 0 && $task['status'] != 'Done');
                        ?>
                            <tr onclick="window.location.href='infotask.php?id=<?php echo $task['id']; ?>'" style="cursor: pointer;">
                                <td>
                                    <span class="task-link">
                                        <?php echo htmlspecialchars($task['task_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="client-name"><?php echo htmlspecialchars($task['client_name'] ?: '-'); ?></span>
                                </td>
                                <td>
                                    <?php if ($due_date): ?>
                                        <span class="deadline-date <?php echo $is_overdue ? 'deadline-overdue' : ($is_soon ? 'deadline-soon' : ''); ?>">
                                            <?php echo date('d M Y', strtotime($due_date)); ?>
                                            <?php if ($is_overdue): ?>
                                                <br><small>(Terlewat)</small>
                                            <?php elseif ($is_soon): ?>
                                                <br><small>(<?php echo $days_left; ?> hari lagi)</small>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $priority_class = '';
                                    switch(strtolower($task['priority'])) {
                                        case 'urgent': $priority_class = 'priority-urgent'; break;
                                        case 'high': $priority_class = 'priority-high'; break;
                                        case 'medium': $priority_class = 'priority-medium'; break;
                                        case 'low': $priority_class = 'priority-low'; break;
                                        case 'done': $priority_class = 'priority-done'; break;
                                    }
                                    ?>
                                    <span class="priority-badge <?php echo $priority_class; ?>">
                                        <i class="fas <?php echo $task['priority'] == 'Urgent' ? 'fa-exclamation-circle' : ($task['priority'] == 'High' ? 'fa-arrow-up' : ($task['priority'] == 'Low' ? 'fa-arrow-down' : ($task['priority'] == 'Done' ? 'fa-check-circle' : 'fa-minus'))); ?>"></i>
                                        <?php echo $task['priority']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    switch(strtolower($task['status'])) {
                                        case 'to do': $status_class = 'status-ToDo'; break;
                                        case 'in progress': $status_class = 'status-InProgress'; break;
                                        case 'review': $status_class = 'status-Review'; break;
                                        case 'done': $status_class = 'status-Done'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $task['status'] == 'Done' ? 'fa-check-circle' : ($task['status'] == 'In Progress' ? 'fa-spinner fa-pulse' : 'fa-clock'); ?>"></i>
                                        <?php echo $task['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="assigned-staff">
                                        <i class="fas fa-users"></i> 
                                        <?php echo !empty($task['assigned_staff']) ? htmlspecialchars($task['assigned_staff']) : '-'; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-tasks"></i>
                                Belum ada task. Silakan buat task baru dari halaman project.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>">« Prev</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            const search = document.getElementById('searchInput').value;
            
            let url = 'task.php?';
            if (status) url += `status=${encodeURIComponent(status)}&`;
            if (priority) url += `priority=${encodeURIComponent(priority)}&`;
            if (search) url += `search=${encodeURIComponent(search)}&`;
            
            window.location.href = url;
        }
        
        function resetFilters() {
            window.location.href = 'task.php';
        }
        
        // Enter key search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    </script>
</body>
</html>