<?php
// Include at the top of every admin page (after auth.php)
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — Admin Panel</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" href="../assets/images/logo.jpg">
    <link rel="apple-touch-icon" href="../assets/images/logo.jpg">
    <link rel="stylesheet" href="../assets/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendors/fonts/poppins.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
    <!-- jQuery in head so page scripts can use $ -->
    <script src="../assets/vendors/bootstrap/jquery.min.js"></script>
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php require_once 'sidebar.php'; ?>

<div class="admin-main">
    <!-- Top Bar -->
    <div class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <span class="page-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:12px;">
                <i class="fas fa-external-link-alt me-1"></i>View Website
            </a>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 cursor-pointer" data-bs-toggle="dropdown" style="cursor:pointer;">
                    <img src="../uploads/teachers/<?= htmlspecialchars($_SESSION['admin_photo'] ?? 'default.png') ?>"
                         alt="Admin" class="admin-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['admin_name'] ?? 'Admin') ?>&background=1a5c2a&color=fff'">
                    <span class="admin-name d-none d-md-block"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                    <i class="fas fa-chevron-down" style="font-size:11px;color:var(--text-muted);"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:180px;">
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="admin-content">
