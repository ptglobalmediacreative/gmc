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
$user_role = $_SESSION['role'];

$query_user = "SELECT * FROM users WHERE id = '$user_id'";
$result_user = mysqli_query($conn, $query_user);
$user = mysqli_fetch_assoc($result_user);

// Buat tabel projects jika belum ada
$create_table = "CREATE TABLE IF NOT EXISTS projects (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) NOT NULL UNIQUE,
    client_name VARCHAR(255) NOT NULL,
    start_date DATE,
    end_date DATE,
    sales VARCHAR(255),
    status ENUM('Planning', 'In Progress', 'Completed', 'On Hold') DEFAULT 'Planning',
    created_by INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

mysqli_query($conn, $create_table);

// Ambil semua staff untuk pilihan sales
$staff_query = "SELECT id, name, role FROM users ORDER BY name ASC";
$staff_result = mysqli_query($conn, $staff_query);
$staff_list = [];
while ($staff = mysqli_fetch_assoc($staff_result)) {
    $staff_list[] = $staff;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search & Filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Query untuk mengambil data project
$where = "";
if (!empty($search)) {
    $where .= " WHERE (kode LIKE '%$search%' OR client_name LIKE '%$search%' OR sales LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where .= (empty($where) ? " WHERE" : " AND") . " status = '$status_filter'";
}

$query = "SELECT * FROM projects $where ORDER BY created_at DESC LIMIT $offset, $limit";
$result = mysqli_query($conn, $query);

// Hitung total data
$total_query = "SELECT COUNT(*) as total FROM projects $where";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Proses tambah/edit/hapus project (hanya Director yang bisa)
if ($user_role == 'Director' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $kode = mysqli_real_escape_string($conn, $_POST['kode']);
            $client_name = mysqli_real_escape_string($conn, $_POST['client_name']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $sales = mysqli_real_escape_string($conn, $_POST['sales']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $created_by = $_SESSION['user_id'];
            
            $insert = "INSERT INTO projects (kode, client_name, start_date, end_date, sales, status, created_by) 
                       VALUES ('$kode', '$client_name', '$start_date', '$end_date', '$sales', '$status', '$created_by')";
            if (mysqli_query($conn, $insert)) {
                $success = "Project berhasil ditambahkan!";
                echo "<script>window.location.href='project.php';</script>";
            } else {
                $error = "Gagal menambahkan project: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'edit') {
            $id = (int)$_POST['id'];
            $kode = mysqli_real_escape_string($conn, $_POST['kode']);
            $client_name = mysqli_real_escape_string($conn, $_POST['client_name']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $sales = mysqli_real_escape_string($conn, $_POST['sales']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $update = "UPDATE projects SET 
                       kode='$kode', 
                       client_name='$client_name', 
                       start_date='$start_date', 
                       end_date='$end_date', 
                       sales='$sales', 
                       status='$status' 
                       WHERE id=$id";
            
            if (mysqli_query($conn, $update)) {
                $success = "Project berhasil diupdate!";
                echo "<script>window.location.href='project.php';</script>";
            } else {
                $error = "Gagal mengupdate project: " . mysqli_error($conn);
            }
        }
        
        elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            $delete = "DELETE FROM projects WHERE id=$id";
            if (mysqli_query($conn, $delete)) {
                $success = "Project berhasil dihapus!";
                echo "<script>window.location.href='project.php';</script>";
            } else {
                $error = "Gagal menghapus project: " . mysqli_error($conn);
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
    <title>Project Management - Global Media Creative</title>
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

        .project-header {
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

        .filter-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-box select, .search-box input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box button {
            padding: 10px 20px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .project-table {
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

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .status-Planning { background: #e3f2fd; color: #11cdef; }
        .status-InProgress { background: #fff3e0; color: #fb6340; }
        .status-Completed { background: #e3f5ec; color: #2dce89; }
        .status-OnHold { background: #eef2f7; color: #8898aa; }

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

        .btn-edit { color: #11cdef; }
        .btn-delete { color: #f5365c; }

        .btn-detail { 
            background: #17a2b8; 
            color: white; 
            padding: 5px 12px; 
            border-radius: 4px; 
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-detail:hover { background: #138496; }

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
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <div class="main-content">
        <div class="top-header">
            <h1>Project Management</h1>
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

        <div class="project-header">
            <div>
                <?php if ($user_role == 'Director'): ?>
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah Project
                    </button>
                <?php endif; ?>
            </div>
            <div class="filter-box">
                <form method="GET" action="" class="search-box">
                    <input type="text" name="search" placeholder="Cari Kode/Client/Sales..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Cari</button>
                </form>
                <select onchange="location.href='?status='+this.value+'&search=<?php echo urlencode($search); ?>'">
                    <option value="">Semua Status</option>
                    <option value="Planning" <?php echo $status_filter == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                    <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="On Hold" <?php echo $status_filter == 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                </select>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="project.php" class="btn-add" style="background: #8898aa;">Reset</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="project-table">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Client</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Sales</th>
                        <th>Status</th>
                        <?php if ($user_role == 'Director'): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                        <th>Task Manager</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['kode']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                                <td><?php echo $row['start_date'] ? date('d M Y', strtotime($row['start_date'])) : '-'; ?></td>
                                <td><?php echo $row['end_date'] ? date('d M Y', strtotime($row['end_date'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($row['sales']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', $row['status']); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <?php if ($user_role == 'Director'): ?>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick="openEditModal(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['kode']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <button class="btn-detail" onclick="window.location.href='taskdetail.php?id=<?php echo $row['id']; ?>'">
                                        <i class=></i> Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($user_role == 'Director') ? '9' : '8'; ?>" style="text-align: center; padding: 50px;">
                                <i class="fas fa-folder-open" style="font-size: 40px; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                Belum ada data project
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">« Prev</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($user_role == 'Director'): ?>
    <!-- Modal Tambah Project -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Project Baru</h3>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Kode Project *</label>
                    <input type="text" name="kode" placeholder="Contoh: PRJ-001" required>
                </div>
                <div class="form-group">
                    <label>Nama Client *</label>
                    <input type="text" name="client_name" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="start_date">
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="end_date">
                </div>
                <div class="form-group">
                    <label>Sales (Person In Charge)</label>
                    <select name="sales" required>
                        <option value="">-- Pilih Sales --</option>
                        <?php foreach ($staff_list as $staff): ?>
                            <option value="<?php echo htmlspecialchars($staff['name']); ?>">
                                <?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Planning">Planning</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="On Hold">On Hold</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Simpan Project</button>
            </form>
        </div>
    </div>

    <!-- Modal Edit Project -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Project</h3>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Kode Project</label>
                    <input type="text" name="kode" id="edit_kode" required>
                </div>
                <div class="form-group">
                    <label>Nama Client</label>
                    <input type="text" name="client_name" id="edit_client_name" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="start_date" id="edit_start_date">
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="end_date" id="edit_end_date">
                </div>
                <div class="form-group">
                    <label>Sales (Person In Charge)</label>
                    <select name="sales" id="edit_sales" required>
                        <option value="">-- Pilih Sales --</option>
                        <?php foreach ($staff_list as $staff): ?>
                            <option value="<?php echo htmlspecialchars($staff['name']); ?>">
                                <?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="Planning">Planning</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="On Hold">On Hold</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Update Project</button>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Project -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Project</h3>
                <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <p>Apakah Anda yakin ingin menghapus project <strong id="delete_name"></strong>?</p>
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
        
        function openEditModal(id) {
            fetch(`api/get_project.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_kode').value = data.kode;
                    document.getElementById('edit_client_name').value = data.client_name;
                    document.getElementById('edit_start_date').value = data.start_date;
                    document.getElementById('edit_end_date').value = data.end_date;
                    document.getElementById('edit_sales').value = data.sales;
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('editModal').classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal mengambil data project');
                });
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
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>