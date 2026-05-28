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

// Data statistik (sementara dengan angka contoh)
$total_pemesanan = "6.29k";
$persen_pemesanan = "0.43% ↑";
$pemesanan_berhasil = "4.39k";
$persen_berhasil = "0.43% ↑";
$total_pendapatan = "Rp 800m";
$persen_pendapatan = "0.43% ↓";
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-card h4 {
            color: #8898aa;
            font-size: 13px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #1e3c72;
        }

        .stat-card .trend {
            font-size: 12px;
            margin-top: 8px;
            color: #2dce89;
        }

        .stat-card .trend.down {
            color: #f5365c;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .chart-card h3 {
            font-size: 16px;
            margin-bottom: 20px;
            color: #1e3c72;
        }

        .chart-placeholder {
            background: #f0f4f8;
            height: 200px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8898aa;
        }

        /* Bottom Section */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .top-tours {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .top-tours h3 {
            font-size: 16px;
            margin-bottom: 20px;
            color: #1e3c72;
        }

        .tour-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f7;
        }

        .tour-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .rating {
            color: #ffc107;
            font-size: 12px;
        }

        .tour-price {
            font-weight: bold;
            color: #1e3c72;
        }

        .weekly-stats {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .weekly-stats h3 {
            font-size: 16px;
            margin-bottom: 20px;
            color: #1e3c72;
        }

        .bar-chart {
            margin-top: 20px;
        }

        .bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .bar-label {
            width: 80px;
            font-size: 12px;
        }

        .bar-fill {
            flex: 1;
            height: 30px;
            background: #1e3c72;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 12px;
        }

        .role-badge {
            background: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
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

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Pemesanan</h4>
                <div class="value">6.29k</div>
                <div class="trend">0.43% ↑ (30 Hari)</div>
            </div>
            <div class="stat-card">
                <h4>Pemesanan Berhasil</h4>
                <div class="value">4.39k</div>
                <div class="trend">0.43% ↑ (30 Hari)</div>
            </div>
            <div class="stat-card">
                <h4>Total Pendapatan</h4>
                <div class="value">Rp 800m</div>
                <div class="trend down">0.43% ↓ (30 Hari)</div>
            </div>
            <div class="stat-card">
                <h4>Total Pendapatan</h4>
                <div class="value">Rp 800m</div>
                <div class="trend down">0.43% ↓ (30 Hari)</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-section">
            <div class="chart-card">
                <h3>Statistik Pemesanan Bulanan</h3>
                <div class="chart-placeholder">
                    <i class="fas fa-chart-bar"></i> Grafik Pemesanan 90%
                </div>
            </div>
            <div class="chart-card">
                <h3>Statistik Pendapatan Bulanan</h3>
                <div class="chart-placeholder">
                    <i class="fas fa-chart-line"></i> Grafik Pendapatan 80%
                </div>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="bottom-section">
            <div class="top-tours">
                <h3>Tur Terlaris</h3>
                <div class="tour-item">
                    <div class="tour-info">
                        <h4>Phang Ng</h4>
                        <div class="rating">★★★★☆ 4.0</div>
                    </div>
                    <div class="tour-price">600k Pen</div>
                </div>
                <div class="tour-item">
                    <div class="tour-info">
                        <h4>Kemanin</h4>
                        <div class="rating">★★★★☆ 4.0</div>
                    </div>
                    <div class="tour-price">Sekarang</div>
                </div>
                <div class="tour-item">
                    <div class="tour-info">
                        <h4>Bulanan</h4>
                        <div class="rating">★★★★☆ 4.0</div>
                    </div>
                    <div class="tour-price">Popular</div>
                </div>
            </div>

            <div class="weekly-stats">
                <h3>Statistik Pendapatan Mingguan</h3>
                <div class="chart-placeholder" style="height: 150px;">
                    <i class="fas fa-chart-line"></i> Grafik 800-600-400-200
                </div>
                <div class="bar-chart">
                    <div class="bar-item">
                        <span class="bar-label">Minggu Ini</span>
                        <div class="bar-fill" style="width: 70%; background: #2dce89;">Rp 100j</div>
                    </div>
                    <div class="bar-item">
                        <span class="bar-label">Minggu Lalu</span>
                        <div class="bar-fill" style="width: 85%; background: #f5365c;">Rp 125j</div>
                    </div>
                    <div class="bar-item">
                        <span class="bar-label">Jumlah</span>
                        <div class="bar-fill" style="width: 60%; background: #1e3c72;">Rp 96j</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>