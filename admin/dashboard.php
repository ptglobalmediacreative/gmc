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

// Ambil total users aja
$total_users = 0;
$query_users = "SELECT COUNT(*) as total FROM users";
$result_users = mysqli_query($conn, $query_users);
if ($result_users) {
    $data = mysqli_fetch_assoc($result_users);
    $total_users = $data['total'];
}
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

        .navbar {
            background: #1e3c72;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            font-size: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
        }

        .role-badge {
            background: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

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
        }

        .sidebar a:hover {
            background: #1a252f;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

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

        .card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1e3c72;
        }

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
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Global Media Creative - Admin Panel</h2>
        <div class="user-info">
            <span>Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
                <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
            </span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="users.php">👥 Users</a>
            <a href="profile.php">👤 Profile</a>
        </div>

        <div class="main-content">
            <div class="welcome-card">
                <h2>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>Anda login sebagai <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong> di panel admin Global Media Creative.</p>
                <p>📧 Email: <?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>

            <div class="stats">
                <div class="card">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $total_users; ?></div>
                </div>
                <div class="card">
                    <h3>Role Anda</h3>
                    <div class="number" style="font-size: 24px;"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
            </div>

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