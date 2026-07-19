<?php
$pageTitle = 'Scholarship Window Settings';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';

$message = ''; $messageType = '';

function getScholarshipStatus($conn) {
    $isOpen    = getSetting($conn, 'scholarship_is_open');
    $openFrom  = getSetting($conn, 'scholarship_open_from');
    $openUntil = getSetting($conn, 'scholarship_open_until');
    $today     = date('Y-m-d');

    if ($openFrom && $openUntil) {
        $auto = ($today >= $openFrom && $today <= $openUntil);
    } elseif ($openFrom && !$openUntil) {
        $auto = ($today >= $openFrom);
    } elseif (!$openFrom && $openUntil) {
        $auto = ($today <= $openUntil);
    } else {
        $auto = null;
    }
    return $auto !== null ? $auto : (bool)$isOpen;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $isOpen    = isset($_POST['scholarship_is_open']) ? '1' : '0';
        $openMsg   = sanitize($conn, $_POST['scholarship_open_message']   ?? '');
        $closedMsg = sanitize($conn, $_POST['scholarship_closed_message'] ?? '');
        $acadYear  = sanitize($conn, $_POST['scholarship_academic_year']  ?? '');

        // Convert BS dates to AD
        $openFromBS  = trim($_POST['scholarship_open_from_bs']  ?? '');
        $openUntilBS = trim($_POST['scholarship_open_until_bs'] ?? '');
        $openFrom    = '';
        $openUntil   = '';
        if ($openFromBS) {
            $parts = preg_split('/[-\/]/', $openFromBS);
            if (count($parts) === 3) $openFrom = bsToAD((int)$parts[0], (int)$parts[1], (int)$parts[2]);
        }
        if ($openUntilBS) {
            $parts = preg_split('/[-\/]/', $openUntilBS);
            if (count($parts) === 3) $openUntil = bsToAD((int)$parts[0], (int)$parts[1], (int)$parts[2]);
        }

        if ($openFrom && $openUntil && $openFrom > $openUntil) {
            $message = 'Start date cannot be after end date.'; $messageType = 'danger';
        } else {
            foreach ([
                'scholarship_is_open'        => $isOpen,
                'scholarship_open_from'      => $openFrom,
                'scholarship_open_until'     => $openUntil,
                'scholarship_open_message'   => $openMsg,
                'scholarship_closed_message' => $closedMsg,
                'scholarship_academic_year'  => $acadYear,
            ] as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO school_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
                $stmt->bind_param("sss", $key, $val, $val);
                $stmt->execute(); $stmt->close();
            }
            $message = 'Scholarship settings saved!'; $messageType = 'success';
        }

    } elseif ($action === 'quick_open') {
        $days  = (int)($_POST['days'] ?? 30);
        $from  = date('Y-m-d');
        $until = date('Y-m-d', strtotime("+$days days"));
        $conn->query("UPDATE school_settings SET setting_value='1'    WHERE setting_key='scholarship_is_open'");
        $conn->query("UPDATE school_settings SET setting_value='$from'  WHERE setting_key='scholarship_open_from'");
        $conn->query("UPDATE school_settings SET setting_value='$until' WHERE setting_key='scholarship_open_until'");
        $message = "Scholarship window opened for $days days (until ".date('M d, Y',strtotime($until)).")!";
        $messageType = 'success';

    } elseif ($action === 'close_now') {
        $conn->query("UPDATE school_settings SET setting_value='0' WHERE setting_key='scholarship_is_open'");
        $conn->query("UPDATE school_settings SET setting_value=''  WHERE setting_key='scholarship_open_from'");
        $conn->query("UPDATE school_settings SET setting_value=''  WHERE setting_key='scholarship_open_until'");
        $message = 'Scholarship window closed.'; $messageType = 'warning';
    }
}

$isOpen      = getSetting($conn, 'scholarship_is_open');
$openFrom    = getSetting($conn, 'scholarship_open_from');
$openUntil   = getSetting($conn, 'scholarship_open_until');
$openMsg     = getSetting($conn, 'scholarship_open_message');
$closedMsg   = getSetting($conn, 'scholarship_closed_message');
$acadYear    = getSetting($conn, 'scholarship_academic_year') ?: getSetting($conn, 'academic_year');
$currentStatus = getScholarshipStatus($conn);

// Convert AD dates to BS for display
function adToDisplay($adDate) {
    if (!$adDate || $adDate === '0000-00-00') return '';
    $bs = adToBS($adDate);
    return $bs['year'] . '-' . str_pad($bs['month'],2,'0',STR_PAD_LEFT) . '-' . str_pad($bs['day'],2,'0',STR_PAD_LEFT);
}
$openFromBS  = adToDisplay($openFrom);
$openUntilBS = adToDisplay($openUntil);

