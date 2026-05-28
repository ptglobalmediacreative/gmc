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

// Ambil data user yang login
$user_id = $_SESSION['user_id'];
$query_user = "SELECT * FROM users WHERE id = '$user_id'";
$result_user = mysqli_query($conn, $query_user);
$user = mysqli_fetch_assoc($result_user);

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query untuk mengambil data staff
$where = "";
if (!empty($search)) {
    $where = " WHERE username LIKE '%$search%' OR full_name LIKE '%$search%' OR email LIKE '%$search%' OR role LIKE '%$search%'";
}

$query = "SELECT * FROM users $where ORDER BY created_at DESC LIMIT $offset, $limit";
$result = mysqli_query($conn, $query);

// Hitung total data untuk pagination
$total_query = "SELECT COUNT(*) as total FROM users $where";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Proses tambah/edit/hapus staff
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $role = mysqli_real_escape_string($conn, $_POST['role']);
            
            $insert = "INSERT INTO users (username, email, password, full_name, role) 
                       VALUES ('$username', '$email', '$password', '$full_name', '$role')";
            if (mysqli_query($conn, $insert)) {
                $success = "Staff berhasil ditambahkan!";
                echo "<script>window.location.href='staff.php';</script>";
            } else {
                $error = "Gagal menambahkan staff: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'edit') {
            $id = (int)$_POST['id'];
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $role = mysqli_real_escape_string($conn, $_POST['role']);
            
            $update = "UPDATE users SET username='$username', email='$email', full_name='$full_name', role='$role' WHERE id=$id";
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $update = "UPDATE users SET username='$username', email='$email', password='$password', full_name='$full_name', role='$role' WHERE id=$id";
            }
            
            if (mysqli_query($conn, $update)) {
                $success = "Staff berhasil diupdate!";
                echo "<script>window.location.href='staff.php';</script>";
            } else {
                $error = "Gagal mengupdate staff: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            $delete = "DELETE FROM users WHERE id=$id";
            if (mysqli_query($conn, $delete)) {
                $success = "Staff berhasil dihapus!";
                echo "<script>window.location.href='staff.php';</script>";
            } else {
                $error = "Gagal menghapus staff: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Global Media Creative</title>
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

        /* Staff Management */
        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-add {
            background: #1e3c72;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            background: #2a5298;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 300px;
            font-size: 14px;
        }

        .search-box button {
            padding: 10px 20px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .staff-table {
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

        .role-badge-small {
            background: #1e3c72;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .role-badge-small.admin {
            background: #f5365c;
        }

        .role-badge-small.director {
            background: #fb6340;
        }

        .role-badge-small.staff {
            background: #2dce89;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-buttons button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-edit {
            color: #11cdef;
        }

        .btn-delete {
            color: #f5365c;
        }

        /* Modal */
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

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #1e3c72;
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

        /* Pagination */
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
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <div class="main-content">
        <div class="top-header">
            <h1>Staff Management</h1>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="staff-header">
            <button class="btn-add" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Staff
            </button>
            <form method="GET" action="" class="search-box">
                <input type="text" name="search" placeholder="Cari staff..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="staff.php" class="btn-add" style="background: #8898aa;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="staff-table">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Tanggal Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <span class="role-badge-small <?php echo $row['role']; ?>">
                                        <?php echo ucfirst($row['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['username']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['full_name']); ?>', '<?php echo $row['role']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['full_name']); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 50px;">
                                <i class="fas fa-users" style="font-size: 40px; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                Belum ada data staff
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">« Prev</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah Staff -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Staff Baru</h3>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                        <option value="director">Director</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Simpan</button>
            </form>
        </div>
    </div>

    <!-- Modal Edit Staff -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Staff</h3>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>Password (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="full_name" id="edit_fullname" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                        <option value="director">Director</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Update</button>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Staff -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Staff</h3>
                <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <p>Apakah Anda yakin ingin menghapus staff <strong id="delete_name"></strong>?</p>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-submit" style="background: #f5365c;">Ya, Hapus</button>
                    <button type="button" class="btn-submit" onclick="closeDeleteModal()" style="background: #8898aa;">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }
        
        function openEditModal(id, username, email, fullname, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_fullname').value = fullname;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function openDeleteModal(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').innerHTML = name;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }
        
        // Tutup modal saat klik di luar
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>