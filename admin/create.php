<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// Cek apakah sudah login (opsional, untuk keamanan)
session_start();
if (!isset($_SESSION['user_id'])) {
    // Hapus comment berikut jika ingin proteksi
    // header("Location: login.php");
    // exit();
}

// Proses create user
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $join_date = date('Y-m-d');
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Cek apakah email sudah ada
    $check_query = "SELECT id FROM users WHERE email = '$email'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $message = "Email sudah terdaftar!";
        $message_type = "error";
    } else {
        $insert = "INSERT INTO users (name, email, phone, password, role, join_date) 
                   VALUES ('$name', '$email', '$phone', '$hashed_password', '$role', '$join_date')";
        
        if (mysqli_query($conn, $insert)) {
            $message = "User berhasil dibuat!";
            $message_type = "success";
            // Clear form
            $name = $email = $phone = $role = "";
        } else {
            $message = "Gagal membuat user: " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}

// Ambil daftar user untuk ditampilkan
$query = "SELECT id, name, email, phone, role, join_date FROM users ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Global Media Creative</title>
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
            padding: 40px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #1e3c72;
            margin-bottom: 30px;
            text-align: center;
        }

        /* Card Form */
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-card h2 {
            color: #1e3c72;
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 2px solid #1e3c72;
            padding-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
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

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #1e3c72;
        }

        .btn-submit {
            background: #1e3c72;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #2a5298;
        }

        /* Alert */
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

        /* Table */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eef2f7;
        }

        .table-header h3 {
            color: #1e3c72;
            font-size: 18px;
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

        .role-badge {
            background: #1e3c72;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #1e3c72;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create New User</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Create User -->
        <div class="form-card">
            <h2>Tambah User Baru</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" required>
                            <option value="Finance" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                            <option value="Director" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Director') ? 'selected' : ''; ?>>Director</option>
                            <option value="Designer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Designer') ? 'selected' : ''; ?>>Designer</option>
                            <option value="Project Coordinator" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Project Coordinator') ? 'selected' : ''; ?>>Project Coordinator</option>
                            <option value="Content Brief" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Content Brief') ? 'selected' : ''; ?>>Content Brief</option>
                            <option value="Video Graphic" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Video Graphic') ? 'selected' : ''; ?>>Video Graphic</option>
                            <option value="Marketing" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Marketing') ? 'selected' : ''; ?>>Marketing</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Simpan User
                </button>
            </form>
        </div>

        <!-- Daftar User -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-users"></i> Daftar User</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Join Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><span class="role-badge"><?php echo htmlspecialchars($row['role']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($row['join_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <i class="fas fa-database" style="font-size: 40px; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                Belum ada data user
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</body>
</html>