$pendingCount = $conn->query("SELECT COUNT(*) as c FROM scholarship_applications WHERE status='pending'")->fetch_assoc()['c'];
$totalCount   = $conn->query("SELECT COUNT(*) as c FROM scholarship_applications")->fetch_assoc()['c'];
$yearCount    = $conn->query("SELECT COUNT(*) as c FROM scholarship_applications WHERE DATE_FORMAT(submitted_at,'%Y')=YEAR(NOW())")->fetch_assoc()['c'];

require_once 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Status Banner -->
<div class="mb-4 p-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-3"
     style="background:<?= $currentStatus?'linear-gradient(135deg,#1b6b35,#28a048)':'linear-gradient(135deg,#7c1a14,#b5281f)' ?>;color:white;">
    <div class="d-flex align-items-center gap-3">
        <div style="width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:50%;
                    display:flex;align-items:center;justify-content:center;font-size:22px;">
            <i class="fas fa-<?= $currentStatus?'door-open':'door-closed' ?>"></i>
        </div>
        <div>
            <div style="font-size:18px;font-weight:800;">
                Scholarship window is <strong><?= $currentStatus?'OPEN':'CLOSED' ?></strong>
            </div>
            <div style="font-size:13px;opacity:0.85;">
                <?php if ($currentStatus && $openUntil): ?>
                    Closes on: <?= date('F d, Y', strtotime($openUntil)) ?>
                    (<?= max(0,(int)((strtotime($openUntil)-time())/86400)) ?> days left)
                <?php elseif ($currentStatus): ?>
                    No end date — open indefinitely
                <?php elseif ($openFrom && $openFrom > date('Y-m-d')): ?>
                    Opens on: <?= date('F d, Y', strtotime($openFrom)) ?>
                <?php else: ?>
                    Set a date range below to auto-open
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($currentStatus): ?>
        <form method="POST">
            <input type="hidden" name="action" value="close_now">
            <button type="submit" class="btn btn-light btn-sm fw-bold"
                    onclick="return confirm('Close scholarship window now?')">
                <i class="fas fa-times-circle me-1"></i>Close Now
            </button>
        </form>
        <?php else: ?>
        <form method="POST" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="action" value="quick_open">
            <select name="days" class="form-select form-select-sm" style="width:110px;">
                <option value="7">7 days</option>
                <option value="14">14 days</option>
                <option value="30" selected>30 days</option>
                <option value="60">60 days</option>
                <option value="90">90 days</option>
            </select>
            <button type="submit" class="btn btn-light btn-sm fw-bold">
                <i class="fas fa-door-open me-1"></i>Quick Open
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:var(--primary);">
            <div class="icon-box" style="background:var(--primary);"><i class="fas fa-list"></i></div>
            <div class="stat-info"><div class="number"><?= $totalCount ?></div><div class="label">Total Applications</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#e67e22;">
            <div class="icon-box" style="background:#e67e22;"><i class="fas fa-clock"></i></div>
            <div class="stat-info"><div class="number"><?= $pendingCount ?></div><div class="label">Pending Review</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#2980b9;">
            <div class="icon-box" style="background:#2980b9;"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info"><div class="number"><?= $yearCount ?></div><div class="label">This Year</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="scholarship.php?status=approved" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#27ae60;">
                <div class="icon-box" style="background:#27ae60;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $conn->query("SELECT COUNT(*) as c FROM scholarship_applications WHERE status='approved'")->fetch_assoc()['c'] ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Settings Form -->
