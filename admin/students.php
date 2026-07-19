<?php
$pageTitle = 'Manage Students';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    // import_csv is handled separately below — skip main block
    if ($action !== 'import_csv') {

    if ($action === 'add' || $action === 'edit') {
        $studentIdCustom = sanitize($conn, $_POST['student_id_custom'] ?? '');
        $iemisNo         = sanitize($conn, $_POST['iemis_no']          ?? '');
        $fullName        = sanitize($conn, $_POST['full_name']         ?? '');
        $rollNo          = sanitize($conn, $_POST['roll_no']           ?? '');
        $symbolNo        = sanitize($conn, $_POST['symbol_no']         ?? '');
        $classId         = (int)($_POST['class_id']                    ?? 0);
        $section         = sanitize($conn, $_POST['section']           ?? 'A');
        $acadYear        = sanitize($conn, $_POST['academic_year']     ?? '');
        $dob             = normalizeDate($_POST['date_of_birth']       ?? '');
        $gender          = sanitize($conn, $_POST['gender']            ?? '');
        $fatherName      = sanitize($conn, $_POST['father_name']       ?? '');
        $motherName      = sanitize($conn, $_POST['mother_name']       ?? '');
        $guardianPhone   = sanitize($conn, $_POST['guardian_phone']    ?? '');
        $address         = sanitize($conn, $_POST['address']           ?? '');
        $status          = sanitize($conn, $_POST['status']            ?? 'active');

        if (!$fullName || !$classId || !$dob || !$gender) {
            $message = 'Please fill all required fields.';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO students
                    (student_id_custom,iemis_no,full_name,roll_no,symbol_no,
                     class_id,section,academic_year,date_of_birth,gender,
                     father_name,mother_name,guardian_phone,address,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssisssssssss",
                    $studentIdCustom,$iemisNo,
                    $fullName,$rollNo,$symbolNo,$classId,$section,$acadYear,
                    $dob,$gender,$fatherName,$motherName,
                    $guardianPhone,$address,$status);
                $stmt->execute(); $stmt->close();
                $message = 'Student added!'; $messageType = 'success';
            } else {
                $stmt = $conn->prepare("UPDATE students SET
                    student_id_custom=?,iemis_no=?,full_name=?,roll_no=?,symbol_no=?,
                    class_id=?,section=?,academic_year=?,date_of_birth=?,gender=?,
                    father_name=?,mother_name=?,guardian_phone=?,address=?,
                    status=? WHERE id=?");
                $stmt->bind_param("sssssisssssssssi",
                    $studentIdCustom,$iemisNo,
                    $fullName,$rollNo,$symbolNo,$classId,$section,$acadYear,
                    $dob,$gender,$fatherName,$motherName,
                    $guardianPhone,$address,$status,$studentId);
                $stmt->execute(); $stmt->close();
                $message = 'Student updated!'; $messageType = 'success';
            }
        }
    } elseif ($action === 'delete' && $studentId) {
        $conn->query("DELETE FROM students WHERE id=$studentId");
        $message = 'Student deleted.'; $messageType = 'warning';
    }

    // POST/Redirect/GET
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['students_msg']      = $message;
    $_SESSION['students_msg_type'] = $messageType;
    $backClassId  = (int)($_POST['back_class_id']  ?? 0);
    $backSection  = sanitize($conn, $_POST['back_section'] ?? '');
    $qs = $backClassId ? "?class_id=$backClassId" . ($backSection ? "&section=$backSection" : '') : '';
    header("Location: students.php$qs");
    exit();
    } // end if ($action !== 'import_csv')
} // end POST block

// Flash message
if (session_status() === PHP_SESSION_NONE) session_start();
$message     = $_SESSION['students_msg']      ?? '';
$messageType = $_SESSION['students_msg_type'] ?? 'success';
unset($_SESSION['students_msg'], $_SESSION['students_msg_type']);

// ── Selected filters ─────────────────────────────────
$selectedClassId = (int)($_GET['class_id']  ?? 0);
$selectedSection = sanitize($conn, $_GET['section'] ?? '');
$search          = sanitize($conn, $_GET['search']  ?? '');
$filterStatus    = sanitize($conn, $_GET['status']  ?? '');

