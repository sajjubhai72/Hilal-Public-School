<?php
$pageTitle = 'View Results';
require_once 'includes/auth.php';

$examId = (int)($_GET['exam_id'] ?? 0);
if (!$examId) { header('Location: results.php'); exit(); }

$exam = $conn->query("SELECT e.*,c.class_name FROM exams e JOIN classes c ON e.class_id=c.id WHERE e.id=$examId")->fetch_assoc();
if (!$exam) { header('Location: results.php'); exit(); }

$subjects = $conn->query("SELECT * FROM subjects WHERE class_id={$exam['class_id']} AND status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$students = $conn->query("
    SELECT s.id, s.full_name, s.roll_no, s.symbol_no
    FROM students s
    WHERE s.class_id = {$exam['class_id']} AND s.status='active'
    ORDER BY s.roll_no
")->fetch_all(MYSQLI_ASSOC);

// Build results map [student_id][subject_id]
$resultsMap = [];
$allResults = $conn->query("SELECT * FROM results WHERE exam_id=$examId")->fetch_all(MYSQLI_ASSOC);
foreach ($allResults as $r) {
    $resultsMap[$r['student_id']][$r['subject_id']] = $r;
}

require_once 'includes/layout_top.php';
?>

<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="results.php" class="btn-admin-primary" style="background:var(--dark);">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
    <a href="view_results.php?exam_id=<?= $examId ?>&export=csv" class="btn-admin-success">
        <i class="fas fa-file-excel me-1"></i>Export CSV
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h6>
            <i class="fas fa-poll-h me-2"></i>
            <?= htmlspecialchars($exam['exam_name']) ?> — <?= htmlspecialchars($exam['class_name']) ?>
            (<?= $exam['academic_year'] ?>)
        </h6>
        <span class="status-badge <?= $exam['is_published']??0 ? 'status-published' : 'status-draft' ?>">
            <?= ($exam['is_published']??0) ? 'Published' : 'Unpublished' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table style="width:100%;min-width:800px;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;padding:8px 10px;white-space:nowrap;font-weight:700;border:1px solid #1a5c2a;">Roll</th>
                    <th style="background:#0F3A1A;color:white;padding:8px 10px;font-weight:700;border:1px solid #1a5c2a;">Student Name</th>
                    <?php foreach($subjects as $sub): ?>
                    <th style="background:#0F3A1A;color:white;padding:6px 8px;text-align:center;font-weight:700;border:1px solid #1a5c2a;white-space:nowrap;">
                        <?= htmlspecialchars($sub['subject_name']) ?>
                        <div style="font-size:10px;font-weight:400;opacity:0.8;">FM: <?= $sub['full_marks'] ?></div>
                    </th>
                    <?php endforeach; ?>
                    <th style="background:#0F3A1A;color:white;padding:8px 10px;text-align:center;font-weight:700;border:1px solid #1a5c2a;">Total</th>
                    <th style="background:#0F3A1A;color:white;padding:8px 10px;text-align:center;font-weight:700;border:1px solid #1a5c2a;">GPA</th>
                    <th style="background:#0F3A1A;color:white;padding:8px 10px;text-align:center;font-weight:700;border:1px solid #1a5c2a;">Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $i => $st):
                    $totalObt = 0; $totalFM = 0; $totalGPA = 0; $subCount = 0; $hasFail = false;
                    $rowBg = $i % 2 === 0 ? '#ffffff' : '#f7faf8';
                ?>
                <tr style="background:<?= $rowBg ?>;">
                    <td style="padding:6px 10px;border:1px solid #e0e8e2;text-align:center;font-weight:600;color:var(--text-muted);"><?= htmlspecialchars($st['roll_no'] ?? '—') ?></td>
                    <td style="padding:6px 10px;border:1px solid #e0e8e2;font-weight:600;white-space:nowrap;"><?= htmlspecialchars($st['full_name']) ?></td>
                    <?php foreach($subjects as $sub):
                        $r = $resultsMap[$st['id']][$sub['id']] ?? null;
                    ?>
                    <td style="padding:5px 8px;border:1px solid #e0e8e2;text-align:center;">
                        <?php if($r): ?>
                        <div style="font-weight:700;font-size:13px;"><?= $r['total_obtained'] ?></div>
                        <div style="font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;display:inline-block;margin-top:1px;
                            background:<?= $r['grade']==='A+'?'#d4edda':($r['grade']==='A'?'#d4edda':($r['grade']==='B+'?'#d1ecf1':($r['grade']==='B'?'#d1ecf1':($r['grade']==='C+'?'#fff3cd':($r['grade']==='C'?'#fff3cd':($r['remarks']==='fail'?'#f8d7da':'#f8d7da')))))) ?>;
                            color:<?= $r['grade']==='A+'?'#155724':($r['grade']==='A'?'#155724':($r['grade']==='B+'?'#0c5460':($r['grade']==='B'?'#0c5460':($r['grade']==='C+'?'#856404':($r['grade']==='C'?'#856404':'#721c24'))))) ?>;">
                            <?= $r['grade'] ?>
                        </div>
                        <?php if($r['remarks']==='absent'): ?><div style="font-size:9px;color:#e67e22;font-weight:700;">ABS</div><?php endif; ?>
                        <?php else: ?>
                        <span style="color:#ccc;font-size:11px;">—</span>
                        <?php endif; ?>
                        <?php if($r){$totalObt+=$r['total_obtained'];$totalFM+=$r['full_marks'];$totalGPA+=$r['grade_point'];$subCount++;if($r['remarks']==='fail'||$r['remarks']==='absent')$hasFail=true;} ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="padding:6px 10px;border:1px solid #e0e8e2;text-align:center;font-weight:700;">
                        <?= $totalObt ?>/<span style="color:var(--text-muted);font-weight:500;"><?= $totalFM ?></span>
                        <?php if($totalFM > 0): ?>
                        <div style="font-size:10px;color:var(--text-muted);"><?= round($totalObt/$totalFM*100,1) ?>%</div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:6px 10px;border:1px solid #e0e8e2;text-align:center;font-weight:700;color:var(--primary);"><?= $subCount>0 ? round($totalGPA/$subCount,2) : '—' ?></td>
                    <td style="padding:6px 10px;border:1px solid #e0e8e2;text-align:center;">
                        <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;
                            background:<?= $hasFail?'#f8d7da':'#d4edda' ?>;color:<?= $hasFail?'#721c24':'#155724' ?>;">
                            <?= $hasFail ? 'FAIL' : 'PASS' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=results_' . $exam['exam_name'] . '_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    $headers = ['Roll No','Student Name'];
    foreach($subjects as $sub) $headers[] = $sub['subject_name'].' (FM:'.$sub['full_marks'].')';
    $headers[] = 'Total Obtained'; $headers[] = 'Total FM'; $headers[] = 'GPA'; $headers[] = 'Result';
    fputcsv($out, $headers);

    foreach($students as $st){
        $row = [$st['roll_no'] ?? '', $st['full_name']];
        $totalObt=0;$totalFM=0;$totalGPA=0;$subCount=0;$hasFail=false;
        foreach($subjects as $sub){
            $r = $resultsMap[$st['id']][$sub['id']] ?? null;
            $row[] = $r ? $r['total_obtained'].' ('.$r['grade'].')' : '—';
            if($r){$totalObt+=$r['total_obtained'];$totalFM+=$r['full_marks'];$totalGPA+=$r['grade_point'];$subCount++;if($r['remarks']==='fail'||$r['remarks']==='absent')$hasFail=true;}
        }
        $row[] = $totalObt; $row[] = $totalFM;
        $row[] = $subCount > 0 ? round($totalGPA/$subCount,2) : 0;
        $row[] = $hasFail ? 'FAIL' : 'PASS';
        fputcsv($out, $row);
    }
    fclose($out);
    exit();
}
?>

<?php require_once 'includes/layout_bottom.php'; ?>
