<?php
// sidebar.php - File terpisah untuk sidebar
// Cek apakah session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentukan halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Global Media Creative</h2>
        <p>Dashboard Admin</p>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="staff.php" class="<?php echo $current_page == 'staff.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Staff
        </a>
        <a href="project.php" class="<?php echo ($current_page == 'project.php' || $current_page == 'taskdetail.php') ? 'active' : ''; ?>">
            <i class="fas fa-project-diagram"></i> Project
        </a>
        <a href="task.php" class="<?php echo $current_page == 'task.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks"></i> Task
        </a>
    </div>
</div>