// ── Classes with section breakdown ───────────────────
$classRows = $conn->query("
    SELECT c.*,
        COUNT(s.id)              AS total_count,
        SUM(s.status='active')   AS active_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.id
")->fetch_all(MYSQLI_ASSOC);

// Per-class sections (distinct sections that have students)
$sectionMap = []; // [class_id => [section => count]]
$secRows = $conn->query("
    SELECT class_id, section,
           COUNT(*) as cnt,
           SUM(status='active') as active_cnt
    FROM students
    GROUP BY class_id, section
    ORDER BY class_id, section
")->fetch_all(MYSQLI_ASSOC);
foreach ($secRows as $sr) {
    $sectionMap[$sr['class_id']][$sr['section']] = [
        'total'  => $sr['cnt'],
        'active' => $sr['active_cnt'],
    ];
}

// Auto-select first class
if (!$selectedClassId && !empty($classRows)) {
    $selectedClassId = $classRows[0]['id'];
}

// Selected class info
$selectedClass = null;
foreach ($classRows as $c) {
    if ($c['id'] == $selectedClassId) { $selectedClass = $c; break; }
}

// Sections for selected class
$sectionsForClass = $sectionMap[$selectedClassId] ?? [];
ksort($sectionsForClass);

// Auto-select first section if none selected
if (!$selectedSection && !empty($sectionsForClass)) {
    $selectedSection = array_key_first($sectionsForClass);
}

// ── Students query ────────────────────────────────────
$students = [];
if ($selectedClassId) {
    $where = "WHERE s.class_id = $selectedClassId";
    if ($selectedSection) $where .= " AND s.section = '" . $conn->real_escape_string($selectedSection) . "'";
    if ($filterStatus)    $where .= " AND s.status = '" . $conn->real_escape_string($filterStatus) . "'";
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where .= " AND (s.full_name LIKE '%$s%' OR s.roll_no LIKE '%$s%')";
    }
    $students = $conn->query("
        SELECT s.* FROM students s $where
        ORDER BY CAST(s.roll_no AS UNSIGNED), s.full_name
    ")->fetch_all(MYSQLI_ASSOC);
}

$acadYear    = getSetting($conn, 'academic_year');
$totalActive = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$totalAll    = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expClassId = (int)($_GET['class_id'] ?? 0);
    $expSection = sanitize($conn, $_GET['section'] ?? '');
    $expStatus  = sanitize($conn, $_GET['status']  ?? '');

    $expWhere = "WHERE s.class_id = $expClassId";
    if ($expSection) $expWhere .= " AND s.section = '$expSection'";
    if ($expStatus)  $expWhere .= " AND s.status = '$expStatus'";

    $expStudents = $conn->query("
        SELECT s.*, c.class_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        $expWhere
        ORDER BY CAST(s.roll_no AS UNSIGNED), s.full_name
    ")->fetch_all(MYSQLI_ASSOC);

    // Get class name for filename
    $clsName = '';
    foreach ($classRows ?? [] as $cr) {
        if ($cr['id'] == $expClassId) { $clsName = $cr['class_name']; break; }
    }
    $filename = 'students_' . preg_replace('/[^a-z0-9]/i','_', $clsName)
              . ($expSection ? '_Sec'.$expSection : '') . '_' . date('Ymd') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['#','Student ID','IEMIS No','Full Name','Gender','DOB',
                   'Class','Section','Roll No','Symbol No','Academic Year',
                   'Father Name','Mother Name','Guardian Phone','Address','Status']);
    foreach ($expStudents as $i => $st) {
        fputcsv($out, [
            $i + 1,
            $st['student_id_custom'] ?? '',
            $st['iemis_no']          ?? '',
            $st['full_name'],
            ucfirst($st['gender']),
            $st['date_of_birth'],
            $st['class_name'],
            $st['section'],
            $st['roll_no']    ?? '',
            $st['symbol_no']  ?? '',
            $st['academic_year'] ?? '',
            $st['father_name']   ?? '',
            $st['mother_name']   ?? '',
            $st['guardian_phone'] ?? '',
            $st['address']       ?? '',
            ucfirst($st['status']),
        ]);
    }
    fclose($out);
    exit();
}

// ── Template Download ─────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, [
        'full_name','gender','date_of_birth','roll_no','symbol_no',
        'student_id_custom','iemis_no','section','academic_year',
        'father_name','mother_name','guardian_phone','address','status'
    ]);
    fclose($out);
    exit();
}

