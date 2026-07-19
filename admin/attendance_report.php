<?php
$pageTitle = 'Attendance Report';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';

// ── CSV Export ────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expClassId = (int)($_GET['class_id']  ?? 0);
    $expSection = sanitize($conn, $_GET['section']   ?? '');
    $expNpYear  = (int)($_GET['np_year']   ?? 0);
    $expNpMonth = (int)($_GET['np_month']  ?? 0);

    if ($expClassId && $expNpYear && $expNpMonth) {
        $monthDaysExp = getSchoolDaysInBSMonth($expNpYear, $expNpMonth);
        $firstDay = $monthDaysExp[0]['ad_date']       ?? date('Y-m-d');
        $lastDay  = end($monthDaysExp)['ad_date'] ?? date('Y-m-d');
        $wSec = $expSection ? "AND section='$expSection'" : '';

        $studentsExp = $conn->query("SELECT id,full_name,roll_no,section FROM students
            WHERE class_id=$expClassId $wSec AND status='active'
            ORDER BY section, CAST(roll_no AS UNSIGNED), full_name")->fetch_all(MYSQLI_ASSOC);

        $attExp = [];
        $rows = $conn->query("SELECT student_id,attendance_date,status FROM attendance
            WHERE class_id=$expClassId $wSec
            AND attendance_date BETWEEN '$firstDay' AND '$lastDay'")->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $r) $attExp[$r['student_id']][$r['attendance_date']] = $r['status'];

        $clsName = $conn->query("SELECT class_name FROM classes WHERE id=$expClassId")->fetch_assoc()['class_name'] ?? '';
        $mnName  = getNpMonthName($expNpMonth);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_report_'
            .preg_replace('/[^a-z0-9]/i','_',$clsName)
            .($expSection?"_Sec{$expSection}":'')
            ."_{$mnName}{$expNpYear}.csv\"");
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        $headers = ['Roll No','Student Name','Section'];
        foreach ($monthDaysExp as $d) $headers[] = $d['bs_day'].' '.date('D',strtotime($d['ad_date']));
        $headers[] = 'Present'; $headers[] = 'Absent'; $headers[] = 'Late'; $headers[] = 'Total Days';
        fputcsv($out, $headers);

        foreach ($studentsExp as $st) {
            $row = [$st['roll_no']??'', $st['full_name'], $st['section']];
            $p=$a=$l=0;
            foreach ($monthDaysExp as $d) {
                $isWknd = $d['is_weekend']??false;
                $status = $attExp[$st['id']][$d['ad_date']] ?? ($isWknd?'H':'');
                $row[]  = strtoupper(substr($status?:'-',0,1));
                if ($status==='present') $p++;
                elseif ($status==='absent') $a++;
                elseif ($status==='late')   $l++;
            }
            $row[] = $p; $row[] = $a; $row[] = $l;
            $row[] = count($monthDaysExp);
            fputcsv($out, $row);
        }
        fclose($out);
        exit();
    }
}

// ── Filters ───────────────────────────────────────────
$classes    = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$todayBS    = getCurrentBS();
$selClassId = (int)($_GET['class_id']  ?? 0);
$selSection = sanitize($conn, $_GET['section']   ?? '');
$selNpYear  = (int)($_GET['np_year']   ?? $todayBS['year']);
$selNpMonth = (int)($_GET['np_month']  ?? $todayBS['month']);
$reportType = sanitize($conn, $_GET['report_type'] ?? 'monthly'); // monthly or yearly

if ($selNpMonth < 1)  { $selNpMonth = 12; $selNpYear--; }
if ($selNpMonth > 12) { $selNpMonth = 1;  $selNpYear++; }

// Sections for selected class
$sections = [];
if ($selClassId) {
    $secRows = $conn->query("SELECT DISTINCT section FROM students WHERE class_id=$selClassId AND status='active' ORDER BY section")->fetch_all(MYSQLI_ASSOC);
    $sections = array_column($secRows,'section');
}

// ── Data fetch ────────────────────────────────────────
$students   = [];
$monthDays  = [];
$allAtt     = [];
$summary    = [];
$yearlyData = [];

