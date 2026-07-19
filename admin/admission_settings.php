<?php
$pageTitle = 'Admission Window Settings';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';

$message = ''; $messageType = '';

// Helper to get admission status logic
function getAdmissionStatus($conn) {
    $isOpen     = getSetting($conn, 'admission_is_open');
    $openFrom   = getSetting($conn, 'admission_open_from');
    $openUntil  = getSetting($conn, 'admission_open_until');
    $today      = date('Y-m-d');

    // Auto date-based check
    if ($openFrom && $openUntil) {
        $autoOpen = ($today >= $openFrom && $today <= $openUntil);
    } elseif ($openFrom && !$openUntil) {
        $autoOpen = ($today >= $openFrom);
    } elseif (!$openFrom && $openUntil) {
        $autoOpen = ($today <= $openUntil);
    } else {
        $autoOpen = null; // no dates set — use manual toggle only
    }

    // If dates are set, auto status overrides manual toggle
    if ($autoOpen !== null) {
        return $autoOpen;
    }
    return (bool)$isOpen;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $isOpen    = isset($_POST['admission_is_open']) ? '1' : '0';
        $openMsg   = sanitize($conn, $_POST['admission_open_message']   ?? '');
        $closedMsg = sanitize($conn, $_POST['admission_closed_message'] ?? '');

        // Convert BS dates to AD
        $openFromBS  = trim($_POST['admission_open_from_bs']  ?? '');
        $openUntilBS = trim($_POST['admission_open_until_bs'] ?? '');
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

        // Validate dates
        if ($openFrom && $openUntil && $openFrom > $openUntil) {
            $message = 'Start date cannot be after end date.';
            $messageType = 'danger';
        } else {
            $updates = [
                'admission_is_open'        => $isOpen,
                'admission_open_from'      => $openFrom,
                'admission_open_until'     => $openUntil,
                'admission_open_message'   => $openMsg,
                'admission_closed_message' => $closedMsg,
            ];
            foreach ($updates as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
                $stmt->bind_param("sss", $key, $val, $val);
                $stmt->execute(); $stmt->close();
            }
            $message = 'Admission settings saved successfully!';
            $messageType = 'success';
        }

    } elseif ($action === 'quick_open') {
        $days  = (int)($_POST['days'] ?? 7);
        $from  = date('Y-m-d');
        $until = date('Y-m-d', strtotime("+$days days"));
        $conn->query("UPDATE school_settings SET setting_value='1' WHERE setting_key='admission_is_open'");
        $conn->query("UPDATE school_settings SET setting_value='$from' WHERE setting_key='admission_open_from'");
        $conn->query("UPDATE school_settings SET setting_value='$until' WHERE setting_key='admission_open_until'");
        $message = "Admissions opened for $days days (until " . date('M d, Y', strtotime($until)) . ")!";
        $messageType = 'success';

    } elseif ($action === 'close_now') {
        $conn->query("UPDATE school_settings SET setting_value='0' WHERE setting_key='admission_is_open'");
        $conn->query("UPDATE school_settings SET setting_value='' WHERE setting_key='admission_open_from'");
        $conn->query("UPDATE school_settings SET setting_value='' WHERE setting_key='admission_open_until'");
        $message = 'Admissions closed immediately.';
        $messageType = 'warning';
    }
}

// Load current settings
$isOpen      = getSetting($conn, 'admission_is_open');
$openFrom    = getSetting($conn, 'admission_open_from');
$openUntil   = getSetting($conn, 'admission_open_until');
$openMsg     = getSetting($conn, 'admission_open_message');
$closedMsg   = getSetting($conn, 'admission_closed_message');
$currentStatus = getAdmissionStatus($conn);

// Convert AD dates to BS for display
function adToDisplayAdm($adDate) {
    if (!$adDate || $adDate === '0000-00-00') return '';
    $bs = adToBS($adDate);
    return $bs['year'].'-'.str_pad($bs['month'],2,'0',STR_PAD_LEFT).'-'.str_pad($bs['day'],2,'0',STR_PAD_LEFT);
}
$openFromBS  = adToDisplayAdm($openFrom);
$openUntilBS = adToDisplayAdm($openUntil);