// ── CSV Import Handler ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    $impClassId = (int)($_POST['import_class_id'] ?? 0);
    $impSection = sanitize($conn, $_POST['import_section'] ?? 'A');
    $impAcadYear = sanitize($conn, $_POST['import_acad_year'] ?? getSetting($conn,'academic_year'));
    $rows       = $_POST['rows'] ?? [];

    $imported = 0; $skipped = 0;
    foreach ($rows as $row) {
        $fn  = sanitize($conn, $row['full_name']       ?? '');
        $gen = sanitize($conn, $row['gender']          ?? 'male');
        $dob = normalizeDate($row['date_of_birth']     ?? '');
        // Reject clearly invalid dates (strtotime returning false or year < 1900)
        if ($dob) {
            $ts = strtotime($dob);
            if (!$ts || date('Y', $ts) < 1900 || date('Y', $ts) > date('Y')) $dob = null;
        }
        $rn  = sanitize($conn, $row['roll_no']         ?? '');
        $sn  = sanitize($conn, $row['symbol_no']       ?? '');
        $sid = sanitize($conn, $row['student_id_custom'] ?? '');
        $iem = sanitize($conn, $row['iemis_no']        ?? '');
        $sec = sanitize($conn, $row['section']         ?? $impSection);
        $fn2 = sanitize($conn, $row['father_name']     ?? '');
        $mn  = sanitize($conn, $row['mother_name']     ?? '');
        $ph  = sanitize($conn, $row['guardian_phone']  ?? '');
        $adr = sanitize($conn, $row['address']         ?? '');
        $sts = sanitize($conn, $row['status']          ?? 'active');

        if (!$fn || !$dob || !$impClassId) { $skipped++; continue; }

        $st = $conn->prepare("INSERT INTO students
            (student_id_custom,iemis_no,full_name,roll_no,symbol_no,
             class_id,section,academic_year,date_of_birth,gender,
             father_name,mother_name,guardian_phone,address,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param("sssssisssssssss",
            $sid,$iem,$fn,$rn,$sn,$impClassId,$sec,$impAcadYear,
            $dob,$gen,$fn2,$mn,$ph,$adr,$sts);
        if ($st->execute()) $imported++; else $skipped++;
        $st->close();
    }

    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['students_msg']      = "Import complete: $imported added, $skipped skipped.";
    $_SESSION['students_msg_type'] = $imported > 0 ? 'success' : 'warning';
    header("Location: students.php?class_id=$impClassId");
    exit();
}


require_once 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-3">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card" style="border-left-color:var(--primary);">
            <div class="icon-box" style="background:var(--primary);"><i class="fas fa-users"></i></div>
            <div class="stat-info"><div class="number"><?= $totalAll ?></div><div class="label">Total Students</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card" style="border-left-color:#27ae60;">
            <div class="icon-box" style="background:#27ae60;"><i class="fas fa-user-check"></i></div>
            <div class="stat-info"><div class="number"><?= $totalActive ?></div><div class="label">Active</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card" style="border-left-color:#e67e22;">
            <div class="icon-box" style="background:#e67e22;"><i class="fas fa-school"></i></div>
            <div class="stat-info"><div class="number"><?= count($classRows) ?></div><div class="label">Classes</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card" style="border-left-color:#8e44ad;">
            <div class="icon-box" style="background:#8e44ad;"><i class="fas fa-layer-group"></i></div>
            <div class="stat-info">
                <div class="number"><?= count($sectionsForClass) ?: '—' ?></div>
                <div class="label">Sections <?= $selectedClass ? '('.$selectedClass['class_name'].')' : '' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 2-Column Layout -->
<div class="row g-4">

<!-- ══ LEFT: Class + Section Sidebar ══════════════ -->
<div class="col-lg-3 col-md-4">
    <div class="admin-card" style="overflow:hidden;">
        <div class="admin-card-header" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));">
            <h6 style="color:white;margin:0;"><i class="fas fa-school me-2"></i>Classes</h6>
            <span style="color:rgba(255,255,255,0.7);font-size:12px;"><?= count($classRows) ?> total</span>
        </div>

        <div class="st-class-list">
            <?php
            $levelColors = [
                'primary'          => ['bg'=>'#e8f4fb', 'color'=>'#0284c7'],
                'lower_secondary'  => ['bg'=>'#edf7f0', 'color'=>'#1b6b35'],
                'secondary'        => ['bg'=>'#fdf0ef', 'color'=>'#b5281f'],
                'higher_secondary' => ['bg'=>'#f3eeff', 'color'=>'#7c3aed'],
            ];
            foreach ($classRows as $cls):
                $isSelected = ($cls['id'] == $selectedClassId);
                $lc = $levelColors[$cls['level']] ?? ['bg'=>'#f0f0f0','color'=>'#666'];
                $classSections = $sectionMap[$cls['id']] ?? [];
                ksort($classSections);
            ?>

            <!-- Class Row -->
            <a href="students.php?class_id=<?= $cls['id'] ?>"
               class="st-class-item <?= $isSelected ? 'selected' : '' ?>">
                <div class="st-class-icon" style="background:<?= $lc['bg'] ?>;color:<?= $lc['color'] ?>;">
                    <?= mb_substr($cls['class_name'], 0, 2) ?>
                </div>
                <div class="st-class-info">
                    <div class="st-class-name"><?= htmlspecialchars($cls['class_name']) ?></div>
                    <div class="st-class-meta">
                        <?= $cls['active_count'] ?> students
                        <?php if (!empty($classSections)): ?>
                        · <?= count($classSections) ?> section<?= count($classSections)>1?'s':'' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="st-class-badge"><?= $cls['active_count'] ?></span>
            </a>

            <!-- Section Pills (shown only for selected class) -->
            <?php if ($isSelected && !empty($classSections)): ?>
            <div class="st-section-group">
                <?php foreach ($classSections as $sec => $secData):
                    $isSecActive = ($selectedSection === $sec);
                ?>
                <a href="students.php?class_id=<?= $cls['id'] ?>&section=<?= urlencode($sec) ?>"
                   class="st-section-pill <?= $isSecActive ? 'active' : '' ?>">
                    <span class="st-sec-letter"><?= htmlspecialchars($sec) ?></span>
                    <span class="st-sec-label">Section <?= htmlspecialchars($sec) ?></span>
                    <span class="st-sec-count"><?= $secData['active'] ?></span>
                </a>
                <?php endforeach; ?>
                <!-- All sections option -->
                <a href="students.php?class_id=<?= $cls['id'] ?>"
                   class="st-section-pill all <?= !$selectedSection ? 'active' : '' ?>">
                    <span class="st-sec-letter" style="font-size:10px;">ALL</span>
                    <span class="st-sec-label">All Sections</span>
                    <span class="st-sec-count"><?= $cls['active_count'] ?></span>
                </a>
            </div>
            <?php elseif ($isSelected && empty($classSections)): ?>
            <div class="st-no-section">
                <i class="fas fa-info-circle me-1"></i>No students yet
            </div>
            <?php endif; ?>

            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══ RIGHT: Students Panel ══════════════════════ -->
