<?php
// sidebar.php - File terpisah untuk sidebar
// Cek apakah session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Global Media Creative</h2>
        <p>Dashboard Admin</p>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="staff.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Staff
        </a>
        <a href="project.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'project.php' ? 'active' : ''; ?>">
            <i class="fas fa-project-diagram"></i> Project
        </a>
        <a href="task.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analisis.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Task
        </a>
    </div>
</div>