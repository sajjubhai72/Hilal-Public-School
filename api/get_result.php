<?php
/* =====================================================
   PUBLIC RESULT CHECKER API
   POST: exam_year, exam_type, class_id, roll_no, dob

   Logic:
   - 1st Terminal → single exam marks
   - 2nd Terminal → single exam marks
   - Final        → tino exam (1st + 2nd + Final) combine
                    subject-wise dekhaucha + grand total
   ===================================================== */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// ── Input sanitize ────────────────────────────────────
$exam_year = sanitize($conn, $_POST['exam_year'] ?? '');
$exam_type = sanitize($conn, $_POST['exam_type'] ?? '');
$class_id  = (int)($_POST['class_id'] ?? 0);
$roll_no   = sanitize($conn, $_POST['roll_no']   ?? '');
$dob       = normalizeDate($_POST['dob'] ?? '');

if (!$exam_year || !$exam_type || !$class_id || !$roll_no || !$dob) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

$validTypes = ['1st_terminal', '2nd_terminal', 'final'];
if (!in_array($exam_type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam type.']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date of birth.']);
    exit();
}

// ── Step 1: Find student ─────────────────────────────
$stmt = $conn->prepare("
    SELECT s.*, c.class_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE (s.roll_no = ? OR s.symbol_no = ?)
      AND s.class_id = ?
      AND s.date_of_birth = ?
      AND s.status = 'active'
    LIMIT 1
");
$stmt->bind_param("ssis", $roll_no, $roll_no, $class_id, $dob);
$stmt->execute();
$studentRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$studentRow) {
    echo json_encode(['success' => false, 'message' => 'No student found. Please check your Roll No, Class, and Date of Birth.']);
    exit();
}