<div class="row g-4">
<div class="col-lg-7">
<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-cog me-2"></i>Scholarship Window Settings</h6>
    </div>
    <div class="admin-card-body">
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="save_settings">

            <div class="mb-4 p-3 rounded-2" style="background:var(--light);border:2px solid var(--border);">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="schol_is_open"
                           name="scholarship_is_open" role="switch"
                           style="width:48px;height:24px;" <?= $isOpen?'checked':'' ?>>
                    <label class="form-check-label ms-2 fw-bold" for="schol_is_open" style="font-size:15px;">
                        Manually Open Scholarship
                    </label>
                </div>
                <div class="text-muted mt-1" style="font-size:12.5px;padding-left:58px;">
                    <i class="fas fa-info-circle me-1"></i>
                    If date range is set below, dates take priority over this toggle.
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-calendar-check me-1 text-success"></i>Opens From (BS)</label>
                    <input type="text" name="scholarship_open_from_bs" id="openFromBS"
                           class="form-control" placeholder="YYYY-MM-DD (e.g. 2083-01-01)"
                           value="<?= htmlspecialchars($openFromBS) ?>"
                           autocomplete="off">
                    <div class="form-text text-success">
                        <?php if($openFrom): ?>
                        AD: <?= date('M d, Y', strtotime($openFrom)) ?>
                        <?php else: ?>BS format: 2083-01-01<?php endif; ?>
                        <span id="previewFrom"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-calendar-times me-1 text-danger"></i>Closes On (BS)</label>
                    <input type="text" name="scholarship_open_until_bs" id="openUntilBS"
                           class="form-control" placeholder="YYYY-MM-DD (e.g. 2083-03-31)"
                           value="<?= htmlspecialchars($openUntilBS) ?>"
                           autocomplete="off">
                    <div class="form-text text-danger">
                        <?php if($openUntil): ?>
                        AD: <?= date('M d, Y', strtotime($openUntil)) ?>
                        <?php else: ?>BS format: 2083-03-31<?php endif; ?>
                        <span id="previewUntil"></span>
                    </div>
                </div>
                <div class="col-12">
                    <div class="p-2 rounded-2" style="background:#f0f7f0;font-size:12.5px;color:#1a5c2a;">
                        <i class="fas fa-info-circle me-1"></i>
                        Enter Nepali (BS) date in <strong>YYYY-MM-DD</strong> format.
                        Example: <strong>2083-01-15</strong> = Baisakh 15, 2083
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Academic Year (for duplicate check)</label>
                <input type="text" name="scholarship_academic_year" class="form-control"
                       value="<?= htmlspecialchars($acadYear) ?>" placeholder="e.g. 2083">
                <div class="form-text">
                    <i class="fas fa-shield-alt me-1 text-primary"></i>
                    Same student (Father's Name + DOB + this year) cannot apply twice.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-check-circle me-1 text-success"></i>Message when OPEN</label>
                <input type="text" name="scholarship_open_message" class="form-control"
                       value="<?= htmlspecialchars($openMsg) ?>" maxlength="200">
            </div>
            <div class="mb-4">
                <label class="form-label"><i class="fas fa-times-circle me-1 text-danger"></i>Message when CLOSED</label>
                <input type="text" name="scholarship_closed_message" class="form-control"
                       value="<?= htmlspecialchars($closedMsg) ?>" maxlength="200">
            </div>

            <button type="submit" class="btn-admin-primary w-100" style="padding:12px;font-size:15px;">
                <i class="fas fa-save me-2"></i>Save Settings
            </button>
        </form>
    </div>
</div>
</div>

<!-- How it works -->
<div class="col-lg-5">
    <div class="admin-card mb-4">
        <div class="admin-card-header"><h6><i class="fas fa-question-circle me-2"></i>How It Works</h6></div>
        <div class="admin-card-body" style="font-size:13.5px;">
            <?php foreach([
                ['bg'=>'var(--primary)','n'=>1,'title'=>'Set Date Range','desc'=>'Set open/close dates. System auto-opens on those dates.'],
                ['bg'=>'var(--secondary)','n'=>2,'title'=>'Quick Open','desc'=>'Select days and click Quick Open to instantly open.'],
                ['bg'=>'#e67e22','n'=>3,'title'=>'Duplicate Prevention','desc'=>'Same Father Name + DOB + Academic Year = blocked from applying twice.'],
                ['bg'=>'#27ae60','n'=>4,'title'=>'Public Page','desc'=>'When closed, form is hidden and closed message is shown.'],
            ] as $step): ?>
            <div class="d-flex gap-3 mb-3">
                <div style="width:32px;height:32px;background:<?= $step['bg'] ?>;border-radius:8px;
                            display:flex;align-items:center;justify-content:center;color:white;
                            font-weight:700;flex-shrink:0;"><?= $step['n'] ?></div>
                <div><strong><?= $step['title'] ?></strong><br>
                    <span class="text-muted"><?= $step['desc'] ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Preview -->
    <div class="admin-card">
        <div class="admin-card-header"><h6><i class="fas fa-eye me-2"></i>Public Page Preview</h6></div>
        <div class="admin-card-body">
            <div class="p-3 rounded-2 mb-2" style="background:#d4edda;border:1px solid #b8ddc8;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-check-circle text-success"></i>
                    <div><div style="font-size:13px;font-weight:700;color:#155724;">When OPEN:</div>
                    <div style="font-size:12.5px;color:#155724;"><?= htmlspecialchars($openMsg ?: 'Scholarship applications are now open!') ?></div></div>
                </div>
            </div>
            <div class="p-3 rounded-2" style="background:#f8d7da;border:1px solid #f0b8bc;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-times-circle text-danger"></i>
                    <div><div style="font-size:13px;font-weight:700;color:#721c24;">When CLOSED:</div>
                    <div style="font-size:12.5px;color:#721c24;"><?= htmlspecialchars($closedMsg ?: 'Scholarship applications are currently closed.') ?></div></div>
                </div>
            </div>
            <div class="mt-3">
                <a href="../scholarship.php" target="_blank" class="btn-admin-primary w-100" style="justify-content:center;">
                    <i class="fas fa-external-link-alt me-1"></i>View Scholarship Page
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<!-- BS date live AD preview -->
<script>
// Nepali month days (2078-2110)
var NP_DAYS = {
    2078:[31,31,32,32,31,30,30,29,30,29,30,30],
    2079:[31,31,32,32,31,30,30,29,30,29,30,30],
    2080:[31,32,31,32,31,30,30,30,29,29,30,30],
    2081:[31,31,32,32,31,30,30,29,30,29,30,30],
    2082:[31,32,31,32,31,30,30,29,30,29,30,31],
    2083:[30,32,31,32,31,30,30,30,29,29,30,31],
    2084:[31,31,32,31,31,31,30,29,30,29,30,30],
    2085:[31,31,32,32,31,30,30,29,30,29,30,30],
    2086:[31,32,31,32,31,30,30,29,30,29,30,30],
    2087:[31,32,31,32,31,30,30,29,30,29,30,30],
    2088:[31,31,32,32,31,30,30,29,30,29,30,31],
    2089:[30,32,31,32,31,30,30,30,29,29,30,30],
    2090:[31,31,32,32,31,30,30,29,30,29,30,30],
    2091:[31,31,32,32,31,30,30,29,30,29,30,30],
    2092:[31,32,31,32,31,30,30,30,29,29,30,30],
    2093:[31,31,32,32,31,30,30,29,30,29,30,30],
    2094:[31,32,31,32,31,30,30,29,30,29,30,31],
    2095:[30,32,31,32,31,30,30,30,29,29,30,31],
    2096:[31,31,32,31,31,31,30,29,30,29,30,30],
    2097:[31,31,32,32,31,30,30,29,30,29,30,30],
    2098:[31,32,31,32,31,30,30,29,30,29,30,30],
    2099:[31,31,32,32,31,30,30,29,30,30,29,31],
    2100:[30,32,31,32,31,30,30,30,29,29,30,30],
    2101:[31,31,32,32,31,30,30,29,30,29,30,30],
    2102:[31,32,31,32,31,30,30,29,30,29,30,30],
    2103:[31,32,31,32,31,30,30,30,29,29,30,30],
    2104:[31,31,32,32,31,30,30,29,30,29,30,30],
    2105:[31,32,31,32,31,30,30,29,30,29,30,31],
    2106:[30,32,31,32,31,30,30,30,29,29,30,31],
    2107:[31,31,32,31,31,31,30,29,30,29,30,30],
    2108:[31,31,32,32,31,30,30,29,30,29,30,30],
    2109:[31,32,31,32,31,30,30,29,30,29,30,30],
    2110:[31,32,31,32,31,30,30,30,29,29,30,30]
};
var AD_REF = new Date(2024, 3, 13); // 2081-01-01
var BS_REF = {y:2081, m:1, d:1};

function bsToAd(bsY, bsM, bsD) {
    var diff = 0;
    // Count days from ref to target
    for (var y = BS_REF.y; y < bsY; y++) {
        for (var m = 1; m <= 12; m++) {
            diff += (NP_DAYS[y] || [])[m-1] || (m<=6?31:30);
        }
    }
    for (var m = BS_REF.m; m < bsM; m++) {
        diff += (NP_DAYS[bsY] || [])[m-1] || (m<=6?31:30);
    }
    diff += bsD - BS_REF.d;
    var adDate = new Date(AD_REF);
    adDate.setDate(adDate.getDate() + diff);
    return adDate;
}

function updateAdPreview(inputId, hintId) {
    var val = document.getElementById(inputId).value.trim();
    var hint = document.getElementById(hintId);
    if (!val) { hint.textContent = ''; return; }
    var parts = val.split(/[-\/]/);
    if (parts.length !== 3) { hint.textContent = 'Format: YYYY-MM-DD'; hint.style.color='#b5281f'; return; }
    var y=parseInt(parts[0]), m=parseInt(parts[1]), d=parseInt(parts[2]);
    if (!y||!m||!d||m<1||m>12||d<1||d>32) { hint.textContent = 'Invalid date'; hint.style.color='#b5281f'; return; }
    try {
        var ad = bsToAd(y, m, d);
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        hint.textContent = 'AD: ' + months[ad.getMonth()] + ' ' + ad.getDate() + ', ' + ad.getFullYear();
        hint.style.color = '#1a5c2a';
    } catch(e) { hint.textContent = 'Conversion error'; hint.style.color='#b5281f'; }
}

document.getElementById('openFromBS').addEventListener('input',  function(){ updateAdPreview('openFromBS',  'previewFrom'); });
document.getElementById('openUntilBS').addEventListener('input', function(){ updateAdPreview('openUntilBS', 'previewUntil'); });
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