<div class="col-lg-9 col-md-8">

    <?php if ($selectedClass): ?>

    <!-- Panel Header -->
    <div class="st-panel-header mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="st-panel-icon">
                    <?= mb_substr($selectedClass['class_name'], 0, 2) ?>
                </div>
                <div>
                    <h5 class="mb-0">
                        <?= htmlspecialchars($selectedClass['class_name']) ?>
                        <?php if ($selectedSection): ?>
                        <span class="st-section-tag">Section <?= htmlspecialchars($selectedSection) ?></span>
                        <?php else: ?>
                        <span class="st-section-tag all">All Sections</span>
                        <?php endif; ?>
                    </h5>
                    <div style="font-size:13px;opacity:0.82;">
                        <?= count($students) ?> student<?= count($students)!=1?'s':'' ?> shown
                        &bull; Year: <?= $acadYear ?>
                        <?php if (!empty($sectionsForClass)): ?>
                        &bull; Sections:
                        <?php foreach ($sectionsForClass as $s => $d): ?>
                        <strong><?= $s ?></strong>(<?= $d['active'] ?>)<?= $s !== array_key_last($sectionsForClass) ? ', ' : '' ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button class="st-add-btn" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-user-plus me-2"></i>Add Student
            </button>
            <?php if ($selectedClassId): ?>
            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#importCsvModal"
                    style="background:#7c3aed;color:white;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;">
                <i class="fas fa-file-upload me-1"></i>Import CSV
            </button>
            <a href="students.php?export=template"
               class="btn btn-sm" style="background:#0284c7;color:white;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;text-decoration:none;"
               title="Download blank template">
                <i class="fas fa-download me-1"></i>Template
            </a>
            <a href="students.php?class_id=<?= $selectedClassId ?><?= $selectedSection?'&section='.urlencode($selectedSection):'' ?><?= $filterStatus?'&status='.$filterStatus:'' ?>&export=csv"
               class="btn btn-sm" style="background:#1a7a3a;color:white;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;text-decoration:none;">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </a>
            <button onclick="printStudentTable()" class="btn btn-sm"
                    style="background:#0d6efd;color:white;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;">
                <i class="fas fa-print me-1"></i>Print
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search + Filter -->
    <div class="admin-card mb-3">
        <div class="admin-card-body" style="padding:12px 16px;">
            <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                <?php if ($selectedSection): ?>
                <input type="hidden" name="section" value="<?= htmlspecialchars($selectedSection) ?>">
                <?php endif; ?>
                <div class="search-box flex-grow-1" style="min-width:180px;">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Search name or roll no..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Status</option>
                    <option value="active"     <?= $filterStatus==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive"   <?= $filterStatus==='inactive'?'selected':'' ?>>Inactive</option>
                    <option value="passed_out" <?= $filterStatus==='passed_out'?'selected':'' ?>>Passed Out</option>
                </select>
                <button type="submit" class="btn-admin-primary btn-sm">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
                <?php if ($search || $filterStatus): ?>
                <a href="students.php?class_id=<?= $selectedClassId ?><?= $selectedSection?"&section=".urlencode($selectedSection):'' ?>"
                   class="btn-admin-warning btn-sm">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <?php if (empty($students)): ?>
    <div class="admin-card">
        <div class="admin-card-body text-center py-5">
            <div style="width:72px;height:72px;background:var(--primary-soft);border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 16px;font-size:28px;color:var(--primary);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h5 class="text-muted mb-2">
                No students in
                <?= htmlspecialchars($selectedClass['class_name']) ?>
                <?= $selectedSection ? ' — Section '.$selectedSection : '' ?>
            </h5>
            <p class="text-muted mb-4" style="font-size:14px;">Add the first student to get started.</p>
            <button class="st-add-btn" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-user-plus me-2"></i>Add First Student
            </button>
        </div>
    </div>

    <?php else: ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h6>
                <i class="fas fa-user-graduate me-2"></i>
                <?= htmlspecialchars($selectedClass['class_name']) ?>
                <?= $selectedSection ? '— Section '.$selectedSection : '— All Sections' ?>
                <span class="ms-2" style="background:var(--primary);color:white;
                      padding:2px 10px;border-radius:12px;font-size:12px;">
                    <?= count($students) ?>
                </span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Student</th>
                        <th>Section</th>
                        <th>Roll / Symbol</th>
                        <th>DOB</th>
                        <th>Guardian</th>
                        <th>Status</th>
                        <th style="width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $st): ?>
                    <tr>
                        <td class="text-muted" style="font-size:12px;"><?= $i+1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="st-avatar <?= $st['gender']==='female'?'female':'' ?>">
                                    <?= strtoupper(mb_substr($st['full_name'],0,1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold" style="font-size:13.5px;">
                                        <?= htmlspecialchars($st['full_name']) ?>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--text-muted);">
                                        <?= ucfirst($st['gender']) ?>
                                        <?php if($st['student_id_custom']): ?>
                                        &bull; <span style="color:var(--primary);font-weight:600;">ID: <?= htmlspecialchars($st['student_id_custom']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="st-section-badge">
                                <?= htmlspecialchars($st['section'] ?: 'A') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($st['roll_no']): ?>
                            <div style="font-size:13px;"><span class="text-muted">Roll:</span> <strong><?= htmlspecialchars($st['roll_no']) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($st['symbol_no']): ?>
                            <div style="font-size:11.5px;color:var(--text-muted);">Symbol: <?= htmlspecialchars($st['symbol_no']) ?></div>
                            <?php endif; ?>
                            <?php if (!$st['roll_no'] && !$st['symbol_no']): ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12.5px;"><?= ($st['date_of_birth'] && $st['date_of_birth'] !== '0000-00-00') ? date('M d, Y', strtotime($st['date_of_birth'])) : '--' ?></td>
                        <td>
                            <?php if ($st['father_name']): ?>
                            <div style="font-size:13px;"><?= htmlspecialchars($st['father_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($st['guardian_phone']): ?>
                            <div style="font-size:11.5px;color:var(--text-muted);">
                                <i class="fas fa-phone-alt me-1" style="font-size:10px;"></i><?= htmlspecialchars($st['guardian_phone']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $sc = ['active'=>['#d4edda','#155724','Active'],'inactive'=>['#f8d7da','#721c24','Inactive'],'passed_out'=>['#fff3cd','#856404','Passed Out']];
                            $c = $sc[$st['status']] ?? $sc['active'];
                            ?>
                            <span style="background:<?= $c[0] ?>;color:<?= $c[1] ?>;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;">
                                <?= $c[2] ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn-admin-warning btn-sm edit-student-btn"
                                        data-student='<?= htmlspecialchars(json_encode($st), ENT_QUOTES) ?>'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="students.php"
                                      onsubmit="return confirm('Delete <?= htmlspecialchars($st['full_name']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                    <input type="hidden" name="back_class_id" value="<?= $selectedClassId ?>">
                                    <input type="hidden" name="back_section" value="<?= htmlspecialchars($selectedSection) ?>">
                                    <button type="submit" class="btn-admin-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="admin-card">
        <div class="admin-card-body text-center py-5 text-muted">
            <i class="fas fa-arrow-left fa-2x mb-3 d-block opacity-40"></i>
            <p>Select a class from the left panel.</p>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.col right -->
</div><!-- /.row -->

<!-- ══ ADD MODAL ═══════════════════════════════════ -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:white;">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add Student
                    <?php if ($selectedClass): ?>
                    — <?= htmlspecialchars($selectedClass['class_name']) ?>
                    <?php if ($selectedSection): ?> · Section <?= htmlspecialchars($selectedSection) ?><?php endif; ?>
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="students.php" class="admin-form">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="back_class_id" value="<?= $selectedClassId ?>">
                <input type="hidden" name="back_section"  value="<?= htmlspecialchars($selectedSection) ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Student ID + IEMIS -->
                        <div class="col-md-6">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id_custom" class="form-control"
                                   maxlength="20" placeholder="e.g. 1179">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Registration No (IEMIS NO.)</label>
                            <input type="text" name="iemis_no" class="form-control"
                                   maxlength="30" placeholder="e.g. 0601900038002748">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="text" name="date_of_birth" class="form-control dob-input" required
                                   placeholder="YYYY-MM-DD or DD/MM/YYYY">
                            <div class="form-text">e.g. 2010-05-15 or 15/05/2010 (AD or BS)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classRows as $cls): ?>
                                <option value="<?= $cls['id'] ?>" <?= $cls['id']==$selectedClassId?'selected':'' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Section</label>
                            <input type="text" name="section" class="form-control"
                                   value="<?= htmlspecialchars($selectedSection ?: 'A') ?>"
                                   maxlength="5" placeholder="A"
                                   style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Roll No</label>
                            <input type="text" name="roll_no" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Symbol No</label>
                            <input type="text" name="symbol_no" class="form-control" maxlength="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Academic Year</label>
                            <input type="text" name="academic_year" class="form-control" value="<?= $acadYear ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="passed_out">Passed Out</option>
                            </select>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" name="mother_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guardian Phone</label>
                            <input type="tel" name="guardian_phone" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" maxlength="300">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ EDIT MODAL ══════════════════════════════════ -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--accent);">
                <h5 class="modal-title" style="color:var(--dark);"><i class="fas fa-edit me-2"></i>Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="students.php" class="admin-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="student_id" id="es_id">
                <input type="hidden" name="back_class_id" value="<?= $selectedClassId ?>">
                <input type="hidden" name="back_section"  value="<?= htmlspecialchars($selectedSection) ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Student ID + IEMIS -->
                        <div class="col-md-6">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id_custom" id="es_sid" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Registration No (IEMIS NO.)</label>
                            <input type="text" name="iemis_no" id="es_iemis" class="form-control" maxlength="30">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="es_name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="es_gender" class="form-select" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="text" name="date_of_birth" id="es_dob" class="form-control dob-input" required
                                   placeholder="YYYY-MM-DD or DD/MM/YYYY">
                            <div class="form-text">e.g. 2010-05-15 or 15/05/2010 (AD or BS)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select name="class_id" id="es_class" class="form-select" required>
                                <?php foreach ($classRows as $cls): ?>
                                <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Section</label>
                            <input type="text" name="section" id="es_section" class="form-control"
                                   maxlength="5" style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Roll No</label>
                            <input type="text" name="roll_no" id="es_roll" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Symbol No</label>
                            <input type="text" name="symbol_no" id="es_symbol" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Academic Year</label>
                            <input type="text" name="academic_year" id="es_year" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="es_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="passed_out">Passed Out</option>
                            </select>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" id="es_father" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" name="mother_name" id="es_mother" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guardian Phone</label>
                            <input type="tel" name="guardian_phone" id="es_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="es_address" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ── Class Sidebar ─────────────────────────────── */
.st-class-list { max-height: calc(100vh - 300px); overflow-y: auto; }
.st-class-list::-webkit-scrollbar { width: 4px; }
.st-class-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

.st-class-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; text-decoration: none;
    color: var(--text-dark); transition: var(--transition);
    border-left: 3px solid transparent;
    border-bottom: 1px solid var(--border);
}
.st-class-item:last-of-type { border-bottom: none; }
.st-class-item:hover { background: var(--primary-soft); border-left-color: var(--primary); color: var(--primary); }
.st-class-item.selected { background: var(--primary-soft); border-left-color: var(--primary); color: var(--primary); }

.st-class-icon {
    width: 36px; height: 36px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; flex-shrink: 0;
}
.st-class-info { flex: 1; min-width: 0; }
.st-class-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.st-class-meta { font-size: 11px; color: var(--text-muted); }
.st-class-badge {
    background: var(--primary); color: white;
    border-radius: 12px; padding: 2px 8px;
    font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.st-class-item.selected .st-class-badge { background: var(--primary-dark); }

/* ── Section Pills ──────────────────────────────── */
.st-section-group {
    padding: 6px 14px 10px 14px;
    background: var(--primary-soft);
    border-bottom: 1px solid var(--border);
    display: flex; flex-direction: column; gap: 4px;
}
.st-section-pill {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 12px; border-radius: 8px;
    text-decoration: none; color: var(--text-dark);
    background: white; border: 1.5px solid var(--border);
    transition: var(--transition); font-size: 13px;
}
.st-section-pill:hover { border-color: var(--primary); color: var(--primary); background: white; }
.st-section-pill.active {
    background: var(--primary); color: white;
    border-color: var(--primary);
}
.st-section-pill.all.active { background: var(--dark); border-color: var(--dark); color: white; }
.st-section-pill.all:hover  { border-color: var(--dark); color: var(--dark); }

.st-sec-letter {
    width: 26px; height: 26px; border-radius: 6px; flex-shrink: 0;
    background: rgba(27,107,53,0.12); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800;
}
.st-section-pill.active .st-sec-letter { background: rgba(255,255,255,0.25); color: white; }
.st-section-pill.all .st-sec-letter    { background: rgba(18,32,46,0.1); color: var(--dark); font-size: 9px; }
.st-section-pill.all.active .st-sec-letter { background: rgba(255,255,255,0.2); color: white; }

.st-sec-label { flex: 1; font-size: 12.5px; font-weight: 500; }
.st-sec-count {
    background: rgba(27,107,53,0.1); color: var(--primary);
    border-radius: 10px; padding: 1px 8px; font-size: 11px; font-weight: 700;
}
.st-section-pill.active .st-sec-count { background: rgba(255,255,255,0.25); color: white; }
.st-section-pill.all.active .st-sec-count { background: rgba(255,255,255,0.2); color: white; }

.st-no-section {
    padding: 8px 14px; font-size: 12px; color: var(--text-muted);
    background: var(--primary-soft); border-bottom: 1px solid var(--border);
}

/* ── Panel Header ────────────────────────────────── */
.st-panel-header {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    padding: 18px 22px; border-radius: var(--radius); color: white;
}
.st-panel-header h5 { color: white; font-weight: 800; font-size: 17px; margin: 0; }
.st-panel-icon {
    width: 50px; height: 50px; border-radius: 13px;
    background: rgba(255,255,255,0.18); color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; flex-shrink: 0;
}
.st-section-tag {
    display: inline-block; margin-left: 8px;
    background: rgba(255,255,255,0.2); color: white;
    font-size: 12px; font-weight: 600; padding: 2px 10px; border-radius: 20px;
    vertical-align: middle;
}
.st-section-tag.all { background: rgba(255,255,255,0.12); }

/* Add Student button */
.st-add-btn {
    background: var(--accent); color: var(--dark);
    border: none; padding: 9px 20px; border-radius: 25px;
    font-size: 13.5px; font-weight: 700; cursor: pointer;
    transition: var(--transition); white-space: nowrap;
    display: inline-flex; align-items: center;
}
.st-add-btn:hover { background: var(--accent-light); transform: translateY(-1px); }

/* Student Avatar */
.st-avatar {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; color: white;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
}
.st-avatar.female { background: linear-gradient(135deg, #7c1a14, var(--secondary)); }

/* Section badge in table */
.st-section-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--primary-soft); color: var(--primary);
    font-size: 12px; font-weight: 800;
}

@media (max-width: 767px) {
    .st-class-list { max-height: 220px; }
    .st-panel-header { padding: 14px 16px; }
    .st-section-group { flex-direction: row; flex-wrap: wrap; }
    .st-section-pill { flex: 1; min-width: 80px; justify-content: center; }
}
</style>

<script>
$(document).ready(function () {
    // Section input uppercase
    $('input[name="section"]').on('input', function () {
        $(this).val($(this).val().toUpperCase());
    });

    // Edit student — populate modal
    $(document).on('click', '.edit-student-btn', function () {
        const s = $(this).data('student');
        $('#es_id').val(s.id);
        $('#es_sid').val(s.student_id_custom || '');
        $('#es_iemis').val(s.iemis_no || '');
        $('#es_name').val(s.full_name);
        $('#es_gender').val(s.gender);
        $('#es_dob').val(s.date_of_birth);
        $('#es_class').val(s.class_id);
        $('#es_section').val(s.section || 'A');
        $('#es_roll').val(s.roll_no       || '');
        $('#es_symbol').val(s.symbol_no   || '');
        $('#es_year').val(s.academic_year || '');
        $('#es_father').val(s.father_name   || '');
        $('#es_mother').val(s.mother_name   || '');
        $('#es_phone').val(s.guardian_phone || '');
        $('#es_status').val(s.status);
        $('#es_address').val(s.address || '');
        $('#editStudentModal').modal('show');
    });
});
</script>


<!-- Import CSV Modal -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:#7c3aed;color:white;">
                <h5 class="modal-title"><i class="fas fa-file-upload me-2"></i>Import Students from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Step 1: Upload -->
                <div id="importStep1">
                    <div class="alert alert-info" style="font-size:13px;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>How to use:</strong>
                        1) Download the template &nbsp;
                        <a href="students.php?export=template" class="alert-link">
                            <i class="fas fa-download me-1"></i>Download Template
                        </a>
                        &nbsp; 2) Fill data in Excel &nbsp; 3) Save as CSV &nbsp; 4) Upload here
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Importing to Class</label>
                            <input type="text" class="form-control" readonly
                                   value="<?= htmlspecialchars($selectedClass['class_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Default Section <span class="text-danger">*</span></label>
                            <input type="text" id="importDefaultSection" class="form-control"
                                   value="<?= htmlspecialchars($selectedSection ?: 'A') ?>"
                                   placeholder="A" maxlength="5">
                            <div class="form-text">Used if CSV has no section column.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Academic Year</label>
                            <input type="text" id="importAcadYear" class="form-control"
                                   value="<?= htmlspecialchars($acadYear) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" id="csvFileInput" class="form-control" accept=".csv">
                        <div class="form-text">Only .csv files. Max 2MB. First row must be headers.</div>
                    </div>
                    <button class="btn-admin-primary" onclick="parseCSV()">
                        <i class="fas fa-eye me-1"></i>Preview Data
                    </button>
                </div>

                <!-- Step 2: Preview -->
                <div id="importStep2" style="display:none;">
                    <div id="importSummary" class="alert mb-3" style="font-size:13px;"></div>
                    <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                        <table class="admin-table" id="importPreviewTable">
                            <thead>
                                <tr style="background:#0F3A1A;">
                                    <th style="background:#0F3A1A;color:white;">#</th>
                                    <th style="background:#0F3A1A;color:white;">Status</th>
                                    <th style="background:#0F3A1A;color:white;">Full Name</th>
                                    <th style="background:#0F3A1A;color:white;">Gender</th>
                                    <th style="background:#0F3A1A;color:white;">DOB</th>
                                    <th style="background:#0F3A1A;color:white;">Roll No</th>
                                    <th style="background:#0F3A1A;color:white;">Section</th>
                                    <th style="background:#0F3A1A;color:white;">Father Name</th>
                                    <th style="background:#0F3A1A;color:white;">Phone</th>
                                </tr>
                            </thead>
                            <tbody id="importPreviewBody"></tbody>
                        </table>
                    </div>

                    <!-- Hidden form for confirmed import -->
                    <form method="POST" action="students.php" id="importConfirmForm">
                        <input type="hidden" name="action" value="import_csv">
                        <input type="hidden" name="import_class_id" value="<?= $selectedClassId ?>">
                        <input type="hidden" name="import_section" id="hiddenSection">
                        <input type="hidden" name="import_acad_year" id="hiddenAcadYear">
                        <div id="importHiddenRows"></div>
                    </form>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-secondary" onclick="resetImport()">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </button>
                        <button class="btn-admin-primary" id="confirmImportBtn" onclick="confirmImport()">
                            <i class="fas fa-check me-1"></i>Import <span id="importValidCount">0</span> Students
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    body * { visibility: hidden !important; }
    #printArea, #printArea * { visibility: visible !important; }
    #printArea { position: fixed; inset: 0; padding: 20px; }
    .no-print { display: none !important; }
}
</style>

