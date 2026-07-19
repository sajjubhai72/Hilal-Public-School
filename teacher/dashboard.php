<?php
$pageTitle = 'My Dashboard';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';

// ── Get teacher's assigned classes with section ────────
$myClasses = $conn->query("
    SELECT DISTINCT
        tc.class_id,
        tc.section,
        tc.subject_id,
        tc.is_class_teacher,
        c.class_name,
        c.level,
        s.subject_name,
        s.full_marks,
        COUNT(DISTINCT st.id) as student_count
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.id
    JOIN subjects s ON tc.subject_id = s.id
    LEFT JOIN students st ON st.class_id = tc.class_id
        AND st.section = tc.section
        AND st.status = 'active'
    WHERE tc.teacher_id = $teacherId
    GROUP BY tc.class_id, tc.section, tc.subject_id
    ORDER BY c.id, tc.section, s.subject_name
")->fetch_all(MYSQLI_ASSOC);

// Group by class + section
$classGroups = [];
foreach ($myClasses as $row) {
    $key = $row['class_id'] . '_' . $row['section'];
    if (!isset($classGroups[$key])) {
        $classGroups[$key] = [
            'class_id'        => $row['class_id'],
            'class_name'      => $row['class_name'],
            'section'         => $row['section'],
            'level'           => $row['level'],
            'student_count'   => $row['student_count'],
            'is_class_teacher'=> 0,
            'subjects'        => [],
        ];
    }
    // If any row for this class+section is class teacher, mark it
    if ($row['is_class_teacher']) $classGroups[$key]['is_class_teacher'] = 1;
    $classGroups[$key]['subjects'][] = [
        'subject_id'   => $row['subject_id'],
        'subject_name' => $row['subject_name'],
        'full_marks'   => $row['full_marks'],
    ];
}

// ── Today's attendance summary ─────────────────────────
$today    = date('Y-m-d');
$todayBS  = getCurrentBS();
$todayAtt = $conn->query("
    SELECT status, COUNT(*) as cnt
    FROM attendance
    WHERE teacher_id = $teacherId AND attendance_date = '$today'
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);
$attMap = array_column($todayAtt, 'cnt', 'status');

// ── Pending exam marks (unpublished) ──────────────────
$pendingExams = $conn->query("
    SELECT DISTINCT
        e.id, e.exam_name, e.exam_type, e.academic_year,
        c.class_name, c.id as class_id,
        COALESCE(rp.is_published,0) as is_published,
        COUNT(DISTINCT r.id) as entered_count,
        (SELECT COUNT(*) FROM students st WHERE st.class_id=e.class_id AND st.status='active') as total_students
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    JOIN teacher_classes tc ON tc.class_id = e.class_id AND tc.teacher_id = $teacherId
    LEFT JOIN result_publish rp ON rp.exam_id = e.id AND rp.class_id = e.class_id
    LEFT JOIN results r ON r.exam_id = e.id AND r.entered_by = $teacherId
    WHERE COALESCE(rp.is_published,0) = 0
    GROUP BY e.id
    ORDER BY e.id DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$levelColors = [
    'primary'          => ['bg'=>'#e8f4fb','color'=>'#0284c7','dark'=>'#0369a1'],
    'lower_secondary'  => ['bg'=>'#edf7f0','color'=>'#1b6b35','dark'=>'#14532d'],
    'secondary'        => ['bg'=>'#fdf0ef','color'=>'#b5281f','dark'=>'#7c1a14'],
    'higher_secondary' => ['bg'=>'#f3eeff','color'=>'#7c3aed','dark'=>'#5b21b6'],
];

require_once 'includes/layout_top.php';
?>

<!-- Welcome Banner -->
<div class="mb-4 p-4 rounded-3 text-white"
     style="background:linear-gradient(135deg,#1a2a3a,#1b6b35);">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fas fa-hand-wave me-2" style="color:var(--accent-light);"></i>
                Welcome, <?= htmlspecialchars($teacherName) ?>!
            </h4>
            <p class="mb-0 opacity-75" style="font-size:14px;">
                <i class="fas fa-calendar-alt me-1"></i>
                <?= date('l, F d, Y') ?>
                &nbsp;&bull;&nbsp;
                <i class="fas fa-sun me-1"></i>
                <?= getNpMonthName($todayBS['month']) ?> <?= $todayBS['day'] ?>, <?= $todayBS['year'] ?> BS
            </p>
        </div>
        <a href="../index.php" target="_blank"
           class="btn btn-sm btn-light fw-semibold" style="font-size:12px;">
            <i class="fas fa-external-link-alt me-1"></i>View Website
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:var(--primary);">
            <div class="icon-box" style="background:var(--primary);"><i class="fas fa-school"></i></div>
            <div class="stat-info">
                <div class="number"><?= count($classGroups) ?></div>
                <div class="label">My Classes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#27ae60;">
            <div class="icon-box" style="background:#27ae60;"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <div class="number"><?= $attMap['present'] ?? 0 ?></div>
                <div class="label">Present Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:var(--secondary);">
            <div class="icon-box" style="background:var(--secondary);"><i class="fas fa-user-times"></i></div>
            <div class="stat-info">
                <div class="number"><?= $attMap['absent'] ?? 0 ?></div>
                <div class="label">Absent Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#e67e22;">
            <div class="icon-box" style="background:#e67e22;"><i class="fas fa-tasks"></i></div>
            <div class="stat-info">
                <div class="number"><?= count($pendingExams) ?></div>
                <div class="label">Pending Marks</div>
            </div>
        </div>
    </div>
</div>

<!-- ══ My Classes Section ══════════════════════════════ -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h6><i class="fas fa-chalkboard me-2"></i>My Assigned Classes & Sections</h6>
        <span style="font-size:13px;color:rgba(255,255,255,0.7);">
            <?= count($classGroups) ?> class-section<?= count($classGroups)!=1?'s':'' ?>
        </span>
    </div>
    <div class="admin-card-body">
        <?php if (empty($classGroups)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-exclamation-circle fa-3x mb-3 d-block opacity-40"></i>
            <h6>No classes assigned yet.</h6>
            <p style="font-size:13px;">Contact the admin to assign classes and subjects.</p>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($classGroups as $cg):
                $lc = $levelColors[$cg['level']] ?? $levelColors['primary'];
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="teacher-class-card" style="border-top:4px solid <?= $lc['color'] ?>;">

                    <!-- Card Header -->
                    <div class="tc-card-header">
                        <div class="tc-class-icon" style="background:<?= $lc['bg'] ?>;color:<?= $lc['color'] ?>;">
                            <?= mb_substr($cg['class_name'],0,2) ?>
                        </div>
                        <div>
                            <div class="tc-class-name"><?= htmlspecialchars($cg['class_name']) ?></div>
                            <div class="tc-section-badge" style="background:<?= $lc['color'] ?>;">
                                Section <?= htmlspecialchars($cg['section']) ?>
                            </div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="tc-student-count"><?= $cg['student_count'] ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">students</div>
                        </div>
                    </div>

                    <!-- Subjects -->
                    <div class="tc-subjects">
                        <?php foreach ($cg['subjects'] as $sub): ?>
                        <div class="tc-subject-pill">
                            <i class="fas fa-book-open me-1" style="font-size:10px;color:<?= $lc['color'] ?>;"></i>
                            <?= htmlspecialchars($sub['subject_name']) ?>
                            <span style="color:var(--text-muted);font-size:11px;">(FM:<?= $sub['full_marks'] ?>)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="tc-actions">
                        <?php if ($cg['is_class_teacher']): ?>
                        <a href="attendance.php?class_id=<?= $cg['class_id'] ?>&section=<?= urlencode($cg['section']) ?>"
                           class="tc-btn attendance"
                           style="background:<?= $lc['color'] ?>;">
                            <i class="fas fa-clipboard-check me-1"></i>Attendance
                        </a>
                        <?php endif; ?>
                        <a href="marks_entry.php?class_id=<?= $cg['class_id'] ?>&section=<?= urlencode($cg['section']) ?>"
                           class="tc-btn marks"
                           style="background:var(--dark);<?= !$cg['is_class_teacher'] ? 'border-radius:0;' : '' ?>">
                            <i class="fas fa-poll-h me-1"></i>Marks
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Pending Exam Marks ══════════════════════════════ -->
<?php if (!empty($pendingExams)): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-exclamation-circle me-2" style="color:#ffc53d;"></i>Pending Marks Entry</h6>
        <a href="marks_entry.php" class="btn-admin-primary" style="font-size:12px;padding:5px 12px;">
            Enter Marks
        </a>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>Exam</th><th>Class</th><th>Progress</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($pendingExams as $ex):
                    $pct = $ex['total_students'] > 0
                        ? round(($ex['entered_count'] / $ex['total_students']) * 100)
                        : 0;
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($ex['exam_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);">
                            <?= strtoupper(str_replace('_',' ',$ex['exam_type'])) ?> &bull; <?= $ex['academic_year'] ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($ex['class_name']) ?></td>
                    <td>
                        <div style="font-size:12px;margin-bottom:4px;">
                            <?= $ex['entered_count'] ?>/<?= $ex['total_students'] ?> students
                        </div>
                        <div style="background:#e0e0e0;border-radius:4px;height:6px;overflow:hidden;">
                            <div style="background:<?= $pct==100?'#27ae60':'var(--primary)' ?>;
                                        width:<?= $pct ?>%;height:100%;border-radius:4px;
                                        transition:width 0.3s;"></div>
                        </div>
                    </td>
                    <td>
                        <a href="marks_entry.php?exam_id=<?= $ex['id'] ?>&class_id=<?= $ex['class_id'] ?>"
                           class="btn-admin-primary btn-sm" style="font-size:12px;">
                            <i class="fas fa-edit me-1"></i>Enter
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ══ Card Styles ════════════════════════════════════ -->
<style>
.teacher-class-card {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    overflow: hidden;
    transition: var(--transition);
    height: 100%;
    display: flex;
    flex-direction: column;
}
.teacher-class-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-3px);
}
.tc-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 16px 12px;
}
.tc-class-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800;
    flex-shrink: 0;
}
.tc-class-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}
.tc-section-badge {
    display: inline-block;
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 20px;
    margin-top: 4px;
}
.tc-student-count {
    font-size: 22px;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}
.tc-subjects {
    padding: 8px 16px 12px;
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.tc-subject-pill {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-dark);
}
.tc-actions {
    display: flex;
    border-top: 1px solid var(--border);
}
.tc-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    font-size: 13px;
    font-weight: 600;
    color: white;
    text-decoration: none;
    transition: var(--transition);
}
.tc-btn:hover { opacity: 0.9; color: white; }
.tc-btn.attendance { border-right: 1px solid rgba(255,255,255,0.2); }
</style>

<?php require_once 'includes/layout_bottom.php'; ?>
