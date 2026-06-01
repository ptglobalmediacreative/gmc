<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";
session_start();

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'];

// Ambil data task
$task_query = "SELECT t.*, p.kode as project_kode, p.client_name 
               FROM tasks t 
               LEFT JOIN projects p ON t.project_id = p.id 
               WHERE t.id = $task_id";
$task_result = mysqli_query($conn, $task_query);
$task = mysqli_fetch_assoc($task_result);

if (!$task) {
    echo "Task tidak ditemukan";
    exit();
}

$format = $task['format']; // Video, Image, Motion

// Tentukan folder upload berdasarkan format
$upload_base_dir = "admin/uploads/";
switch(strtolower($format)) {
    case 'video':
        $upload_subdir = "video/";
        break;
    case 'motion':
        $upload_subdir = "motion/";
        break;
    default:
        $upload_subdir = "image/";
        break;
}
$target_dir = $upload_base_dir . $upload_subdir . "task_" . $task_id . "/";

// Folder untuk screenshot
$screenshot_dir = "admin/uploads/screenshot/task_" . $task_id . "/";

// Buat tabel jika belum ada
$brief_table = "CREATE TABLE IF NOT EXISTS task_briefs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    task_id INT(11) NOT NULL,
    google_slide_link TEXT,
    caption TEXT,
    ss_google_slide TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
)";
mysqli_query($conn, $brief_table);

$media_table = "CREATE TABLE IF NOT EXISTS task_media (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    task_id INT(11) NOT NULL,
    media_type ENUM('image', 'video', 'motion') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT(11),
    uploaded_by INT(11),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
)";
mysqli_query($conn, $media_table);

$status_table = "CREATE TABLE IF NOT EXISTS task_status_checklist (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    task_id INT(11) NOT NULL,
    status_key VARCHAR(100) NOT NULL,
    status_label VARCHAR(100) NOT NULL,
    is_checked TINYINT(1) DEFAULT 0,
    checked_by INT(11),
    checked_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_status (task_id, status_key)
)";
mysqli_query($conn, $status_table);

// Definisikan status checklist berdasarkan format
if ($format == 'Video') {
    $status_list = [
        'konten_brief' => 'Konten Brief (Upload Konten)',
        'revisi_konten' => 'Revisi Konten Brief',
        'shooting' => 'Shooting Video',
        'editing' => 'Editing Video (Upload Video Hasil Edit)',
        'review_1' => 'Review 1 (Konten Brief)',
        'review_2' => 'Review 2 (Project Koor & Acc Client)',
        'revisi_design' => 'Revisi Video Editing',
        'posting' => 'Posting (SEO)'
    ];
} else { // Image atau Motion
    $status_list = [
        'konten_brief' => 'Konten Brief (Upload Konten)',
        'revisi_konten' => 'Revisi Konten Brief',
        'designer' => 'Designer (Upload Design)',
        'review_1' => 'Review 1 (Konten Brief)',
        'review_2' => 'Review 2 (Project Koor & Acc Client)',
        'revisi_design' => 'Revisi Design',
        'posting' => 'Posting (SEO)'
    ];
}

// Inisialisasi status checklist untuk task ini
foreach ($status_list as $key => $label) {
    $insert_status = "INSERT IGNORE INTO task_status_checklist (task_id, status_key, status_label) VALUES ($task_id, '$key', '$label')";
    mysqli_query($conn, $insert_status);
}

