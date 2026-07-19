<?php
$pageTitle = 'Manage Classes & Subjects';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Classes CRUD ──────────────────────────────────
    if ($action === 'add_class') {
        $name    = sanitize($conn, $_POST['class_name'] ?? '');
        $section = sanitize($conn, $_POST['section'] ?? 'A');
        $level   = sanitize($conn, $_POST['level'] ?? 'primary');
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO classes (class_name,section,level) VALUES (?,?,?)");
            $stmt->bind_param("sss", $name, $section, $level);
            $stmt->execute(); $stmt->close();
            $message = "Class '$name' added!"; $messageType = 'success';
        }

    } elseif ($action === 'edit_class') {
        $id      = (int)($_POST['class_id'] ?? 0);
        $name    = sanitize($conn, $_POST['class_name'] ?? '');
        $section = sanitize($conn, $_POST['section'] ?? 'A');
        $level   = sanitize($conn, $_POST['level'] ?? 'primary');
        $status  = sanitize($conn, $_POST['status'] ?? 'active');
        $stmt = $conn->prepare("UPDATE classes SET class_name=?,section=?,level=?,status=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $section, $level, $status, $id);
        $stmt->execute(); $stmt->close();
        $message = 'Class updated!'; $messageType = 'success';

    } elseif ($action === 'delete_class') {
        $id = (int)($_POST['class_id'] ?? 0);
        $inUse = $conn->query("SELECT COUNT(*) as c FROM students WHERE class_id=$id")->fetch_assoc()['c'];
        if ($inUse > 0) {
            $message = 'Cannot delete — students are assigned to this class.'; $messageType = 'danger';
        } else {
            $conn->query("DELETE FROM classes WHERE id=$id");
            $message = 'Class deleted.'; $messageType = 'warning';
        }

    // ── Subjects CRUD ─────────────────────────────────
    } elseif ($action === 'add_subject') {
        $classId     = (int)($_POST['class_id'] ?? 0);
        $subjectName = sanitize($conn, $_POST['subject_name'] ?? '');
        $subjectCode = sanitize($conn, $_POST['subject_code'] ?? '');

        // Per-exam FM/PM
        $fm1   = (int)($_POST['fm_1st_terminal']    ?? 20);
        $pm1   = (int)($_POST['pm_1st_terminal']    ?? 8);
        $fm2   = (int)($_POST['fm_2nd_terminal']    ?? 40);
        $pm2   = (int)($_POST['pm_2nd_terminal']    ?? 16);
        $fmF   = (int)($_POST['fm_final']           ?? 40);
        $pmF   = (int)($_POST['pm_final']           ?? 16);

        // Practical per exam
        $hasPr1  = (int)($_POST['has_practical_1st']   ?? 0);
        $fmPr1   = (int)($_POST['fm_practical_1st']    ?? 0);
        $hasPr2  = (int)($_POST['has_practical_2nd']   ?? 0);
        $fmPr2   = (int)($_POST['fm_practical_2nd']    ?? 0);
        $hasPrF  = (int)($_POST['has_practical_final'] ?? 0);
        $fmPrF   = (int)($_POST['fm_practical_final']  ?? 0);

        // Total full_marks = sum of all exam FMs (including practicals)
        $fullMarks = ($fm1 + $fmPr1) + ($fm2 + $fmPr2) + ($fmF + $fmPrF);
        $passMarks = (int)round($fullMarks * 0.4);

        if ($classId && $subjectName) {
            $stmt = $conn->prepare("
                INSERT INTO subjects
                (class_id, subject_name, subject_code, full_marks, pass_marks,
                 fm_1st_terminal, pm_1st_terminal, fm_2nd_terminal, pm_2nd_terminal,
                 fm_final, pm_final,
                 has_practical_1st, fm_practical_1st,
                 has_practical_2nd, fm_practical_2nd,
                 has_practical_final, fm_practical_final,
                 has_practical, practical_full_marks, practical_pass_marks)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $hasPrAny  = ($hasPr1 || $hasPr2 || $hasPrF) ? 1 : 0;
            $fmPrTotal = $fmPr1 + $fmPr2 + $fmPrF;
            $pmPrTotal = (int)round($fmPrTotal * 0.4);
            $stmt->bind_param("issiiiiiiiiiiiiiiiii",
                $classId, $subjectName, $subjectCode, $fullMarks, $passMarks,
                $fm1, $pm1, $fm2, $pm2, $fmF, $pmF,
                $hasPr1, $fmPr1, $hasPr2, $fmPr2, $hasPrF, $fmPrF,
                $hasPrAny, $fmPrTotal, $pmPrTotal
            );
            $stmt->execute(); $stmt->close();
            $message = "Subject '$subjectName' added! (Total FM: $fullMarks)";
            $messageType = 'success';
        }

    } elseif ($action === 'edit_subject') {
        $subId       = (int)($_POST['subject_id'] ?? 0);
        $subjectName = sanitize($conn, $_POST['subject_name'] ?? '');
        $subjectCode = sanitize($conn, $_POST['subject_code'] ?? '');
        $subStatus   = sanitize($conn, $_POST['status'] ?? 'active');

        $fm1   = (int)($_POST['fm_1st_terminal']    ?? 20);
        $pm1   = (int)($_POST['pm_1st_terminal']    ?? 8);
        $fm2   = (int)($_POST['fm_2nd_terminal']    ?? 40);
        $pm2   = (int)($_POST['pm_2nd_terminal']    ?? 16);
        $fmF   = (int)($_POST['fm_final']           ?? 40);
        $pmF   = (int)($_POST['pm_final']           ?? 16);

        $hasPr1  = (int)($_POST['has_practical_1st']   ?? 0);
        $fmPr1   = (int)($_POST['fm_practical_1st']    ?? 0);
        $hasPr2  = (int)($_POST['has_practical_2nd']   ?? 0);
        $fmPr2   = (int)($_POST['fm_practical_2nd']    ?? 0);
        $hasPrF  = (int)($_POST['has_practical_final'] ?? 0);
        $fmPrF   = (int)($_POST['fm_practical_final']  ?? 0);

        $fullMarks = ($fm1 + $fmPr1) + ($fm2 + $fmPr2) + ($fmF + $fmPrF);
        $passMarks = (int)round($fullMarks * 0.4);
        $hasPrAny  = ($hasPr1 || $hasPr2 || $hasPrF) ? 1 : 0;
        $fmPrTotal = $fmPr1 + $fmPr2 + $fmPrF;

        $pmPrTotal = (int)round($fmPrTotal * 0.4);
        $stmt = $conn->prepare("
            UPDATE subjects SET
                subject_name=?, subject_code=?, full_marks=?, pass_marks=?, status=?,
                fm_1st_terminal=?, pm_1st_terminal=?, fm_2nd_terminal=?, pm_2nd_terminal=?,
                fm_final=?, pm_final=?,
                has_practical_1st=?, fm_practical_1st=?,
                has_practical_2nd=?, fm_practical_2nd=?,
                has_practical_final=?, fm_practical_final=?,
                has_practical=?, practical_full_marks=?, practical_pass_marks=?
            WHERE id=?
        ");
        $stmt->bind_param("ssiisiiiiiiiiiiiiiiii",
            $subjectName, $subjectCode, $fullMarks, $passMarks, $subStatus,
            $fm1, $pm1, $fm2, $pm2, $fmF, $pmF,
            $hasPr1, $fmPr1, $hasPr2, $fmPr2, $hasPrF, $fmPrF,
            $hasPrAny, $fmPrTotal, $pmPrTotal, $subId
        );
        $stmt->execute(); $stmt->close();
        $message = 'Subject updated! (Total FM: '.$fullMarks.')';
        $messageType = 'success';

    } elseif ($action === 'delete_subject') {
        $subId = (int)($_POST['subject_id'] ?? 0);
        $conn->query("DELETE FROM subjects WHERE id=$subId");
        $message = 'Subject deleted.'; $messageType = 'warning';

    // ── Teacher Assignment ─────────────────────────────
    } elseif ($action === 'assign_teacher') {
        $teacherId  = (int)($_POST['teacher_id'] ?? 0);
        $acadYear   = sanitize($conn, $_POST['academic_year'] ?? getSetting($conn,'academic_year'));
        $pairs      = $_POST['pairs'] ?? [];

        $saved = 0;
        if ($teacherId && !empty($pairs)) {
            $stmt = $conn->prepare("INSERT INTO teacher_classes (teacher_id,class_id,section,subject_id,academic_year,is_class_teacher) VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE is_class_teacher=VALUES(is_class_teacher)");
            foreach ($pairs as $pair) {
                $cid           = (int)($pair['class_id']       ?? 0);
                $sid           = (int)($pair['subject_id']      ?? 0);
                $section       = strtoupper(sanitize($conn, $pair['section'] ?? 'A')) ?: 'A';
                $isClassTeacher= isset($pair['is_class_teacher']) ? 1 : 0;

                if ($cid && $sid) {
                    // If marking as class teacher, unset any existing class teacher for same class+section
                    if ($isClassTeacher) {
                        $conn->query("UPDATE teacher_classes SET is_class_teacher=0
                            WHERE class_id=$cid AND section='$section' AND academic_year='$acadYear'");
                    }
                    $stmt->bind_param("iisisi", $teacherId, $cid, $section, $sid, $acadYear, $isClassTeacher);
                    $stmt->execute();
                    $saved++;
                }
            }
            $stmt->close();
        }
        $message = $saved > 0 ? "Assigned $saved class-subject-section pair(s)!" : 'Nothing assigned.';
        $messageType = $saved > 0 ? 'success' : 'danger';

    } elseif ($action === 'remove_assignment') {
        $tcId = (int)($_POST['tc_id'] ?? 0);
        $conn->query("DELETE FROM teacher_classes WHERE id=$tcId");
        $message = 'Assignment removed.'; $messageType = 'warning';
    }

    // ── POST/Redirect/GET — prevent double submit on refresh ──
    $redirectClassId = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
    session_start();
    $_SESSION['classes_msg']      = $message;
    $_SESSION['classes_msg_type'] = $messageType;
    header("Location: classes.php" . ($redirectClassId ? "?class_id=$redirectClassId" : ''));
    exit();
}

// Read flash message from session
if (session_status() === PHP_SESSION_NONE) session_start();
$message     = $_SESSION['classes_msg']      ?? '';
$messageType = $_SESSION['classes_msg_type'] ?? 'success';
unset($_SESSION['classes_msg'], $_SESSION['classes_msg_type']);

$classes  = $conn->query("SELECT * FROM classes ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$teachers = $conn->query("SELECT u.id,u.full_name,td.specialization FROM users u LEFT JOIN teacher_details td ON u.id=td.user_id WHERE u.role='teacher' AND u.status='active' ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);
$acadYear = getSetting($conn, 'academic_year');

$levels = ['primary'=>'Primary','lower_secondary'=>'Lower Secondary','secondary'=>'Secondary','higher_secondary'=>'Higher Secondary (+2)'];

require_once 'includes/layout_top.php';
?>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

<!-- ══ LEFT: Classes List ═══════════════════════════ -->
<div class="col-lg-4">
    <div class="admin-card">
        <div class="admin-card-header">
            <h6><i class="fas fa-school me-2"></i>Classes (<?= count($classes) ?>)</h6>
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        <div class="admin-card-body p-0">
            <?php foreach($classes as $cls): ?>
            <?php
                $studentCount = $conn->query("SELECT COUNT(*) as c FROM students WHERE class_id={$cls['id']} AND status='active'")->fetch_assoc()['c'];
                $subjectCount = $conn->query("SELECT COUNT(*) as c FROM subjects WHERE class_id={$cls['id']} AND status='active'")->fetch_assoc()['c'];
            ?>
            <div class="class-list-item <?= isset($_GET['class_id']) && $_GET['class_id']==$cls['id'] ? 'active' : '' ?>">
                <a href="classes.php?class_id=<?= $cls['id'] ?>" class="class-list-link">
                    <div class="class-list-icon">
                        <?= substr($cls['class_name'],0,2) ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:14px;"><?= htmlspecialchars($cls['class_name']) ?></div>
                        <div style="font-size:11.5px;color:var(--text-muted);">
                            <?= $levels[$cls['level']] ?? $cls['level'] ?>
                            &bull; <?= $studentCount ?> students &bull; <?= $subjectCount ?> subjects
                        </div>
                    </div>
                    <span class="status-badge status-<?= $cls['status'] ?>" style="font-size:10px;">
                        <?= ucfirst($cls['status']) ?>
                    </span>
                </a>
                <div class="class-list-actions">
                    <button class="btn-admin-warning btn-sm edit-class-btn"
                            data-class='<?= htmlspecialchars(json_encode($cls), ENT_QUOTES) ?>'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" style="display:inline;"
                          onsubmit="return confirm('Delete class <?= htmlspecialchars($cls['class_name']) ?>?')">
                        <input type="hidden" name="action" value="delete_class">
                        <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                        <button type="submit" class="btn-admin-danger btn-sm"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══ RIGHT: Subjects + Teacher Assignments ════════ -->
<div class="col-lg-8">
<?php
$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$selectedClass   = null;
foreach($classes as $c){ if($c['id']==$selectedClassId){ $selectedClass=$c; break; } }

if($selectedClass):
    $subjects    = $conn->query("SELECT * FROM subjects WHERE class_id=$selectedClassId ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);
    $assignments = $conn->query("
        SELECT tc.id as tc_id, tc.section, tc.is_class_teacher, u.full_name as teacher_name, s.subject_name, c.class_name
        FROM teacher_classes tc
        JOIN users u ON tc.teacher_id=u.id
        JOIN subjects s ON tc.subject_id=s.id
        JOIN classes c ON tc.class_id=c.id
        WHERE tc.class_id=$selectedClassId
        ORDER BY tc.section, u.full_name
    ")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Subjects -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h6><i class="fas fa-book me-2"></i>Subjects — <?= htmlspecialchars($selectedClass['class_name']) ?></h6>
        <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fas fa-plus"></i> Add Subject
        </button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>Subject</th><th>Code</th><th>F.M</th><th>P.M</th><th>Practical</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if(empty($subjects)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No subjects. Add one!</td></tr>
                <?php else: ?>
                <?php foreach($subjects as $sub): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($sub['subject_name']) ?></td>
                    <td><?= htmlspecialchars($sub['subject_code'] ?: '—') ?></td>
                    <td><?= $sub['full_marks'] ?></td>
                    <td><?= $sub['pass_marks'] ?></td>
                    <td>
                        <?php if($sub['has_practical']): ?>
                        <span class="status-badge status-approved">Yes (<?= $sub['practical_full_marks'] ?>)</span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge status-<?= $sub['status'] ?>"><?= ucfirst($sub['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-admin-warning btn-sm edit-subject-btn"
                                    data-subject='<?= htmlspecialchars(json_encode($sub), ENT_QUOTES) ?>'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" onsubmit="return confirm('Delete subject?')">
                                <input type="hidden" name="action" value="delete_subject">
                                <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>">
                                <button type="submit" class="btn-admin-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Teacher Assignments -->
<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Assignments — <?= htmlspecialchars($selectedClass['class_name']) ?></h6>
        <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#assignTeacherModal">
            <i class="fas fa-plus"></i> Assign Teacher
        </button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Teacher</th><th>Section</th><th>Subject</th><th>Action</th></tr></thead>
            <tbody>
                <?php if(empty($assignments)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No teachers assigned yet.</td></tr>
                <?php else: ?>
                <?php foreach($assignments as $a): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($a['teacher_name']) ?></div>
                        <?php if($a['is_class_teacher']): ?>
                        <span style="background:#1a5c2a;color:white;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;display:inline-block;margin-top:2px;">
                            <i class="fas fa-star" style="font-size:8px;"></i> Class Teacher
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-active" style="font-size:11px;letter-spacing:0.5px;">
                            Sec <?= htmlspecialchars($a['section'] ?: 'A') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($a['subject_name']) ?></td>
                    <td>
                        <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" onsubmit="return confirm('Remove assignment?')">
                            <input type="hidden" name="action" value="remove_assignment">
                            <input type="hidden" name="tc_id" value="<?= $a['tc_id'] ?>">
                            <button type="submit" class="btn-admin-danger btn-sm"><i class="fas fa-unlink me-1"></i>Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="admin-card"><div class="admin-card-body text-center py-5 text-muted">
    <i class="fas fa-arrow-left fa-2x mb-3 d-block"></i>
    Select a class from the left to manage subjects and teacher assignments.
</div></div>
<?php endif; ?>
</div><!-- /.col -->
</div><!-- /.row -->

<!-- ══ MODALS ═══════════════════════════════════════ -->

<!-- Add Class -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header" style="background:var(--primary);color:white;">
            <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Class</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" class="admin-form">
            <input type="hidden" name="action" value="add_class">
            <div class="modal-body row g-3">
                <div class="col-8">
                    <label class="form-label">Class Name <span class="text-danger">*</span></label>
                    <input type="text" name="class_name" class="form-control" required placeholder="e.g. Class 11">
                </div>
                <div class="col-4">
                    <label class="form-label">Section</label>
                    <input type="text" name="section" class="form-control" value="A" maxlength="10">
                </div>
                <div class="col-12">
                    <label class="form-label">Level</label>
                    <select name="level" class="form-select">
                        <?php foreach($levels as $val=>$lbl): ?>
                        <option value="<?= $val ?>"><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Add Class</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Edit Class -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header" style="background:var(--accent);">
            <h5 class="modal-title" style="color:var(--dark);"><i class="fas fa-edit me-2"></i>Edit Class</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" class="admin-form">
            <input type="hidden" name="action" value="edit_class">
            <input type="hidden" name="class_id" id="ec_id">
            <div class="modal-body row g-3">
                <div class="col-8">
                    <label class="form-label">Class Name</label>
                    <input type="text" name="class_name" id="ec_name" class="form-control" required>
                </div>
                <div class="col-4">
                    <label class="form-label">Section</label>
                    <input type="text" name="section" id="ec_section" class="form-control">
                </div>
                <div class="col-8">
                    <label class="form-label">Level</label>
                    <select name="level" id="ec_level" class="form-select">
                        <?php foreach($levels as $val=>$lbl): ?>
                        <option value="<?= $val ?>"><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label">Status</label>
                    <select name="status" id="ec_status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Save</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Add Subject -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header" style="background:var(--primary);color:white;">
            <h5 class="modal-title">
                <i class="fas fa-book me-2"></i>
                Add Subject — <?= htmlspecialchars($selectedClass['class_name'] ?? '') ?>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" class="admin-form" id="addSubjectForm">
            <input type="hidden" name="action" value="add_subject">
            <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
            <div class="modal-body">

                <!-- Subject Name + Code -->
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" class="form-control" required placeholder="e.g. Mathematics">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g. MAT101">
                    </div>
                </div>

                <!-- Per Exam FM Table -->
                <div class="fm-table-wrapper">
                    <div class="fm-table-title">
                        <i class="fas fa-table me-2"></i>
                        Marks Distribution Per Exam
                        <span class="fm-total-display ms-3">Total FM: <strong id="addTotalFM">100</strong></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 admin-form" style="font-size:13.5px;">
                            <thead style="background:var(--light);">
                                <tr>
                                    <th style="width:22%;">Exam</th>
                                    <th>Theory FM</th>
                                    <th>Theory PM</th>
                                    <th>Has Practical?</th>
                                    <th>Practical FM</th>
                                    <th>Exam Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 1st Terminal -->
                                <tr>
                                    <td>
                                        <span class="exam-label" style="background:#e8f4fb;color:#0284c7;">
                                            <i class="fas fa-circle me-1" style="font-size:8px;"></i>1st Terminal
                                        </span>
                                    </td>
                                    <td><input type="number" name="fm_1st_terminal"    class="form-control form-control-sm fm-input" value="20" min="0" max="200" id="a_fm1"></td>
                                    <td><input type="number" name="pm_1st_terminal"    class="form-control form-control-sm" value="8"  min="0" max="200"></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input pr-toggle" type="checkbox" name="has_practical_1st" value="1"
                                                   data-target="#a_pr1" id="a_hasPr1">
                                        </div>
                                    </td>
                                    <td><input type="number" name="fm_practical_1st"   class="form-control form-control-sm fm-input pr-field" value="0" min="0" max="200" id="a_pr1" disabled></td>
                                    <td><span class="fw-bold exam-row-total" id="a_tot1">20</span></td>
                                </tr>
                                <!-- 2nd Terminal -->
                                <tr>
                                    <td>
                                        <span class="exam-label" style="background:#edf7f0;color:#1b6b35;">
                                            <i class="fas fa-circle me-1" style="font-size:8px;"></i>2nd Terminal
                                        </span>
                                    </td>
                                    <td><input type="number" name="fm_2nd_terminal"    class="form-control form-control-sm fm-input" value="40" min="0" max="200" id="a_fm2"></td>
                                    <td><input type="number" name="pm_2nd_terminal"    class="form-control form-control-sm" value="16" min="0" max="200"></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input pr-toggle" type="checkbox" name="has_practical_2nd" value="1"
                                                   data-target="#a_pr2" id="a_hasPr2">
                                        </div>
                                    </td>
                                    <td><input type="number" name="fm_practical_2nd"   class="form-control form-control-sm fm-input pr-field" value="0" min="0" max="200" id="a_pr2" disabled></td>
                                    <td><span class="fw-bold exam-row-total" id="a_tot2">40</span></td>
                                </tr>
                                <!-- Final -->
                                <tr>
                                    <td>
                                        <span class="exam-label" style="background:#fdf0ef;color:#b5281f;">
                                            <i class="fas fa-circle me-1" style="font-size:8px;"></i>Final Exam
                                        </span>
                                    </td>
                                    <td><input type="number" name="fm_final"           class="form-control form-control-sm fm-input" value="40" min="0" max="200" id="a_fmF"></td>
                                    <td><input type="number" name="pm_final"           class="form-control form-control-sm" value="16" min="0" max="200"></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input pr-toggle" type="checkbox" name="has_practical_final" value="1"
                                                   data-target="#a_prF" id="a_hasPrF">
                                        </div>
                                    </td>
                                    <td><input type="number" name="fm_practical_final" class="form-control form-control-sm fm-input pr-field" value="0" min="0" max="200" id="a_prF" disabled></td>
                                    <td><span class="fw-bold exam-row-total" id="a_totF">40</span></td>
                                </tr>
                                <!-- Total Row -->
                                <tr style="background:var(--light);font-weight:700;">
                                    <td colspan="5" class="text-end">Grand Total Full Marks:</td>
                                    <td><span class="text-primary-custom fw-bold" id="a_grandTotal">100</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-muted" style="font-size:12px;">
                        <i class="fas fa-info-circle me-1"></i>
                        Grand Total is auto-calculated. Theory FM + Practical FM per exam must add up correctly.
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-admin-primary">
                    <i class="fas fa-save me-1"></i>Add Subject
                </button>
            </div>
        </form>
    </div></div>
</div>

<!-- Edit Subject -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header" style="background:var(--accent);">
            <h5 class="modal-title" style="color:var(--dark);">
                <i class="fas fa-edit me-2"></i>Edit Subject
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" class="admin-form">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="subject_id" id="esub_id">
            <div class="modal-body">

                <!-- Name + Code + Status -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" id="esub_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" id="esub_code" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" id="esub_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Per Exam FM Table -->
                <div class="fm-table-wrapper">
                    <div class="fm-table-title">
                        <i class="fas fa-table me-2"></i>
                        Marks Distribution Per Exam
                        <span class="fm-total-display ms-3">Total FM: <strong id="eTotalFM">100</strong></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 admin-form" style="font-size:13.5px;">
                            <thead style="background:var(--light);">
                                <tr>
                                    <th style="width:22%;">Exam</th>
                                    <th>Theory FM</th>
                                    <th>Theory PM</th>
                                    <th>Has Practical?</th>
                                    <th>Practical FM</th>
                                    <th>Exam Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 1st Terminal -->
                                <tr>
                                    <td>
                                        <span class="exam-label" style="background:#e8f4fb;color:#0284c7;">
                                            <i class="fas fa-circle me-1" style="font-size:8px;"></i>1st Terminal
                                        </span>
                                    </td>
                                    <td><input type="number" name="fm_1st_terminal"    id="e_fm1"   class="form-control form-control-sm efm-input" min="0" max="200"></td>
                                    <td><input type="number" name="pm_1st_terminal"    id="e_pm1"   class="form-control form-control-sm" min="0" max="200"></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input epr-toggle" type="checkbox" name="has_practical_1st" value="1"
                                                   data-target="#e_pr1" id="e_hasPr1">
                                        </div>
                                    </td>
                                    <td><input type="number" name="fm_practical_1st"   id="e_pr1"   class="form-control form-control-sm efm-input epr-field" min="0" max="200" disabled></td>
                                    <td><span class="fw-bold erow-total" id="e_tot1">0</span></td>
                                </tr>
                                <!-- 2nd Terminal -->
                                <tr>
                                    <td>
                                        <span class="exam-label" style="background:#edf7f0;color:#1b6b35;">
                                            <i class="fas fa-circle me-1" style="font-size:8px;"></i>2nd Terminal
                                        </span>
                                    </td>
                                    <td><input type="number" name="fm_2nd_terminal"    id="e_fm2"   class="form-control form-control-sm efm-input" min="0" max="200"></td>
                                    <td><input type="number" name="pm_2nd_terminal"    id="e_pm2"   class="form-control form-control-sm" min="0" max="200"></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input epr-toggle" type="checkbox" name="has_practical_2nd" value="1"
                                                   data-target="#e_pr2" id="e_hasPr2">
                                        </div>
                                    </td>
                                    <td><input type="number" name="fm_practical_2nd"   id="e_pr2"   class="form-control form-control-sm efm-input epr-field" min="0" max="200" disabled></td>
                                    <td><span class="fw-bold erow-total" id="e_tot2">0</span></td>
                                </tr>
                                <!-- Final -->
                                <tr>
                                    <td>
                                        <span class="exam-label" style="background:#fdf0ef;color:#b5281f;">
                                            <i class="fas fa-circle me-1" style="font-size:8px;"></i>Final Exam
                                        </span>
                                    </td>
                                    <td><input type="number" name="fm_final"           id="e_fmF"   class="form-control form-control-sm efm-input" min="0" max="200"></td>
                                    <td><input type="number" name="pm_final"           id="e_pmF"   class="form-control form-control-sm" min="0" max="200"></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input epr-toggle" type="checkbox" name="has_practical_final" value="1"
                                                   data-target="#e_prF" id="e_hasPrF">
                                        </div>
                                    </td>
                                    <td><input type="number" name="fm_practical_final" id="e_prF"   class="form-control form-control-sm efm-input epr-field" min="0" max="200" disabled></td>
                                    <td><span class="fw-bold erow-total" id="e_totF">0</span></td>
                                </tr>
                                <!-- Grand Total -->
                                <tr style="background:var(--light);font-weight:700;">
                                    <td colspan="5" class="text-end">Grand Total Full Marks:</td>
                                    <td><span class="text-primary-custom fw-bold" id="e_grandTotal">0</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-muted" style="font-size:12px;">
                        <i class="fas fa-info-circle me-1"></i>
                        Grand Total = sum of all theory + practical marks across 3 exams.
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-admin-primary">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div></div>
</div>

<!-- Assign Teacher — Multiple Classes/Subjects -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header" style="background:var(--primary);color:white;">
            <h5 class="modal-title"><i class="fas fa-chalkboard-teacher me-2"></i>Assign Teacher to Classes & Subjects</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="classes.php?class_id=<?= $selectedClassId ?>" class="admin-form" id="assignTeacherForm">
            <input type="hidden" name="action" value="assign_teacher">
            <div class="modal-body">

                <!-- Teacher Select with Search -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="at_teacher" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['full_name']) ?>
                                <?= $t['specialization'] ? ' — '.$t['specialization'] : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?= $acadYear ?>">
                    </div>
                </div>

                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="form-label fw-bold mb-0">
                        <i class="fas fa-list me-1 text-primary-custom"></i>
                        Class — Section — Subject Pairs <span class="text-danger">*</span>
                    </label>
                    <button type="button" class="btn-admin-success btn-sm" id="addPairRow">
                        <i class="fas fa-plus me-1"></i>Add Row
                    </button>
                </div>

                <!-- Column headers hint -->
                <div class="row g-2 mb-1" style="font-size:11.5px;font-weight:700;color:var(--text-muted);padding:0 4px;">
                    <div class="col-md-3">Class</div>
                    <div class="col-md-2">Section</div>
                    <div class="col-md-3">Subject</div>
                    <div class="col-md-2">Class Teacher?</div>
                    <div class="col-md-2"></div>
                </div>

                <!-- Pair Rows -->
                <div id="pairRows">
                    <!-- Row 1 (default) -->
                    <div class="pair-row row g-2 mb-2 align-items-center">
                        <div class="col-md-4">
                            <select name="pairs[0][class_id]" class="form-select pair-class-select" data-row="0">
                                <option value="">-- Select Class --</option>
                                <?php foreach($classes as $cls): ?>
                                <option value="<?= $cls['id'] ?>" <?= $cls['id']==$selectedClassId?'selected':'' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="pairs[0][section]" class="form-select pair-section-field">
                                <?php
                                $initSecs = $conn->query("SELECT DISTINCT section FROM students WHERE class_id=$selectedClassId ORDER BY section")->fetch_all(MYSQLI_ASSOC);
                                $initSecList = array_column($initSecs, 'section');
                                if (empty($initSecList)) $initSecList = ['A'];
                                foreach($initSecList as $sec):
                                ?>
                                <option value="<?= $sec ?>"><?= $sec ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="pairs[0][subject_id]" class="form-select pair-subject-select" data-row="0">
                                <option value="">-- Select Subject --</option>
                                <?php
                                $initSubs = $conn->query("SELECT * FROM subjects WHERE class_id=$selectedClassId AND status='active' ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);
                                foreach($initSubs as $sb):
                                ?>
                                <option value="<?= $sb['id'] ?>"><?= htmlspecialchars($sb['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-center">
                            <label class="d-flex align-items-center justify-content-center gap-1" style="cursor:pointer;font-size:12px;">
                                <input type="checkbox" name="pairs[0][is_class_teacher]" value="1"
                                       class="form-check-input mt-0" style="width:18px;height:18px;">
                                <span style="color:var(--primary);font-weight:600;">CT</span>
                            </label>
                        </div>
                        <div class="col-md-2 text-center">
                            <button type="button" class="btn-admin-danger btn-sm remove-pair-row" style="width:36px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Info note -->
                <div class="mt-3 p-3 rounded-2" style="background:var(--primary-soft);font-size:13px;">
                    <i class="fas fa-info-circle me-1 text-primary-custom"></i>
                    A teacher can teach <strong>different subjects in different classes</strong>.
                    Add one row per class-subject combination.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-admin-primary">
                    <i class="fas fa-link me-1"></i>Assign All
                </button>
            </div>
        </form>
    </div></div>
</div>

<style>
.class-list-item { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--border); transition:var(--transition); }
.class-list-item:hover, .class-list-item.active { background:var(--primary-soft); }
.class-list-item:last-child { border-bottom:none; }
.class-list-link { display:flex; align-items:center; gap:10px; flex-grow:1; text-decoration:none; color:var(--text-dark); }
.class-list-icon { width:38px; height:38px; background:var(--primary); color:white; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0; }
.class-list-actions { display:flex; gap:4px; flex-shrink:0; }
/* FM Table styles */
.fm-table-wrapper { background:var(--light); border-radius:var(--radius); border:1px solid var(--border); overflow:hidden; }
.fm-table-title { padding:12px 16px; font-weight:700; font-size:14px; color:var(--text-dark); background:white; border-bottom:1px solid var(--border); display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.fm-total-display { font-size:13px; color:var(--text-muted); }
.exam-label { display:inline-flex; align-items:center; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; }
.fm-input { width:80px !important; display:inline-block; }
</style>

<script>
// All classes and their subjects for dynamic loading
const allClasses = <?php
    $allClassesData = [];
    foreach($classes as $cls) {
        $subs = $conn->query("SELECT id, subject_name FROM subjects WHERE class_id={$cls['id']} AND status='active' ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);
        // Get distinct sections for this class from students table
        $secs = $conn->query("SELECT DISTINCT section FROM students WHERE class_id={$cls['id']} ORDER BY section")->fetch_all(MYSQLI_ASSOC);
        $secList = array_column($secs, 'section');
        // Also include class default section if no students yet
        if (empty($secList) && $cls['section']) $secList[] = $cls['section'];
        $allClassesData[] = [
            'id'       => $cls['id'],
            'name'     => $cls['class_name'],
            'subjects' => $subs,
            'sections' => $secList,
        ];
    }
    echo json_encode($allClassesData);
?>;

let pairRowCount = 1;

function buildClassOptions(selectedClassId) {
    let html = '<option value="">-- Select Class --</option>';
    allClasses.forEach(cls => {
        const sel = cls.id == selectedClassId ? 'selected' : '';
        html += `<option value="${cls.id}" ${sel}>${cls.name}</option>`;
    });
    return html;
}

function buildSubjectOptions(classId, selectedSubId = 0) {
    const cls = allClasses.find(c => c.id == classId);
    if (!cls || !cls.subjects.length) return '<option value="">-- No subjects found --</option>';
    let html = '<option value="">-- Select Subject --</option>';
    cls.subjects.forEach(s => {
        const sel = s.id == selectedSubId ? 'selected' : '';
        html += `<option value="${s.id}" ${sel}>${s.subject_name}</option>`;
    });
    return html;
}

function buildSectionOptions(classId, selectedSec = 'A') {
    const cls = allClasses.find(c => c.id == classId);
    const secs = (cls && cls.sections && cls.sections.length) ? cls.sections : ['A'];
    let html = '';
    secs.forEach(s => {
        const sel = s === selectedSec ? 'selected' : '';
        html += `<option value="${s}" ${sel}>${s}</option>`;
    });
    return html;
}

$(document).ready(function(){

    // Edit class
    $(document).on('click','.edit-class-btn',function(){
        const c = $(this).data('class');
        $('#ec_id').val(c.id); $('#ec_name').val(c.class_name);
        $('#ec_section').val(c.section); $('#ec_level').val(c.level);
        $('#ec_status').val(c.status);
        $('#editClassModal').modal('show');
    });

    // Edit subject — populate modal with per-exam FM data
    $(document).on('click','.edit-subject-btn',function(){
        const s = $(this).data('subject');
        $('#esub_id').val(s.id);
        $('#esub_name').val(s.subject_name);
        $('#esub_code').val(s.subject_code || '');
        $('#esub_status').val(s.status);

        // Per-exam theory FM/PM
        $('#e_fm1').val(s.fm_1st_terminal   || 20);
        $('#e_pm1').val(s.pm_1st_terminal   || 8);
        $('#e_fm2').val(s.fm_2nd_terminal   || 40);
        $('#e_pm2').val(s.pm_2nd_terminal   || 16);
        $('#e_fmF').val(s.fm_final          || 40);
        $('#e_pmF').val(s.pm_final          || 16);

        // Practical 1st
        if(s.has_practical_1st == 1){
            $('#e_hasPr1').prop('checked', true);
            $('#e_pr1').val(s.fm_practical_1st || 0).prop('disabled', false);
        } else {
            $('#e_hasPr1').prop('checked', false);
            $('#e_pr1').val(0).prop('disabled', true);
        }
        // Practical 2nd
        if(s.has_practical_2nd == 1){
            $('#e_hasPr2').prop('checked', true);
            $('#e_pr2').val(s.fm_practical_2nd || 0).prop('disabled', false);
        } else {
            $('#e_hasPr2').prop('checked', false);
            $('#e_pr2').val(0).prop('disabled', true);
        }
        // Practical Final
        if(s.has_practical_final == 1){
            $('#e_hasPrF').prop('checked', true);
            $('#e_prF').val(s.fm_practical_final || 0).prop('disabled', false);
        } else {
            $('#e_hasPrF').prop('checked', false);
            $('#e_prF').val(0).prop('disabled', true);
        }
        calcEditTotals();
        $('#editSubjectModal').modal('show');
    });

    // ── ADD modal: FM auto-calculate ─────────────────
    function calcAddTotals() {
        const fm1  = parseInt($('#a_fm1').val())  || 0;
        const pr1  = parseInt($('#a_pr1').val())  || 0;
        const fm2  = parseInt($('#a_fm2').val())  || 0;
        const pr2  = parseInt($('#a_pr2').val())  || 0;
        const fmF  = parseInt($('#a_fmF').val())  || 0;
        const prF  = parseInt($('#a_prF').val())  || 0;
        $('#a_tot1').text(fm1 + pr1);
        $('#a_tot2').text(fm2 + pr2);
        $('#a_totF').text(fmF + prF);
        const grand = (fm1+pr1) + (fm2+pr2) + (fmF+prF);
        $('#a_grandTotal').text(grand);
        $('#addTotalFM').text(grand);
    }
    $('#addSubjectModal').on('input', '.fm-input', calcAddTotals);
    calcAddTotals();

    // ADD modal practical toggles
    $(document).on('change', '#addSubjectModal .pr-toggle', function(){
        const target = $(this).data('target');
        if($(this).is(':checked')){
            $(target).prop('disabled', false).val(0);
        } else {
            $(target).prop('disabled', true).val(0);
        }
        calcAddTotals();
    });

    // ── EDIT modal: FM auto-calculate ────────────────
    function calcEditTotals() {
        const fm1  = parseInt($('#e_fm1').val())  || 0;
        const pr1  = parseInt($('#e_pr1').val())  || 0;
        const fm2  = parseInt($('#e_fm2').val())  || 0;
        const pr2  = parseInt($('#e_pr2').val())  || 0;
        const fmF  = parseInt($('#e_fmF').val())  || 0;
        const prF  = parseInt($('#e_prF').val())  || 0;
        $('#e_tot1').text(fm1 + pr1);
        $('#e_tot2').text(fm2 + pr2);
        $('#e_totF').text(fmF + prF);
        const grand = (fm1+pr1) + (fm2+pr2) + (fmF+prF);
        $('#e_grandTotal').text(grand);
        $('#eTotalFM').text(grand);
    }
    $('#editSubjectModal').on('input', '.efm-input', calcEditTotals);

    // EDIT modal practical toggles
    $(document).on('change', '#editSubjectModal .epr-toggle', function(){
        const target = $(this).data('target');
        if($(this).is(':checked')){
            $(target).prop('disabled', false).val(0);
        } else {
            $(target).prop('disabled', true).val(0);
        }
        calcEditTotals();
    });
    $(document).on('change', '.pair-class-select', function(){
        const row   = $(this).data('row');
        const cid   = $(this).val();
        const subSel = $(`select[name="pairs[${row}][subject_id]"]`);
        subSel.html(buildSubjectOptions(cid));
        // Update section select
        const secFld = $(this).closest('.pair-row').find('.pair-section-field');
        if (secFld.length) {
            secFld.html(buildSectionOptions(cid));
        }
    });

    // Add new pair row
    $('#addPairRow').on('click', function(){
        const idx = pairRowCount++;
        const classOpts = buildClassOptions(0);
        const secOpts   = buildSectionOptions(0);
        const html = `
            <div class="pair-row row g-2 mb-2 align-items-center">
                <div class="col-md-3">
                    <select name="pairs[${idx}][class_id]" class="form-select pair-class-select" data-row="${idx}">
                        ${classOpts}
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="pairs[${idx}][section]" class="form-select pair-section-field">
                        <option value="A">A</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="pairs[${idx}][subject_id]" class="form-select pair-subject-select" data-row="${idx}">
                        <option value="">-- Select Subject --</option>
                    </select>
                </div>
                <div class="col-md-2 text-center">
                    <label class="d-flex align-items-center justify-content-center gap-1" style="cursor:pointer;font-size:12px;">
                        <input type="checkbox" name="pairs[${idx}][is_class_teacher]" value="1"
                               class="form-check-input mt-0" style="width:18px;height:18px;">
                        <span style="color:var(--primary);font-weight:600;">CT</span>
                    </label>
                </div>
                <div class="col-md-2 text-center">
                    <button type="button" class="btn-admin-danger btn-sm remove-pair-row" style="width:36px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>`;
        $('#pairRows').append(html);
    });

    // Remove pair row (keep at least 1)
    $(document).on('click', '.remove-pair-row', function(){
        if($('.pair-row').length > 1){
            $(this).closest('.pair-row').remove();
        } else {
            // Last row — just clear values instead of removing
            $(this).closest('.pair-row').find('select').val('');
        }
    });

    // Teacher searchable dropdown — custom pure JS
    (function(){
        const orig = document.getElementById('at_teacher');
        if (!orig) return;

        // Build custom wrapper
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'position:relative;';
        orig.parentNode.insertBefore(wrapper, orig);
        orig.style.display = 'none';
        wrapper.appendChild(orig);

        // Input box
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.placeholder = 'Type to search teacher...';
        input.setAttribute('autocomplete','off');
        wrapper.insertBefore(input, orig);

        // Dropdown list
        const list = document.createElement('div');
        list.style.cssText = 'position:absolute;top:100%;left:0;right:0;z-index:9999;background:white;border:1px solid #ccc;border-radius:6px;max-height:220px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
        wrapper.appendChild(list);

        const options = Array.from(orig.options);

        function renderList(q) {
            list.innerHTML = '';
            const filtered = options.filter(o => !q || o.text.toLowerCase().includes(q.toLowerCase()));
            if (!filtered.length) {
                list.innerHTML = '<div style="padding:10px 14px;color:#999;font-size:13px;">No teachers found</div>';
            }
            filtered.forEach(o => {
                const item = document.createElement('div');
                item.textContent = o.text;
                item.style.cssText = 'padding:8px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0;';
                item.addEventListener('mouseenter', function(){ this.style.background='#f0f7f0'; });
                item.addEventListener('mouseleave', function(){ this.style.background='white'; });
                item.addEventListener('mousedown', function(e){
                    e.preventDefault();
                    orig.value = o.value;
                    input.value = o.value ? o.text : '';
                    list.style.display = 'none';
                });
                list.appendChild(item);
            });
            list.style.display = filtered.length ? 'block' : 'none';
        }

        input.addEventListener('focus', function(){ renderList(this.value); });
        input.addEventListener('input', function(){ renderList(this.value); });
        input.addEventListener('blur',  function(){ setTimeout(()=>{ list.style.display='none'; }, 150); });

        // Reset on modal open
        document.getElementById('assignTeacherModal').addEventListener('show.bs.modal', function(){
            orig.value = '';
            input.value = '';
            list.style.display = 'none';
        });
    })();

    // Reset on modal close (keep for existing code compatibility)
    $('#assignTeacherModal').on('hidden.bs.modal', function(){
        pairRowCount = 1;
        $('#at_teacher').val('');
        $('#pairRows .pair-row:not(:first)').remove();
        $('#pairRows .pair-row:first .pair-class-select').html(buildClassOptions(<?= $selectedClassId ?>));
        $('#pairRows .pair-row:first .pair-subject-select').html(buildSubjectOptions(<?= $selectedClassId ?>));
    });
});
</script>

<!-- Tom Select — searchable select (classes.php only) -->

<?php require_once 'includes/layout_bottom.php'; ?>