// ── Step 2: Find published exam ──────────────────────
$stmt2 = $conn->prepare("
    SELECT e.*
    FROM exams e
    JOIN result_publish rp ON e.id = rp.exam_id AND rp.class_id = e.class_id
    WHERE e.academic_year = ?
      AND e.exam_type = ?
      AND e.class_id = ?
      AND rp.is_published = 1
    LIMIT 1
");
$stmt2->bind_param("ssi", $exam_year, $exam_type, $class_id);
$stmt2->execute();
$exam = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if (!$exam) {
    echo json_encode(['success' => false, 'message' => 'Results for this exam have not been published yet.']);
    exit();
}

$studentId = $studentRow['id'];

// ── Helper: fetch results for one exam ───────────────
function fetchExamResults($conn, $examId, $studentId) {
    $stmt = $conn->prepare("
        SELECT r.*,
               sub.subject_name,
               sub.fm_1st_terminal, sub.fm_2nd_terminal, sub.fm_final,
               sub.fm_practical_1st, sub.fm_practical_2nd, sub.fm_practical_final,
               sub.has_practical_1st, sub.has_practical_2nd, sub.has_practical_final,
               sub.full_marks as grand_fm
        FROM results r
        JOIN subjects sub ON r.subject_id = sub.id
        WHERE r.exam_id = ? AND r.student_id = ?
        ORDER BY sub.id
    ");
    $stmt->bind_param("ii", $examId, $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ── Helper: find published exam by type ──────────────
function findPublishedExam($conn, $exam_year, $exam_type, $class_id) {
    $stmt = $conn->prepare("
        SELECT e.*
        FROM exams e
        JOIN result_publish rp ON e.id = rp.exam_id AND rp.class_id = e.class_id
        WHERE e.academic_year = ? AND e.exam_type = ?
          AND e.class_id = ? AND rp.is_published = 1
        LIMIT 1
    ");
    $stmt->bind_param("ssi", $exam_year, $exam_type, $class_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

// ══════════════════════════════════════════════════════
// CASE A: 1st or 2nd Terminal — single exam simple view
// ══════════════════════════════════════════════════════
if ($exam_type !== 'final') {

    $rows = fetchExamResults($conn, $exam['id'], $studentId);

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'No result data found for this student.']);
        exit();
    }

    $subjects      = [];
    $totalObtained = 0;
    $totalFull     = 0;
    $totalGPA      = 0;
    $hasFailed     = false;
    $hasPractical  = false;

    foreach ($rows as $row) {
        $grade = calculateGrade($row['total_obtained'], $row['full_marks']);
        if ($row['remarks'] === 'fail' || $row['remarks'] === 'absent') $hasFailed = true;
        if ((float)$row['practical_marks'] > 0) $hasPractical = true;

        $subjects[] = [
            'subject_name'    => $row['subject_name'],
            'full_marks'      => $row['full_marks'],
            'pass_marks'      => $row['pass_marks'],
            'obtained_marks'  => $row['obtained_marks'],
            'practical_marks' => $row['practical_marks'],
            'total_obtained'  => $row['total_obtained'],
            'grade'           => $grade['grade'],
            'grade_point'     => $grade['gpa'],
            'has_practical'   => ((float)$row['practical_marks'] > 0),
            'remarks'         => $row['remarks'],
        ];
        $totalObtained += $row['total_obtained'];
        $totalFull     += $row['full_marks'];
        $totalGPA      += $grade['gpa'];
    }

    $subCount   = count($subjects);
    $overallGPA = $subCount > 0 ? round($totalGPA / $subCount, 2) : 0;
    $percentage = $totalFull > 0 ? round(($totalObtained / $totalFull) * 100, 2) : 0;

    $publishData = $conn->query("
        SELECT published_at FROM result_publish
        WHERE exam_id={$exam['id']} AND class_id=$class_id
    ")->fetch_assoc();

    echo json_encode([
        'success'        => true,
        'is_final'       => false,
        'student'        => [
            'full_name'  => $studentRow['full_name'],
            'roll_no'    => $studentRow['roll_no'],
            'symbol_no'  => $studentRow['symbol_no'],
            'class_name' => $studentRow['class_name'],
        ],
        'exam'           => [
            'exam_name'     => $exam['exam_name'],
            'exam_type'     => $exam['exam_type'],
            'academic_year' => $exam['academic_year'],
        ],
        'subjects'       => $subjects,
        'has_practical'  => $hasPractical,
        'total_obtained' => $totalObtained,
        'total_full'     => $totalFull,
        'percentage'     => $percentage,
        'overall_gpa'    => $overallGPA,
        'final_status'   => $hasFailed ? 'FAIL' : 'PASS',
        'published_at'   => $publishData ? date('d M Y', strtotime($publishData['published_at'])) : 'N/A',
    ]);
    exit();
}

// ══════════════════════════════════════════════════════
// CASE B: Final Exam — tino exam combine garnu
// ══════════════════════════════════════════════════════

// Find 1st and 2nd terminal exams (published or not — show if data exists)
$exam1st = findPublishedExam($conn, $exam_year, '1st_terminal', $class_id);
$exam2nd = findPublishedExam($conn, $exam_year, '2nd_terminal', $class_id);

// Fetch results for each exam
$rows1st   = $exam1st ? fetchExamResults($conn, $exam1st['id'],  $studentId) : [];
$rows2nd   = $exam2nd ? fetchExamResults($conn, $exam2nd['id'],  $studentId) : [];
$rowsFinal = fetchExamResults($conn, $exam['id'], $studentId);

if (empty($rowsFinal)) {
    echo json_encode(['success' => false, 'message' => 'No final exam result data found.']);
    exit();
}

// Build subject map indexed by subject_id
function buildSubjectMap($rows) {
    $map = [];
    foreach ($rows as $r) {
        $map[$r['subject_id']] = $r;
    }
    return $map;
}

$map1st   = buildSubjectMap($rows1st);
$map2nd   = buildSubjectMap($rows2nd);
$mapFinal = buildSubjectMap($rowsFinal);

$subjects      = [];
$grandObtained = 0;
$grandFull     = 0;
$grandGPA      = 0;
$hasFailed     = false;
$hasPractical  = false;

foreach ($rowsFinal as $row) {
    $subId     = $row['subject_id'];
    $r1        = $map1st[$subId]   ?? null;
    $r2        = $map2nd[$subId]   ?? null;
    $rF        = $row;

    // Per-exam marks
    $marks1st  = $r1 ? (float)$r1['total_obtained']  : 0;
    $marks2nd  = $r2 ? (float)$r2['total_obtained']  : 0;
    $marksFinal= (float)$rF['total_obtained'];

    // Per-exam FM from subject table
    $fm1st     = (int)$rF['fm_1st_terminal']  + (int)$rF['fm_practical_1st'];
    $fm2nd     = (int)$rF['fm_2nd_terminal']  + (int)$rF['fm_practical_2nd'];
    $fmFinal   = (int)$rF['fm_final']         + (int)$rF['fm_practical_final'];
    $grandFM   = (int)$rF['grand_fm'];  // total full marks = fm1+fm2+fmF

    // Combined total
    $totalObt  = $marks1st + $marks2nd + $marksFinal;

    // Grade based on grand total
    $grade     = calculateGrade($totalObt, $grandFM);

    // Has practical in any exam
    if ($rF['has_practical_1st'] || $rF['has_practical_2nd'] || $rF['has_practical_final']) {
        $hasPractical = true;
    }

    // Fail check — if any exam absent/fail = overall fail
    $remarksFinal = $rF['remarks'] ?? 'pass';
    $remarks1st   = $r1 ? ($r1['remarks'] ?? 'pass') : 'N/A';
    $remarks2nd   = $r2 ? ($r2['remarks'] ?? 'pass') : 'N/A';

    if ($remarksFinal === 'fail' || $remarksFinal === 'absent') $hasFailed = true;

    $subjects[] = [
        'subject_name'  => $rF['subject_name'],

        // 1st terminal
        'marks_1st'     => $r1 ? $marks1st   : null,
        'fm_1st'        => $fm1st,
        'remarks_1st'   => $remarks1st,

        // 2nd terminal
        'marks_2nd'     => $r2 ? $marks2nd   : null,
        'fm_2nd'        => $fm2nd,
        'remarks_2nd'   => $remarks2nd,

        // Final
        'marks_final'   => $marksFinal,
        'fm_final'      => $fmFinal,
        'remarks_final' => $remarksFinal,

        // Combined
        'grand_fm'      => $grandFM,
        'total_obtained'=> round($totalObt, 2),
        'grade'         => $grade['grade'],
        'grade_point'   => $grade['gpa'],
    ];

    $grandObtained += $totalObt;
    $grandFull     += $grandFM;
    $grandGPA      += $grade['gpa'];
}

$subCount    = count($subjects);
$overallGPA  = $subCount > 0 ? round($grandGPA / $subCount, 2) : 0;
$percentage  = $grandFull > 0 ? round(($grandObtained / $grandFull) * 100, 2) : 0;

$publishFinal = $conn->query("
    SELECT published_at FROM result_publish
    WHERE exam_id={$exam['id']} AND class_id=$class_id
")->fetch_assoc();

echo json_encode([
    'success'        => true,
    'is_final'       => true,
    'student'        => [
        'full_name'  => $studentRow['full_name'],
        'roll_no'    => $studentRow['roll_no'],
        'symbol_no'  => $studentRow['symbol_no'],
        'class_name' => $studentRow['class_name'],
    ],
    'exam'           => [
        'exam_name'     => $exam['exam_name'],
        'exam_type'     => 'final',
        'academic_year' => $exam['academic_year'],
    ],
    'subjects'       => $subjects,
    'has_practical'  => $hasPractical,
    'has_1st'        => !empty($rows1st),
    'has_2nd'        => !empty($rows2nd),
    'total_obtained' => round($grandObtained, 2),
    'total_full'     => $grandFull,
    'percentage'     => $percentage,
    'overall_gpa'    => $overallGPA,
    'final_status'   => $hasFailed ? 'FAIL' : 'PASS',
    'published_at'   => $publishFinal ? date('d M Y', strtotime($publishFinal['published_at'])) : 'N/A',
]);