// Proses upload brief
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        
        // Upload Brief
        if ($_POST['action'] == 'upload_brief') {
            $google_slide_link = mysqli_real_escape_string($conn, $_POST['google_slide_link']);
            $caption = mysqli_real_escape_string($conn, $_POST['caption']);
            $ss_google_slide = '';
            
            // Proses upload screenshot jika ada
            if (isset($_FILES['ss_google_slide_file']) && $_FILES['ss_google_slide_file']['error'] == 0) {
                // Buat folder screenshot jika belum ada
                if (!file_exists($screenshot_dir)) {
                    mkdir($screenshot_dir, 0777, true);
                }
                
                $screenshot_name = basename($_FILES['ss_google_slide_file']['name']);
                $screenshot_ext = pathinfo($screenshot_name, PATHINFO_EXTENSION);
                $screenshot_new_name = time() . '_screenshot.' . $screenshot_ext;
                $screenshot_target = $screenshot_dir . $screenshot_new_name;
                
                // Validasi file gambar
                $check = getimagesize($_FILES['ss_google_slide_file']['tmp_name']);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES['ss_google_slide_file']['tmp_name'], $screenshot_target)) {
                        $ss_google_slide = $screenshot_target;
                    }
                }
            } else {
                // Jika tidak ada upload file, gunakan input text (untuk URL)
                $ss_google_slide = mysqli_real_escape_string($conn, $_POST['ss_google_slide']);
            }
            
            $check_brief = "SELECT id FROM task_briefs WHERE task_id = $task_id";
            $brief_result = mysqli_query($conn, $check_brief);
            
            if (mysqli_num_rows($brief_result) > 0) {
                $update_brief = "UPDATE task_briefs SET google_slide_link='$google_slide_link', caption='$caption', ss_google_slide='$ss_google_slide' WHERE task_id=$task_id";
                mysqli_query($conn, $update_brief);
            } else {
                $insert_brief = "INSERT INTO task_briefs (task_id, google_slide_link, caption, ss_google_slide) VALUES ($task_id, '$google_slide_link', '$caption', '$ss_google_slide')";
                mysqli_query($conn, $insert_brief);
            }
            $success = "Brief berhasil diupload!";
            // Refresh halaman untuk menampilkan mode read-only
            echo "<script>window.location.href='infotask.php?id=$task_id';</script>";
            exit();
        }
        
        // Upload Media
        if ($_POST['action'] == 'upload_media') {
            $media_type = strtolower($format);
            
            // Buat folder jika belum ada
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $uploaded_files = [];
            foreach ($_FILES['media_files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['media_files']['error'][$key] == 0) {
                    $original_name = basename($_FILES['media_files']['name'][$key]);
                    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                    $new_filename = time() . '_' . uniqid() . '.' . $file_ext;
                    $target_file = $target_dir . $new_filename;
                    
                    // Validasi file berdasarkan tipe
                    if ($media_type == 'image') {
                        $check = getimagesize($tmp_name);
                        if ($check === false) {
                            continue;
                        }
                    } elseif ($media_type == 'video') {
                        $video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
                        if (!in_array(strtolower($file_ext), $video_extensions)) {
                            continue;
                        }
                    }
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $file_size = $_FILES['media_files']['size'][$key];
                        $relative_path = $target_file;
                        $insert_media = "INSERT INTO task_media (task_id, media_type, file_path, original_name, file_size, uploaded_by) 
                                         VALUES ($task_id, '$media_type', '$relative_path', '$original_name', $file_size, $user_id)";
                        mysqli_query($conn, $insert_media);
                        $uploaded_files[] = $original_name;
                    }
                }
            }
            if (count($uploaded_files) > 0) {
                $success = count($uploaded_files) . " file berhasil diupload ke admin/uploads/" . $upload_subdir . "task_" . $task_id . "/";
            } else {
                $error = "Gagal mengupload file. Pastikan format file sesuai.";
            }
        }
        
        // Delete Screenshot
        if ($_POST['action'] == 'delete_screenshot') {
            $get_brief = "SELECT ss_google_slide FROM task_briefs WHERE task_id = $task_id";
            $brief_result = mysqli_query($conn, $get_brief);
            if ($brief_row = mysqli_fetch_assoc($brief_result)) {
                if (!empty($brief_row['ss_google_slide']) && file_exists($brief_row['ss_google_slide']) && strpos($brief_row['ss_google_slide'], 'admin/uploads/screenshot/') !== false) {
                    unlink($brief_row['ss_google_slide']);
                }
                mysqli_query($conn, "UPDATE task_briefs SET ss_google_slide = '' WHERE task_id = $task_id");
                $success = "Screenshot berhasil dihapus!";
            }
        }
        
        // Delete Media
        if ($_POST['action'] == 'delete_media') {
            $media_id = (int)$_POST['media_id'];
            $get_media = "SELECT file_path FROM task_media WHERE id = $media_id";
            $media_result = mysqli_query($conn, $get_media);
            if ($media_row = mysqli_fetch_assoc($media_result)) {
                if (file_exists($media_row['file_path'])) {
                    unlink($media_row['file_path']);
                }
                mysqli_query($conn, "DELETE FROM task_media WHERE id = $media_id");
                $success = "File berhasil dihapus!";
            }
        }
        
        // Update Status Checklist
        if ($_POST['action'] == 'update_status') {
            $status_key = mysqli_real_escape_string($conn, $_POST['status_key']);
            $is_checked = isset($_POST['is_checked']) ? 1 : 0;
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            $update_status = "UPDATE task_status_checklist SET is_checked = $is_checked, checked_by = $user_id, checked_at = NOW(), notes = '$notes' 
                              WHERE task_id = $task_id AND status_key = '$status_key'";
            mysqli_query($conn, $update_status);
            $success = "Status berhasil diupdate!";
        }
    }
}

