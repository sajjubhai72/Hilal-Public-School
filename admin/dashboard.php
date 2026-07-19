<?php
$pageTitle = 'Dashboard';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';
require_once 'includes/layout_top.php';

// Stats
$totalStudents       = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$totalTeachers       = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='teacher' AND status='active'")->fetch_assoc()['c'];
$totalAdmissions     = $conn->query("SELECT COUNT(*) as c FROM admissions")->fetch_assoc()['c'];
$pendingAdmissions   = $conn->query("SELECT COUNT(*) as c FROM admissions WHERE status='pending'")->fetch_assoc()['c'];
$totalScholarships   = $conn->query("SELECT COUNT(*) as c FROM scholarship_applications")->fetch_assoc()['c'];
$pendingScholarships = $conn->query("SELECT COUNT(*) as c FROM scholarship_applications WHERE status='pending'")->fetch_assoc()['c'];
$totalNotices        = $conn->query("SELECT COUNT(*) as c FROM notices WHERE is_active=1")->fetch_assoc()['c'];
$unreadMessages      = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'];
$totalClasses        = $conn->query("SELECT COUNT(*) as c FROM classes WHERE status='active'")->fetch_assoc()['c'];
$unpublishedResults  = $conn->query("SELECT COUNT(*) as c FROM result_publish WHERE is_published=0")->fetch_assoc()['c'];

// Recent admissions
$recentAdmissions = $conn->query("SELECT * FROM admissions ORDER BY submitted_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Recent notices
$recentNotices = $conn->query("SELECT * FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent messages
$recentMessages = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Welcome Banner -->
<div class="mb-4 p-4 rounded-3 text-white"
     style="background:linear-gradient(135deg, var(--primary-dark), var(--primary));">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <?php
            $todayAD = date('Y-m-d');
            $bs      = adToBS($todayAD);
            $bsDayName = date('l'); // same weekday name
            $bsStr   = $bs['day'] . ' ' . getNpMonthName($bs['month']) . ' ' . $bs['year'] . ' BS';
            $adStr   = date('l, F d, Y');
        ?>
        <div>
            <h4 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['admin_name']) ?>! 👋</h4>
            <p class="mb-0 opacity-75" style="font-size:14px;">
                <i class="fas fa-calendar-alt me-1"></i><?= $adStr ?>
                <span style="margin:0 8px;opacity:0.5;">|</span>
                <i class="fas fa-calendar me-1"></i><?= $bsStr ?>
                <span style="margin:0 6px;opacity:0.5;">&bull;</span>
                Admin Panel Overview
            </p>
        </div>
        <a href="../index.php" target="_blank" class="btn btn-light btn-sm fw-semibold">
            <i class="fas fa-external-link-alt me-1"></i>View Website
        </a>
    </div>
</div>

<!-- Stat Cards Row 1 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="students.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:var(--primary);">
                <div class="icon-box" style="background:var(--primary);"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $totalStudents ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="teachers.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#8e44ad;">
                <div class="icon-box" style="background:#8e44ad;"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $totalTeachers ?></div>
                    <div class="label">Teachers</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="admissions.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#e67e22;">
                <div class="icon-box" style="background:#e67e22;"><i class="fas fa-user-plus"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $totalAdmissions ?></div>
                    <div class="label">Admissions
                        <?php if($pendingAdmissions): ?>
                        <span class="status-badge status-pending ms-1"><?= $pendingAdmissions ?> pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="scholarship.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:var(--accent);">
                <div class="icon-box" style="background:var(--accent);"><i class="fas fa-award"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $totalScholarships ?></div>
                    <div class="label">Scholarships
                        <?php if($pendingScholarships): ?>
                        <span class="status-badge status-pending ms-1"><?= $pendingScholarships ?> pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Stat Cards Row 2 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="notices.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#27ae60;">
                <div class="icon-box" style="background:#27ae60;"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $totalNotices ?></div>
                    <div class="label">Active Notices</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="messages.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:var(--secondary);">
                <div class="icon-box" style="background:var(--secondary);"><i class="fas fa-envelope"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $unreadMessages ?></div>
                    <div class="label">Unread Messages</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="classes.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#16a085;">
                <div class="icon-box" style="background:#16a085;"><i class="fas fa-school"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $totalClasses ?></div>
                    <div class="label">Active Classes</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="results.php" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#2980b9;">
                <div class="icon-box" style="background:#2980b9;"><i class="fas fa-poll-h"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $unpublishedResults ?></div>
                    <div class="label">Unpublished Results</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Quick Actions -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h6><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h6>
    </div>
    <div class="admin-card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="teachers.php?action=add" class="btn-admin-primary">
                <i class="fas fa-user-plus"></i>Add Teacher
            </a>
            <a href="students.php?action=add" class="btn-admin-primary" style="background:#8e44ad;">
                <i class="fas fa-user-graduate"></i>Add Student
            </a>
            <a href="notices.php?action=add" class="btn-admin-primary" style="background:#27ae60;">
                <i class="fas fa-plus"></i>Post Notice
            </a>
            <a href="events.php?action=add" class="btn-admin-primary" style="background:#e67e22;">
                <i class="fas fa-calendar-plus"></i>Add Event
            </a>
            <a href="gallery.php?action=add" class="btn-admin-primary" style="background:#16a085;">
                <i class="fas fa-image"></i>Upload Photo
            </a>
            <a href="results.php" class="btn-admin-primary" style="background:#2980b9;">
                <i class="fas fa-poll-h"></i>Manage Results
            </a>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="row g-4">

    <!-- Recent Admissions -->
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h6><i class="fas fa-user-plus me-2"></i>Recent Admissions</h6>
                <a href="admissions.php" class="btn-admin-primary" style="font-size:12px;padding:5px 12px;">
                    View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recentAdmissions)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No admissions yet</td></tr>
                        <?php else: ?>
                        <?php foreach($recentAdmissions as $adm): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($adm['student_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($adm['guardian_phone']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($adm['applying_for_class']) ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($adm['submitted_at'])) ?></td>
                            <td><span class="status-badge status-<?= $adm['status'] ?>"><?= ucfirst($adm['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Messages -->
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h6><i class="fas fa-envelope me-2"></i>Recent Messages</h6>
                <a href="messages.php" class="btn-admin-primary" style="font-size:12px;padding:5px 12px;">
                    View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recentMessages)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No messages yet</td></tr>
                        <?php else: ?>
                        <?php foreach($recentMessages as $msg): ?>
                        <tr <?= !$msg['is_read'] ? 'style="background:rgba(26,92,42,0.04);font-weight:600;"' : '' ?>>
                            <td>
                                <div><?= htmlspecialchars($msg['sender_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($msg['sender_email']) ?></div>
                            </td>
                            <td style="font-size:13px;"><?= htmlspecialchars(substr($msg['subject'], 0, 25)) ?>...</td>
                            <td style="font-size:12px;"><?= date('M d', strtotime($msg['created_at'])) ?></td>
                            <td>
                                <?php if(!$msg['is_read']): ?>
                                <span class="status-badge" style="background:#cce5ff;color:#004085;">New</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/layout_bottom.php'; ?>
