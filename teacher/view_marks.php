<?php
$pageTitle = 'My Entered Marks';
require_once 'includes/auth.php';
require_once 'includes/layout_top.php';

$selectedExamId = (int)($_GET['exam_id'] ?? 0);

// Get teacher's exams
$exams = $conn->query("
    SELECT DISTINCT e.id, e.exam_name, e.academic_year, c.class_name, COALESCE(rp.is_published,0) as is_published
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    JOIN teacher_classes tc ON tc.class_id = e.class_id AND tc.teacher_id = $teacherId
    LEFT JOIN result_publish rp ON rp.exam_id=e.id AND rp.class_id=e.class_id
    ORDER BY e.academic_year DESC, e.id DESC
")->fetch_all(MYSQLI_ASSOC);

$entries = [];
$examInfo = null;

if ($selectedExamId) {
    $examInfo = $conn->query("SELECT e.*,c.class_name FROM exams e JOIN classes c ON e.class_id=c.id WHERE e.id=$selectedExamId")->fetch_assoc();
    $entries = $conn->query("
        SELECT r.*, s.full_name as student_name, s.roll_no,
               sub.subject_name, sub.full_marks as sub_fm
        FROM results r
        JOIN students s ON r.student_id = s.id
        JOIN subjects sub ON r.subject_id = sub.id
        WHERE r.exam_id = $selectedExamId AND r.entered_by = $teacherId
        ORDER BY s.roll_no, sub.subject_name
    ")->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="admin-card mb-4">
    <div class="admin-card-header"><h6><i class="fas fa-filter me-2"></i>Select Exam</h6></div>
    <div class="admin-card-body">
        <form method="GET" class="admin-form">
            <div class="row g-3">
                <div class="col-md-6">
                    <select name="exam_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select Exam --</option>
                        <?php foreach($exams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $selectedExamId==$ex['id']?'selected':'' ?>>
                            <?= htmlspecialchars($ex['exam_name']) ?> — <?= htmlspecialchars($ex['class_name']) ?>
                            (<?= $ex['is_published']?'Published':'Pending' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExamId && !empty($entries)): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-table me-2"></i><?= htmlspecialchars($examInfo['exam_name']) ?> — <?= htmlspecialchars($examInfo['class_name']) ?></h6>
        <?php $pubCheck = $conn->query("SELECT is_published FROM result_publish WHERE exam_id=$selectedExamId")->fetch_assoc(); ?>
        <span class="status-badge <?= ($pubCheck['is_published']??0)?'status-published':'status-draft' ?>">
            <?= ($pubCheck['is_published']??0)?'Published — Read Only':'Not Published Yet' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>#</th><th>Roll</th><th>Student</th><th>Subject</th><th>Theory</th><th>Practical</th><th>Total</th><th>Grade</th><th>GPA</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <?php foreach($entries as $i => $e): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($e['roll_no'] ?? '—') ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($e['student_name']) ?></td>
                    <td><?= htmlspecialchars($e['subject_name']) ?></td>
                    <td><?= $e['obtained_marks'] ?></td>
                    <td><?= $e['practical_marks'] ?: '—' ?></td>
                    <td class="fw-bold"><?= $e['total_obtained'] ?>/<?= $e['full_marks'] ?></td>
                    <td><span class="grade-badge grade-<?= str_replace('+','-plus',$e['grade']) ?>"><?= $e['grade'] ?></span></td>
                    <td><?= $e['grade_point'] ?></td>
                    <td><span class="status-badge status-<?= $e['remarks']==='pass'?'approved':($e['remarks']==='fail'?'rejected':'pending') ?>"><?= ucfirst($e['remarks']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($selectedExamId): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No marks entered by you for this exam yet.</div>
<?php else: ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Select an exam to view your entered marks.</div>
<?php endif; ?>

<?php require_once 'includes/layout_bottom.php'; ?>
