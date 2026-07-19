<?php
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — Teacher Panel</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" href="../assets/images/logo.jpg">
    <link rel="apple-touch-icon" href="../assets/images/logo.jpg">
    <link rel="stylesheet" href="../assets/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendors/fonts/poppins.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
    <script src="../assets/vendors/bootstrap/jquery.min.js"></script>
    <style>
        body { font-family:'Poppins',sans-serif; }
        .admin-sidebar { background: #1a2a3a; }
        .nav-item.active { background: #c0392b; border-left-color: #f0a500; }
        .nav-item:hover { border-left-color: #f0a500; }
        .sidebar-brand { background: #0f1e2a; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Teacher Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" alt="Logo"
             style="width:40px;height:40px;object-fit:contain;"
             onerror="this.style.display='none'">
        <div class="ms-2">
            <div style="font-size:12px;font-weight:700;color:white;line-height:1.2;">Teacher Portal</div>
            <div style="font-size:11px;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($teacherName) ?></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php $cp = basename($_SERVER['PHP_SELF']); ?>
        <div class="nav-section">My Panel</div>
        <a href="dashboard.php" class="nav-item <?= $cp==='dashboard.php'?'active':'' ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>

        <div class="nav-section">Academic</div>
        <a href="marks_entry.php" class="nav-item <?= $cp==='marks_entry.php'?'active':'' ?>">
            <i class="fas fa-poll-h"></i><span>Enter Marks</span>
        </a>
        <a href="attendance.php" class="nav-item <?= $cp==='attendance.php'?'active':'' ?>">
            <i class="fas fa-clipboard-check"></i><span>Attendance</span>
        </a>
        <a href="view_marks.php" class="nav-item <?= $cp==='view_marks.php'?'active':'' ?>">
            <i class="fas fa-table"></i><span>View My Entries</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="../index.php" target="_blank" class="nav-item">
            <i class="fas fa-external-link-alt"></i><span>View Website</span>
        </a>
        <a href="logout.php" class="nav-item text-danger-custom">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </nav>
</div>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <span class="page-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
        <div class="topbar-right">
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" style="cursor:pointer;">
                    <img src="../uploads/teachers/<?= htmlspecialchars($_SESSION['teacher_photo'] ?? 'default.png') ?>"
                         alt="Teacher" class="admin-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($teacherName) ?>&background=c0392b&color=fff'">
                    <span class="admin-name d-none d-md-block"><?= htmlspecialchars($teacherName) ?></span>
                    <i class="fas fa-chevron-down" style="font-size:11px;"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="admin-content">