if ($selClassId) {
    $wSec = $selSection ? "AND section='$selSection'" : '';
    $students = $conn->query("SELECT id,full_name,roll_no,section FROM students
        WHERE class_id=$selClassId $wSec AND status='active'
        ORDER BY section, CAST(roll_no AS UNSIGNED), full_name")->fetch_all(MYSQLI_ASSOC);

    if ($reportType === 'monthly') {
        $monthDays = getSchoolDaysInBSMonth($selNpYear, $selNpMonth);
        if (!empty($monthDays)) {
            $firstDay = $monthDays[0]['ad_date'];
            $lastDay  = end($monthDays)['ad_date'];
            $rows = $conn->query("SELECT student_id,attendance_date,status FROM attendance
                WHERE class_id=$selClassId $wSec
                AND attendance_date BETWEEN '$firstDay' AND '$lastDay'")->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $r) $allAtt[$r['student_id']][$r['attendance_date']] = $r['status'];
        }
        // Summary per student
        foreach ($students as $st) {
            $p=$a=$l=$h=0;
            foreach ($monthDays as $d) {
                $isWknd = $d['is_weekend']??false;
                $status = $allAtt[$st['id']][$d['ad_date']] ?? ($isWknd?'holiday':'');
                if ($status==='present') $p++;
                elseif ($status==='absent')  $a++;
                elseif ($status==='late')    $l++;
                elseif ($status==='holiday') $h++;
            }
            $summary[$st['id']] = ['p'=>$p,'a'=>$a,'l'=>$l,'h'=>$h,'total'=>count($monthDays)];
        }
    } else {
        // Yearly — all 12 months summary
        for ($m = 1; $m <= 12; $m++) {
            $mDays = getSchoolDaysInBSMonth($selNpYear, $m);
            if (empty($mDays)) continue;
            $fDay = $mDays[0]['ad_date'];
            $lDay = end($mDays)['ad_date'];
            $rows = $conn->query("SELECT student_id,attendance_date,status FROM attendance
                WHERE class_id=$selClassId $wSec
                AND attendance_date BETWEEN '$fDay' AND '$lDay'")->fetch_all(MYSQLI_ASSOC);
            $mAtt = [];
            foreach ($rows as $r) $mAtt[$r['student_id']][$r['attendance_date']] = $r['status'];
            foreach ($students as $st) {
                $p=$a=$l=0;
                foreach ($mDays as $d) {
                    $isWknd = $d['is_weekend']??false;
                    $status = $mAtt[$st['id']][$d['ad_date']] ?? ($isWknd?'holiday':'');
                    if ($status==='present') $p++;
                    elseif ($status==='absent') $a++;
                    elseif ($status==='late')   $l++;
                }
                if (!isset($yearlyData[$st['id']])) $yearlyData[$st['id']] = array_fill(1,12,['p'=>0,'a'=>0,'l'=>0,'total'=>0]);
                $yearlyData[$st['id']][$m] = ['p'=>$p,'a'=>$a,'l'=>$l,'total'=>count(array_filter($mDays,fn($d)=>!($d['is_weekend']??false)))];
            }
        }
    }
}

$selClassInfo = null;
foreach ($classes as $c) { if ($c['id']==$selClassId) { $selClassInfo=$c; break; } }
$monthName = getNpMonthName($selNpMonth);

require_once 'includes/layout_top.php';
?>

<?php if ($message ?? ''): ?>
<div class="alert alert-success alert-dismissible alert-auto-dismiss fade show mb-4">
    <?= htmlspecialchars($message ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter Card -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h6><i class="fas fa-chart-bar me-2"></i>Attendance Report</h6>
    </div>
    <div class="admin-card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Select Class --</option>
                    <?php foreach($classes as $cls): ?>
                    <option value="<?= $cls['id'] ?>" <?= $cls['id']==$selClassId?'selected':'' ?>>
                        <?= htmlspecialchars($cls['class_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Section</label>
                <select name="section" class="form-select" onchange="this.form.submit()">
                    <option value="">All Sections</option>
                    <?php foreach($sections as $sec): ?>
                    <option value="<?= $sec ?>" <?= $sec===$selSection?'selected':'' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Report Type</label>
                <select name="report_type" class="form-select" onchange="this.form.submit()">
                    <option value="monthly" <?= $reportType==='monthly'?'selected':'' ?>>Monthly</option>
                    <option value="yearly"  <?= $reportType==='yearly'?'selected':'' ?>>Yearly</option>
                </select>
            </div>
            <?php if($reportType==='monthly'): ?>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Month</label>
                <select name="np_month" class="form-select">
                    <?php for($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m==$selNpMonth?'selected':'' ?>><?= getNpMonthName($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label fw-semibold">BS Year</label>
                <select name="np_year" class="form-select">
                    <?php for($y=2078;$y<=2110;$y++): ?>
                    <option value="<?= $y ?>" <?= $y==$selNpYear?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn-admin-primary w-100">Go</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selClassId && !empty($students)): ?>
<!-- Actions -->
<div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
    <h6 class="mb-0 fw-bold">
        <?= htmlspecialchars($selClassInfo['class_name']??'') ?>
        <?= $selSection ? "— Section $selSection" : '' ?>
        &bull;
        <?= $reportType==='monthly' ? "$monthName $selNpYear BS" : "Year $selNpYear BS (All Months)" ?>
        &bull; <?= count($students) ?> students
    </h6>
    <div class="ms-auto d-flex gap-2">
        <?php if($reportType==='monthly'): ?>
        <a href="attendance_report.php?class_id=<?= $selClassId ?>&section=<?= urlencode($selSection) ?>&np_year=<?= $selNpYear ?>&np_month=<?= $selNpMonth ?>&report_type=monthly&export=csv"
           class="btn-admin-success btn-sm">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn-admin-primary btn-sm">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</div>

<?php if($reportType === 'monthly'): ?>
<!-- Monthly Table -->
<div class="admin-card" id="printSection">
    <div style="display:none;" class="print-only text-center mb-2" style="font-size:13px;">
        <strong><?= htmlspecialchars(getSetting($conn,'school_name')) ?></strong><br>
        <?= htmlspecialchars($selClassInfo['class_name']??'') ?>
        <?= $selSection?"— Sec $selSection":'' ?>
        | <?= $monthName ?> <?= $selNpYear ?> BS
    </div>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;padding:5px 8px;">Roll</th>
                    <th style="background:#0F3A1A;color:white;padding:5px 8px;">Student Name</th>
                    <?php if(!$selSection): ?><th style="background:#0F3A1A;color:white;padding:5px 8px;">Sec</th><?php endif; ?>
                    <?php foreach($monthDays as $d): ?>
                    <th style="background:<?= ($d['is_weekend']??false)?'#5b21b6':'#0F3A1A' ?>;color:white;
                               padding:3px 2px;text-align:center;min-width:24px;">
                        <?= $d['bs_day'] ?><br>
                        <span style="font-size:8px;opacity:0.8;"><?= date('D',strtotime($d['ad_date']))[0] ?></span>
                    </th>
                    <?php endforeach; ?>
                    <th style="background:#1a4a2a;color:white;padding:5px 4px;text-align:center;">P</th>
                    <th style="background:#4a1a1a;color:white;padding:5px 4px;text-align:center;">A</th>
                    <th style="background:#4a3a1a;color:white;padding:5px 4px;text-align:center;">L</th>
                    <th style="background:#0F3A1A;color:white;padding:5px 4px;text-align:center;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $idx => $st):
                    $sm = $summary[$st['id']] ?? ['p'=>0,'a'=>0,'l'=>0,'total'=>0];
                    $schoolDaysCount = count(array_filter($monthDays, fn($d)=>!($d['is_weekend']??false)));
                    $pct = $schoolDaysCount > 0 ? round(($sm['p']+$sm['l'])/$schoolDaysCount*100) : 0;
                    $rowBg = $idx%2===0?'':'background:#f7faf8;';
                ?>
                <tr style="<?= $rowBg ?>">
                    <td style="padding:4px 8px;border:1px solid #e0e8e2;text-align:center;font-weight:600;color:var(--text-muted);"><?= htmlspecialchars($st['roll_no']??'') ?></td>
                    <td style="padding:4px 8px;border:1px solid #e0e8e2;font-weight:600;"><?= htmlspecialchars($st['full_name']) ?></td>
                    <?php if(!$selSection): ?><td style="padding:4px 8px;border:1px solid #e0e8e2;text-align:center;"><?= $st['section'] ?></td><?php endif; ?>
                    <?php foreach($monthDays as $d):
                        $isWknd = $d['is_weekend']??false;
                        $status = $allAtt[$st['id']][$d['ad_date']] ?? ($isWknd?'holiday':'');
                        $lbl    = $isWknd ? 'H' : ($status ? strtoupper(substr($status,0,1)) : '');
                        $bg     = $isWknd ? 'background:#ede9fe;' : (
                            $status==='present'?'background:#dcfce7;':(
                            $status==='absent'?'background:#fee2e2;':(
                            $status==='late'?'background:#fef3c7;':'')));
                    ?>
                    <td style="padding:2px;border:1px solid #e0e8e2;text-align:center;font-size:10px;font-weight:700;<?= $bg ?>"><?= $lbl ?></td>
                    <?php endforeach; ?>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:700;color:#155724;"><?= $sm['p'] ?></td>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:700;color:#721c24;"><?= $sm['a'] ?></td>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:700;color:#856404;"><?= $sm['l'] ?></td>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:700;
                        color:<?= $pct>=75?'#155724':($pct>=50?'#856404':'#721c24') ?>;"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Yearly Summary Table -->
<div class="admin-card" id="printSection">
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;padding:6px 8px;">Roll</th>
                    <th style="background:#0F3A1A;color:white;padding:6px 8px;">Student Name</th>
                    <?php if(!$selSection): ?><th style="background:#0F3A1A;color:white;padding:6px 8px;">Sec</th><?php endif; ?>
                    <?php for($m=1;$m<=12;$m++): ?>
                    <th style="background:#0F3A1A;color:white;padding:4px 6px;text-align:center;" title="<?= getNpMonthName($m) ?>">
                        <?= substr(getNpMonthName($m),0,3) ?>
                    </th>
                    <?php endfor; ?>
                    <th style="background:#1a4a2a;color:white;padding:6px 8px;text-align:center;">Total P</th>
                    <th style="background:#4a1a1a;color:white;padding:6px 8px;text-align:center;">Total A</th>
                    <th style="background:#0F3A1A;color:white;padding:6px 8px;text-align:center;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $idx => $st):
                    $totalP=$totalA=$totalL=$totalDays=0;
                    $rowBg = $idx%2?'background:#f7faf8;':'';
                ?>
                <tr style="<?= $rowBg ?>">
                    <td style="padding:4px 8px;border:1px solid #e0e8e2;text-align:center;"><?= htmlspecialchars($st['roll_no']??'') ?></td>
                    <td style="padding:4px 8px;border:1px solid #e0e8e2;font-weight:600;"><?= htmlspecialchars($st['full_name']) ?></td>
                    <?php if(!$selSection): ?><td style="padding:4px 8px;border:1px solid #e0e8e2;text-align:center;"><?= $st['section'] ?></td><?php endif; ?>
                    <?php for($m=1;$m<=12;$m++):
                        $md = $yearlyData[$st['id']][$m] ?? ['p'=>0,'a'=>0,'l'=>0,'total'=>0];
                        $totalP    += $md['p'];
                        $totalA    += $md['a'];
                        $totalL    += $md['l'];
                        $totalDays += $md['total'];
                        $mPct = $md['total']>0 ? round(($md['p']+$md['l'])/$md['total']*100) : 0;
                    ?>
                    <td style="padding:3px 4px;border:1px solid #e0e8e2;text-align:center;font-size:11px;">
                        <?php if($md['total']>0): ?>
                        <div style="font-weight:700;color:<?= $mPct>=75?'#155724':($mPct>=50?'#856404':'#721c24') ?>;"><?= $md['p'] ?></div>
                        <div style="font-size:9px;color:var(--text-muted);"><?= $mPct ?>%</div>
                        <?php else: ?><span style="color:#ccc;">—</span><?php endif; ?>
                    </td>
                    <?php endfor; ?>
                    <?php $yearPct = $totalDays>0 ? round(($totalP+$totalL)/$totalDays*100) : 0; ?>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:700;color:#155724;"><?= $totalP ?></td>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:700;color:#721c24;"><?= $totalA ?></td>
                    <td style="padding:4px;border:1px solid #e0e8e2;text-align:center;font-weight:800;font-size:13px;
                        color:<?= $yearPct>=75?'#155724':($yearPct>=50?'#856404':'#721c24') ?>;"><?= $yearPct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif($selClassId): ?>
<div class="admin-card"><div class="admin-card-body text-center py-5 text-muted">
    <i class="fas fa-users-slash fa-3x mb-3 d-block opacity-40"></i>
    <h6>No active students found.</h6>
</div></div>
<?php endif; ?>

<style>
@media print {
    .admin-sidebar, .admin-topbar, .admin-card-header .btn-admin-success,
    .admin-card-header .btn-admin-primary, form, .no-print { display:none !important; }
    #printSection { display: block !important; }
    .print-only { display: block !important; }
}
</style>

<?php require_once 'includes/layout_bottom.php'; ?>