// Ambil data brief
$brief_query = "SELECT * FROM task_briefs WHERE task_id = $task_id";
$brief_result = mysqli_query($conn, $brief_query);
$brief = mysqli_fetch_assoc($brief_result);
$has_brief = ($brief && ($brief['google_slide_link'] || $brief['caption'] || $brief['ss_google_slide']));

// Ambil data media
$media_query = "SELECT * FROM task_media WHERE task_id = $task_id ORDER BY uploaded_at DESC";
$media_result = mysqli_query($conn, $media_query);

// Ambil data status checklist
$status_query = "SELECT * FROM task_status_checklist WHERE task_id = $task_id";
$status_result = mysqli_query($conn, $status_query);
$status_data = [];
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_data[$row['status_key']] = $row;
}

// Fungsi untuk ambil nama user
function getUserName($conn, $user_id) {
    if (!$user_id) return 'System';
    $query = "SELECT name FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['name'];
    }
    return 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['task_name']); ?> - Detail Task</title>
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
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .task-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .task-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .task-header .project-info {
            opacity: 0.9;
            font-size: 14px;
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
            margin-top: 15px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            padding: 18px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eef2f7;
            font-weight: 600;
            font-size: 16px;
            color: #1e3c72;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header i {
            margin-right: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #525f7f;
            font-size: 13px;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
        }

        .btn-primary {
            background: #1e3c72;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #2a5298;
        }

        .btn-edit {
            background: #ffc107;
            color: #1e3c72;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-edit:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #f5365c;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .media-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .media-item {
            border: 1px solid #eef2f7;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        .media-item img, .media-item video {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .media-info {
            padding: 10px;
            background: #f8f9fa;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .media-info a {
            color: #f5365c;
            text-decoration: none;
        }

        .status-checklist {
            list-style: none;
        }

        .status-item {
            padding: 15px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-check {
            width: 24px;
            margin-top: 2px;
        }

        .status-check input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .status-content {
            flex: 1;
        }

        .status-label {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .status-label.checked {
            text-decoration: line-through;
            color: #2dce89;
        }

        .status-notes {
            margin-top: 8px;
        }

        .status-notes textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 12px;
            resize: vertical;
        }

        .status-meta {
            font-size: 11px;
            color: #8898aa;
            margin-top: 5px;
        }

        .btn-status {
            background: #11cdef;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            margin-top: 5px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
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

        .format-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 15px;
        }
        .format-Video { background: #e3f2fd; color: #11cdef; }
        .format-Image { background: #f0e6ff; color: #8965e0; }
        .format-Motion { background: #ffe6f0; color: #ff6b9d; }

        .brief-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .brief-display p {
            margin-bottom: 10px;
            word-break: break-word;
        }

        .brief-display a {
            color: #1e3c72;
        }

        .brief-display img {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 10px;
        }

        .path-info {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #11cdef;
            margin-top: 10px;
        }

        .screenshot-preview {
            margin-top: 10px;
            max-width: 200px;
            position: relative;
        }

        .screenshot-preview img {
            width: 100%;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .delete-screenshot {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(245,54,92,0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
        }

        .edit-mode {
            display: none;
        }

        .edit-mode.show {
            display: block;
        }

        .view-mode {
            display: block;
        }

        .view-mode.hide {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="task-header">
            <h1>
                <?php echo htmlspecialchars($task['task_name']); ?>
                <span class="format-badge format-<?php echo $task['format']; ?>">
                    <i class="fas <?php echo $task['format'] == 'Video' ? 'fa-video' : ($task['format'] == 'Image' ? 'fa-image' : 'fa-film'); ?>"></i>
                    <?php echo $task['format']; ?>
                </span>
            </h1>
            <div class="project-info">
                <i class="fas fa-folder"></i> Project: <?php echo htmlspecialchars($task['project_kode']); ?> - <?php echo htmlspecialchars($task['client_name']); ?>
                <br>
                <i class="fas fa-calendar"></i> Due Date: <?php echo date('d M Y', strtotime($task['due_date'])); ?>
                <i class="fas fa-flag" style="margin-left: 15px;"></i> Priority: <?php echo $task['priority']; ?>
            </div>
            <a href="taskdetail.php?project_id=<?php echo $task['project_id']; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali ke Task List
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Konten Brief Section -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-file-alt"></i> Konten Brief</span>
                        <?php if ($has_brief): ?>
                        <button class="btn-edit" onclick="toggleEditBrief()">
                            <i class="fas fa-edit"></i> Edit Brief
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- View Mode - Tampilan Brief yang sudah diupload -->
                        <div id="viewBriefMode" class="view-mode <?php echo $has_brief ? '' : 'hide'; ?>">
                            <?php if ($has_brief): ?>
                            <div class="brief-display">
                                <?php if ($brief['google_slide_link']): ?>
                                <p>
                                    <i class="fab fa-google"></i> 
                                    <strong>Google Slide Link:</strong><br>
                                    <a href="<?php echo $brief['google_slide_link']; ?>" target="_blank"><?php echo $brief['google_slide_link']; ?></a>
                                </p>
                                <?php endif; ?>
                                
                                <?php if ($brief['caption']): ?>
                                <p>
                                    <i class="fas fa-quote-left"></i> 
                                    <strong>Caption:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($brief['caption'])); ?>
                                </p>
                                <?php endif; ?>
                                
                                <?php if ($brief['ss_google_slide']): ?>
                                <div>
                                    <strong><i class="fas fa-image"></i> Screenshot Google Slide:</strong>
                                    <div class="screenshot-preview">
                                        <img src="<?php echo $brief['ss_google_slide']; ?>" alt="Screenshot Google Slide">
                                        <form method="POST" onsubmit="return confirm('Hapus screenshot ini?')" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_screenshot">
                                            <button type="submit" class="delete-screenshot"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="brief-display" style="text-align: center; color: #8898aa; padding: 40px;">
                                <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                Belum ada brief. Klik tombol Edit untuk membuat brief.
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Edit Mode - Form Upload Brief -->
                        <div id="editBriefMode" class="edit-mode">
                            <form method="POST" enctype="multipart/form-data" class="brief-form">
                                <input type="hidden" name="action" value="upload_brief">
                                <div class="form-group">
                                    <label>Google Slide Link</label>
                                    <input type="url" name="google_slide_link" placeholder="https://docs.google.com/presentation/..." value="<?php echo htmlspecialchars($brief['google_slide_link'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Caption</label>
                                    <textarea name="caption" placeholder="Masukkan caption untuk konten..."><?php echo htmlspecialchars($brief['caption'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Screenshot Google Slide</label>
                                    <div class="form-row">
                                        <input type="file" name="ss_google_slide_file" accept="image/*">
                                        <span style="padding: 0 10px;">atau</span>
                                        <input type="text" name="ss_google_slide" placeholder="URL gambar (opsional)" value="<?php echo (!empty($brief['ss_google_slide']) && strpos($brief['ss_google_slide'], 'http') === 0) ? htmlspecialchars($brief['ss_google_slide']) : ''; ?>" style="flex: 1;">
                                    </div>
                                    <small style="color: #8898aa;">Upload file screenshot (JPG, PNG, GIF) atau masukkan URL gambar</small>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Brief</button>
                                    <?php if ($has_brief): ?>
                                    <button type="button" class="btn-edit" onclick="toggleEditBrief()" style="background: #6c757d; color: white;">Batal</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Upload Media Section -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-upload"></i> Upload Media 
                        (<?php echo $format == 'Video' ? 'Video' : ($format == 'Motion' ? 'Motion' : 'Image/Design'); ?>)
                    </div>
                    <div class="card-body">
                        <div class="path-info">
                            <i class="fas fa-folder"></i> File akan disimpan di: admin/uploads/<?php echo $upload_subdir; ?>task_<?php echo $task_id; ?>/
                        </div>
                        <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="upload_media">
                            <div class="form-group">
                                <label>Pilih File (bisa multiple)</label>
                                <input type="file" name="media_files[]" accept="<?php echo $format == 'Video' ? 'video/*' : 'image/*'; ?>" multiple required>
                                <small style="color: #8898aa;">
                                    <?php if ($format == 'Video'): ?>
                                        Format yang didukung: MP4, WebM, AVI, MOV
                                    <?php elseif ($format == 'Motion'): ?>
                                        Format yang didukung: MP4, GIF, JSON, AEP
                                    <?php else: ?>
                                        Format yang didukung: JPG, PNG, GIF, WebP
                                    <?php endif; ?>
                                </small>
                            </div>
                            <button type="submit" class="btn-primary"><i class="fas fa-cloud-upload-alt"></i> Upload Media</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Status Checklist Section -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-check-circle"></i> Status Pengerjaan
                    </div>
                    <div class="card-body">
                        <ul class="status-checklist">
                            <?php foreach ($status_list as $key => $label): ?>
                            <?php $status = $status_data[$key] ?? null; ?>
                            <li class="status-item">
                                <div class="status-check">
                                    <form method="POST" onchange="this.submit()">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="status_key" value="<?php echo $key; ?>">
                                        <input type="hidden" name="is_checked" value="<?php echo ($status && $status['is_checked']) ? 1 : 0; ?>">
                                        <input type="checkbox" name="is_checked_temp" value="1" <?php echo ($status && $status['is_checked']) ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    </form>
                                </div>
                                <div class="status-content">
                                    <div class="status-label <?php echo ($status && $status['is_checked']) ? 'checked' : ''; ?>">
                                        <?php echo $label; ?>
                                    </div>
                                    <form method="POST" class="status-notes">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="status_key" value="<?php echo $key; ?>">
                                        <input type="hidden" name="is_checked" value="<?php echo ($status && $status['is_checked']) ? 1 : 0; ?>">
                                        <textarea name="notes" placeholder="Tambahkan catatan..."><?php echo htmlspecialchars($status['notes'] ?? ''); ?></textarea>
                                        <button type="submit" class="btn-status"><i class="fas fa-save"></i> Simpan Catatan</button>
                                    </form>
                                    <?php if ($status && $status['checked_at'] && $status['is_checked']): ?>
                                    <div class="status-meta">
                                        <i class="fas fa-check-circle"></i> Dicentang oleh: <?php echo getUserName($conn, $status['checked_by']); ?> pada <?php echo date('d M Y H:i', strtotime($status['checked_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Media Gallery Section (Full Width) -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-photo-video"></i> Gallery Media
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($media_result) > 0): ?>
                <div class="media-gallery">
                    <?php while ($media = mysqli_fetch_assoc($media_result)): ?>
                    <div class="media-item">
                        <?php if ($media['media_type'] == 'image'): ?>
                            <img src="<?php echo $media['file_path']; ?>" alt="<?php echo htmlspecialchars($media['original_name']); ?>">
                        <?php else: ?>
                            <video controls>
                                <source src="<?php echo $media['file_path']; ?>" type="video/mp4">
                                Browser tidak support video tag.
                            </video>
                        <?php endif; ?>
                        <div class="media-info">
                            <span title="<?php echo htmlspecialchars($media['original_name']); ?>">
                                <?php echo substr($media['original_name'], 0, 20); ?>
                            </span>
                            <div>
                                <a href="<?php echo $media['file_path']; ?>" download="<?php echo $media['original_name']; ?>">
                                    <i class="fas fa-download"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus file ini?')">
                                    <input type="hidden" name="action" value="delete_media">
                                    <input type="hidden" name="media_id" value="<?php echo $media['id']; ?>">
                                    <button type="submit" style="background: none; border: none; color: #f5365c; cursor: pointer;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #8898aa; padding: 40px;">
                    <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    Belum ada media yang diupload.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleEditBrief() {
            const viewMode = document.getElementById('viewBriefMode');
            const editMode = document.getElementById('editBriefMode');
            
            if (viewMode.classList.contains('hide')) {
                viewMode.classList.remove('hide');
                editMode.classList.remove('show');
            } else {
                viewMode.classList.add('hide');
                editMode.classList.add('show');
            }
        }
        
        // Jika belum ada brief, langsung tampilkan form edit
        <?php if (!$has_brief): ?>
        document.addEventListener('DOMContentLoaded', function() {
            toggleEditBrief();
        });
        <?php endif; ?>
    </script>
</body>
</html>