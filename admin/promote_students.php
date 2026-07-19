<?php
$pageTitle = 'Promote Students';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

// ── POST: Undo Promotion ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'undo_promotion') {
    $histId = (int)($_POST['history_id'] ?? 0);
    if ($histId) {
        $h = $conn->query("SELECT * FROM promotion_history WHERE id=$histId")->fetch_assoc();
        if ($h) {
            $conn->prepare("UPDATE students SET class_id=?, section=?, academic_year=? WHERE id=?")
                 ->bind_param("issi", $h['from_class_id'], $h['from_section'], $h['from_academic_year'], $h['student_id']) || null;
            $st = $conn->prepare("UPDATE students SET class_id=?, section=?, academic_year=? WHERE id=?");
            $st->bind_param("issi", $h['from_class_id'], $h['from_section'], $h['from_academic_year'], $h['student_id']);
            $st->execute(); $st->close();
            $conn->query("DELETE FROM promotion_history WHERE id=$histId");
            $message = 'Promotion undone successfully!'; $messageType = 'success';
        }
    }
}

// ── POST: Mark as Passed Out ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'passed_out') {
    $passedIds = $_POST['passed_out_ids'] ?? [];
    $count = 0;
    foreach ($passedIds as $sid) {
        $sid = (int)$sid;
        if (!$sid) continue;
        $conn->query("UPDATE students SET status='passed_out' WHERE id=$sid");
        $count++;
    }
    $message = "$count student".($count!=1?'s':'')." marked as passed out!"; $messageType = 'success';
}

// ── POST: Assign Roll Numbers ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_rolls') {
    $rollClassId    = (int)($_POST['roll_class_id']    ?? 0);
    $rollSection    = sanitize($conn, $_POST['roll_section']    ?? '');
    $rollAcadYear   = sanitize($conn, $_POST['roll_acad_year']  ?? '');
    $rollStart      = (int)($_POST['roll_start']       ?? 1);
    $rollOrderBy    = in_array($_POST['roll_order'] ?? '', ['full_name','roll_no']) ? $_POST['roll_order'] : 'full_name';
    $genderMode     = sanitize($conn, $_POST['roll_gender_mode'] ?? 'all');
    $rollStartBoys  = (int)($_POST['roll_start_boys']  ?? 1);
    $rollStartGirls = (int)($_POST['roll_start_girls'] ?? 1);

    if ($rollClassId) {
        $wSec = $rollSection  ? "AND section='$rollSection'"           : '';
        $wYr  = $rollAcadYear ? "AND academic_year='$rollAcadYear'"    : '';

        if ($genderMode === 'separate') {
            $boys   = $conn->query("SELECT id FROM students WHERE class_id=$rollClassId $wSec $wYr AND status='active' AND gender='male'   ORDER BY $rollOrderBy")->fetch_all(MYSQLI_ASSOC);
            $girls  = $conn->query("SELECT id FROM students WHERE class_id=$rollClassId $wSec $wYr AND status='active' AND gender='female' ORDER BY $rollOrderBy")->fetch_all(MYSQLI_ASSOC);
            $others = $conn->query("SELECT id FROM students WHERE class_id=$rollClassId $wSec $wYr AND status='active' AND gender NOT IN ('male','female') ORDER BY $rollOrderBy")->fetch_all(MYSQLI_ASSOC);
            $rb = $rollStartBoys;
            foreach ($boys  as $row) { $conn->query("UPDATE students SET roll_no='$rb' WHERE id={$row['id']}"); $rb++; }
            $rg = $rollStartGirls;
            foreach ($girls as $row) { $conn->query("UPDATE students SET roll_no='$rg' WHERE id={$row['id']}"); $rg++; }
            foreach ($others as $row){ $conn->query("UPDATE students SET roll_no='$rg' WHERE id={$row['id']}"); $rg++; }
            $message = "Roll numbers assigned: ".count($boys)." boys (from $rollStartBoys), ".count($girls)." girls (from $rollStartGirls)!";
        } else {
            $stList = $conn->query("SELECT id FROM students WHERE class_id=$rollClassId $wSec $wYr AND status='active' ORDER BY $rollOrderBy")->fetch_all(MYSQLI_ASSOC);
            $r = $rollStart;
            foreach ($stList as $row) { $conn->query("UPDATE students SET roll_no='$r' WHERE id={$row['id']}"); $r++; }
            $message = "Roll numbers assigned (1–".($r-1).") to ".count($stList)." students!";
        }
        $messageType = 'success';
    }
}

