<?php
$currentAdminPage = basename($_SERVER['PHP_SELF']);
$schoolName = getSetting($conn, 'school_name');

// Stats for sidebar badges
$pendingAdmissions   = $conn->query("SELECT COUNT(*) as cnt FROM admissions WHERE status='pending'")->fetch_assoc()['cnt'];
$pendingScholarships = $conn->query("SELECT COUNT(*) as cnt FROM scholarship_applications WHERE status='pending'")->fetch_assoc()['cnt'];
$unreadMessages      = $conn->query("SELECT COUNT(*) as cnt FROM contact_messages WHERE is_read=0")->fetch_assoc()['cnt'];
$unpublishedResults  = $conn->query("SELECT COUNT(*) as cnt FROM result_publish WHERE is_published=0")->fetch_assoc()['cnt'];
?>
<!-- Admin Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand" data-school="<?= htmlspecialchars($schoolName) ?>">
        <img src="../assets/images/<?= getSetting($conn,'school_logo') ?: 'logo.jpg' ?>"
             alt="<?= htmlspecialchars($schoolName) ?>"
             title="<?= htmlspecialchars($schoolName) ?>"
             class="sidebar-logo"
             style="width:32px;height:32px;object-fit:contain;border-radius:5px;flex-shrink:0;"
             onerror="this.src='https://ui-avatars.com/api/?name=H&background=1a5c2a&color=fff&size=32&bold=true'">
        <div class="ms-2 sidebar-brand-text">
            <div style="font-size:13px;font-weight:700;color:white;line-height:1.2;">
                <?= htmlspecialchars($schoolName) ?>
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,0.6);">Admin Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Dashboard</div>
        <a href="dashboard.php" class="nav-item <?= $currentAdminPage==='dashboard.php'?'active':'' ?>" data-tooltip="Dashboard">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>

        <div class="nav-section">Academics</div>
        <a href="teachers.php" class="nav-item <?= $currentAdminPage==='teachers.php'?'active':'' ?>" data-tooltip="Teachers">
            <i class="fas fa-chalkboard-teacher"></i><span>Teachers</span>
        </a>
        <a href="students.php" class="nav-item <?= $currentAdminPage==='students.php'?'active':'' ?>" data-tooltip="Students">
            <i class="fas fa-user-graduate"></i><span>Students</span>
        </a>
        <a href="promote_students.php" class="nav-item <?= $currentAdminPage==='promote_students.php'?'active':'' ?>" data-tooltip="Promote Students">
            <i class="fas fa-graduation-cap"></i><span>Promote Students</span>
        </a>
        <a href="attendance_report.php" class="nav-item <?= $currentAdminPage==='attendance_report.php'?'active':'' ?>" data-tooltip="Attendance Report">
            <i class="fas fa-clipboard-list"></i><span>Attendance Report</span>
        </a>
        <a href="classes.php" class="nav-item <?= $currentAdminPage==='classes.php'?'active':'' ?>" data-tooltip="Classes & Subjects">
            <i class="fas fa-school"></i><span>Classes & Subjects</span>
        </a>

        <div class="nav-section">Examinations</div>
        <a href="results.php" class="nav-item <?= $currentAdminPage==='results.php'?'active':'' ?>" data-tooltip="Results">
            <i class="fas fa-poll-h"></i>
            <span>Results</span>
            <?php if($unpublishedResults > 0): ?>
            <span class="badge-count"><?= $unpublishedResults ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Content</div>
        <a href="hero_slider.php" class="nav-item <?= $currentAdminPage==='hero_slider.php'?'active':'' ?>" data-tooltip="Hero Slider">
            <i class="fas fa-images"></i><span>Hero Slider</span>
        </a>
        <a href="notices.php" class="nav-item <?= $currentAdminPage==='notices.php'?'active':'' ?>" data-tooltip="Notices">
            <i class="fas fa-bullhorn"></i><span>Notices</span>
        </a>
        <a href="events.php" class="nav-item <?= $currentAdminPage==='events.php'?'active':'' ?>" data-tooltip="Events">
            <i class="fas fa-calendar-alt"></i><span>Events</span>
        </a>
        <a href="gallery.php" class="nav-item <?= $currentAdminPage==='gallery.php'?'active':'' ?>" data-tooltip="Gallery">
            <i class="fas fa-photo-video"></i><span>Gallery</span>
        </a>

        <div class="nav-section">Applications</div>
        <a href="admissions.php" class="nav-item <?= $currentAdminPage==='admissions.php'?'active':'' ?>" data-tooltip="Admissions">
            <i class="fas fa-user-plus"></i>
            <span>Admissions</span>
            <?php if($pendingAdmissions > 0): ?>
            <span class="badge-count"><?= $pendingAdmissions ?></span>
            <?php endif; ?>
        </a>
        <a href="admission_settings.php" class="nav-item <?= $currentAdminPage==='admission_settings.php'?'active':'' ?>" data-tooltip="Admission Window">
            <i class="fas fa-calendar-check"></i>
            <span>Admission Window</span>
        </a>
        <a href="scholarship.php" class="nav-item <?= $currentAdminPage==='scholarship.php'?'active':'' ?>" data-tooltip="Scholarship">
            <i class="fas fa-award"></i>
            <span>Scholarship</span>
            <?php if($pendingScholarships > 0): ?>
            <span class="badge-count"><?= $pendingScholarships ?></span>
            <?php endif; ?>
        </a>
        <a href="scholarship_settings.php" class="nav-item <?= $currentAdminPage==='scholarship_settings.php'?'active':'' ?>" data-tooltip="Scholarship Window">
            <i class="fas fa-calendar-check"></i>
            <span>Scholarship Window</span>
        </a>
        <a href="messages.php" class="nav-item <?= $currentAdminPage==='messages.php'?'active':'' ?>" data-tooltip="Messages">
            <i class="fas fa-envelope"></i>
            <span>Messages</span>
            <?php if($unreadMessages > 0): ?>
            <span class="badge-count"><?= $unreadMessages ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Settings</div>
        <a href="settings.php" class="nav-item <?= $currentAdminPage==='settings.php'?'active':'' ?>" data-tooltip="School Settings">
            <i class="fas fa-cog"></i><span>School Settings</span>
        </a>
        <a href="logout.php" class="nav-item text-danger-custom" data-tooltip="Logout">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </nav>
</div>
