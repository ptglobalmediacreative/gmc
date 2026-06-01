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

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Cek apakah user memiliki akses untuk manage task (Director atau Project Coordinator)
$can_manage = ($user_role == 'Director' || $user_role == 'Project Coordinator');

// Ambil data project jika ada project_id
$project = null;
if ($project_id > 0) {
    $project_query = "SELECT * FROM projects WHERE id = $project_id";
    $project_result = mysqli_query($conn, $project_query);
    $project = mysqli_fetch_assoc($project_result);
}

// Buat tabel tasks jika belum ada
$create_table = "CREATE TABLE IF NOT EXISTS tasks (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    project_id INT(11) NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    format ENUM('Video', 'Image', 'Motion') DEFAULT 'Image',
    priority ENUM('Low', 'Medium', 'High', 'Urgent', 'Done') DEFAULT 'Medium',
    status ENUM('To Do', 'In Progress', 'Review', 'Done') DEFAULT 'In Progress',
    start_date DATE,
    due_date DATE,
    created_by INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_table);

// Fungsi untuk menghitung priority berdasarkan deadline
function calculatePriority($due_date) {
    if (empty($due_date)) {
        return 'Low';
    }
    
    $today = new DateTime();
    $deadline = new DateTime($due_date);
    $interval = $today->diff($deadline);
    $days_left = (int)$interval->format('%r%a');
    
    if ($days_left < 0) {
        return 'Urgent';
    }
    
    if ($days_left == 0) {
        return 'Urgent';
    } elseif ($days_left >= 1 && $days_left <= 2) {
        return 'Urgent';
    } elseif ($days_left >= 3 && $days_left <= 4) {
        return 'High';
    } elseif ($days_left >= 5 && $days_left <= 7) {
        return 'Medium';
    } elseif ($days_left >= 8) {
        return 'Low';
    } else {
        return 'Low';
    }
}

// Fungsi untuk mengecek apakah semua status checklist sudah selesai untuk suatu task
function isAllStatusCompleted($conn, $task_id) {
    $check_query = "SELECT COUNT(*) as total, 
                           SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) as completed
                    FROM task_status_checklist 
                    WHERE task_id = $task_id";
    $check_result = mysqli_query($conn, $check_query);
    $check_row = mysqli_fetch_assoc($check_result);
    
    if ($check_row['total'] > 0 && $check_row['total'] == $check_row['completed']) {
        return true;
    }
    return false;
}

// Update priority untuk task yang due_date-nya berubah atau task baru
// Hanya dijalankan saat diperlukan, tidak setiap load halaman
if ($project_id > 0 && isset($_GET['update_priority']) && $_GET['update_priority'] == 1) {
    $tasks_query_all = "SELECT id, due_date FROM tasks WHERE project_id = $project_id AND status != 'Done'";
    $tasks_all_result = mysqli_query($conn, $tasks_query_all);
    
    while ($task_row = mysqli_fetch_assoc($tasks_all_result)) {
        $task_id_loop = $task_row['id'];
        $due_date = $task_row['due_date'];
        $new_priority = calculatePriority($due_date);
        $update_priority = "UPDATE tasks SET priority = '$new_priority' WHERE id = $task_id_loop";
        mysqli_query($conn, $update_priority);
    }
}