// ── POST: Confirm Promotion ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promote') {
    $fromClassId  = (int)($_POST['from_class_id']   ?? 0);
    $toClassId    = (int)($_POST['to_class_id']      ?? 0);
    $fromAcadYear = sanitize($conn, $_POST['from_acad_year'] ?? '');
    $toAcadYear   = sanitize($conn, $_POST['to_acad_year']   ?? '');
    $toSection    = sanitize($conn, $_POST['to_section']     ?? 'A');
    $studentIds   = $_POST['student_ids'] ?? [];
    $notes        = sanitize($conn, $_POST['notes'] ?? '');

    if (!$fromClassId || !$toClassId || !$fromAcadYear || !$toAcadYear || empty($studentIds)) {
        $message = 'Please fill all fields and select at least one student.';
        $messageType = 'danger';
    } else {
        $promoted = 0;
        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            if (!$sid) continue;
            $st = $conn->query("SELECT * FROM students WHERE id=$sid AND class_id=$fromClassId")->fetch_assoc();
            if (!$st) continue;
            $fromSection = $st['section'];
            $upd = $conn->prepare("UPDATE students SET class_id=?, section=?, academic_year=?, roll_no=NULL WHERE id=?");
            $upd->bind_param("issi", $toClassId, $toSection, $toAcadYear, $sid);
            $upd->execute(); $upd->close();
            $ins = $conn->prepare("INSERT INTO promotion_history (student_id,from_class_id,from_section,from_academic_year,to_class_id,to_section,to_academic_year,promoted_by,notes) VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->bind_param("iisssssis", $sid, $fromClassId, $fromSection, $fromAcadYear, $toClassId, $toSection, $toAcadYear, $adminId, $notes);
            $ins->execute(); $ins->close();
            $promoted++;
        }
        $toName = $conn->query("SELECT class_name FROM classes WHERE id=$toClassId")->fetch_assoc()['class_name'];
        $message = "$promoted student".($promoted!=1?'s':'')." promoted to $toName ($toAcadYear)!";
        $messageType = 'success';
    }
}

// ── Load Data ─────────────────────────────────────────
$classes    = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$acadYear   = getSetting($conn, 'academic_year');

// Selected filters
$fromClassId  = (int)($_GET['from_class_id'] ?? (int)($_POST['from_class_id'] ?? 0));
$fromAcadYear = sanitize($conn, $_GET['from_acad_year'] ?? $acadYear);
$toClassId    = (int)($_GET['to_class_id'] ?? 0);

