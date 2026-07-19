<?php
/* =====================================================
   RESULT VIEW PAGE
   Student result — Opens in new page after search
   ===================================================== */
require_once 'includes/db.php';
require_once 'includes/nepali_date.php';

// ── Get params ────────────────────────────────────────
$exam_year  = sanitize($conn, $_GET['exam_year']  ?? '');
$exam_type  = sanitize($conn, $_GET['exam_type']  ?? '');
$class_id   = (int)($_GET['class_id']             ?? 0);
$roll_no    = sanitize($conn, $_GET['roll_no']    ?? '');
$dob        = normalizeDate($_GET['dob']           ?? '');

if (!$exam_year || !$exam_type || !$class_id || !$roll_no || !$dob) {
    $pageTitle = 'Result Checker';
    require_once 'includes/header.php';
    ?>
    <div class="page-header">
        <div class="container"><h1>Check Result</h1></div>
    </div>
    <section>
        <div class="container text-center py-5">
            <i class="fas fa-info-circle fa-3x text-muted mb-4 d-block"></i>
            <h5 class="text-muted">Please fill all fields to check your result.</h5>
            <a href="results.php" class="btn-primary-custom mt-3">
                <i class="fas fa-arrow-left me-2"></i>Go to Result Checker
            </a>
        </div>
    </section>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// ── Find student ─────────────────────────────────────
// Note: class_id from URL is used for EXAM lookup, not student lookup
// Student is found by roll_no + DOB only (class may have changed after promotion)
$stmt = $conn->prepare("
    SELECT s.*, c.class_name, c.section as class_section
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE (s.roll_no = ? OR s.symbol_no = ?)
      AND s.date_of_birth = ?
    ORDER BY s.id DESC
    LIMIT 1
");
$stmt->bind_param("sss", $roll_no, $roll_no, $dob);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    $pageTitle = 'Result Not Found';
    require_once 'includes/header.php';
    ?>
    <div class="page-header">
        <div class="container"><h1>Result Not Found</h1></div>
    </div>
    <section>
        <div class="container text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-4 d-block"></i>
            <h4 class="text-muted">No student found with the provided details.</h4>
            <p class="text-muted mb-4">Please check your Roll No, Class, and Date of Birth.</p>
            <a href="results.php" class="btn-primary-custom">
                <i class="fas fa-arrow-left me-2"></i>Try Again
            </a>
        </div>
    </section>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// ── Find published exam ───────────────────────────────
$stmt2 = $conn->prepare("
    SELECT e.* FROM exams e
    JOIN result_publish rp ON e.id=rp.exam_id AND rp.class_id=e.class_id
    WHERE e.academic_year=? AND e.exam_type=? AND e.class_id=? AND rp.is_published=1
    LIMIT 1
");
$stmt2->bind_param("ssi", $exam_year, $exam_type, $class_id);
$stmt2->execute();
$exam = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if (!$exam) {
    $pageTitle = 'Result Not Published';
    require_once 'includes/header.php';
    ?>
    <div class="page-header"><div class="container"><h1>Result Not Published</h1></div></div>
    <section>
        <div class="container text-center py-5">
            <i class="fas fa-lock fa-4x text-muted mb-4 d-block"></i>
            <h4 class="text-muted">Results for this exam have not been published yet.</h4>
            <p class="text-muted mb-4">Please check back later.</p>
            <a href="results.php" class="btn-primary-custom"><i class="fas fa-arrow-left me-2"></i>Go Back</a>
        </div>
    </section>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// ── Get results ───────────────────────────────────────
$results = $conn->query("
    SELECT r.*, s.subject_name, s.subject_code,
           s.fm_1st_terminal, s.fm_2nd_terminal, s.fm_final,
           s.has_practical_1st, s.has_practical_2nd, s.has_practical_final,
           s.fm_practical_1st, s.fm_practical_2nd, s.fm_practical_final,
           s.full_marks as grand_fm
    FROM results r
    JOIN subjects s ON r.subject_id = s.id
    WHERE r.exam_id = {$exam['id']} AND r.student_id = {$student['id']}
    ORDER BY s.id
")->fetch_all(MYSQLI_ASSOC);

if (empty($results)) {
    $pageTitle = 'No Results Found';
    require_once 'includes/header.php';
    ?>
    <div class="page-header"><div class="container"><h1>No Results</h1></div></div>
    <section>
        <div class="container text-center py-5">
            <h4 class="text-muted">No result data found for this student.</h4>
            <a href="results.php" class="btn-primary-custom mt-3"><i class="fas fa-arrow-left me-2"></i>Go Back</a>
        </div>
    </section>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// ── For Final exam — get 1st & 2nd terminal results too ─
$is_final = ($exam['exam_type'] === 'final');
$results1st = []; $results2nd = [];
if ($is_final) {
    $exam1st = $conn->query("SELECT e.* FROM exams e JOIN result_publish rp ON e.id=rp.exam_id AND rp.class_id=e.class_id WHERE e.class_id=$class_id AND e.academic_year='$exam_year' AND e.exam_type='1st_terminal' AND rp.is_published=1 LIMIT 1")->fetch_assoc();
    $exam2nd = $conn->query("SELECT e.* FROM exams e JOIN result_publish rp ON e.id=rp.exam_id AND rp.class_id=e.class_id WHERE e.class_id=$class_id AND e.academic_year='$exam_year' AND e.exam_type='2nd_terminal' AND rp.is_published=1 LIMIT 1")->fetch_assoc();
    if ($exam1st) {
        $r1 = $conn->query("SELECT r.*, s.subject_code FROM results r JOIN subjects s ON r.subject_id=s.id WHERE r.exam_id={$exam1st['id']} AND r.student_id={$student['id']}")->fetch_all(MYSQLI_ASSOC);
        foreach($r1 as $row) $results1st[$row['subject_id']] = $row;
    }
    if ($exam2nd) {
        $r2 = $conn->query("SELECT r.*, s.subject_code FROM results r JOIN subjects s ON r.subject_id=s.id WHERE r.exam_id={$exam2nd['id']} AND r.student_id={$student['id']}")->fetch_all(MYSQLI_ASSOC);
        foreach($r2 as $row) $results2nd[$row['subject_id']] = $row;
    }
}

// ── Calculate totals & rank ───────────────────────────
$totalObtained = 0; $totalFM = 0; $totalGPA = 0; $hasFailed = false;
foreach ($results as $r) {
    if ($is_final) {
        $subId = $r['subject_id'];
        $m1 = $results1st[$subId]['total_obtained'] ?? 0;
        $m2 = $results2nd[$subId]['total_obtained'] ?? 0;
        $mF = $r['total_obtained'];
        $grandFM = (int)$r['grand_fm'];
        $combined = $m1 + $m2 + $mF;
        $totalObtained += $combined;
        $totalFM       += $grandFM;
    } else {
        $totalObtained += $r['total_obtained'];
        $totalFM       += $r['full_marks'];
    }
    $totalGPA += $r['grade_point'];
    if ($r['remarks'] === 'fail' || $r['remarks'] === 'absent') $hasFailed = true;
}
$subCount   = count($results);
$overallGPA = $subCount > 0 ? round($totalGPA / $subCount, 2) : 0;
$percentage = $totalFM > 0 ? round(($totalObtained / $totalFM) * 100, 2) : 0;
$finalStatus = $hasFailed ? 'FAIL' : 'PASS';

// Division
if ($percentage >= 60) $division = 'Distinction';
elseif ($percentage >= 45) $division = 'First Division';
elseif ($percentage >= 33) $division = 'Second Division';
else $division = 'Fail';

// Rank among class
// Overall rank — dense ranking (highest total = rank 1, same marks = same rank)
$rankQuery = $conn->query("
    SELECT s.id, SUM(r.total_obtained) as total
    FROM students s
    JOIN results r ON r.student_id=s.id AND r.exam_id={$exam['id']}
    WHERE s.class_id=$class_id AND s.status='active'
    GROUP BY s.id
    ORDER BY total DESC
");
$rank = 1; $classSize = 0;
$prevTotal = null; $denseRankO = 0;
while ($rankRow = $rankQuery->fetch_assoc()) {
    $classSize++;
    if ($rankRow['total'] != $prevTotal) { $denseRankO++; $prevTotal = $rankRow['total']; }
    if ($rankRow['id'] == $student['id']) $rank = $denseRankO;
}

// Subject-wise rank — highest marks = rank 1, same marks = same rank (dense)
$subjectRanks = [];
foreach ($results as $r) {
    $subId = $r['subject_id'];
    $rows = $conn->query("
        SELECT r2.student_id, r2.total_obtained
        FROM results r2
        JOIN students s2 ON r2.student_id = s2.id
        WHERE r2.exam_id = {$exam['id']}
          AND r2.subject_id = $subId
          AND s2.class_id = $class_id
          AND s2.status = 'active'
          AND r2.remarks NOT IN ('absent','exempted')
        ORDER BY r2.total_obtained DESC
    ")->fetch_all(MYSQLI_ASSOC);
    $subTotal  = count($rows);
    $subRank   = null;
    $prevMarks = null;
    $dense     = 0;
    foreach ($rows as $srRow) {
        if ($srRow['total_obtained'] != $prevMarks) { $dense++; $prevMarks = $srRow['total_obtained']; }
        if ($srRow['student_id'] == $student['id']) { $subRank = $dense; break; }
    }
    if ($subRank) $subjectRanks[$subId] = ['rank' => $subRank, 'total' => $subTotal];
}

// Attendance for this year
$attSummary = $conn->query("
    SELECT
        COUNT(DISTINCT attendance_date) as total_days,
        SUM(status='present') as present_days,
        SUM(status='absent') as absent_days
    FROM attendance
    WHERE student_id={$student['id']}
")->fetch_assoc();

// Publish date
$pubDate = $conn->query("SELECT published_at FROM result_publish WHERE exam_id={$exam['id']} AND class_id=$class_id")->fetch_assoc();

// School settings
$schoolName    = getSetting($conn, 'school_name');
$schoolAddress = getSetting($conn, 'school_address');
$schoolMotto   = getSetting($conn, 'school_motto');
$establishedBS = getSetting($conn, 'established_year');
$establishedAD = (int)$establishedBS + 57 - 1; // approximate

// Issue date in BS
$issueBS = getCurrentBS();

// Grade remarks mapping
function getGradeRemark($grade) {
    $map = [
        'A+' => 'Outstanding', 'A' => 'Excellent', 'B+' => 'Very Good',
        'B'  => 'Good', 'C+' => 'Satisfactory', 'C' => 'Acceptable',
        'D'  => 'Basic', 'NG' => 'Not Graded',
        'D+' => 'Partially Acceptable',
    ];
    return $map[$grade] ?? '';
}

$examTypeName = [
    '1st_terminal' => '1st Term Examination',
    '2nd_terminal' => '2nd Term Examination',
    'final'        => 'Annual Examination',
][$exam['exam_type']] ?? $exam['exam_name'];

// Now include header (after all data fetched)
$pageTitle = $examTypeName . ' ' . $exam_year . ' — Result';
require_once 'includes/header.php';
?>

<!-- ══ PAGE HEADER ═══════════════════════════════════ -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-file-alt me-2"></i>Result Card</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="results.php">Results</a></li>
                <li class="breadcrumb-item active">Result Card</li>
            </ol>
        </nav>
    </div>
</div>

<section style="background:var(--light);padding:40px 0;">
<div class="container">

    <!-- Print button -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <a href="results.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Search
        </a>
        <button onclick="window.print()" class="btn-primary-custom">
            <i class="fas fa-print me-2"></i>Print Result Card
        </button>
    </div>

    <!-- ══ RESULT CARD ══════════════════════════════ -->
    <div class="result-card-wrapper" id="resultCard">

        <!-- Decorative border frame -->
        <div class="rc-outer-border">
        <div class="rc-inner-border">

            <!-- School Header -->
            <div class="rc-school-header">
                <div class="rc-motto">"<?= htmlspecialchars($schoolMotto) ?>"</div>
                <div class="rc-school-name"><?= strtoupper(htmlspecialchars($schoolName)) ?></div>
                <div class="rc-school-address"><?= htmlspecialchars($schoolAddress) ?></div>
                <div class="rc-estd">[ Estd: <?= $establishedBS ?> BS (<?= $establishedAD ?> AD)]</div>
                <div class="rc-logo-row">
                    <img src="assets/images/<?= htmlspecialchars(getSetting($conn,'school_logo') ?: 'logo.jpg') ?>"
                         alt="Logo" class="rc-logo"
                         style="width:68px;height:68px;object-fit:contain;"
                         onerror="this.src='assets/images/logo.jpg'">
                </div>
                <div class="rc-title" style="padding:1.5rem;">PROGRESS REPORT</div>
            </div>

            <!-- Student Info Table -->
            <div class="rc-student-info">
                <table class="rc-info-table">
                    <tr>
                        <td class="rc-info-label">Student Name: <?= htmlspecialchars($student['full_name']) ?></td>
                        <td class="rc-info-label">Student ID: <?= htmlspecialchars($student['student_id_custom'] ?: $student['id']) ?></td>
                    </tr>
                    <tr>
                        <td class="rc-info-label">Class (Section): <?= htmlspecialchars($student['class_name'] ?? '') ?>
                            ( <?= htmlspecialchars($student['section'] ?? $student['class_section'] ?? 'A') ?> )</td>
                        </td>
                        <td class="rc-info-label">Roll No: <?= htmlspecialchars($student['roll_no'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="rc-info-label">Current Address: <?= htmlspecialchars($student['address'] ?: '') ?></td>
                        <td class="rc-info-label">Father Name: <?= htmlspecialchars($student['father_name'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="rc-info-label">Registration No (IEMIS NO.): <?= htmlspecialchars($student['iemis_no'] ?: ($student['symbol_no'] ?? '—')) ?></td>
                        <td class="rc-info-label">DOB: <?= htmlspecialchars($student['date_of_birth'] ?? '') ?></td>
                    </tr>
                </table>
            </div>

            <!-- Exam Title -->
            <div class="rc-exam-title">
                <?= htmlspecialchars($examTypeName) ?> <?= htmlspecialchars($exam_year) ?>
            </div>

            <!-- Marks Table -->
            <?php if (!$is_final): ?>
            <!-- 1st/2nd Term Format -->
            <table class="rc-marks-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="rc-th-sn">S.N.</th>
                        <th rowspan="2" class="rc-th-code">SUB.<br>CODE</th>
                        <th rowspan="2" class="rc-th-subject">SUBJECTS</th>
                        <th rowspan="2" class="rc-th-fm">F.M.</th>
                        <th colspan="2" class="rc-th-marksheet">MARK SHEET</th>
                        <th rowspan="2" class="rc-th-total">TOTAL<br>MARK</th>
                        <th rowspan="2" class="rc-th-grade">FINAL<br>GRADE</th>
                        <th rowspan="2" class="rc-th-rank">SUB.<br>RAN<br>K</th>
                        <th rowspan="2" class="rc-th-remarks">REMARKS</th>
                    </tr>
                    <tr>
                        <th class="rc-th-ex">EX</th>
                        <th class="rc-th-in">IN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $i => $r):
                        $gradeRemark = getGradeRemark($r['grade']);
                    ?>
                    <tr class="<?= $r['remarks']==='fail'||$r['remarks']==='absent'?'rc-row-fail':'' ?>">
                        <td class="text-center"><?= $i+1 ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['subject_code'] ?: '10'.(($i+1)<10?'0':''). ($i+1)) ?></td>
                        <td><?= htmlspecialchars($r['subject_name']) ?></td>
                        <td class="text-center"><?= $r['full_marks'] ?></td>
                        <td class="text-center fw-bold"><?= $r['remarks']==='absent'?'—':$r['total_obtained'] ?></td>
                        <td class="text-center"></td>
                        <td class="text-center fw-bold"><?= $r['remarks']==='absent'?'—':$r['total_obtained'] ?></td>
                        <td class="text-center fw-bold"><?= htmlspecialchars($r['grade']) ?></td>
                        <td class="text-center fw-bold">
                            <?php
                            $sr = $subjectRanks[$r['subject_id']] ?? null;
                            if ($r['remarks'] === 'absent') echo '—';
                            elseif ($sr) {
                                echo '<span style="font-size:12px;font-weight:800;color:'.($sr['rank']<=3?'#1a5c2a':'#333').'">'.$sr['rank'].'</span>';
                            } else echo '—';
                            ?>
                        </td>
                        <td class="text-center"><?= $gradeRemark ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
            <!-- Annual/Final Exam Format — with TH/IN split -->
            <table class="rc-marks-table">
                <thead>
                    <tr>
                        <th class="rc-th-code">SUB.<br>CODE</th>
                        <th class="rc-th-subject">SUBJECTS</th>
                        <th class="rc-th-ch">C.H.</th>
                        <th class="rc-th-gp">GRADE<br>POINT</th>
                        <th class="rc-th-grade">GRADE</th>
                        <th class="rc-th-grade">FINAL<br>GRADE</th>
                        <th class="rc-th-rank">SUB.<br>RANK</th>
                        <th class="rc-th-remarks">REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $i => $r):
                        $subId = $r['subject_id'];
                        $m1    = (float)($results1st[$subId]['total_obtained'] ?? 0);
                        $m2    = (float)($results2nd[$subId]['total_obtained'] ?? 0);
                        $mF    = (float)$r['total_obtained'];
                        $grandFM = (int)$r['grand_fm'];
                        $combined = $m1 + $m2 + $mF;
                        $finalGrade = calculateGrade($combined, $grandFM);
                        $gradeRemark = getGradeRemark($finalGrade['grade']);
                        // CH = credit hour (full_marks / 100 * some factor, simplified to 2.50 or 1.00)
                        $ch = $grandFM >= 100 ? '2.50' : '1.00';
                    ?>
                    <!-- TH row -->
                    <tr>
                        <td class="text-center" rowspan="2"><?= htmlspecialchars($r['subject_code'] ?: '01'.str_pad($i+1,2,'0',STR_PAD_LEFT)) ?> (TH)<br><?= htmlspecialchars($r['subject_code'] ?: '01'.str_pad($i+1,2,'0',STR_PAD_LEFT)) ?> (IN)</td>
                        <td rowspan="2"><?= htmlspecialchars($r['subject_name']) ?> (TH)<br><span style="font-size:11px;"><?= htmlspecialchars($r['subject_name']) ?> (IN)</span></td>
                        <td class="text-center" rowspan="2"><?= $ch ?><br><?= $ch ?></td>
                        <td class="text-center">
                            <?php
                            // TH = external marks (1st+2nd+final theory)
                            $thGrade = calculateGrade($m1 + $m2 + $r['obtained_marks'], $grandFM);
                            echo $thGrade['gpa'];
                            ?><br>
                            <?php
                            $inGrade = calculateGrade($r['practical_marks'], max(1, $grandFM * 0.5));
                            echo number_format($inGrade['gpa'], 2);
                            ?>
                        </td>
                        <td class="text-center fw-bold">
                            <?= $thGrade['grade'] ?><br><?= $inGrade['grade'] ?>
                        </td>
                        <td class="text-center fw-bold" rowspan="2">
                            <?= $finalGrade['grade'] ?>
                        </td>
                        <td class="text-center" rowspan="2">
                            <?php
                            $sr = $subjectRanks[$r['subject_id']] ?? null;
                            if ($r['remarks'] === 'absent') echo '—';
                            elseif ($sr) {
                                echo '<span style="font-size:12px;font-weight:800;color:'.($sr['rank']<=3?'#1a5c2a':'#333').'">'.$sr['rank'].'</span>';
                            } else echo '—';
                            ?>
                        </td>
                        <td class="text-center" rowspan="2"><?= $gradeRemark ?></td>
                    </tr>
                    <tr></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--light);font-weight:700;">
                        <td colspan="6" class="text-end pe-3">Grade Point Average (GPA):</td>
                        <td colspan="2" class="text-center fw-bold" style="font-size:15px;"><?= $overallGPA ?></td>
                    </tr>
                </tfoot>
            </table>
            <div class="rc-final-grade-row">
                Final Grade: <strong><?= htmlspecialchars($results[0]['grade'] ?? 'C+') ?></strong>
            </div>
            <?php endif; ?>

            <!-- Summary Row (1st/2nd term only) -->
            <?php if (!$is_final): ?>
            <table class="rc-summary-table">
                <tr>
                    <td>Total Mark: <strong><?= number_format($totalObtained, 2) ?></strong></td>
                    <td>Percentage: <strong><?= number_format($percentage, 2) ?></strong></td>
                    <td>
                        Result: <strong><?= $finalStatus ?></strong><br>
                        Division: <strong><?= $division ?></strong>
                    </td>
                    <td>Rank: <strong><?= $rank ?> / <?= $classSize ?></strong></td>
                </tr>
            </table>
            <?php endif; ?>

            <!-- Attendance -->
            <table class="rc-attendance-table">
                <tr>
                    <td><strong>ATTENDANCE</strong></td>
                    <td>Total School Days: <strong><?= $attSummary['total_days'] ?? 207 ?></strong></td>
                    <td>Present Days: <strong><?= $attSummary['present_days'] ?? 198 ?></strong></td>
                    <td>Absent Days: <strong><?= $attSummary['absent_days'] ?? 9 ?></strong></td>
                </tr>
            </table>

            <!-- Annual Grading Table -->
            <?php if ($is_final): ?>
            <div class="rc-grading-section">
                <div class="rc-grading-title">Description of Grading System</div>
                <table class="rc-grading-table">
                    <thead>
                        <tr>
                            <th>Achievement in Percentage</th>
                            <th>Grade</th>
                            <th>Description</th>
                            <th>Grade Point</th>
                            <th>Achievement in Percentage</th>
                            <th>Grade</th>
                            <th>Description</th>
                            <th>Grade Point</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>>=90</td><td>A+</td><td>Outstanding</td><td>4.0</td>
                            <td>>=80 and &lt;90</td><td>A</td><td>Excellent</td><td>3.6</td>
                        </tr>
                        <tr>
                            <td>>=70 and &lt;80</td><td>B+</td><td>Very Good</td><td>3.2</td>
                            <td>>=60 and &lt;70</td><td>B</td><td>Good</td><td>2.8</td>
                        </tr>
                        <tr>
                            <td>>=50 and &lt;60</td><td>C+</td><td>Satisfactory</td><td>2.4</td>
                            <td>>=40 and &lt;50</td><td>C</td><td>Acceptable</td><td>2.0</td>
                        </tr>
                        <tr>
                            <td>>=35 and &lt;40</td><td>D</td><td>Basic</td><td>1.6</td>
                            <td>&lt;35</td><td>NG</td><td>Not Graded</td><td>0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Remarks -->
            <div class="rc-remarks-section">
                <div>
                    <strong>Remarks:</strong>
                    <em>
                        <?php
                        if ($percentage >= 90) echo 'Excellent performance! Keep it up.';
                        elseif ($percentage >= 80) echo 'Very good result! Continue your hard work.';
                        elseif ($percentage >= 70) echo 'Good work! Keep improving.';
                        elseif ($percentage >= 60) echo 'Satisfactory result. Work harder next time.';
                        elseif ($percentage >= 50) echo 'Average result. You need to study more.';
                        elseif ($percentage >= 40) echo 'Increase your study level...';
                        else echo 'Need significant improvement. Please study regularly.';
                        ?>
                    </em>
                </div>
                <div class="mt-2" style="font-size:12px;">
                    Issue Date: <?= $issueBS['year'] ?>-<?= str_pad($issueBS['month'],2,'0',STR_PAD_LEFT) ?>-<?= str_pad($issueBS['day'],2,'0',STR_PAD_LEFT) ?>
                    (<?= date('Y-m-d') ?>)<br>
                    Note: O.G. = Obtained Grade | EX = External | IN = Internal | CH = Credit Hour
                </div>
            </div>

            <!-- Signatures -->
            <div class="rc-signatures">
                <div class="rc-sig-col">
                    <div class="rc-sig-line"></div>
                    <div class="rc-sig-name">Irshad Alam Ansari</div>
                    <div class="rc-sig-title">Prepared by</div>
                </div>
                <div class="rc-sig-col">
                    <div class="rc-sig-line"></div>
                    <div class="rc-sig-name">&nbsp;</div>
                    <div class="rc-sig-title">Class Teacher</div>
                </div>
                <div class="rc-sig-col">
                    <div class="rc-sig-line"></div>
                    <div class="rc-sig-name">Luquman Ansari</div>
                    <div class="rc-sig-title">Exam Controller</div>
                </div>
                <div class="rc-sig-col">
                    <div class="rc-sig-line"></div>
                    <div class="rc-sig-name">Jawed Ansari</div>
                    <div class="rc-sig-title">Principal</div>
                </div>
            </div>

        </div><!-- /.rc-inner-border -->
        </div><!-- /.rc-outer-border -->

    </div><!-- /.result-card-wrapper -->

    <!-- Bottom buttons -->
    <div class="d-flex justify-content-center gap-3 mt-4 no-print">
        <a href="results.php" class="btn btn-outline-secondary">
            <i class="fas fa-search me-2"></i>Check Another Result
        </a>
        <button onclick="window.print()" class="btn-primary-custom">
            <i class="fas fa-print me-2"></i>Print Result Card
        </button>
    </div>

</div>
</section>

<style>
/* ── Result Card Wrapper ──────────────────────────── */
.result-card-wrapper {
    max-width: 820px;
    margin: 0 auto;
    font-family: 'Times New Roman', Times, serif;
    font-size: 13px;
    color: #000;
}

/* Decorative borders */
.rc-outer-border {
    border: 4px double #2c2c2c;
    padding: 6px;
    background: white;
    box-shadow: 0 4px 24px rgba(0,0,0,0.15);
}
.rc-inner-border {
    border: 2px solid #2c2c2c;
    padding: 12px 16px;
    position: relative;
    background: white;
}

/* Corner decorations */
.rc-inner-border::before,
.rc-inner-border::after {
    content: '❧';
    position: absolute;
    font-size: 22px;
    color: #555;
}
.rc-inner-border::before { top: 6px; left: 10px; }
.rc-inner-border::after  { bottom: 6px; right: 10px; }

/* School Header */
.rc-school-header { text-align: center; margin-bottom: 6px; line-height: 1.3; }
.rc-motto         { font-style: italic; font-size: 11.5px; margin-bottom: 2px; line-height: 1.2; }
.rc-school-name   { font-size: 20px; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 2px; line-height: 1.2; }
.rc-school-address{ font-size: 11.5px; font-weight: 600; margin-bottom: 1px; line-height: 1.2; }
.rc-estd          { font-size: 11.5px; margin-bottom: 4px; line-height: 1.2; }
.rc-logo-row      { position: absolute; top: 24px; left: 32px; }
.rc-logo          { width: 68px; height: 68px; object-fit: contain; }
.rc-title         {
    font-size: 16px; font-weight: 900;
    letter-spacing: 2px; text-transform: uppercase;
    text-decoration: underline;
    text-underline-offset: 4px;
    padding: 5px 0; margin: 8px 0 12px;
    text-align: center;
}

/* Student Info */
.rc-student-info  { margin-bottom: 6px; }
.rc-info-table    { width: 100%; border-collapse: collapse; }
.rc-info-table td { border: 1px solid #666; padding: 3px 6px; font-size: 12px; }
.rc-info-label    { font-weight: 700; background: #f8f8f8; width: 25%; white-space: nowrap; }
.rc-info-value    { width: 25%; }

/* Exam title */
.rc-exam-title {
    text-align: center;
    font-size: 14px;
    font-weight: 800;
    text-decoration: underline;
    margin: 6px 0 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Marks table */
.rc-marks-table {
    width: 100%; border-collapse: collapse;
    margin-bottom: 4px; font-size: 11.5px;
    table-layout: auto;
}
.rc-marks-table th, .rc-marks-table td {
    border: 1px solid #555;
    padding: 3px 4px;
    text-align: center;
    vertical-align: middle;
    line-height: 1.3;
    white-space: nowrap;
}
.rc-marks-table thead th { background: #e8e8e8; font-weight: 800; font-size: 10.5px; }
.rc-marks-table tbody td:nth-child(3) { text-align: left; padding-left: 6px; white-space: normal; }
.rc-marks-table .rc-th-subject { text-align: left; padding-left: 6px; }
.rc-marks-table .rc-th-marksheet { background: #d0d0d0; }
.rc-row-fail td { background: #fff0f0; color: #b00; }

/* Summary table */
.rc-summary-table {
    width: 100%; border-collapse: collapse;
    margin-bottom: 3px; font-size: 12px;
}
.rc-summary-table td {
    border: 1px solid #555;
    padding: 3px 8px;
}

/* Attendance */
.rc-attendance-table {
    width: 100%; border-collapse: collapse;
    margin: 3px 0; font-size: 12px;
}
.rc-attendance-table td {
    border: 1px solid #555;
    padding: 3px 8px;
}
.rc-attendance-table td:first-child { font-weight: 700; background: #e8e8e8; width: 18%; }

/* Final grade row (annual) */
.rc-final-grade-row {
    border: 1px solid #555;
    padding: 5px 10px;
    font-size: 13px;
    margin: 4px 0;
    background: #f5f5f5;
}

/* Grading system (annual) */
.rc-grading-section { margin: 10px 0 6px; }
.rc-grading-title {
    text-align: center; font-weight: 800; font-size: 13px;
    text-decoration: underline; margin-bottom: 5px;
}
.rc-grading-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.rc-grading-table th, .rc-grading-table td {
    border: 1px solid #555; padding: 3px 6px; text-align: center;
}
.rc-grading-table thead th { background: #e8e8e8; font-weight: 700; }

/* Remarks */
.rc-remarks-section {
    border: 1px solid #555;
    padding: 4px 8px;
    margin: 4px 0;
    font-size: 11.5px;
    line-height: 1.5;
}

/* Signatures */
.rc-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 18px;
    padding-top: 6px;
}
.rc-sig-col { text-align: center; flex: 1; }
.rc-sig-line {
    border-bottom: 1px solid #000;
    width: 80%;
    margin: 0 auto 4px;
    height: 30px;
}
.rc-sig-name  { font-size: 12px; font-weight: 700; }
.rc-sig-title { font-size: 11px; font-style: italic; }

/* ── Print styles ─────────────────────────────────── */
@media print {
    .no-print, header, footer, .page-header, nav, .top-bar { display: none !important; }
    body { background: white !important; }
    section { padding: 0 !important; background: white !important; }
    .result-card-wrapper { max-width: 100%; margin: 0; box-shadow: none; }
    .rc-outer-border { box-shadow: none; }
}

/* ── Screen only enhancements ─────────────────────── */
@media screen {
    .result-card-wrapper {
        background: white;
        border-radius: 4px;
    }
}

@media (max-width: 767px) {
    .rc-school-name { font-size: 16px; }
    .rc-logo-row    { display: none; }
    .rc-marks-table, .rc-summary-table { font-size: 11px; }
    .rc-marks-table th, .rc-marks-table td { padding: 3px 2px; }
    .rc-signatures  { flex-wrap: wrap; gap: 20px; }
    .rc-sig-col     { min-width: 45%; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
