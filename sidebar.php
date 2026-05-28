<?php
// sidebar.php - File terpisah untuk sidebar
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
        <a href="wisata.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'wisata.php' ? 'active' : ''; ?>">
            <i class="fas fa-umbrella-beach"></i> Wisata
        </a>
        <a href="ulasan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'ulasan.php' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> Ulasan
        </a>
        <a href="analisis.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analisis.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Analisis
        </a>
        <a href="kalender.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kalender.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar"></i> Kalender
        </a>
        <a href="pesan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pesan.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> Pesan
        </a>
    </div>
</div>