// Section breakdown for selected class
$sectionBreakdown = [];
$totalInClass     = 0;
if ($fromClassId) {
    $secRows = $conn->query("
        SELECT section, COUNT(*) as cnt
        FROM students
        WHERE class_id=$fromClassId AND status='active'
        GROUP BY section ORDER BY section
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($secRows as $sr) {
        $sectionBreakdown[$sr['section']] = (int)$sr['cnt'];
        $totalInClass += (int)$sr['cnt'];
    }
}

// Students with result status — all sections
$students = [];
if ($fromClassId) {
    $students = $conn->query("
        SELECT s.*,
            -- Check if passed final or any published exam
            (SELECT COUNT(*) FROM exams e
             JOIN result_publish rp ON e.id=rp.exam_id AND rp.class_id=e.class_id
             JOIN results r ON r.exam_id=e.id AND r.student_id=s.id
             WHERE e.class_id=s.class_id AND e.academic_year='$fromAcadYear'
             AND rp.is_published=1) as has_results,
            (SELECT SUM(r2.total_obtained) FROM results r2
             JOIN exams e2 ON r2.exam_id=e2.id
             JOIN result_publish rp2 ON e2.id=rp2.exam_id AND rp2.class_id=e2.class_id
             WHERE r2.student_id=s.id AND e2.class_id=s.class_id
             AND e2.academic_year='$fromAcadYear' AND rp2.is_published=1) as total_marks,
            (SELECT COUNT(*) FROM results r3
             JOIN exams e3 ON r3.exam_id=e3.id
             JOIN result_publish rp3 ON e3.id=rp3.exam_id AND rp3.class_id=e3.class_id
             WHERE r3.student_id=s.id AND e3.class_id=s.class_id
             AND e3.academic_year='$fromAcadYear' AND rp3.is_published=1
             AND r3.remarks IN ('fail','absent')) as failed_count
        FROM students s
        WHERE s.class_id=$fromClassId AND s.status='active'
        ORDER BY s.section, CAST(s.roll_no AS UNSIGNED), s.full_name
    ")->fetch_all(MYSQLI_ASSOC);
}

// Recent promotion history
$history = $conn->query("
    SELECT ph.*, s.full_name,
           fc.class_name as from_class, tc.class_name as to_class,
           u.full_name as promoted_by_name
    FROM promotion_history ph
    JOIN students s  ON ph.student_id    = s.id
    JOIN classes  fc ON ph.from_class_id = fc.id
    JOIN classes  tc ON ph.to_class_id   = tc.id
    JOIN users    u  ON ph.promoted_by   = u.id
    ORDER BY ph.promoted_at DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

<!-- ══ LEFT: Filter + Student List ═══════════════════ -->
<div class="col-lg-8">

<!-- Step 1: Select Source -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h6><i class="fas fa-search me-2"></i>Step 1 — Select Source Class</h6>
    </div>
    <div class="admin-card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">From Class <span class="text-danger">*</span></label>
                <select name="from_class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Select Class --</option>
                    <?php foreach($classes as $cls): ?>
                    <option value="<?= $cls['id'] ?>" <?= $cls['id']==$fromClassId?'selected':'' ?>>
                        <?= htmlspecialchars($cls['class_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Academic Year</label>
                <input type="text" name="from_acad_year" class="form-control"
                       value="<?= htmlspecialchars($fromAcadYear) ?>" placeholder="e.g. 2083">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn-admin-primary w-100">Load</button>
            </div>
        </form>

        <?php if ($fromClassId && $totalInClass > 0): ?>
        <!-- Total count + section breakdown -->
        <div class="mt-3 p-3 rounded-2 d-flex align-items-center flex-wrap gap-3"
             style="background:#f0f7f0;border:1px solid #b8ddc8;">
            <div style="font-size:15px;font-weight:700;color:var(--primary);">
                <i class="fas fa-users me-2"></i>
                Total Students: <span style="font-size:20px;"><?= $totalInClass ?></span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach($sectionBreakdown as $sec => $cnt): ?>
                <span style="background:var(--primary);color:white;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;">
                    Section <?= $sec ?>: <?= $cnt ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif($fromClassId): ?>
        <div class="mt-3 text-muted" style="font-size:13px;">
            <i class="fas fa-info-circle me-1"></i>No active students found in this class.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($students)): ?>
<!-- Step 2: Student List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h6>
            <i class="fas fa-users me-2"></i>
            Step 2 — Select Students to Promote
            <span style="background:var(--primary);color:white;padding:2px 10px;border-radius:12px;font-size:12px;margin-left:6px;">
                <?= count($students) ?>
            </span>
        </h6>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="studentSearchBox" class="form-control form-control-sm"
                       placeholder="Search name, roll no..."
                       style="width:200px;" autocomplete="off">
            </div>
            <button type="button" class="btn-admin-success btn-sm" onclick="selectAll(true)">
                <i class="fas fa-check-square me-1"></i>Select PASS
            </button>
            <button type="button" class="btn-admin-warning btn-sm" onclick="selectAll(false)">
                <i class="fas fa-square me-1"></i>Deselect All
            </button>
        </div>
    </div>
    <form method="POST" id="promoteForm">
        <input type="hidden" name="action"         value="promote">
        <input type="hidden" name="from_class_id"  value="<?= $fromClassId ?>">
        <input type="hidden" name="from_acad_year" value="<?= htmlspecialchars($fromAcadYear) ?>">

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr style="background:#0F3A1A;">
                        <th style="background:#0F3A1A;color:white;width:40px;">
                            <input type="checkbox" id="checkAll" onchange="toggleAll(this)">
                        </th>
                        <th style="background:#0F3A1A;color:white;">#</th>
                        <th style="background:#0F3A1A;color:white;">Student</th>
                        <th style="background:#0F3A1A;color:white;">Roll No</th>
                        <th style="background:#0F3A1A;color:white;">Section</th>
                        <th style="background:#0F3A1A;color:white;">Total Marks</th>
                        <th style="background:#0F3A1A;color:white;">Result Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $i => $st):
                        $isPassed  = $st['has_results'] > 0 && $st['failed_count'] == 0;
                        $isFailed  = $st['has_results'] > 0 && $st['failed_count'] > 0;
                        $noResult  = $st['has_results'] == 0;
                        $rowBg     = $isFailed ? 'background:#fff8f8;' : ($noResult ? 'background:#fffdf0;' : '');
                    ?>
                    <tr style="<?= $rowBg ?>">
                        <td style="padding:4px 8px;">
                            <input type="checkbox" name="student_ids[]"
                                   value="<?= $st['id'] ?>"
                                   class="student-check <?= $isPassed?'pass-check':'fail-check' ?>"
                                   <?= $isPassed?'checked':'' ?>>
                        </td>
                        <td><?= $i+1 ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($st['full_name']) ?></div>
                            <?php if($st['father_name']): ?>
                            <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($st['father_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($st['roll_no'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($st['section']) ?></td>
                        <td>
                            <?php if($st['total_marks'] !== null): ?>
                            <span class="fw-bold"><?= number_format($st['total_marks'],0) ?></span>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">No result</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($isPassed): ?>
                            <span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">
                                <i class="fas fa-check me-1"></i>PASS
                            </span>
                            <?php elseif($isFailed): ?>
                            <span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">
                                <i class="fas fa-times me-1"></i>FAIL
                            </span>
                            <?php else: ?>
                            <span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">
                                <i class="fas fa-question me-1"></i>No Result
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Step 3: Target -->
        <div class="admin-card-body" style="border-top:1px solid var(--border);padding:20px;">
            <h6 class="mb-3"><i class="fas fa-arrow-right me-2 text-success"></i>Step 3 — Promote To</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Target Class <span class="text-danger">*</span></label>
                    <select name="to_class_id" class="form-select" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach($classes as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= $cls['id']==$toClassId?'selected':'' ?>>
                            <?= htmlspecialchars($cls['class_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Section</label>
                    <input type="text" name="to_section" class="form-control"
                           value="A" placeholder="A" maxlength="5"
                           style="text-transform:uppercase;">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">New Academic Year <span class="text-danger">*</span></label>
                    <input type="text" name="to_acad_year" class="form-control" required
                           value="<?= (int)$fromAcadYear + 1 ?>" placeholder="e.g. 2084">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Notes (optional)</label>
                    <input type="text" name="notes" class="form-control"
                           placeholder="e.g. Year-end promotion 2083">
                </div>
            </div>
            <div class="mt-3 p-3 rounded-2" style="background:#f0f7f0;font-size:13px;">
                <i class="fas fa-info-circle me-1 text-success"></i>
                <strong id="selectedCount">0</strong> student(s) selected for promotion.
                PASS students are auto-selected. Uncheck to exclude. Check FAIL students to promote anyway.
            </div>
            <div class="mt-3">
                <button type="submit" class="btn-admin-primary" style="padding:10px 32px;font-size:15px;"
                        onclick="return confirmPromotion()">
                    <i class="fas fa-graduation-cap me-2"></i>Promote Selected Students
                </button>
            </div>
        </div>
    </form>
</div>

<?php elseif($fromClassId): ?>
<div class="admin-card">
    <div class="admin-card-body text-center py-5 text-muted">
        <i class="fas fa-users fa-3x mb-3 d-block"></i>
        No active students found in this class/section.
    </div>
</div>
<?php endif; ?>

</div><!-- /.col-lg-8 -->

<!-- ══ RIGHT: Tools + History ════════════════════════ -->
<div class="col-lg-4">

    <!-- Roll Number Assign -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h6><i class="fas fa-list-ol me-2"></i>Assign Roll Numbers</h6>
        </div>
        <div class="admin-card-body">
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="assign_rolls">
                <div class="mb-2">
                    <label class="form-label" style="font-size:13px;">Class</label>
                    <select name="roll_class_id" class="form-select form-select-sm" required>
                        <option value="">-- Select --</option>
                        <?php foreach($classes as $cls): ?>
                        <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:13px;">Section</label>
                        <input type="text" name="roll_section" class="form-control form-control-sm" placeholder="A (blank=all)">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:13px;">Acad. Year</label>
                        <input type="text" name="roll_acad_year" class="form-control form-control-sm" value="<?= htmlspecialchars($acadYear) ?>">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:13px;">Start From</label>
                        <input type="number" name="roll_start" class="form-control form-control-sm" value="1" min="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:13px;">Order By</label>
                        <select name="roll_order" class="form-select form-select-sm">
                            <option value="full_name">Name (A-Z)</option>
                            <option value="roll_no">Current Roll</option>
                        </select>
                    </div>
                </div>
                <!-- Gender-wise option -->
                <div class="mb-3 p-2 rounded-2" style="background:#f0f7f0;border:1px solid #b8ddc8;">
                    <label class="form-label fw-semibold mb-1" style="font-size:13px;">
                        <i class="fas fa-venus-mars me-1 text-primary"></i>Gender-wise Roll
                    </label>
                    <div class="form-check mb-1">
                        <input type="radio" name="roll_gender_mode" value="all" id="rgAll"
                               class="form-check-input" checked onchange="toggleGenderRoll(this.value)">
                        <label class="form-check-label" for="rgAll" style="font-size:13px;">
                            All students together
                        </label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="radio" name="roll_gender_mode" value="separate" id="rgSep"
                               class="form-check-input" onchange="toggleGenderRoll(this.value)">
                        <label class="form-check-label" for="rgSep" style="font-size:13px;">
                            Boys & Girls separate
                        </label>
                    </div>
                    <!-- Gender separate options -->
                    <div id="genderRollOpts" style="display:none;margin-top:8px;" class="row g-2">
                        <div class="col-6">
                            <label style="font-size:11px;font-weight:700;color:#0284c7;">👦 Boys start from</label>
                            <input type="number" name="roll_start_boys" class="form-control form-control-sm"
                                   value="1" min="1">
                        </div>
                        <div class="col-6">
                            <label style="font-size:11px;font-weight:700;color:#b5281f;">👧 Girls start from</label>
                            <input type="number" name="roll_start_girls" class="form-control form-control-sm"
                                   value="1" min="1">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-admin-primary w-100" style="font-size:13px;"
                        onclick="return confirm('This will overwrite existing roll numbers. Proceed?')">
                    <i class="fas fa-list-ol me-1"></i>Assign Roll Numbers
                </button>
            </form>
        </div>
    </div>

    <!-- Passed Out -->
    <?php if(!empty($students)): ?>
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h6><i class="fas fa-user-graduate me-2"></i>Mark as Passed Out</h6>
        </div>
        <div class="admin-card-body" style="font-size:13px;">
            <p class="text-muted mb-2">Select students who have graduated/left the school permanently.</p>
            <form method="POST">
                <input type="hidden" name="action" value="passed_out">
                <?php foreach($students as $st): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <input type="checkbox" name="passed_out_ids[]" value="<?= $st['id'] ?>"
                           id="po_<?= $st['id'] ?>" class="form-check-input mt-0">
                    <label for="po_<?= $st['id'] ?>" style="cursor:pointer;">
                        <?= htmlspecialchars($st['full_name']) ?>
                        <span class="text-muted" style="font-size:11px;">(Roll: <?= $st['roll_no'] ?? '—' ?>)</span>
                    </label>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-admin-danger w-100 mt-3" style="font-size:13px;"
                        onclick="return confirm('Mark selected students as passed out? They will be removed from active student lists.')">
                    <i class="fas fa-graduation-cap me-1"></i>Mark Passed Out
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Promotion History -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h6><i class="fas fa-history me-2"></i>Recent Promotions</h6>
        </div>
        <?php if(empty($history)): ?>
        <div class="text-center py-4 text-muted" style="font-size:13px;">No promotion history yet.</div>
        <?php else: ?>
        <div style="max-height:400px;overflow-y:auto;">
            <?php foreach($history as $h): ?>
            <div class="p-3 border-bottom" style="font-size:12.5px;">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($h['full_name']) ?></div>
                        <div class="text-muted" style="font-size:11.5px;">
                            <?= htmlspecialchars($h['from_class']) ?> (<?= $h['from_academic_year'] ?>)
                            <i class="fas fa-arrow-right mx-1 text-success" style="font-size:10px;"></i>
                            <?= htmlspecialchars($h['to_class']) ?> (<?= $h['to_academic_year'] ?>)
                        </div>
                        <div class="text-muted" style="font-size:11px;">
                            <?= date('M d, Y', strtotime($h['promoted_at'])) ?>
                            &bull; <?= htmlspecialchars($h['promoted_by_name']) ?>
                        </div>
                    </div>
                    <form method="POST" style="flex-shrink:0;">
                        <input type="hidden" name="action"     value="undo_promotion">
                        <input type="hidden" name="history_id" value="<?= $h['id'] ?>">
                        <button type="submit" class="btn-admin-warning btn-sm"
                                title="Undo this promotion"
                                onclick="return confirm('Undo promotion for <?= htmlspecialchars(addslashes($h['full_name'])) ?>?\nThis will move them back to <?= htmlspecialchars($h['from_class']) ?> (<?= $h['from_academic_year'] ?>).')">
                            <i class="fas fa-undo"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.col-lg-4 -->

</div><!-- /.row -->

<script>
// Live search filter
var searchBox = document.getElementById('studentSearchBox');
if (searchBox) {
    searchBox.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('#promoteForm tbody tr').forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    });
}

function toggleGenderRoll(val) {
    document.getElementById('genderRollOpts').style.display = val === 'separate' ? 'flex' : 'none';
}

function toggleAll(cb) {
    document.querySelectorAll('.student-check').forEach(c => c.checked = cb.checked);
    updateCount();
}
function selectAll(passOnly) {
    if (passOnly) {
        document.querySelectorAll('.student-check').forEach(c => c.checked = c.classList.contains('pass-check'));
    } else {
        document.querySelectorAll('.student-check').forEach(c => c.checked = false);
        document.getElementById('checkAll').checked = false;
    }
    updateCount();
}
function updateCount() {
    var n = document.querySelectorAll('.student-check:checked').length;
    var el = document.getElementById('selectedCount');
    if (el) el.textContent = n;
}
function confirmPromotion() {
    var n = document.querySelectorAll('.student-check:checked').length;
    if (n === 0) { alert('Please select at least one student.'); return false; }
    var toClass = document.querySelector('[name="to_class_id"]').options[document.querySelector('[name="to_class_id"]').selectedIndex].text;
    var toYear  = document.querySelector('[name="to_acad_year"]').value;
    if (!document.querySelector('[name="to_class_id"]').value) { alert('Please select target class.'); return false; }
    return confirm('Promote ' + n + ' student(s) to ' + toClass + ' (' + toYear + ')?\n\nThis will update their class and academic year. This cannot be undone easily.');
}
document.querySelectorAll('.student-check').forEach(c => c.addEventListener('change', updateCount));
document.addEventListener('DOMContentLoaded', updateCount);
</script>

<style>
#promoteForm .admin-table tbody td {
    padding: 4px 8px;
    font-size: 13px;
    line-height: 1.3;
}
#promoteForm .admin-table tbody td .fw-semibold {
    font-size: 13px;
}
#promoteForm .admin-table tbody td div[style*="font-size:11px"] {
    font-size: 11px;
    line-height: 1.2;
}
</style>

<?php require_once 'includes/layout_bottom.php'; ?>
