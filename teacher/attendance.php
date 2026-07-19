<?php
$pageTitle = 'Attendance';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';

// ── CSV Export ────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expClassId = (int)($_GET['class_id'] ?? 0);
    $expSection = sanitize($conn, $_GET['section'] ?? '');
    $expNpYear  = (int)($_GET['np_year']  ?? 0);
    $expNpMonth = (int)($_GET['np_month'] ?? 0);

    if ($expClassId && $expSection && $expNpYear && $expNpMonth) {
        $monthDaysExp = getSchoolDaysInBSMonth($expNpYear, $expNpMonth);
        $firstDay = $monthDaysExp[0]['ad_date'] ?? date('Y-m-d');
        $lastDay  = end($monthDaysExp)['ad_date'] ?? date('Y-m-d');

        $studentsExp = $conn->query("
            SELECT id, full_name, roll_no FROM students
            WHERE class_id=$expClassId AND section='$expSection' AND status='active'
            ORDER BY CAST(roll_no AS UNSIGNED), full_name
        ")->fetch_all(MYSQLI_ASSOC);

        $attExp = [];
        $rows = $conn->query("
            SELECT student_id, attendance_date, status
            FROM attendance
            WHERE class_id=$expClassId AND section='$expSection'
              AND attendance_date BETWEEN '$firstDay' AND '$lastDay'
        ")->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $r) $attExp[$r['student_id']][$r['attendance_date']] = $r['status'];

        $clsName = $conn->query("SELECT class_name FROM classes WHERE id=$expClassId")->fetch_assoc()['class_name'] ?? '';
        $mnName  = getNpMonthName($expNpMonth);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_'
            . preg_replace('/[^a-z0-9]/i','_',$clsName)
            . "_Sec{$expSection}_{$mnName}{$expNpYear}.csv\"");
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        // Headers: Roll, Name, then each BS day
        $headers = ['Roll No', 'Student Name'];
        foreach ($monthDaysExp as $d) {
            $headers[] = $d['bs_day'] . ' ' . date('D', strtotime($d['ad_date']));
        }
        $headers[] = 'Present'; $headers[] = 'Absent'; $headers[] = 'Late'; $headers[] = 'Total Days';
        fputcsv($out, $headers);

        foreach ($studentsExp as $st) {
            $row = [$st['roll_no'] ?? '', $st['full_name']];
            $p = $a = $l = 0;
            foreach ($monthDaysExp as $d) {
                $isWknd = $d['is_weekend'] ?? false;
                $status = $attExp[$st['id']][$d['ad_date']] ?? ($isWknd ? 'H' : '');
                $row[]  = strtoupper(substr($status ?: '-', 0, 1));
                if ($status === 'present') $p++;
                elseif ($status === 'absent')  $a++;
                elseif ($status === 'late')    $l++;
            }
            $row[] = $p; $row[] = $a; $row[] = $l;
            $row[] = count($monthDaysExp);
            fputcsv($out, $row);
        }
        fclose($out);
        exit();
    }
}

// ── Flash message from session ────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
$message     = $_SESSION['att_message']      ?? '';
$messageType = $_SESSION['att_message_type'] ?? 'success';
unset($_SESSION['att_message'], $_SESSION['att_message_type']);

