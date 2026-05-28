<?php
require_once "config.php";
session_start();

// Cek apakah sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user yang login
$user_id = $_SESSION['user_id'];
$query_user = "SELECT * FROM users WHERE id = '$user_id'";
$result_user = mysqli_query($conn, $query_user);
$user = mysqli_fetch_assoc($result_user);

// Ambil statistik sederhana
$query_projects = "SELECT COUNT(*) as total FROM projects";
$result_projects = mysqli_query($conn, $query_projects);
$total_projects = mysqli_fetch_assoc($result_projects)['total'];

$query_users = "SELECT COUNT(*) as total FROM users";
$result_users = mysqli_query($conn, $query_users);
$total_users = mysqli_fetch_assoc($result_users)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Global Media Creative</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f6f9;
        }

        /* Navbar */
        .navbar {
            background: #1e3c72;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .navbar h2 {
            font-size: 20px;
        }

        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar .user-info span {
            font-size: 14px;
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

        /* Sidebar & Main Content */
        .container {
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            min-height: calc(100vh - 60px);
            padding: 20px 0;
        }

        .sidebar a {
            display: block;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .sidebar a:hover {
            background: #1a252f;
        }

        .sidebar a.active {
            background: #1a252f;
            border-left: 4px solid #1e3c72;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        /* Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1e3c72;
        }

        /* Welcome Section */
        .welcome-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .welcome-card h2 {
            margin-bottom: 10px;
        }

        .welcome-card p {
            opacity: 0.9;
        }

        /* Info Section */
        .info-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .info-section h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e3c72;
        }

        .info-section p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .role-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Global Media Creative - Admin Panel</h2>
        <div class="user-info">
            <span>Halo, <?php echo $_SESSION['full_name']; ?> 
                <span class="role-badge" style="background: #17a2b8;"><?php echo $_SESSION['role']; ?></span>
            </span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <a href="projects.php">📁 Projects</a>
            <a href="users.php">👥 Users</a>
            <a href="profile.php">👤 Profile</a>
            <a href="settings.php">⚙️ Settings</a>
        </div>

        <div class="main-content">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>Selamat datang, <?php echo $_SESSION['full_name']; ?>!</h2>
                <p>Anda login sebagai <strong><?php echo $_SESSION['role']; ?></strong> di panel admin Global Media Creative.</p>
                <p>📧 Email: <?php echo $_SESSION['email']; ?></p>
            </div>

            <!-- Stats Cards -->
            <div class="stats">
                <div class="card">
                    <h3>Total Projects</h3>
                    <div class="number"><?php echo $total_projects; ?></div>
                </div>
                <div class="card">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $total_users; ?></div>
                </div>
                <div class="card">
                    <h3>Login Sebagai</h3>
                    <div class="number" style="font-size: 24px;"><?php echo $_SESSION['role']; ?></div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="info-section">
                <h3>Informasi Agency</h3>
                <p><strong>Global Media Creative</strong> adalah agency creative yang bergerak di bidang:</p>
                <p>✓ Branding & Desain Grafis<br>
                   ✓ Pengembangan Website & Aplikasi<br>
                   ✓ Digital Marketing & Social Media<br>
                   ✓ Videografi & Fotografi</p>
                <p>📞 Kontak: admin@globalmediacreative.id<br>
                   🌐 Website: https://globalmediacreative.id</p>
            </div>
        </div>
    </div>
</body>
</html>