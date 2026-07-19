<?php
$pageTitle = 'Enter Marks';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

// ── Helper: get per-exam FM ────────────────────────────
function getExamFM($sub, $examType) {
    switch ($examType) {
        case '1st_terminal':
            return ['theory_fm'=>(int)$sub['fm_1st_terminal'],'theory_pm'=>(int)$sub['pm_1st_terminal'],
                    'has_practical'=>(int)$sub['has_practical_1st'],'practical_fm'=>(int)$sub['fm_practical_1st']];
        case '2nd_terminal':
            return ['theory_fm'=>(int)$sub['fm_2nd_terminal'],'theory_pm'=>(int)$sub['pm_2nd_terminal'],
                    'has_practical'=>(int)$sub['has_practical_2nd'],'practical_fm'=>(int)$sub['fm_practical_2nd']];
        default: // final
            return ['theory_fm'=>(int)$sub['fm_final'],'theory_pm'=>(int)$sub['pm_final'],
                    'has_practical'=>(int)$sub['has_practical_final'],'practical_fm'=>(int)$sub['fm_practical_final']];
    }
}

// ── Save marks (POST) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marks'])) {
    $examId    = (int)($_POST['exam_id']    ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $marksData = $_POST['marks'] ?? [];

    if ($examId && $subjectId && !empty($marksData)) {
        $examInfo = $conn->query("SELECT * FROM exams WHERE id=$examId")->fetch_assoc();
        $subInfo  = $conn->query("SELECT * FROM subjects WHERE id=$subjectId")->fetch_assoc();

        if ($examInfo && $subInfo) {
            $pub = $conn->query("SELECT is_published FROM result_publish WHERE exam_id=$examId AND class_id={$examInfo['class_id']}")->fetch_assoc();
            if ($pub && $pub['is_published']) {
                $message = 'Results already published. Cannot modify.'; $messageType = 'danger';
            } else {
                $fm       = getExamFM($subInfo, $examInfo['exam_type']);
                $totalFM  = $fm['theory_fm'] + ($fm['has_practical'] ? $fm['practical_fm'] : 0);
                $saved = 0;
                foreach ($marksData as $studentId => $data) {
                    $studentId = (int)$studentId;
                    $obtained  = min((float)($data['obtained']  ?? 0), $fm['theory_fm']);
                    $practical = min((float)($data['practical'] ?? 0), $fm['has_practical'] ? $fm['practical_fm'] : 0);
                    $remarks   = sanitize($conn, $data['remarks'] ?? 'pass');
                    if ($remarks === 'absent') { $obtained = 0; $practical = 0; }
                    $total     = $obtained + $practical;
                    $grade     = calculateGrade($total, $totalFM);
                    $stmt = $conn->prepare("
                        INSERT INTO results
                          (exam_id,student_id,subject_id,obtained_marks,practical_marks,
                           total_obtained,full_marks,pass_marks,grade,grade_point,remarks,entered_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                          obtained_marks=VALUES(obtained_marks),practical_marks=VALUES(practical_marks),
                          total_obtained=VALUES(total_obtained),full_marks=VALUES(full_marks),
                          grade=VALUES(grade),grade_point=VALUES(grade_point),
                          remarks=VALUES(remarks),entered_by=VALUES(entered_by),updated_at=NOW()
                    ");
                    $stmt->bind_param("iiidddiisdsi",
                        $examId,$studentId,$subjectId,$obtained,$practical,
                        $total,$totalFM,$fm['theory_pm'],
                        $grade['grade'],$grade['gpa'],$remarks,$teacherId);
                    if ($stmt->execute()) $saved++;
                    $stmt->close();
                }
                $message = "Marks saved for $saved students!"; $messageType = 'success';
            }
        }
    }

    // PRG — redirect to prevent re-submit on refresh
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['marks_message']      = $message;
    $_SESSION['marks_message_type'] = $messageType;
    $qs = http_build_query([
        'class_id'   => $_POST['class_id']    ?? 0,
        'section'    => $_POST['section']     ?? '',
        'exam_id'    => $_POST['exam_id']     ?? 0,
        'subject_id' => $_POST['subject_id']  ?? 0,
    ]);
    header("Location: marks_entry.php?$qs");
    exit();
}

// ── Flash message from session ────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
$message     = $_SESSION['marks_message']      ?? '';
$messageType = $_SESSION['marks_message_type'] ?? 'success';
unset($_SESSION['marks_message'], $_SESSION['marks_message_type']);

// ── Teacher's assigned classes/sections ───────────────
$myClasses = $conn->query("
    SELECT DISTINCT tc.class_id, tc.section, c.class_name, c.id
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.id
    WHERE tc.teacher_id = $teacherId
    ORDER BY c.id, tc.section
")->fetch_all(MYSQLI_ASSOC);

// Selected filters
$selectedClassId  = (int)($_GET['class_id']   ?? 0);
$selectedSection  = sanitize($conn, $_GET['section']    ?? '');
$selectedExamId   = (int)($_GET['exam_id']    ?? 0);
$selectedSubjectId= (int)($_GET['subject_id'] ?? 0);

// Auto-select first class
if (!$selectedClassId && !empty($myClasses)) {
    $selectedClassId = $myClasses[0]['class_id'];
    $selectedSection = $myClasses[0]['section'];
}

// ── Exams for selected class (unpublished) ────────────
$exams = [];
if ($selectedClassId) {
    $exams = $conn->query("
        SELECT DISTINCT e.id, e.exam_name, e.exam_type, e.academic_year
        FROM exams e
        JOIN teacher_classes tc ON tc.class_id=e.class_id AND tc.teacher_id=$teacherId
        LEFT JOIN result_publish rp ON rp.exam_id=e.id AND rp.class_id=e.class_id
        WHERE e.class_id=$selectedClassId AND COALESCE(rp.is_published,0)=0
        ORDER BY e.id DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

// ── Subjects for selected class (teacher's) ───────────
$subjects = [];
$examInfo = null;
if ($selectedExamId && $selectedClassId) {
    $examInfo = $conn->query("SELECT * FROM exams WHERE id=$selectedExamId")->fetch_assoc();
    $subjects = $conn->query("
        SELECT s.* FROM subjects s
        JOIN teacher_classes tc ON tc.subject_id=s.id
            AND tc.class_id=$selectedClassId AND tc.teacher_id=$teacherId
        WHERE s.class_id=$selectedClassId AND s.status='active'
        ORDER BY s.subject_name
    ")->fetch_all(MYSQLI_ASSOC);
}

// ── Students for selected class/section ───────────────
$students = [];
$subInfo  = null;
$examFM   = null;
$totalFM  = 0;

if ($selectedExamId && $selectedClassId && $selectedSubjectId && $selectedSection) {
    $subInfo = $conn->query("SELECT * FROM subjects WHERE id=$selectedSubjectId")->fetch_assoc();
    $examFM  = getExamFM($subInfo, $examInfo['exam_type']);
    $totalFM = $examFM['theory_fm'] + ($examFM['has_practical'] ? $examFM['practical_fm'] : 0);

    $students = $conn->query("
        SELECT s.*,
               COALESCE(r.obtained_marks,0)   as obtained,
               COALESCE(r.practical_marks,0)  as practical,
               COALESCE(r.remarks,'pass')     as result_remarks,
               r.grade, r.grade_point
        FROM students s
        LEFT JOIN results r ON r.student_id=s.id
            AND r.exam_id=$selectedExamId AND r.subject_id=$selectedSubjectId
        WHERE s.class_id=$selectedClassId AND s.section='$selectedSection' AND s.status='active'
        ORDER BY CAST(s.roll_no AS UNSIGNED), s.full_name
    ")->fetch_all(MYSQLI_ASSOC);
}

require_once 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Step 1: Select Class/Section → Exam → Subject -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h6><i class="fas fa-filter me-2"></i>Select Class, Exam &amp; Subject</h6>
    </div>
    <div class="admin-card-body">
        <form method="GET" class="admin-form" id="filterForm">
            <div class="row g-3 align-items-end">

                <!-- Class + Section -->
                <div class="col-md-3">
                    <label class="form-label">Class &amp; Section <span class="text-danger">*</span></label>
                    <select name="class_id" id="classSelect" class="form-select" onchange="submitFilter()">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($myClasses as $mc): ?>
                        <option value="<?= $mc['class_id'] ?>"
                                data-section="<?= htmlspecialchars($mc['section']) ?>"
                                <?= ($mc['class_id']==$selectedClassId && $mc['section']===$selectedSection)?'selected':'' ?>>
                            <?= htmlspecialchars($mc['class_name']) ?> — Sec <?= htmlspecialchars($mc['section']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="section" id="sectionHidden" value="<?= htmlspecialchars($selectedSection) ?>">
                </div>

                <!-- Exam -->
                <div class="col-md-3">
                    <label class="form-label">Exam</label>
                    <select name="exam_id" class="form-select" onchange="submitFilter()"
                            <?= empty($exams)?'disabled':'' ?>>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $selectedExamId==$ex['id']?'selected':'' ?>>
                            <?= htmlspecialchars($ex['exam_name']) ?> (<?= $ex['academic_year'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Subject -->
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" onchange="submitFilter()"
                            <?= empty($subjects)?'disabled':'' ?>>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub):
                            $sFM = getExamFM($sub, $examInfo['exam_type'] ?? '');
                            $sTot = $sFM['theory_fm'] + ($sFM['has_practical']?$sFM['practical_fm']:0);
                        ?>
                        <option value="<?= $sub['id'] ?>" <?= $selectedSubjectId==$sub['id']?'selected':'' ?>>
                            <?= htmlspecialchars($sub['subject_name']) ?> (FM:<?= $sTot ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <a href="marks_entry.php" class="btn-admin-warning w-100" style="justify-content:center;">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Step 2: Marks Table -->
<?php if ($selectedSubjectId && !empty($students) && $subInfo && $examInfo && $examFM): ?>

<!-- Info Banner -->
<div class="mb-3 p-3 rounded-2 d-flex flex-wrap gap-4 align-items-center"
     style="background:var(--primary-soft);border:1px solid rgba(27,107,53,0.2);">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Exam</div>
        <div class="fw-bold"><?= htmlspecialchars($examInfo['exam_name']) ?>
            <span class="badge ms-1" style="background:var(--primary);font-size:11px;">
                <?= strtoupper(str_replace('_',' ',$examInfo['exam_type'])) ?>
            </span>
        </div>
    </div>
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Subject</div>
        <div class="fw-bold"><?= htmlspecialchars($subInfo['subject_name']) ?></div>
    </div>
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Section</div>
        <div class="fw-bold" style="color:var(--primary);">
            <?= htmlspecialchars($selectedSection) ?>
        </div>
    </div>
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Theory FM</div>
        <div class="fw-bold" style="font-size:18px;color:var(--primary);"><?= $examFM['theory_fm'] ?></div>
    </div>
    <?php if ($examFM['has_practical']): ?>
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Practical FM</div>
        <div class="fw-bold" style="font-size:18px;color:#e67e22;"><?= $examFM['practical_fm'] ?></div>
    </div>
    <?php endif; ?>
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Total FM</div>
        <div class="fw-bold" style="font-size:18px;color:var(--secondary);"><?= $totalFM ?></div>
    </div>
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Students</div>
        <div class="fw-bold" style="font-size:18px;"><?= count($students) ?></div>
    </div>
</div>

<form method="POST" class="admin-form">
    <input type="hidden" name="exam_id"    value="<?= $selectedExamId ?>">
    <input type="hidden" name="subject_id" value="<?= $selectedSubjectId ?>">

    <div class="admin-card">
        <div class="admin-card-header">
            <h6><i class="fas fa-edit me-2"></i>Marks Entry</h6>
            <div style="font-size:13px;color:rgba(255,255,255,0.75);">
                <i class="fas fa-info-circle me-1"></i>Admin review garепछि publish hunchha
            </div>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Student</th>
                        <th>Section</th>
                        <th>Theory / <?= $examFM['theory_fm'] ?></th>
                        <?php if ($examFM['has_practical']): ?>
                        <th>Practical / <?= $examFM['practical_fm'] ?></th>
                        <?php endif; ?>
                        <th>Total / <?= $totalFM ?></th>
                        <th>Remarks</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $st): ?>
                    <tr id="mrow_<?= $st['id'] ?>">
                        <td class="text-muted" style="font-size:12px;"><?= $i+1 ?></td>
                        <td>
                            <div class="fw-semibold" style="font-size:13.5px;"><?= htmlspecialchars($st['full_name']) ?></div>
                            <?php if ($st['roll_no']): ?>
                            <div style="font-size:11.5px;color:var(--text-muted);">Roll: <?= htmlspecialchars($st['roll_no']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background:var(--primary-soft);color:var(--primary);
                                         padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                                <?= htmlspecialchars($st['section'] ?: $selectedSection) ?>
                            </span>
                        </td>
                        <td>
                            <input type="number"
                                   name="marks[<?= $st['id'] ?>][obtained]"
                                   class="form-control marks-input"
                                   style="width:85px;"
                                   min="0" max="<?= $examFM['theory_fm'] ?>" step="0.5"
                                   value="<?= $st['obtained'] ?>"
                                   data-fm="<?= $examFM['theory_fm'] ?>"
                                   data-has-pr="<?= $examFM['has_practical'] ?>"
                                   data-pr-fm="<?= $examFM['has_practical']?$examFM['practical_fm']:0 ?>"
                                   data-total-fm="<?= $totalFM ?>"
                                   data-row="<?= $st['id'] ?>">
                        </td>
                        <?php if ($examFM['has_practical']): ?>
                        <td>
                            <input type="number"
                                   name="marks[<?= $st['id'] ?>][practical]"
                                   class="form-control practical-input"
                                   style="width:85px;"
                                   min="0" max="<?= $examFM['practical_fm'] ?>" step="0.5"
                                   value="<?= $st['practical'] ?>"
                                   data-row="<?= $st['id'] ?>">
                        </td>
                        <?php else: ?>
                        <input type="hidden" name="marks[<?= $st['id'] ?>][practical]" value="0">
                        <?php endif; ?>
                        <td>
                            <span class="fw-bold total-display" id="mtotal_<?= $st['id'] ?>"
                                  style="font-size:15px;color:var(--primary);">
                                <?= number_format((float)$st['obtained']+(float)$st['practical'],1) ?>
                            </span>
                        </td>
                        <td>
                            <select name="marks[<?= $st['id'] ?>][remarks]"
                                    class="form-select remarks-select"
                                    style="width:105px;" data-row="<?= $st['id'] ?>">
                                <option value="pass"     <?= $st['result_remarks']==='pass'?'selected':'' ?>>Pass</option>
                                <option value="fail"     <?= $st['result_remarks']==='fail'?'selected':'' ?>>Fail</option>
                                <option value="absent"   <?= $st['result_remarks']==='absent'?'selected':'' ?>>Absent</option>
                                <option value="exempted" <?= $st['result_remarks']==='exempted'?'selected':'' ?>>Exempted</option>
                            </select>
                        </td>
                        <td>
                            <span class="grade-badge fw-bold grade-<?= str_replace('+','-plus',$st['grade']?:'NG') ?>"
                                  id="mgrade_<?= $st['id'] ?>">
                                <?= $st['grade'] ?: '—' ?>
                            </span>
                            <div style="font-size:11px;color:var(--text-muted);" id="mgpa_<?= $st['id'] ?>">
                                <?= $st['grade_point'] ? 'GPA: '.$st['grade_point'] : '' ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top d-flex justify-content-end gap-2">
            <a href="marks_entry.php?class_id=<?= $selectedClassId ?>&section=<?= urlencode($selectedSection) ?>&exam_id=<?= $selectedExamId ?>"
               class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Change Subject
            </a>
            <button type="submit" class="btn-admin-primary" style="padding:10px 30px;font-size:14px;">
                <i class="fas fa-save me-2"></i>Save All Marks
            </button>
        </div>
    </div>
</form>

<?php elseif ($selectedClassId && $selectedExamId && empty($subjects)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    No subjects assigned to you for this class. Contact admin.
</div>

<?php elseif ($selectedClassId && !$selectedExamId): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Select an exam above.</div>

<?php elseif ($selectedClassId && $selectedExamId && !$selectedSubjectId): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Select a subject above.</div>

<?php else: ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Select a class to get started.</div>
<?php endif; ?>

<script>
// Submit filter form
function submitFilter() {
    // Sync section hidden input from class select
    const sel = document.getElementById('classSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('sectionHidden').value = opt ? (opt.dataset.section || '') : '';
    document.getElementById('filterForm').submit();
}

$(document).ready(function(){
    const gradeTable = [
        {min:90,grade:'A+',gpa:4.0,cls:'grade-A-plus'},
        {min:80,grade:'A', gpa:3.6,cls:'grade-A'},
        {min:70,grade:'B+',gpa:3.2,cls:'grade-B-plus'},
        {min:60,grade:'B', gpa:2.8,cls:'grade-B'},
        {min:50,grade:'C+',gpa:2.4,cls:'grade-C-plus'},
        {min:40,grade:'C', gpa:2.0,cls:'grade-C'},
        {min:30,grade:'D', gpa:1.6,cls:'grade-D'},
        {min:0, grade:'NG',gpa:0.0,cls:'grade-NG'},
    ];

    function getGrade(marks, fm) {
        if (!fm) return {grade:'—',gpa:0,cls:''};
        const pct = (marks/fm)*100;
        for (const g of gradeTable) { if (pct >= g.min) return g; }
        return {grade:'NG',gpa:0.0,cls:'grade-NG'};
    }

    function updateRow(rowId) {
        const th  = parseFloat($('input[name="marks['+rowId+'][obtained]"]').val())  || 0;
        const pr  = parseFloat($('input[name="marks['+rowId+'][practical]"]').val()) || 0;
        const fm  = parseFloat($('input[name="marks['+rowId+'][obtained]"]').data('total-fm')) || 0;
        const rmk = $('select[name="marks['+rowId+'][remarks]"]').val();
        const tot = th + pr;

        $('#mtotal_'+rowId).text(tot.toFixed(1));

        if (rmk === 'absent' || rmk === 'exempted') {
            $('#mgrade_'+rowId).text('—').removeClass().addClass('fw-bold').attr('id','mgrade_'+rowId);
            $('#mgpa_'+rowId).text('');
            return;
        }
        const g = getGrade(tot, fm);
        $('#mgrade_'+rowId)
            .text(g.grade)
            .removeClass('grade-A-plus grade-A grade-B-plus grade-B grade-C-plus grade-C grade-D grade-NG')
            .addClass('grade-badge fw-bold ' + g.cls);
        $('#mgpa_'+rowId).text(g.gpa > 0 ? 'GPA: '+g.gpa : '');
    }

    $(document).on('input', '.marks-input,.practical-input', function(){
        const rowId = $(this).data('row');
        const maxFM = parseFloat($(this).attr('max')) || 9999;
        if (parseFloat($(this).val()) > maxFM) $(this).val(maxFM);
        updateRow(rowId);
    });

    $(document).on('change', '.remarks-select', function(){
        const rowId = $(this).data('row');
        const isAbs = $(this).val() === 'absent';
        $('input[name="marks['+rowId+'][obtained]"]').prop('disabled', isAbs);
        $('input[name="marks['+rowId+'][practical]"]').prop('disabled', isAbs);
        if (isAbs) {
            $('input[name="marks['+rowId+'][obtained]"]').val(0);
            $('input[name="marks['+rowId+'][practical]"]').val(0);
        }
        updateRow(rowId);
    });

    // Init all rows
    $('.marks-input').each(function(){ updateRow($(this).data('row')); });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
