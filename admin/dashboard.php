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

// Hitung total staff (role staff, admin, director)
$query_staff = "SELECT COUNT(*) as total FROM users";
$result_staff = mysqli_query($conn, $query_staff);
$total_staff = 0;
if ($result_staff) {
    $data = mysqli_fetch_assoc($result_staff);
    $total_staff = $data['total'];
}

// Sementara untuk Total Client dan Total Project Aktif (nanti bisa diupdate)
$total_client = 0;
$total_project_aktif = 0;
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
            gap: 15px;
        }

        .user-info span {
            color: #525f7f;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
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

        .role-badge {
            background: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .welcome-card h3 {
            margin-bottom: 10px;
            font-size: 20px;
        }

        .welcome-card p {
            opacity: 0.9;
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
                <i class="fas fa-shopping-cart"></i> Pemesanan
            </a>
            <a href="#">
                <i class="fas fa-users"></i> Wisatawan
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

    </div>
</body>
</html>