// ── Save attendance for ONE date (POST) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_date'])) {
    $saveClassId = (int)($_POST['class_id']  ?? 0);
    $saveSection = sanitize($conn, $_POST['section'] ?? '');
    $saveDate    = sanitize($conn, $_POST['save_date'] ?? '');

    // ── Verify this teacher IS class teacher for this class+section ──
    $verify = $conn->query("SELECT id FROM teacher_classes
        WHERE teacher_id=$teacherId AND class_id=$saveClassId
        AND section='$saveSection' AND is_class_teacher=1 LIMIT 1");
    if (!$verify || $verify->num_rows === 0) {
        // Not authorized — redirect silently
        header("Location: attendance.php");
        exit();
    }

    if ($saveClassId && $saveSection && $saveDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $saveDate)) {
        // checked_ids = students marked present
        $checkedIds = array_map('intval', $_POST['checked_ids'] ?? []);
        // all_ids = all students shown
        $allIds     = array_map('intval', $_POST['all_ids']     ?? []);

        $bsDate    = adToBS($saveDate);
        $npYear    = $bsDate['year'];
        $npMonth   = $bsDate['month'];
        $monthName = getNpMonthName($npMonth);
        $dow       = (int)date('w', strtotime($saveDate));
        $isWeekend = ($dow === 5 || $dow === 6);
        $saved     = 0;

        foreach ($allIds as $studentId) {
            $status    = in_array($studentId, $checkedIds) ? 'present' : 'absent';
            // Weekend — save as holiday unless teacher explicitly changed
            if ($isWeekend && !in_array($studentId, $checkedIds)) $status = 'holiday';
            $isHoliday = ($status === 'holiday') ? 1 : 0;
            $remarks   = '';

            $stmt = $conn->prepare("
                INSERT INTO attendance
                    (student_id,class_id,section,teacher_id,attendance_date,
                     nepali_year,nepali_month,nepali_month_name,day_of_week,is_holiday,status,remarks)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    status=VALUES(status), remarks=VALUES(remarks), teacher_id=VALUES(teacher_id)
            ");
            $stmt->bind_param("iisisiiisiss",
                $studentId, $saveClassId, $saveSection, $teacherId, $saveDate,
                $npYear, $npMonth, $monthName, $dow, $isHoliday, $status, $remarks
            );
            if ($stmt->execute()) $saved++;
            $stmt->close();
        }

        $_SESSION['att_message']      = "Attendance saved for $saved students on ".date('M d, Y', strtotime($saveDate))."!";
        $_SESSION['att_message_type'] = 'success';
    }

    header("Location: attendance.php?class_id=$saveClassId&section=".urlencode($saveSection)."&sel_date=$saveDate");
    exit();
}