// Count pending admissions
$pendingCount = $conn->query("SELECT COUNT(*) as c FROM admissions WHERE status='pending'")->fetch_assoc()['c'];
$totalCount   = $conn->query("SELECT COUNT(*) as c FROM admissions")->fetch_assoc()['c'];

require_once 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Current Status Banner -->
<div class="mb-4 p-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-3"
     style="background:<?= $currentStatus ? 'linear-gradient(135deg,#1b6b35,#28a048)' : 'linear-gradient(135deg,#7c1a14,#b5281f)' ?>;color:white;">
    <div class="d-flex align-items-center gap-3">
        <div style="width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:50%;
                    display:flex;align-items:center;justify-content:center;font-size:22px;">
            <i class="fas fa-<?= $currentStatus ? 'door-open' : 'door-closed' ?>"></i>
        </div>
        <div>
            <div style="font-size:18px;font-weight:800;">
                Admissions are currently <strong><?= $currentStatus ? 'OPEN' : 'CLOSED' ?></strong>
            </div>
            <div style="font-size:13px;opacity:0.85;">
                <?php if ($currentStatus && $openUntil): ?>
                    Closes on: <?= date('F d, Y', strtotime($openUntil)) ?>
                    (<?= max(0, (int)((strtotime($openUntil) - time()) / 86400)) ?> days left)
                <?php elseif ($currentStatus): ?>
                    No end date set — open indefinitely
                <?php elseif ($openFrom && $openFrom > date('Y-m-d')): ?>
                    Opens on: <?= date('F d, Y', strtotime($openFrom)) ?>
                <?php else: ?>
                    Set a date range below to auto-open admissions
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($currentStatus): ?>
        <form method="POST">
            <input type="hidden" name="action" value="close_now">
            <button type="submit" class="btn btn-light btn-sm fw-bold"
                    onclick="return confirm('Close admissions immediately?')">
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