// Proses update status via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'update_task_status') {
    header('Content-Type: application/json');
    $id = (int)$_POST['task_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update = "UPDATE tasks SET status='$status' WHERE id=$id";
    if (mysqli_query($conn, $update)) {
        if ($status == 'Done') {
            mysqli_query($conn, "UPDATE tasks SET priority='Done' WHERE id=$id");
        } else {
            $task_data = mysqli_query($conn, "SELECT due_date FROM tasks WHERE id=$id");
            $task_row = mysqli_fetch_assoc($task_data);
            $due_date = $task_row['due_date'];
            $new_priority = calculatePriority($due_date);
            mysqli_query($conn, "UPDATE tasks SET priority='$new_priority' WHERE id=$id");
        }
        echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status']);
    }
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter status
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$tasks_result = null;
$total_pages = 1;
$stats = ['total' => 0, 'in_progress' => 0, 'done' => 0];
$total_priority = 0;

if ($project_id > 0) {
    $where = "WHERE project_id = $project_id";
    if (!empty($status_filter)) {
        $where .= " AND status = '$status_filter'";
    }
    
    $tasks_query = "SELECT t.*, 
                    GROUP_CONCAT(u.name SEPARATOR ', ') as assigned_staff,
                    GROUP_CONCAT(u.id SEPARATOR ',') as assigned_staff_ids
                    FROM tasks t
                    LEFT JOIN task_assignments ta ON t.id = ta.task_id
                    LEFT JOIN users u ON ta.user_id = u.id
                    $where 
                    GROUP BY t.id
                    ORDER BY 
                        CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END ASC,
                        t.due_date ASC";
    $tasks_result = mysqli_query($conn, $tasks_query);
    
    $total_query = "SELECT COUNT(DISTINCT t.id) as total FROM tasks t $where";
    $total_result = mysqli_query($conn, $total_query);
    $total_row = mysqli_fetch_assoc($total_result);
    $total_data = $total_row['total'];
    $total_pages = ceil($total_data / $limit);
    
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done
    FROM tasks WHERE project_id = $project_id";
    $stats_result = mysqli_query($conn, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);
    
    $medium_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM tasks WHERE project_id = $project_id AND priority = 'Medium' AND status != 'Done'");
    $medium = mysqli_fetch_assoc($medium_count);
    $high_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM tasks WHERE project_id = $project_id AND priority = 'High' AND status != 'Done'");
    $high = mysqli_fetch_assoc($high_count);
    $urgent_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM tasks WHERE project_id = $project_id AND priority = 'Urgent' AND status != 'Done'");
    $urgent = mysqli_fetch_assoc($urgent_count);
    
    $total_priority = ($medium['total'] ?? 0) + ($high['total'] ?? 0) + ($urgent['total'] ?? 0);
}

// Proses CRUD lainnya (add, edit, delete, bulk_delete) - tetap pakai POST biasa
if ($can_manage && $_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax_action'])) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $task_name = mysqli_real_escape_string($conn, trim($_POST['task_name']));
            $format = mysqli_real_escape_string($conn, $_POST['format']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
            $assigned_staff = isset($_POST['assigned_staff']) ? $_POST['assigned_staff'] : [];
            
            if ($project_id == 0) {
                $kode_project = "PRJ_" . date('Ymd_His');
                $insert_project = "INSERT INTO projects (kode, client_name, status) VALUES ('$kode_project', 'New Project', 'Planning')";
                mysqli_query($conn, $insert_project);
                $project_id_baru = mysqli_insert_id($conn);
            } else {
                $project_id_baru = $project_id;
            }
            
            $priority = "Low";
            $status = "In Progress";
            $created_by = $user_id;
            
            $insert = "INSERT INTO tasks (project_id, task_name, format, priority, status, start_date, due_date, created_by) 
                       VALUES ('$project_id_baru', '$task_name', '$format', '$priority', '$status', '$start_date', '$due_date', '$created_by')";
            
            if (mysqli_query($conn, $insert)) {
                $task_id_baru = mysqli_insert_id($conn);
                
                foreach ($assigned_staff as $staff_id) {
                    $staff_id = (int)$staff_id;
                    $insert_assign = "INSERT INTO task_assignments (task_id, user_id) VALUES ('$task_id_baru', '$staff_id')";
                    mysqli_query($conn, $insert_assign);
                }
                
                $success = "Task berhasil ditambahkan!";
                echo "<script>window.location.href='taskdetail.php?project_id=$project_id_baru';</script>";
            } else {
                $error = "Gagal menambahkan task: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'edit') {
            $id = (int)$_POST['id'];
            $task_name = mysqli_real_escape_string($conn, $_POST['task_name']);
            $format = mysqli_real_escape_string($conn, $_POST['format']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
            $assigned_staff = isset($_POST['assigned_staff']) ? $_POST['assigned_staff'] : [];
            
            $update = "UPDATE tasks SET task_name='$task_name', format='$format', start_date='$start_date', due_date='$due_date' WHERE id=$id";
            
            if (mysqli_query($conn, $update)) {
                mysqli_query($conn, "DELETE FROM task_assignments WHERE task_id=$id");
                
                foreach ($assigned_staff as $staff_id) {
                    $staff_id = (int)$staff_id;
                    $insert_assign = "INSERT INTO task_assignments (task_id, user_id) VALUES ('$id', '$staff_id')";
                    mysqli_query($conn, $insert_assign);
                }
                
                $success = "Task berhasil diupdate!";
                $task_data = mysqli_query($conn, "SELECT project_id FROM tasks WHERE id=$id");
                $task_row = mysqli_fetch_assoc($task_data);
                echo "<script>window.location.href='taskdetail.php?project_id=" . $task_row['project_id'] . "';</script>";
            } else {
                $error = "Gagal mengupdate task: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            $task_data = mysqli_query($conn, "SELECT project_id FROM tasks WHERE id=$id");
            $task_row = mysqli_fetch_assoc($task_data);
            $current_project_id = $task_row['project_id'];
            
            mysqli_query($conn, "DELETE FROM task_assignments WHERE task_id=$id");
            $delete = "DELETE FROM tasks WHERE id=$id";
            if (mysqli_query($conn, $delete)) {
                $success = "Task berhasil dihapus!";
                echo "<script>window.location.href='taskdetail.php?project_id=$current_project_id';</script>";
            } else {
                $error = "Gagal menghapus task: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'bulk_delete') {
            $task_ids = $_POST['task_ids'];
            $ids_array = explode(',', $task_ids);
            $success_count = 0;
            $current_project_id = 0;
            foreach ($ids_array as $tid) {
                $tid = (int)$tid;
                $task_data = mysqli_query($conn, "SELECT project_id FROM tasks WHERE id=$tid");
                $task_row = mysqli_fetch_assoc($task_data);
                $current_project_id = $task_row['project_id'];
                
                mysqli_query($conn, "DELETE FROM task_assignments WHERE task_id=$tid");
                $delete = "DELETE FROM tasks WHERE id=$tid";
                if (mysqli_query($conn, $delete)) {
                    $success_count++;
                }
            }
            $success = "$success_count task berhasil dihapus!";
            echo "<script>window.location.href='taskdetail.php?project_id=$current_project_id';</script>";
        }
    }
}

// Ambil daftar staff untuk dropdown assignment
$staff_query = "SELECT id, name, role FROM users ORDER BY name";
$staff_result = mysqli_query($conn, $staff_query);
$staff_list = [];
while ($staff = mysqli_fetch_assoc($staff_result)) {
    $staff_list[] = $staff;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Detail - <?php echo $project ? htmlspecialchars($project['kode']) : 'Task Manager'; ?> - Global Media Creative</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* SEMUA STYLE SAMA SEPERTI SEBELUMNYA - Tidak diubah */
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

        .project-info {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .project-info h2 {
            margin-bottom: 8px;
        }

        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-card h4 {
            font-size: 12px;
            color: #8898aa;
            margin-bottom: 5px;
        }

        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-add {
            background: #1e3c72;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-add:hover {
            background: #2a5298;
        }

        .btn-delete-task {
            background: #f5365c;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-delete-task:hover {
            background: #c82333;
        }

        .filter-box {
            display: flex;
            gap: 10px;
        }

        .filter-box select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

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

        .format-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        .format-Video { background: #e3f2fd; color: #11cdef; }
        .format-Image { background: #f0e6ff; color: #8965e0; }
        .format-Motion { background: #ffe6f0; color: #ff6b9d; }

        .priority-urgent { background: #fde8e8; color: #f5365c; font-weight: bold; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 11px; }
        .priority-high { background: #fff3e0; color: #fb6340; font-weight: bold; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 11px; }
        .priority-medium { background: #e3f2fd; color: #11cdef; font-weight: bold; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 11px; }
        .priority-low { background: #e3f5ec; color: #2dce89; font-weight: bold; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 11px; }
        .priority-done { background: #e3f5ec; color: #2dce89; font-weight: bold; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 11px; }

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

        .status-select {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            cursor: pointer;
        }

        .checkbox-col {
            width: 30px;
            text-align: center;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
            padding: 25px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f7;
        }

        .modal-header h3 {
            color: #1e3c72;
        }

        .close-modal {
            cursor: pointer;
            font-size: 24px;
            color: #8898aa;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #525f7f;
            font-size: 13px;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .staff-checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
        }

        .staff-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-bottom: 1px solid #eef2f7;
        }

        .staff-checkbox:last-child {
            border-bottom: none;
        }

        .staff-checkbox input {
            width: auto;
            margin: 0;
        }

        .staff-checkbox label {
            margin: 0;
            flex: 1;
            cursor: pointer;
        }

        .staff-role {
            font-size: 11px;
            color: #8898aa;
            margin-left: 5px;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-submit:hover {
            background: #2a5298;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e3f5ec;
            color: #2dce89;
            border: 1px solid #2dce89;
        }

        .alert-error {
            background: #fde8e8;
            color: #f5365c;
            border: 1px solid #f5365c;
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

        .assigned-staff {
            font-size: 12px;
            color: #525f7f;
            max-width: 200px;
        }

        .assigned-staff i {
            margin-right: 5px;
            color: #11cdef;
        }

        /* Loading spinner untuk AJAX */
        .status-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #1e3c72;
            border-radius: 50%;
            animation: spin 0.5s linear infinite;
            margin-left: 5px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <div class="main-content">
        <div class="top-header">
            <h1>Task Manager</h1>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <?php if ($project && $project_id > 0): ?>
        <div class="project-info">
            <h2><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($project['kode']); ?> - <?php echo htmlspecialchars($project['client_name']); ?></h2>
            <a href="project.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Project</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Task</h4>
                <div class="number" style="color: #1e3c72;"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h4>In Progress</h4>
                <div class="number" style="color: #fb6340;"><?php echo $stats['in_progress'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h4>Priority</h4>
                <div class="number" style="color: #f5365c;"><?php echo $total_priority; ?></div>
            </div>
            <div class="stat-card">
                <h4>Done</h4>
                <div class="number" style="color: #2dce89;"><?php echo $stats['done'] ?? 0; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="task-header">
            <div style="display: flex; gap: 10px;">
                <?php if ($can_manage): ?>
                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Tambah Task
                </button>
                <?php endif; ?>
                <?php if ($can_manage && $project && $project_id > 0 && $tasks_result && mysqli_num_rows($tasks_result) > 0): ?>
                <button class="btn-delete-task" onclick="openBulkDeleteModal()">
                    <i class="fas fa-trash-alt"></i> Hapus Task
                </button>
                <?php endif; ?>
            </div>
            <?php if ($project && $project_id > 0): ?>
            <div class="filter-box">
                <select onchange="location.href='?project_id=<?php echo $project_id; ?>&status='+this.value">
                    <option value="">Semua Status</option>
                    <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Done" <?php echo $status_filter == 'Done' ? 'selected' : ''; ?>>Done</option>
                </select>
                <?php if (!empty($status_filter)): ?>
                    <a href="taskdetail.php?project_id=<?php echo $project_id; ?>" class="btn-add" style="background: #8898aa;">Reset</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($project && $project_id > 0): ?>
        <div class="task-table">
            <table>
                <thead>
                    <tr>
                        <?php if ($can_manage): ?>
                        <th class="checkbox-col"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                        <?php endif; ?>
                        <th>No</th>
                        <th>Judul</th>
                        <th>Format</th>
                        <th>Start Date</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <?php if ($can_manage): ?>
                        <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($tasks_result) > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($task = mysqli_fetch_assoc($tasks_result)): ?>
                            <tr>
                                <?php if ($can_manage): ?>
                                <td class="checkbox-col"><input type="checkbox" class="task-checkbox" value="<?php echo $task['id']; ?>"></td>
                                <?php endif; ?>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <a href="infotask.php?id=<?php echo $task['id']; ?>" style="color: #1e3c72; text-decoration: none; font-weight: bold;">
                                        <?php echo htmlspecialchars($task['task_name']); ?>
                                    </a>
                                    <?php if (!empty($task['assigned_staff'])): ?>
                                        <div class="assigned-staff">
                                            <i class="fas fa-users"></i> <?php echo htmlspecialchars($task['assigned_staff']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $format_class = '';
                                    switch(strtolower($task['format'])) {
                                        case 'video': $format_class = 'format-Video'; break;
                                        case 'image': $format_class = 'format-Image'; break;
                                        case 'motion': $format_class = 'format-Motion'; break;
                                    }
                                    ?>
                                    <span class="format-badge <?php echo $format_class; ?>">
                                        <i class="fas <?php echo $task['format'] == 'Video' ? 'fa-video' : ($task['format'] == 'Image' ? 'fa-image' : 'fa-film'); ?>"></i>
                                        <?php echo $task['format']; ?>
                                    </span>
                                </td>
                                <td><?php echo $task['start_date'] ? date('d M Y', strtotime($task['start_date'])) : '-'; ?></td>
                                <td>
                                    <?php echo $task['due_date'] ? date('d M Y', strtotime($task['due_date'])) : '-'; ?>
                                    <?php if ($task['due_date'] && strtotime($task['due_date']) < time()): ?>
                                        <br><small style="color: #f5365c;">(Terlewat)</small>
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
                                    <span class="<?php echo $priority_class; ?>">
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
                                <?php if ($can_manage): ?>
                                <td>
                                    <select class="status-select" data-task-id="<?php echo $task['id']; ?>" data-current-status="<?php echo $task['status']; ?>">
                                        <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="Done" <?php echo $task['status'] == 'Done' ? 'selected' : ''; ?>>Done</option>
                                    </select>
                                    <div class="status-loading" style="display: none;"></div>
                                    <button onclick="openEditModal(<?php echo $task['id']; ?>)" style="background: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin-top: 5px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $can_manage ? '9' : '8'; ?>" style="text-align: center; padding: 50px;">
                                <i class="fas fa-tasks" style="font-size: 40px; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                Belum ada task. Klik "Tambah Task" untuk membuat task baru.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?project_id=<?php echo $project_id; ?>&page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>">« Prev</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?project_id=<?php echo $project_id; ?>&page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?project_id=<?php echo $project_id; ?>&page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="alert alert-info" style="text-align: center; padding: 50px;">
            <i class="fas fa-info-circle" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
            Silakan tambah task baru.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($can_manage): ?>
    <!-- Modal Tambah Task -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Task Baru</h3>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="" id="addForm">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Judul Task *</label>
                    <input type="text" name="task_name" placeholder="Contoh: Revisi Desain Logo" required>
                </div>
                <div class="form-group">
                    <label>Format *</label>
                    <select name="format" required>
                        <option value="Image">📷 Image</option>
                        <option value="Video">🎬 Video</option>
                        <option value="Motion">🎨 Motion</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assignment To (Pilih Staff)</label>
                    <div class="staff-checkbox-group">
                        <?php foreach ($staff_list as $staff): ?>
                        <div class="staff-checkbox">
                            <input type="checkbox" name="assigned_staff[]" value="<?php echo $staff['id']; ?>" id="staff_add_<?php echo $staff['id']; ?>">
                            <label for="staff_add_<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['name']); ?>
                                <span class="staff-role">(<?php echo htmlspecialchars($staff['role']); ?>)</span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date">
                </div>
                <div class="form-group">
                    <label>Due Date *</label>
                    <input type="date" name="due_date" required>
                </div>
                <button type="submit" class="btn-submit">Simpan Task</button>
            </form>
        </div>
    </div>

    <!-- Modal Edit Task -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Task</h3>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Judul Task</label>
                    <input type="text" name="task_name" id="edit_task_name" required>
                </div>
                <div class="form-group">
                    <label>Format *</label>
                    <select name="format" id="edit_format" required>
                        <option value="Image">📷 Image</option>
                        <option value="Video">🎬 Video</option>
                        <option value="Motion">🎨 Motion</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assignment To (Pilih Staff)</label>
                    <div class="staff-checkbox-group" id="edit_staff_group">
                        <?php foreach ($staff_list as $staff): ?>
                        <div class="staff-checkbox">
                            <input type="checkbox" name="assigned_staff[]" value="<?php echo $staff['id']; ?>" id="staff_edit_<?php echo $staff['id']; ?>">
                            <label for="staff_edit_<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['name']); ?>
                                <span class="staff-role">(<?php echo htmlspecialchars($staff['role']); ?>)</span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" id="edit_start_date">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" id="edit_due_date" required>
                </div>
                <button type="submit" class="btn-submit">Update Task</button>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Massal -->
    <div id="bulkDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Task</h3>
                <span class="close-modal" onclick="closeBulkDeleteModal()">&times;</span>
            </div>
            <form method="POST" action="" id="bulkDeleteForm">
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="task_ids" id="bulk_delete_ids">
                <p>Apakah Anda yakin ingin menghapus <strong id="bulk_delete_count"></strong> task yang dipilih?</p>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-submit" style="background: #f5365c;">Ya, Hapus</button>
                    <button type="button" class="btn-submit" onclick="closeBulkDeleteModal()" style="background: #8898aa;">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }
        
        function openEditModal(id) {
            fetch(`api/get_task.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_task_name').value = data.task_name;
                    document.getElementById('edit_format').value = data.format || 'Image';
                    document.getElementById('edit_start_date').value = data.start_date;
                    document.getElementById('edit_due_date').value = data.due_date;
                    
                    const checkboxes = document.querySelectorAll('#edit_staff_group input[type="checkbox"]');
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                    });
                    
                    if (data.assigned_staff_ids) {
                        const assignedIds = data.assigned_staff_ids.split(',');
                        assignedIds.forEach(id => {
                            const cb = document.querySelector(`#edit_staff_group input[value="${id}"]`);
                            if (cb) cb.checked = true;
                        });
                    }
                    
                    document.getElementById('editModal').classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal mengambil data task');
                });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.task-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        function openBulkDeleteModal() {
            const checkboxes = document.querySelectorAll('.task-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Pilih task yang ingin dihapus terlebih dahulu!');
                return;
            }
            
            const ids = [];
            checkboxes.forEach(checkbox => {
                ids.push(checkbox.value);
            });
            
            document.getElementById('bulk_delete_ids').value = ids.join(',');
            document.getElementById('bulk_delete_count').innerHTML = checkboxes.length;
            document.getElementById('bulkDeleteModal').classList.add('show');
        }
        
        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').classList.remove('show');
        }
        
        // AJAX untuk update status - TIDAK RELOAD HALAMAN
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const taskId = this.dataset.taskId;
                const newStatus = this.value;
                const currentStatus = this.dataset.currentStatus;
                
                if (newStatus === currentStatus) return;
                
                const parentTd = this.parentElement;
                const loadingSpinner = parentTd.querySelector('.status-loading');
                const statusBadge = parentTd.previousElementSibling.querySelector('.status-badge');
                
                // Tampilkan loading
                if (loadingSpinner) loadingSpinner.style.display = 'inline-block';
                this.disabled = true;
                
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=update_task_status&task_id=${taskId}&status=${encodeURIComponent(newStatus)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update status badge
                        if (statusBadge) {
                            const oldStatusClass = statusBadge.className;
                            const newStatusClass = oldStatusClass.replace(/status-\w+/, `status-${newStatus.replace(/ /g, '')}`);
                            statusBadge.className = newStatusClass;
                            statusBadge.innerHTML = `<i class="fas ${newStatus === 'Done' ? 'fa-check-circle' : 'fa-spinner fa-pulse'}"></i> ${newStatus}`;
                        }
                        this.dataset.currentStatus = newStatus;
                        
                        // Refresh halaman untuk update urutan dan statistik
                        setTimeout(() => {
                            location.reload();
                        }, 300);
                    } else {
                        alert('Gagal mengupdate status: ' + data.message);
                        this.value = currentStatus;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengupdate status');
                    this.value = currentStatus;
                })
                .finally(() => {
                    if (loadingSpinner) loadingSpinner.style.display = 'none';
                    this.disabled = false;
                });
            });
        });
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>