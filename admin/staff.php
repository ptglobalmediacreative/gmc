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
$user_role = $_SESSION['role']; // Simpan role user yang login
$user_name = $_SESSION['name'];

$query_user = "SELECT * FROM users WHERE id = '$user_id'";
$result_user = mysqli_query($conn, $query_user);
$user = mysqli_fetch_assoc($result_user);

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query untuk mengambil data staff berdasarkan role
$where = "";
if ($user_role == 'Director') {
    // Director bisa melihat semua staff
    if (!empty($search)) {
        $where = " WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR role LIKE '%$search%'";
    }
    $query = "SELECT * FROM users $where ORDER BY join_date DESC LIMIT $offset, $limit";
    $total_query = "SELECT COUNT(*) as total FROM users $where";
} else {
    // Role lain hanya bisa melihat data diri sendiri
    $where = " WHERE id = $user_id";
    if (!empty($search)) {
        $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR role LIKE '%$search%')";
    }
    $query = "SELECT * FROM users $where ORDER BY join_date DESC LIMIT $offset, $limit";
    $total_query = "SELECT COUNT(*) as total FROM users $where";
}

$result = mysqli_query($conn, $query);

// Hitung total data untuk pagination
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Tambah Staff (Hanya Director)
        if ($action == 'add' && $user_role == 'Director') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = mysqli_real_escape_string($conn, $_POST['role']);
            $join_date = date('Y-m-d');
            
            $insert = "INSERT INTO users (name, email, phone, password, role, join_date) 
                       VALUES ('$name', '$email', '$phone', '$password', '$role', '$join_date')";
            if (mysqli_query($conn, $insert)) {
                $success = "Staff berhasil ditambahkan!";
                echo "<script>window.location.href='staff.php';</script>";
            } else {
                $error = "Gagal menambahkan staff: " . mysqli_error($conn);
            }
        }
        
        // Edit Staff
        elseif ($action == 'edit') {
            $id = (int)$_POST['id'];
            
            // Cek apakah user berhak mengedit data ini
            $can_edit = false;
            if ($user_role == 'Director') {
                $can_edit = true; // Director bisa edit semua
            } elseif ($id == $user_id) {
                $can_edit = true; // User bisa edit data sendiri
            }
            
            if ($can_edit) {
                if ($user_role == 'Director') {
                    // Director bisa edit semua field
                    $name = mysqli_real_escape_string($conn, $_POST['name']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                    $role = mysqli_real_escape_string($conn, $_POST['role']);
                    
                    $update = "UPDATE users SET name='$name', email='$email', phone='$phone', role='$role' WHERE id=$id";
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $update = "UPDATE users SET name='$name', email='$email', phone='$phone', password='$password', role='$role' WHERE id=$id";
                    }
                } else {
                    // Role lain hanya bisa edit password
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $update = "UPDATE users SET password='$password' WHERE id=$id";
                    } else {
                        $error = "Password harus diisi!";
                        echo "<script>window.location.href='staff.php';</script>";
                        exit();
                    }
                }
                
                if (mysqli_query($conn, $update)) {
                    $success = "Data berhasil diupdate!";
                    // Jika user mengubah password sendiri, update session? (opsional)
                    if ($id == $user_id && !empty($_POST['password'])) {
                        // Optional: update session password info jika perlu
                    }
                    echo "<script>window.location.href='staff.php';</script>";
                } else {
                    $error = "Gagal mengupdate data: " . mysqli_error($conn);
                }
            } else {
                $error = "Anda tidak memiliki akses untuk mengedit data ini!";
            }
        }
        
        // Hapus Staff (Hanya Director)
        elseif ($action == 'delete' && $user_role == 'Director') {
            $id = (int)$_POST['id'];
            // Cek jangan sampai menghapus diri sendiri
            if ($id == $_SESSION['user_id']) {
                $error = "Anda tidak dapat menghapus akun sendiri!";
            } else {
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

        .staff-header {
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

        .role-badge-small.Finance { background: #f5365c; }
        .role-badge-small.Director { background: #fb6340; }
        .role-badge-small.Designer { background: #2dce89; }
        .role-badge-small.Project_Coordinator { background: #11cdef; }
        .role-badge-small.Content_Brief { background: #5e72e4; }
        .role-badge-small.Video_Graphic { background: #8965e0; }
        .role-badge-small.Marketing { background: #f3a4b5; }

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

        .no-access {
            text-align: center;
            padding: 40px;
            color: #8898aa;
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

        .info-note {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #11cdef;
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <div class="main-content">
        <div class="top-header">
            <h1>Staff Management</h1>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
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

        <?php if ($user_role != 'Director'): ?>

        <?php endif; ?>

        <div class="staff-header">
            <div>
                <?php if ($user_role == 'Director'): ?>
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah Staff
                    </button>
                <?php endif; ?>
            </div>
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
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Join Date</th>
                        <?php if ($user_role == 'Director'): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong> 
                                    <?php if ($row['id'] == $user_id): ?>
                                        <span style="font-size: 10px; background: #e3f5ec; color: #2dce89; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">(Anda)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td>
                                    <span class="role-badge-small <?php echo str_replace(' ', '_', $row['role']); ?>">
                                        <?php echo str_replace('_', ' ', $row['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['join_date'])); ?></td>
                                <?php if ($user_role == 'Director'): ?>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['phone']); ?>', '<?php echo $row['role']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($row['id'] != $user_id): ?>
                                        <button class="btn-delete" onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                <?php elseif ($row['id'] == $user_id): ?>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick="openEditSelfModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['phone']); ?>', '<?php echo $row['role']; ?>')">
                                            <i class="fas fa-edit"></i> Ganti Password
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($user_role == 'Director') ? '7' : '6'; ?>" style="text-align: center; padding: 50px;">
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

    <?php if ($user_role == 'Director'): ?>
    <!-- Modal Tambah Staff (Hanya untuk Director) -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Staff Baru</h3>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" name="phone" placeholder="Contoh: 08123456789">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="Finance">Finance</option>
                        <option value="Director">Director</option>
                        <option value="Designer">Designer</option>
                        <option value="Project Coordinator">Project Coordinator</option>
                        <option value="Content Brief">Content Brief</option>
                        <option value="Video Graphic">Video Graphic</option>
                        <option value="Marketing">Marketing</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Simpan</button>
            </form>
        </div>
    </div>

    <!-- Modal Edit Staff (Hanya untuk Director - Full Edit) -->
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
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" name="phone" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>Password (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" required>
                        <option value="Finance">Finance</option>
                        <option value="Director">Director</option>
                        <option value="Designer">Designer</option>
                        <option value="Project Coordinator">Project Coordinator</option>
                        <option value="Content Brief">Content Brief</option>
                        <option value="Video Graphic">Video Graphic</option>
                        <option value="Marketing">Marketing</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Update</button>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Staff (Hanya untuk Director) -->
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
    <?php endif; ?>

    <!-- Modal Edit Diri Sendiri (Hanya untuk Ganti Password) - Tampil untuk semua role selain Director -->
    <div id="editSelfModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ganti Password</h3>
                <span class="close-modal" onclick="closeEditSelfModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_self_id">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" id="edit_self_name" disabled style="background: #f5f6fa;">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit_self_email" disabled style="background: #f5f6fa;">
                </div>
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" id="edit_self_phone" disabled style="background: #f5f6fa;">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" id="edit_self_role" disabled style="background: #f5f6fa;">
                </div>
                <div class="form-group">
                    <label>Password Baru *</label>
                    <input type="password" name="password" required>
                    <small style="color: #8898aa;">Minimal 6 karakter</small>
                </div>
                <button type="submit" class="btn-submit">Update Password</button>
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
        
        function openEditModal(id, name, email, phone, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function openEditSelfModal(id, name, email, phone, role) {
            document.getElementById('edit_self_id').value = id;
            document.getElementById('edit_self_name').value = name;
            document.getElementById('edit_self_email').value = email;
            document.getElementById('edit_self_phone').value = phone;
            document.getElementById('edit_self_role').value = role;
            document.getElementById('editSelfModal').classList.add('show');
        }
        
        function closeEditSelfModal() {
            document.getElementById('editSelfModal').classList.remove('show');
        }
        
        function openDeleteModal(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').innerHTML = name;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>