// ── Teacher's assigned classes ─────────────────────────
$myClasses = $conn->query("
    SELECT DISTINCT tc.class_id, tc.section, c.class_name, c.level
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.id
    WHERE tc.teacher_id = $teacherId AND tc.is_class_teacher = 1
    ORDER BY c.id, tc.section
")->fetch_all(MYSQLI_ASSOC);

// ── Selected filters ──────────────────────────────────
$selectedClassId = (int)($_GET['class_id'] ?? 0);
$selectedSection = sanitize($conn, $_GET['section'] ?? '');
$today           = date('Y-m-d');
$selectedDate    = sanitize($conn, $_GET['sel_date'] ?? $today);

// Auto-select first class
if (!$selectedClassId && !empty($myClasses)) {
    $selectedClassId = $myClasses[0]['class_id'];
    $selectedSection = $myClasses[0]['section'];
}

// If no class teacher assignments at all — show message
if (empty($myClasses)) {
    require_once 'includes/layout_top.php';
    ?>
    <div class="admin-card">
        <div class="admin-card-body text-center py-5 text-muted">
            <i class="fas fa-lock fa-3x mb-3 d-block opacity-40"></i>
            <h6>Attendance Not Accessible</h6>
            <p style="font-size:13px;">You are not assigned as a Class Teacher for any class.<br>Contact admin to assign you as Class Teacher.</p>
        </div>
    </div>
    <?php
    require_once 'includes/layout_bottom.php';
    exit();
}

// ── Students ──────────────────────────────────────────
$students = [];
if ($selectedClassId && $selectedSection) {
    $students = $conn->query("
        SELECT id, full_name, roll_no, gender, section
        FROM students
        WHERE class_id=$selectedClassId AND section='$selectedSection' AND status='active'
        ORDER BY CAST(roll_no AS UNSIGNED), full_name
    ")->fetch_all(MYSQLI_ASSOC);
}

// ── Existing attendance for selected date ─────────────
$existingAtt = [];
if ($selectedClassId && $selectedSection && $selectedDate) {
    $rows = $conn->query("
        SELECT student_id, status
        FROM attendance
        WHERE class_id=$selectedClassId
          AND section='$selectedSection'
          AND attendance_date='$selectedDate'
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) $existingAtt[$r['student_id']] = $r['status'];
}

// ── Monthly mini-calendar (for date selection) ────────
$todayBS        = getCurrentBS();
$selDateBS      = adToBS($selectedDate);
$calYear        = $selDateBS['year'];
$calMonth       = $selDateBS['month'];
$monthDays      = getSchoolDaysInBSMonth($calYear, $calMonth);

// ── Prev/Next month navigation dates ──────────────────
$prevMonthYear  = $calMonth === 1  ? $calYear - 1 : $calYear;
$prevMonth      = $calMonth === 1  ? 12           : $calMonth - 1;
$nextMonthYear  = $calMonth === 12 ? $calYear + 1 : $calYear;
$nextMonth      = $calMonth === 12 ? 1            : $calMonth + 1;
$prevMonthDate  = bsToAD($prevMonthYear, $prevMonth, 1);
$nextMonthDate  = bsToAD($nextMonthYear, $nextMonth, 1);

// Dates that already have attendance saved for this class
$savedDates = [];
if ($selectedClassId && $selectedSection) {
    $firstDay = $monthDays[0]['ad_date'] ?? $today;
    $lastDay  = end($monthDays)['ad_date'] ?? $today;
    $rows = $conn->query("
        SELECT DISTINCT attendance_date
        FROM attendance
        WHERE class_id=$selectedClassId AND section='$selectedSection'
          AND attendance_date BETWEEN '$firstDay' AND '$lastDay'
    ")->fetch_all(MYSQLI_ASSOC);
    $savedDates = array_column($rows, 'attendance_date');
}

// Selected class info
$selectedClassInfo = null;
foreach ($myClasses as $mc) {
    if ($mc['class_id'] == $selectedClassId && $mc['section'] === $selectedSection) {
        $selectedClassInfo = $mc; break;
    }
}

$selDateIsWeekend = in_array((int)date('w', strtotime($selectedDate)), [5, 6]);
$selDateIsFuture  = ($selectedDate > $today);
$monthName        = getNpMonthName($calMonth);

require_once 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-3">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

<!-- ══ LEFT: Date picker + controls ══════════════════ -->
<div class="col-lg-3">

    <!-- Class selector -->
    <div class="admin-card mb-3">
        <div class="admin-card-body" style="padding:12px;">
            <label class="form-label" style="font-size:12px;font-weight:700;">Class & Section</label>
            <form method="GET" id="classForm">
                <input type="hidden" name="sel_date" value="<?= htmlspecialchars($selectedDate) ?>">
                <select name="class_id" class="form-select form-select-sm mb-2" onchange="syncSection(this)">
                    <option value="">-- Select --</option>
                    <?php foreach ($myClasses as $mc): ?>
                    <option value="<?= $mc['class_id'] ?>"
                            data-section="<?= htmlspecialchars($mc['section']) ?>"
                            <?= ($mc['class_id']==$selectedClassId && $mc['section']===$selectedSection)?'selected':'' ?>>
                        <?= htmlspecialchars($mc['class_name']) ?> — Sec <?= htmlspecialchars($mc['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="section" id="sectionInput" value="<?= htmlspecialchars($selectedSection) ?>">
                <button type="submit" class="btn-admin-primary w-100 btn-sm">Load</button>
            </form>
        </div>
    </div>

    <!-- Mini Calendar -->
    <div class="admin-card">
        <div class="admin-card-header" style="padding:10px 14px;">
            <div class="d-flex align-items-center justify-content-between w-100">
                <a href="attendance.php?class_id=<?= $selectedClassId ?>&section=<?= urlencode($selectedSection) ?>&sel_date=<?= $prevMonthDate ?>"
                   class="btn btn-sm btn-outline-secondary p-1 px-2" style="text-decoration:none;">
                    <i class="fas fa-chevron-left" style="font-size:10px;"></i>
                </a>
                <span style="font-size:13px;font-weight:700;"><?= $monthName ?> <?= $calYear ?></span>
                <a href="attendance.php?class_id=<?= $selectedClassId ?>&section=<?= urlencode($selectedSection) ?>&sel_date=<?= $nextMonthDate ?>"
                   class="btn btn-sm btn-outline-secondary p-1 px-2" style="text-decoration:none;">
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                </a>
            </div>
        </div>
        <div class="admin-card-body" style="padding:10px;">
            <!-- Day headers -->
            <div class="cal-grid-header">
                <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
                <div class="cal-dh <?= in_array($dh,['Fri','Sat'])?'weekend':'' ?>"><?= $dh ?></div>
                <?php endforeach; ?>
            </div>
            <!-- Days grid -->
            <?php
            // Find first day of month's first AD date
            $firstAdDate  = $monthDays[0]['ad_date'] ?? $today;
            $firstDow     = (int)date('w', strtotime($firstAdDate)); // 0=Sun
            $dayMap       = [];
            foreach ($monthDays as $d) $dayMap[$d['bs_day']] = $d;
            $totalBsDays  = getNpMonthDays($calYear, $calMonth);
            ?>
            <div class="cal-grid">
                <!-- Empty cells before first day -->
                <?php for ($e = 0; $e < $firstDow; $e++): ?>
                <div class="cal-cell empty"></div>
                <?php endfor; ?>
                <!-- Day cells -->
                <?php for ($bsD = 1; $bsD <= $totalBsDays; $bsD++):
                    $dayInfo   = $dayMap[$bsD] ?? null;
                    $adDate    = $dayInfo ? $dayInfo['ad_date'] : null;
                    $isWknd    = $dayInfo ? ($dayInfo['is_weekend'] ?? false) : false;
                    $isSel     = ($adDate === $selectedDate);
                    $isToday   = ($adDate === $today);
                    $isFuture  = $adDate ? ($adDate > $today) : false;
                    $isSaved   = $adDate ? in_array($adDate, $savedDates) : false;
                    $cls       = 'cal-cell';
                    if ($isWknd)  $cls .= ' weekend';
                    if ($isSel)   $cls .= ' selected';
                    if ($isToday) $cls .= ' today';
                    if ($isFuture)$cls .= ' future';
                    if ($isSaved) $cls .= ' saved';
                ?>
                <div class="<?= $cls ?>"
                     <?php if($adDate && !$isFuture): ?>
                     onclick="selectDate('<?= $adDate ?>')"
                     style="cursor:pointer;"
                     title="<?= $adDate ?><?= $isWknd?' (Weekend)':'' ?><?= $isSaved?' ✓ Saved':'' ?>"
                     <?php endif; ?>>
                    <?= $bsD ?>
                    <?php if($isSaved): ?><div class="cal-dot"></div><?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <!-- Legend -->
            <div class="d-flex flex-wrap gap-1 mt-2" style="font-size:10px;">
                <span class="cal-legend today">Today</span>
                <span class="cal-legend selected">Selected</span>
                <span class="cal-legend saved">Saved</span>
                <span class="cal-legend weekend">Weekend</span>
            </div>
        </div>
    </div>

</div><!-- /.col-lg-3 -->

<!-- ══ RIGHT: Attendance for selected date ════════════ -->
<div class="col-lg-9">
    <?php if (empty($students)): ?>
    <div class="admin-card">
        <div class="admin-card-body text-center py-5 text-muted">
            <i class="fas fa-users-slash fa-3x mb-3 d-block opacity-40"></i>
            <h6>Select a class and section to start.</h6>
        </div>
    </div>
    <?php else: ?>

    <!-- Date info bar -->
    <?php
    $selBs   = adToBS($selectedDate);
    $selBsStr = $selBs['day'].' '.getNpMonthName($selBs['month']).' '.$selBs['year'].' BS';
    $selAdStr = date('l, F d, Y', strtotime($selectedDate));
    ?>
    <div class="admin-card mb-3">
        <div class="admin-card-body d-flex align-items-center justify-content-between flex-wrap gap-3" style="padding:14px 20px;">
            <div>
                <div style="font-size:16px;font-weight:700;">
                    <i class="fas fa-calendar-day me-2 text-success"></i>
                    <?= $selBsStr ?>
                    <?php if($selDateIsWeekend): ?>
                    <span style="background:#7c3aed;color:white;font-size:11px;padding:2px 8px;border-radius:12px;margin-left:6px;">Weekend</span>
                    <?php endif; ?>
                    <?php if($selDateIsFuture): ?>
                    <span style="background:#e67e22;color:white;font-size:11px;padding:2px 8px;border-radius:12px;margin-left:6px;">Future</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);"><?= $selAdStr ?></div>
            </div>
            <div style="font-size:13px;color:var(--text-muted);">
                <?= count($students) ?> students &bull;
                <?= htmlspecialchars($selectedClassInfo['class_name'] ?? '') ?> — Sec <?= htmlspecialchars($selectedSection) ?>
            </div>
            <div class="d-flex gap-2">
                <a href="attendance.php?class_id=<?= $selectedClassId ?>&section=<?= urlencode($selectedSection) ?>&np_year=<?= $calYear ?>&np_month=<?= $calMonth ?>&export=csv"
                   class="btn-admin-success btn-sm" style="font-size:12px;">
                    <i class="fas fa-file-csv me-1"></i>Export CSV
                </a>
                <button onclick="printAttendance()" class="btn-admin-primary btn-sm" style="font-size:12px;">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>

    <?php if ($selDateIsFuture): ?>
    <div class="admin-card">
        <div class="admin-card-body text-center py-4 text-muted">
            <i class="fas fa-clock fa-2x mb-2 d-block opacity-40"></i>
            <p>Cannot take attendance for future dates.</p>
        </div>
    </div>
    <?php else: ?>

    <form method="POST" id="attendanceForm"
          action="attendance.php">
        <input type="hidden" name="class_id"  value="<?= $selectedClassId ?>">
        <input type="hidden" name="section"   value="<?= htmlspecialchars($selectedSection) ?>">
        <input type="hidden" name="save_date" value="<?= htmlspecialchars($selectedDate) ?>">
        <?php foreach ($students as $st): ?>
        <input type="hidden" name="all_ids[]" value="<?= $st['id'] ?>">
        <?php endforeach; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h6><i class="fas fa-clipboard-check me-2"></i>Mark Attendance</h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-admin-success btn-sm" onclick="checkAll(true)">
                        <i class="fas fa-check-square me-1"></i>All Present
                    </button>
                    <button type="button" class="btn-admin-danger btn-sm" onclick="checkAll(false)">
                        <i class="fas fa-square me-1"></i>All Absent
                    </button>
                </div>
            </div>

            <div class="admin-card-body" style="padding:0;">
                <table class="admin-table">
                    <thead>
                        <tr style="background:#0F3A1A;">
                            <th style="background:#0F3A1A;color:white;width:50px;">#</th>
                            <th style="background:#0F3A1A;color:white;">Student Name</th>
                            <th style="background:#0F3A1A;color:white;width:80px;text-align:center;">Roll No</th>
                            <th style="background:#0F3A1A;color:white;width:120px;text-align:center;">
                                Present ✓
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $st):
                            $savedStatus = $existingAtt[$st['id']] ?? null;
                            // Default: present for past/today school days, holiday for weekends
                            if ($savedStatus !== null) {
                                $isChecked = ($savedStatus === 'present' || $savedStatus === 'late');
                            } elseif ($selDateIsWeekend) {
                                $isChecked = false; // weekend default = holiday/absent
                            } else {
                                $isChecked = false; // no auto-check — teacher must explicitly mark
                            }
                            $rowBg = $savedStatus ? ($isChecked ? 'background:#f0fdf4;' : 'background:#fff5f5;') : '';
                        ?>
                        <tr style="<?= $rowBg ?>">
                            <td style="text-align:center;color:var(--text-muted);"><?= $i+1 ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($st['full_name']) ?></div>
                                <?php if($savedStatus): ?>
                                <div style="font-size:11px;color:var(--text-muted);">
                                    Previously: <span style="font-weight:700;color:<?= $isChecked?'#1a5c2a':'#b5281f' ?>">
                                        <?= ucfirst($savedStatus) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= htmlspecialchars($st['roll_no'] ?? '—') ?></td>
                            <td style="text-align:center;">
                                <input type="checkbox"
                                       name="checked_ids[]"
                                       value="<?= $st['id'] ?>"
                                       class="student-check form-check-input"
                                       style="width:22px;height:22px;cursor:pointer;"
                                       <?= $isChecked ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between px-4 py-3"
                 style="border-top:1px solid var(--border);">
                <div style="font-size:13px;color:var(--text-muted);">
                    <span id="presentCount" class="fw-bold text-success">0</span> present,
                    <span id="absentCount"  class="fw-bold text-danger">0</span> absent
                </div>
                <button type="submit" class="btn-admin-primary" style="padding:10px 28px; font-size:14px;">
                    <i class="fas fa-save me-2"></i>Save Attendance for <?= $selBsStr ?>
                </button>
            </div>
        </div>
    </form>
    <?php endif; // future check ?>
    <?php endif; // students check ?>
</div><!-- /.col-lg-9 -->

</div><!-- /.row -->

<!-- ══ STYLES ══════════════════════════════════════════ -->
<style>
/* Mini Calendar */
.cal-grid-header, .cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    margin-bottom: 4px;
}
.cal-dh {
    text-align: center;
    font-size: 10px;
    font-weight: 700;
    color: var(--text-muted);
    padding: 2px 0;
}
.cal-dh.weekend { color: #7c3aed; }
.cal-cell {
    text-align: center;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 2px;
    border-radius: 6px;
    position: relative;
    color: var(--text-dark);
    min-height: 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.cal-cell:hover:not(.empty):not(.future) { background: #edf7f0; }
.cal-cell.empty { }
.cal-cell.weekend { color: #7c3aed; background: #f3eeff; }
.cal-cell.today { background: #1a5c2a; color: white; border-radius: 6px; }
.cal-cell.selected { background: var(--accent); color: var(--dark); font-weight: 800; }
.cal-cell.saved { font-weight: 800; }
.cal-cell.future { opacity: 0.35; cursor: default !important; }
.cal-dot {
    width: 5px; height: 5px;
    background: #27ae60;
    border-radius: 50%;
    margin-top: 1px;
}
.cal-cell.selected .cal-dot { background: var(--dark); }
.cal-cell.today .cal-dot { background: white; }

/* Cal legend */
.cal-legend {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}
.cal-legend.today    { background: #1a5c2a; color: white; }
.cal-legend.selected { background: var(--accent); color: var(--dark); }
.cal-legend.saved    { background: #d4edda; color: #155724; border: 1px solid #b8ddc8; }
.cal-legend.weekend  { background: #f3eeff; color: #7c3aed; }
</style>

<!-- ══ JAVASCRIPT ═══════════════════════════════════════ -->
<script>
function syncSection(sel) {
    var sec = sel.options[sel.selectedIndex].dataset.section || '';
    document.getElementById('sectionInput').value = sec;
    sel.form.submit();
}

function selectDate(adDate) {
    var url = 'attendance.php?class_id=<?= $selectedClassId ?>&section=<?= urlencode($selectedSection) ?>&sel_date=' + adDate;
    window.location.href = url;
}

function checkAll(check) {
    document.querySelectorAll('.student-check').forEach(cb => cb.checked = check);
    updateCount();
}

function updateCount() {
    var checked = document.querySelectorAll('.student-check:checked').length;
    var total   = document.querySelectorAll('.student-check').length;
    var el_p = document.getElementById('presentCount');
    var el_a = document.getElementById('absentCount');
    if (el_p) el_p.textContent = checked;
    if (el_a) el_a.textContent = (total - checked);
}

document.querySelectorAll('.student-check').forEach(cb => cb.addEventListener('change', updateCount));
document.addEventListener('DOMContentLoaded', updateCount);

// Warn before leaving with unsaved changes - only when form is dirty
var formDirty = false;
document.querySelectorAll('.student-check').forEach(cb => cb.addEventListener('change', () => formDirty = true));
document.getElementById('attendanceForm') && document.getElementById('attendanceForm').addEventListener('submit', () => formDirty = false);

// Print monthly summary
function printAttendance() {
    document.getElementById('printArea').style.display = 'block';
    window.print();
    document.getElementById('printArea').style.display = 'none';
}
</script>

<!-- Print Area -->
<div id="printArea" style="display:none;">
<?php if ($selectedClassInfo && !empty($students)): ?>
<?php
// Load all month data for print
$allMonthDays = getSchoolDaysInBSMonth($calYear, $calMonth);
$firstDayPr   = $allMonthDays[0]['ad_date'] ?? date('Y-m-d');
$lastDayPr    = end($allMonthDays)['ad_date'] ?? date('Y-m-d');
$allAttPr     = [];
$pRows = $conn->query("SELECT student_id, attendance_date, status FROM attendance
    WHERE class_id=$selectedClassId AND section='$selectedSection'
    AND attendance_date BETWEEN '$firstDayPr' AND '$lastDayPr'")->fetch_all(MYSQLI_ASSOC);
foreach ($pRows as $r) $allAttPr[$r['student_id']][$r['attendance_date']] = $r['status'];
$schoolName = getSetting($conn, 'school_name');
?>
<div style="font-family:'Times New Roman',serif;font-size:12px;color:#000;padding:20px;">
    <div style="text-align:center;margin-bottom:12px;border-bottom:2px solid #000;padding-bottom:8px;">
        <div style="font-size:18px;font-weight:900;text-transform:uppercase;"><?= htmlspecialchars($schoolName) ?></div>
        <div style="font-size:13px;font-weight:700;margin-top:4px;">Monthly Attendance Register</div>
        <div style="font-size:12px;">
            Class: <b><?= htmlspecialchars($selectedClassInfo['class_name']) ?></b>
            &nbsp;|&nbsp; Section: <b><?= htmlspecialchars($selectedSection) ?></b>
            &nbsp;|&nbsp; Month: <b><?= getNpMonthName($calMonth) ?> <?= $calYear ?> BS</b>
            &nbsp;|&nbsp; School Days: <b><?= count(array_filter($allMonthDays, fn($d)=>!($d['is_weekend']??false))) ?></b>
        </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:10px;">
        <thead>
            <tr style="background:#1a3a1a;color:white;">
                <th style="border:1px solid #555;padding:3px 5px;">Roll</th>
                <th style="border:1px solid #555;padding:3px 5px;text-align:left;">Student Name</th>
                <?php foreach ($allMonthDays as $d): ?>
                <th style="border:1px solid #555;padding:2px;text-align:center;width:22px;
                    <?= ($d['is_weekend']??false)?'background:#5b21b6;':'' ?>">
                    <?= $d['bs_day'] ?>
                </th>
                <?php endforeach; ?>
                <th style="border:1px solid #555;padding:3px;">P</th>
                <th style="border:1px solid #555;padding:3px;">A</th>
                <th style="border:1px solid #555;padding:3px;">L</th>
            </tr>
            <tr style="background:#e8e8e8;">
                <th style="border:1px solid #aaa;padding:2px;"></th>
                <th style="border:1px solid #aaa;padding:2px;"></th>
                <?php foreach ($allMonthDays as $d): ?>
                <th style="border:1px solid #aaa;padding:1px;text-align:center;font-size:9px;
                    <?= ($d['is_weekend']??false)?'color:#5b21b6;':'' ?>">
                    <?= date('D', strtotime($d['ad_date']))[0] ?>
                </th>
                <?php endforeach; ?>
                <th style="border:1px solid #aaa;padding:2px;"></th>
                <th style="border:1px solid #aaa;padding:2px;"></th>
                <th style="border:1px solid #aaa;padding:2px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $idx => $st):
                $p=0; $a=0; $l=0;
            ?>
            <tr style="<?= $idx%2?'background:#f5f5f5;':'' ?>">
                <td style="border:1px solid #ccc;padding:2px 4px;text-align:center;"><?= $st['roll_no']??$idx+1 ?></td>
                <td style="border:1px solid #ccc;padding:2px 6px;"><?= htmlspecialchars($st['full_name']) ?></td>
                <?php foreach ($allMonthDays as $d):
                    $isWknd = $d['is_weekend'] ?? false;
                    $status = $allAttPr[$st['id']][$d['ad_date']] ?? ($isWknd ? 'holiday' : '');
                    $lbl    = $isWknd ? 'H' : ($status ? strtoupper(substr($status,0,1)) : '');
                    $bg     = $isWknd ? 'background:#ede9fe;' : ($status==='absent'?'background:#fee2e2;':($status==='present'?'background:#dcfce7;':($status==='late'?'background:#fef3c7;':'')));
                    if ($status==='present') $p++;
                    elseif ($status==='absent') $a++;
                    elseif ($status==='late') $l++;
                ?>
                <td style="border:1px solid #ccc;padding:1px;text-align:center;font-size:9px;font-weight:700;<?= $bg ?>"><?= $lbl ?></td>
                <?php endforeach; ?>
                <td style="border:1px solid #ccc;padding:2px 4px;text-align:center;font-weight:700;color:green;"><?= $p ?></td>
                <td style="border:1px solid #ccc;padding:2px 4px;text-align:center;font-weight:700;color:red;"><?= $a ?></td>
                <td style="border:1px solid #ccc;padding:2px 4px;text-align:center;font-weight:700;color:orange;"><?= $l ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:16px;display:flex;justify-content:space-between;font-size:11px;">
        <div>P = Present &nbsp; A = Absent &nbsp; L = Late &nbsp; H = Holiday/Weekend</div>
        <div>Printed: <?= date('F d, Y') ?></div>
    </div>
    <div style="margin-top:30px;display:flex;justify-content:space-around;">
        <div style="text-align:center;"><div style="border-top:1px solid #000;width:120px;margin:0 auto;"></div><div>Class Teacher</div></div>
        <div style="text-align:center;"><div style="border-top:1px solid #000;width:120px;margin:0 auto;"></div><div>Principal</div></div>
    </div>
</div>
<?php endif; ?>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #printArea, #printArea * { visibility: visible !important; }
    #printArea { position: fixed; inset: 0; padding: 10px; }
}
</style>

<?php require_once 'includes/layout_bottom.php'; ?>