<!-- Stats Row -->
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
        <a href="admissions.php?status=approved" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:#27ae60;">
                <div class="icon-box" style="background:#27ae60;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $conn->query("SELECT COUNT(*) as c FROM admissions WHERE status='approved'")->fetch_assoc()['c'] ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="admissions.php?status=rejected" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:var(--secondary);">
                <div class="icon-box" style="background:var(--secondary);"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <div class="number"><?= $conn->query("SELECT COUNT(*) as c FROM admissions WHERE status='rejected'")->fetch_assoc()['c'] ?></div>
                    <div class="label">Rejected</div>
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
                <h6><i class="fas fa-cog me-2"></i>Admission Window Settings</h6>
            </div>
            <div class="admin-card-body">
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="save_settings">

                    <!-- Manual Toggle -->
                    <div class="mb-4 p-3 rounded-2" style="background:var(--light);border:2px solid var(--border);">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="admission_is_open"
                                   name="admission_is_open" role="switch"
                                   style="width:48px;height:24px;"
                                   <?= $isOpen ? 'checked' : '' ?>>
                            <label class="form-check-label ms-2 fw-bold" for="admission_is_open" style="font-size:15px;">
                                Manually Open Admissions
                            </label>
                        </div>
                        <div class="text-muted mt-1" style="font-size:12.5px;padding-left:58px;">
                            <i class="fas fa-info-circle me-1"></i>
                            If date range is set below, dates take priority over this toggle.
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-calendar-check me-1 text-success"></i>
                                Admission Opens From (BS)
                            </label>
                            <input type="text" name="admission_open_from_bs" id="admOpenFromBS"
                                   class="form-control" placeholder="YYYY-MM-DD (e.g. 2083-01-01)"
                                   value="<?= htmlspecialchars($openFromBS) ?>" autocomplete="off">
                            <div class="form-text text-success">
                                <?php if($openFrom): ?>AD: <?= date('M d, Y', strtotime($openFrom)) ?>
                                <?php else: ?>BS format: 2083-01-01<?php endif; ?>
                                <span id="admPreviewFrom"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-calendar-times me-1 text-danger"></i>
                                Admission Closes On (BS)
                            </label>
                            <input type="text" name="admission_open_until_bs" id="admOpenUntilBS"
                                   class="form-control" placeholder="YYYY-MM-DD (e.g. 2083-03-31)"
                                   value="<?= htmlspecialchars($openUntilBS) ?>" autocomplete="off">
                            <div class="form-text text-danger">
                                <?php if($openUntil): ?>AD: <?= date('M d, Y', strtotime($openUntil)) ?>
                                <?php else: ?>BS format: 2083-03-31<?php endif; ?>
                                <span id="admPreviewUntil"></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-2 rounded-2" style="background:#f0f7f0;font-size:12.5px;color:#1a5c2a;">
                                <i class="fas fa-info-circle me-1"></i>
                                Enter Nepali (BS) date in <strong>YYYY-MM-DD</strong> format. Example: <strong>2083-01-15</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-check-circle me-1 text-success"></i>
                            Message when Admissions are OPEN
                        </label>
                        <input type="text" name="admission_open_message" class="form-control"
                               value="<?= htmlspecialchars($openMsg) ?>" maxlength="200"
                               placeholder="e.g. Admissions are now open! Apply before the deadline.">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-times-circle me-1 text-danger"></i>
                            Message when Admissions are CLOSED
                        </label>
                        <input type="text" name="admission_closed_message" class="form-control"
                               value="<?= htmlspecialchars($closedMsg) ?>" maxlength="200"
                               placeholder="e.g. Admissions are currently closed.">
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
            <div class="admin-card-header">
                <h6><i class="fas fa-question-circle me-2"></i>How It Works</h6>
            </div>
            <div class="admin-card-body" style="font-size:13.5px;">
                <div class="d-flex gap-3 mb-3">
                    <div style="width:32px;height:32px;background:var(--primary);border-radius:8px;
                                display:flex;align-items:center;justify-content:center;color:white;
                                font-weight:700;flex-shrink:0;">1</div>
                    <div>
                        <strong>Set Date Range</strong><br>
                        <span class="text-muted">Set "Opens From" and "Closes On" dates. System auto-opens/closes on those dates.</span>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div style="width:32px;height:32px;background:var(--secondary);border-radius:8px;
                                display:flex;align-items:center;justify-content:center;color:white;
                                font-weight:700;flex-shrink:0;">2</div>
                    <div>
                        <strong>Quick Open Button</strong><br>
                        <span class="text-muted">Select number of days and click "Quick Open" to instantly open admissions.</span>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div style="width:32px;height:32px;background:#e67e22;border-radius:8px;
                                display:flex;align-items:center;justify-content:center;color:white;
                                font-weight:700;flex-shrink:0;">3</div>
                    <div>
                        <strong>Manual Toggle</strong><br>
                        <span class="text-muted">Use the ON/OFF switch for indefinite opening without date restrictions.</span>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div style="width:32px;height:32px;background:#27ae60;border-radius:8px;
                                display:flex;align-items:center;justify-content:center;color:white;
                                font-weight:700;flex-shrink:0;">4</div>
                    <div>
                        <strong>Public Website</strong><br>
                        <span class="text-muted">When closed, admission form is hidden and a closed message is shown instead.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h6><i class="fas fa-eye me-2"></i>Public Page Preview</h6>
            </div>
            <div class="admin-card-body">
                <div class="mb-2" style="font-size:13px;color:var(--text-muted);">
                    What students see on the admissions page:
                </div>
                <!-- Open preview -->
                <div class="p-3 rounded-2 mb-2" style="background:#d4edda;border:1px solid #b8ddc8;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-check-circle text-success"></i>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#155724;">When OPEN:</div>
                            <div style="font-size:12.5px;color:#155724;"><?= htmlspecialchars($openMsg ?: 'Admissions are now open!') ?></div>
                        </div>
                    </div>
                </div>
                <!-- Closed preview -->
                <div class="p-3 rounded-2" style="background:#f8d7da;border:1px solid #f0b8bc;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-times-circle text-danger"></i>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#721c24;">When CLOSED:</div>
                            <div style="font-size:12.5px;color:#721c24;"><?= htmlspecialchars($closedMsg ?: 'Admissions are currently closed.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="../admissions.php" target="_blank" class="btn-admin-primary w-100" style="justify-content:center;">
                        <i class="fas fa-external-link-alt me-1"></i>View Admissions Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BS date live AD preview -->
<script>
var NP_DAYS_ADM = {
    2078:[31,31,32,32,31,30,30,29,30,29,30,30],2079:[31,31,32,32,31,30,30,29,30,29,30,30],
    2080:[31,32,31,32,31,30,30,30,29,29,30,30],2081:[31,31,32,32,31,30,30,29,30,29,30,30],
    2082:[31,32,31,32,31,30,30,29,30,29,30,31],2083:[30,32,31,32,31,30,30,30,29,29,30,31],
    2084:[31,31,32,31,31,31,30,29,30,29,30,30],2085:[31,31,32,32,31,30,30,29,30,29,30,30],
    2086:[31,32,31,32,31,30,30,29,30,29,30,30],2087:[31,32,31,32,31,30,30,29,30,29,30,30],
    2088:[31,31,32,32,31,30,30,29,30,29,30,31],2089:[30,32,31,32,31,30,30,30,29,29,30,30],
    2090:[31,31,32,32,31,30,30,29,30,29,30,30],2091:[31,31,32,32,31,30,30,29,30,29,30,30],
    2092:[31,32,31,32,31,30,30,30,29,29,30,30],2093:[31,31,32,32,31,30,30,29,30,29,30,30],
    2094:[31,32,31,32,31,30,30,29,30,29,30,31],2095:[30,32,31,32,31,30,30,30,29,29,30,31],
    2096:[31,31,32,31,31,31,30,29,30,29,30,30],2097:[31,31,32,32,31,30,30,29,30,29,30,30],
    2098:[31,32,31,32,31,30,30,29,30,29,30,30],2099:[31,31,32,32,31,30,30,29,30,30,29,31],
    2100:[30,32,31,32,31,30,30,30,29,29,30,30],2101:[31,31,32,32,31,30,30,29,30,29,30,30],
    2102:[31,32,31,32,31,30,30,29,30,29,30,30],2103:[31,32,31,32,31,30,30,30,29,29,30,30],
    2104:[31,31,32,32,31,30,30,29,30,29,30,30],2105:[31,32,31,32,31,30,30,29,30,29,30,31],
    2106:[30,32,31,32,31,30,30,30,29,29,30,31],2107:[31,31,32,31,31,31,30,29,30,29,30,30],
    2108:[31,31,32,32,31,30,30,29,30,29,30,30],2109:[31,32,31,32,31,30,30,29,30,29,30,30],
    2110:[31,32,31,32,31,30,30,30,29,29,30,30]
};
var ADM_REF = new Date(2024, 3, 13);
var BS_REF_ADM = {y:2081, m:1, d:1};

function bsToAdAdm(bsY, bsM, bsD) {
    var diff = 0;
    for (var y = BS_REF_ADM.y; y < bsY; y++)
        for (var m = 1; m <= 12; m++)
            diff += (NP_DAYS_ADM[y]||[])[m-1]||(m<=6?31:30);
    for (var m = BS_REF_ADM.m; m < bsM; m++)
        diff += (NP_DAYS_ADM[bsY]||[])[m-1]||(bsM<=6?31:30);
    diff += bsD - BS_REF_ADM.d;
    var d = new Date(ADM_REF); d.setDate(d.getDate() + diff); return d;
}

function admUpdatePreview(inputId, spanId) {
    var val = document.getElementById(inputId).value.trim();
    var span = document.getElementById(spanId);
    if (!val) { span.textContent = ''; return; }
    var p = val.split(/[-\/]/);
    if (p.length !== 3) { span.textContent = ' (Format: YYYY-MM-DD)'; span.style.color='#b5281f'; return; }
    var y=parseInt(p[0]),m=parseInt(p[1]),d=parseInt(p[2]);
    if (!y||!m||!d||m<1||m>12||d<1||d>32) { span.textContent=' (Invalid)'; span.style.color='#b5281f'; return; }
    try {
        var ad = bsToAdAdm(y,m,d);
        var mn=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        span.textContent = ' → AD: '+mn[ad.getMonth()]+' '+ad.getDate()+', '+ad.getFullYear();
        span.style.color = '#1a5c2a';
    } catch(e) { span.textContent=' (Error)'; span.style.color='#b5281f'; }
}

document.getElementById('admOpenFromBS').addEventListener('input',  function(){ admUpdatePreview('admOpenFromBS',  'admPreviewFrom'); });
document.getElementById('admOpenUntilBS').addEventListener('input', function(){ admUpdatePreview('admOpenUntilBS', 'admPreviewUntil'); });
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