<!-- Hidden Print Area -->
<div id="printArea" style="display:none;">
    <?php if ($selectedClass && !empty($students)): ?>
    <div style="text-align:center;margin-bottom:16px;border-bottom:2px solid #0F3A1A;padding-bottom:10px;">
        <h3 style="margin:0;color:#0F3A1A;"><?= htmlspecialchars(getSetting($conn,'school_name')) ?></h3>
        <p style="margin:4px 0;font-size:13px;">
            Student List — <?= htmlspecialchars($selectedClass['class_name']) ?>
            <?= $selectedSection ? ' | Section '.$selectedSection : '' ?>
            <?= $filterStatus ? ' | '.ucfirst($filterStatus) : '' ?>
            &nbsp;&bull;&nbsp; Academic Year: <?= $acadYear ?>
        </p>
        <p style="margin:0;font-size:12px;color:#555;">Printed on: <?= date('F d, Y h:i A') ?></p>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
            <tr style="background:#0F3A1A;color:white;">
                <th style="border:1px solid #ccc;padding:6px 8px;">#</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Roll No</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Full Name</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Gender</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">DOB</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Section</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Father's Name</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Phone</th>
                <th style="border:1px solid #ccc;padding:6px 8px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $i => $st): ?>
            <tr style="<?= $i%2===0?'background:#f9f9f9;':'' ?>">
                <td style="border:1px solid #ddd;padding:5px 8px;text-align:center;"><?= $i+1 ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;"><?= htmlspecialchars($st['roll_no'] ?? '--') ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;font-weight:600;"><?= htmlspecialchars($st['full_name']) ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;"><?= ucfirst($st['gender']) ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;"><?= ($st['date_of_birth'] && $st['date_of_birth'] !== '0000-00-00') ? date('M d, Y', strtotime($st['date_of_birth'])) : '--' ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;text-align:center;"><?= htmlspecialchars($st['section']) ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;"><?= htmlspecialchars($st['father_name'] ?? '--') ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;"><?= htmlspecialchars($st['guardian_phone'] ?? '--') ?></td>
                <td style="border:1px solid #ddd;padding:5px 8px;"><?= ucfirst($st['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f0f0f0;">
                <td colspan="9" style="border:1px solid #ddd;padding:6px 8px;font-weight:600;">
                    Total: <?= count($students) ?> student<?= count($students)!=1?'s':'' ?>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>

<script>
function printStudentTable() {
    document.getElementById('printArea').style.display = 'block';
    window.print();
    document.getElementById('printArea').style.display = 'none';
}
</script>

<script>
function parseCSV() {
    var file = document.getElementById('csvFileInput').files[0];
    if (!file) { alert('Please select a CSV file first.'); return; }
    if (!file.name.endsWith('.csv')) { alert('Only .csv files allowed.'); return; }

    var reader = new FileReader();
    reader.onload = function(e) {
        var lines = e.target.result.split('\n');
        if (lines.length < 2) { alert('CSV file is empty or has no data rows.'); return; }

        // Parse headers
        var headers = parseCSVLine(lines[0]);
        headers = headers.map(function(h){ return h.trim().toLowerCase().replace(/\s+/g,'_'); });

        var validRows = [], invalidRows = [], allRows = [];

        for (var i = 1; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) continue;
            var cols = parseCSVLine(line);
            var row = {};
            headers.forEach(function(h, idx) {
                row[h] = (cols[idx] || '').trim();
            });

            // Validate
            var errors = [];
            if (!row['full_name']) errors.push('Name missing');
            if (!row['date_of_birth']) errors.push('DOB missing');
            if (row['gender'] && !['male','female','other'].includes(row['gender'].toLowerCase())) {
                row['gender'] = 'male'; // default
            }
            if (!row['gender']) row['gender'] = 'male';
            if (!row['section']) row['section'] = document.getElementById('importDefaultSection').value || 'A';
            if (!row['status']) row['status'] = 'active';

            row['_valid']  = errors.length === 0;
            row['_errors'] = errors.join(', ');
            allRows.push(row);
            if (row['_valid']) validRows.push(row); else invalidRows.push(row);
        }

        // Build preview table
        var tbody = document.getElementById('importPreviewBody');
        tbody.innerHTML = '';
        allRows.forEach(function(row, idx) {
            var bg = row['_valid'] ? '' : 'background:#fff3cd;';
            var badge = row['_valid']
                ? '<span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:12px;font-size:11px;">OK</span>'
                : '<span style="background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:12px;font-size:11px;" title="'+row['_errors']+'">Error</span>';
            tbody.innerHTML += '<tr style="'+bg+'">'
                + '<td>'+(idx+1)+'</td>'
                + '<td>'+badge+'</td>'
                + '<td><strong>'+(row['full_name']||'--')+'</strong></td>'
                + '<td>'+(row['gender']||'--')+'</td>'
                + '<td>'+(row['date_of_birth']||'--')+'</td>'
                + '<td>'+(row['roll_no']||'--')+'</td>'
                + '<td>'+(row['section']||'--')+'</td>'
                + '<td>'+(row['father_name']||'--')+'</td>'
                + '<td>'+(row['guardian_phone']||'--')+'</td>'
                + '</tr>';
        });

        // Summary
        var summary = document.getElementById('importSummary');
        summary.className = 'alert mb-3 ' + (invalidRows.length > 0 ? 'alert-warning' : 'alert-success');
        summary.innerHTML = '<i class="fas fa-info-circle me-2"></i>'
            + '<strong>'+allRows.length+' rows found:</strong> '
            + validRows.length+' valid (will import), '
            + invalidRows.length+' invalid (will skip).';

        document.getElementById('importValidCount').textContent = validRows.length;

        // Show step 2
        document.getElementById('importStep1').style.display = 'none';
        document.getElementById('importStep2').style.display = 'block';

        // Store valid rows for form submission
        window._importValidRows = validRows;
    };
    reader.readAsText(file, 'UTF-8');
}

function parseCSVLine(line) {
    var result = [], current = '', inQuotes = false;
    for (var i = 0; i < line.length; i++) {
        var ch = line[i];
        if (ch === '"') { inQuotes = !inQuotes; }
        else if (ch === ',' && !inQuotes) { result.push(current); current = ''; }
        else { current += ch; }
    }
    result.push(current);
    return result;
}

function confirmImport() {
    var rows = window._importValidRows || [];
    if (rows.length === 0) { alert('No valid rows to import.'); return; }

    document.getElementById('hiddenSection').value  = document.getElementById('importDefaultSection').value;
    document.getElementById('hiddenAcadYear').value = document.getElementById('importAcadYear').value;

    // Build hidden inputs
    var container = document.getElementById('importHiddenRows');
    container.innerHTML = '';
    var fields = ['full_name','gender','date_of_birth','roll_no','symbol_no',
                  'student_id_custom','iemis_no','section',
                  'father_name','mother_name','guardian_phone','address','status'];
    rows.forEach(function(row, i) {
        fields.forEach(function(f) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'rows['+i+']['+f+']';
            inp.value = row[f] || '';
            container.appendChild(inp);
        });
    });

    document.getElementById('confirmImportBtn').disabled = true;
    document.getElementById('confirmImportBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Importing...';
    document.getElementById('importConfirmForm').submit();
}

function resetImport() {
    document.getElementById('importStep1').style.display = 'block';
    document.getElementById('importStep2').style.display = 'none';
    document.getElementById('csvFileInput').value = '';
    document.getElementById('importPreviewBody').innerHTML = '';
}
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
