<?php
$pageTitle = 'Online Admission';
require_once 'includes/header.php';

$academicYear = getSetting($conn, 'academic_year');
$admIsOpen    = getSetting($conn, 'admission_is_open');
$admOpenFrom  = getSetting($conn, 'admission_open_from');
$admOpenUntil = getSetting($conn, 'admission_open_until');
$admOpenMsg   = getSetting($conn, 'admission_open_message') ?: 'Admissions are now open! Apply before the deadline.';
$admClosedMsg = getSetting($conn, 'admission_closed_message') ?: 'Admissions are currently closed.';
$today        = date('Y-m-d');

if ($admOpenFrom && $admOpenUntil)       $admissionActive = ($today >= $admOpenFrom && $today <= $admOpenUntil);
elseif ($admOpenFrom && !$admOpenUntil)  $admissionActive = ($today >= $admOpenFrom);
elseif (!$admOpenFrom && $admOpenUntil)  $admissionActive = ($today <= $admOpenUntil);
else                                     $admissionActive = (bool)$admIsOpen;

$daysLeft = null;
if ($admissionActive && $admOpenUntil)
    $daysLeft = max(0, (int)ceil((strtotime($admOpenUntil) - time()) / 86400));

$daysUntilOpen = null;
if (!$admissionActive && $admOpenFrom && $admOpenFrom > $today)
    $daysUntilOpen = (int)ceil((strtotime($admOpenFrom) - time()) / 86400);

$classes = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-user-graduate me-2"></i>Online Admission</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Admissions</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Status Banner -->
<?php if ($admissionActive): ?>
<div class="admission-status-banner open">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="adm-status-dot open"></span>
            <strong><?= htmlspecialchars($admOpenMsg) ?></strong>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($daysLeft !== null): ?>
            <span class="adm-deadline"><i class="fas fa-clock me-1"></i>
                <?= $daysLeft == 0 ? 'Last day today!' : "Closes in <strong>$daysLeft day".($daysLeft==1?'':'s')."</strong>" ?>
            </span>
            <?php endif; ?>
            <?php if ($admOpenUntil): ?>
            <?php
                $bsUntil = adToBS($admOpenUntil);
                $bsUntilStr = $bsUntil['day'] . ' ' . getNpMonthName($bsUntil['month']) . ' ' . $bsUntil['year'] . ' BS';
            ?>
            <span style="font-size:13px;opacity:0.9;">Deadline: <strong><?= $bsUntilStr ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="admission-status-banner closed">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="adm-status-dot closed"></span>
            <strong><?= htmlspecialchars($admClosedMsg) ?></strong>
        </div>
        <?php if ($daysUntilOpen): ?>
        <?php
            $bsFrom = adToBS($admOpenFrom);
            $bsFromStr = $bsFrom['day'] . ' ' . getNpMonthName($bsFrom['month']) . ' ' . $bsFrom['year'] . ' BS';
        ?>
        <span style="font-size:13px;"><i class="fas fa-calendar-alt me-1"></i>
            Opens in <strong><?= $daysUntilOpen ?> day<?= $daysUntilOpen==1?'':'s' ?></strong>
            on <?= $bsFromStr ?>
        </span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($admissionActive): ?>
<section class="adm-section">
    <div class="container">
        <div class="row g-5">

            <!-- Sidebar info -->
            <div class="col-lg-3" data-animate>
                <!-- Steps indicator -->
                <div class="adm-steps-sidebar">
                    <div class="adm-step-item active" id="sideStep1">
                        <div class="adm-step-num">1</div>
                        <div class="adm-step-info">
                            <div class="adm-step-title">Student Info</div>
                            <div class="adm-step-sub">Personal details</div>
                        </div>
                    </div>
                    <div class="adm-step-connector"></div>
                    <div class="adm-step-item" id="sideStep2">
                        <div class="adm-step-num">2</div>
                        <div class="adm-step-info">
                            <div class="adm-step-title">Parent Info</div>
                            <div class="adm-step-sub">Guardian details</div>
                        </div>
                    </div>
                    <div class="adm-step-connector"></div>
                    <div class="adm-step-item" id="sideStep3">
                        <div class="adm-step-num">3</div>
                        <div class="adm-step-info">
                            <div class="adm-step-title">Documents</div>
                            <div class="adm-step-sub">Upload & submit</div>
                        </div>
                    </div>
                </div>

                <!-- Info cards -->
                <div class="adm-info-card mt-4">
                    <div class="adm-info-title"><i class="fas fa-calendar-check me-2"></i>Academic Year</div>
                    <div class="adm-info-val"><?= $academicYear ?> BS</div>
                </div>
                <div class="adm-info-card">
                    <div class="adm-info-title"><i class="fas fa-graduation-cap me-2"></i>Available Classes</div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <?php foreach($classes as $cls): ?>
                        <span class="adm-cls-badge"><?= htmlspecialchars($cls['class_name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="adm-info-card">
                    <div class="adm-info-title"><i class="fas fa-file-alt me-2"></i>Required Documents</div>
                    <ul class="adm-doc-list">
                        <li>Birth Certificate</li>
                        <li>Previous Report Card</li>
                        <li>Character Certificate</li>
                        <li>Guardian Citizenship Copy</li>
                        <li>2 Passport Size Photos</li>
                        <li>Migration Certificate (if any)</li>
                    </ul>
                </div>
                <div class="adm-info-card">
                    <div class="adm-info-title"><i class="fas fa-question-circle me-2"></i>Need Help?</div>
                    <a href="contact.php" class="btn-primary-custom mt-2" style="font-size:13px;padding:8px 16px;">
                        <i class="fas fa-envelope me-1"></i>Contact Us
                    </a>
                </div>
            </div>

            <!-- Main form card -->
            <div class="col-lg-9" data-animate>
                <div class="adm-form-card">

                    <!-- Progress bar -->
                    <div class="adm-progress-wrap">
                        <div class="adm-progress-bar">
                            <div class="adm-progress-fill" id="admProgressFill" style="width:33.3%"></div>
                        </div>
                        <div class="adm-progress-steps">
                            <span class="adm-prog-step active" data-step="1">
                                <i class="fas fa-user-graduate"></i> Student Info
                            </span>
                            <span class="adm-prog-step" data-step="2">
                                <i class="fas fa-users"></i> Parent Info
                            </span>
                            <span class="adm-prog-step" data-step="3">
                                <i class="fas fa-paperclip"></i> Documents
                            </span>
                        </div>
                    </div>

                    <div id="admAlert"></div>

                    <form id="admissionForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="academic_year" value="<?= $academicYear ?>">

                    <!-- ══ STEP 1: Student Info ══ -->
                    <div class="adm-step-pane active" id="pane1">
                        <div class="adm-pane-header">
                            <div class="adm-pane-icon"><i class="fas fa-user-graduate"></i></div>
                            <div>
                                <h5>Student Information</h5>
                                <p>Fill in the student's personal details accurately</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="adm-field-wrap">
                                    <label>Student Full Name <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-user"></i>
                                        <input type="text" name="student_name" class="adm-input" required
                                               placeholder="Full name as per birth certificate" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="adm-field-wrap">
                                    <label>Gender <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-venus-mars"></i>
                                        <select name="gender" class="adm-input" required>
                                            <option value="">Select</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Date of Birth <span class="req">*</span></label>
                                    <!-- BS / AD toggle -->
                                    <div class="d-flex gap-3 mb-2">
                                        <label class="d-flex align-items-center gap-1" style="font-size:13px;font-weight:600;cursor:pointer;">
                                            <input type="radio" name="dob_type" value="BS" checked
                                                   onchange="updateDobPlaceholder(this.value)">
                                            <span style="color:#1a5c2a;">BS (Nepali)</span>
                                        </label>
                                        <label class="d-flex align-items-center gap-1" style="font-size:13px;font-weight:600;cursor:pointer;">
                                            <input type="radio" name="dob_type" value="AD"
                                                   onchange="updateDobPlaceholder(this.value)">
                                            <span style="color:#0284c7;">AD (English)</span>
                                        </label>
                                    </div>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-calendar"></i>
                                        <input type="text" name="date_of_birth" id="dobInput" class="adm-input" required
                                               placeholder="YYYY-MM-DD (e.g. 2068-05-15)">
                                    </div>
                                    <span class="adm-hint" id="dobHint"><i class="fas fa-info-circle me-1"></i>BS format: 2068-05-15</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Blood Group</label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-tint"></i>
                                        <select name="blood_group" class="adm-input">
                                            <option value="">Unknown / Not tested</option>
                                            <option>A+</option><option>A-</option>
                                            <option>B+</option><option>B-</option>
                                            <option>AB+</option><option>AB-</option>
                                            <option>O+</option><option>O-</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Religion <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-mosque"></i>
                                        <select name="religion" class="adm-input" required>
                                            <option value="">Select</option>
                                            <option value="Islam">Islam</option>
                                            <option value="Hindu">Hindu</option>
                                            <option value="Buddhist">Buddhist</option>
                                            <option value="Christian">Christian</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Nationality <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-flag"></i>
                                        <select name="nationality" class="adm-input" required>
                                            <option value="">Select</option>
                                            <option value="Nepali">Nepali</option>
                                            <option value="Indian">Indian</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Applying for Class <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-school"></i>
                                        <select name="applying_for_class" class="adm-input" required>
                                            <option value="">Select Class</option>
                                            <?php foreach($classes as $cls): ?>
                                            <option value="<?= htmlspecialchars($cls['class_name']) ?>">
                                                <?= htmlspecialchars($cls['class_name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Previous School</label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-building"></i>
                                        <input type="text" name="previous_school" class="adm-input"
                                               placeholder="Previous school name" maxlength="200">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Previous Class Passed</label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                        <select name="previous_class" class="adm-input">
                                            <option value="">— None / Not applicable —</option>
                                            <?php foreach($classes as $cls): ?>
                                            <option value="<?= htmlspecialchars($cls['class_name']) ?>">
                                                <?= htmlspecialchars($cls['class_name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="adm-nav-row">
                            <div></div>
                            <button type="button" class="adm-btn-next" onclick="admNext(1)">
                                Next: Parent Info <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div><!-- /pane1 -->

                    <!-- ══ STEP 2: Parent / Guardian Info ══ -->
                    <div class="adm-step-pane" id="pane2">
                        <div class="adm-pane-header">
                            <div class="adm-pane-icon" style="background:var(--secondary);"><i class="fas fa-users"></i></div>
                            <div>
                                <h5>Parent / Guardian Information</h5>
                                <p>Provide accurate contact information for communication</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Father's Name <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-male"></i>
                                        <input type="text" name="father_name" class="adm-input" required
                                               placeholder="Father's full name" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Mother's Name <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-female"></i>
                                        <input type="text" name="mother_name" class="adm-input" required
                                               placeholder="Mother's full name" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Grandfather's Name <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-user"></i>
                                        <input type="text" name="grandfather_name" class="adm-input" required
                                               placeholder="Grandfather's full name" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Guardian Phone <span class="req">*</span></label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-phone"></i>
                                        <input type="tel" name="guardian_phone" class="adm-input" required
                                               placeholder="Primary contact number" maxlength="20">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>WhatsApp Number</label>
                                    <div class="adm-input-icon">
                                        <i class="fab fa-whatsapp" style="color:#25d366;"></i>
                                        <input type="tel" name="whatsapp_no" class="adm-input"
                                               placeholder="WhatsApp number (if different)" maxlength="20">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Guardian Email</label>
                                    <div class="adm-input-icon">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" name="guardian_email" class="adm-input"
                                               placeholder="Email address (optional)" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="adm-field-wrap">
                                    <label>Home Address <span class="req">*</span></label>
                                    <div class="adm-input-icon adm-textarea-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <textarea name="address" class="adm-input" rows="3" required
                                                  placeholder="Ward No., Village/City, District" maxlength="300"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="adm-nav-row">
                            <button type="button" class="adm-btn-back" onclick="admBack(2)">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button type="button" class="adm-btn-next" onclick="admNext(2)">
                                Next: Documents <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div><!-- /pane2 -->

                    <!-- ══ STEP 3: Documents & Submit ══ -->
                    <div class="adm-step-pane" id="pane3">
                        <div class="adm-pane-header">
                            <div class="adm-pane-icon" style="background:#0284c7;"><i class="fas fa-paperclip"></i></div>
                            <div>
                                <h5>Documents & Submission</h5>
                                <p>Upload required documents and review your application</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Student Passport Photo <span class="req">*</span><span class="adm-hint-inline">(JPG/PNG, max 2MB)</span></label>
                                    <div class="adm-upload-box" id="photoBox" onclick="document.getElementById('student_photo_input').click()">
                                        <div class="adm-upload-preview" id="photoPreview">
                                            <i class="fas fa-camera"></i>
                                            <span>Click to upload photo</span>
                                        </div>
                                        <input type="file" id="student_photo_input" name="student_photo"
                                               accept=".jpg,.jpeg,.png" style="display:none"
                                               onchange="previewPhoto(this)">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="adm-field-wrap">
                                    <label>Documents <span class="req">*</span> <span class="adm-hint-inline">(PDF/JPG, max 5MB)</span></label>
                                    <div class="adm-upload-box" id="docBox" onclick="document.getElementById('documents_input').click()">
                                        <div class="adm-upload-preview" id="docPreview">
                                            <i class="fas fa-file-upload"></i>
                                            <span>Click to upload documents</span>
                                        </div>
                                        <input type="file" id="documents_input" name="documents"
                                               accept=".pdf,.jpg,.jpeg,.png" style="display:none"
                                               onchange="previewDoc(this)">
                                    </div>
                                    <span class="adm-hint">Combine all documents into one file if possible</span>
                                </div>
                            </div>
                        </div>

                        <!-- Review summary -->
                        <div class="adm-review-box mt-4" id="admReviewBox"></div>

                        <div class="adm-nav-row">
                            <button type="button" class="adm-btn-back" onclick="admBack(3)">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button type="button" class="adm-btn-submit" id="admSubmitBtn" onclick="admSubmit()">
                                <i class="fas fa-paper-plane me-2"></i>Submit Application
                            </button>
                        </div>
                    </div><!-- /pane3 -->

                    </form>
                </div><!-- /.adm-form-card -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container -->
</section>

<?php else: ?>
<!-- Admissions Closed -->
<section>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center" data-animate>
                <div class="admission-closed-box">
                    <div class="closed-icon"><i class="fas fa-door-closed"></i></div>
                    <h2>Admissions Currently Closed</h2>
                    <p class="text-muted mb-4"><?= htmlspecialchars($admClosedMsg) ?></p>
                    <?php if ($admOpenFrom && $admOpenFrom > $today): ?>
                    <div class="coming-soon-box mb-4">
                        <div class="coming-soon-label">Next Admission Opens</div>
                        <div class="coming-soon-date"><?= date('F d, Y', strtotime($admOpenFrom)) ?></div>
                        <?php if ($daysUntilOpen): ?>
                        <div class="coming-soon-days">in <strong><?= $daysUntilOpen ?> day<?= $daysUntilOpen==1?'':'s' ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <a href="contact.php" class="btn-primary-custom"><i class="fas fa-envelope me-2"></i>Contact School</a>
                        <a href="tel:<?= getSetting($conn,'school_phone') ?>" class="btn-secondary-custom">
                            <i class="fas fa-phone-alt me-2"></i><?= getSetting($conn,'school_phone') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
var currentStep = 1;

function admNext(from) {
    // Validate required fields in current pane
    var pane = document.getElementById('pane' + from);
    var required = pane.querySelectorAll('[required]');
    var valid = true;
    required.forEach(function(el) {
        if (!el.value.trim()) { el.classList.add('adm-invalid'); valid = false; }
        else el.classList.remove('adm-invalid');
    });
    if (!valid) {
        showAlert('Please fill all required fields marked with *', 'danger');
        pane.querySelector('.adm-invalid').focus();
        return;
    }
    clearAlert();
    goToStep(from + 1);
}

function admBack(from) { goToStep(from - 1); }

function goToStep(step) {
    document.querySelectorAll('.adm-step-pane').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('pane' + step).classList.add('active');

    // Progress bar
    var pct = (step / 3) * 100;
    document.getElementById('admProgressFill').style.width = pct + '%';

    // Step pills
    document.querySelectorAll('.adm-prog-step').forEach(function(s) {
        var n = parseInt(s.dataset.step);
        s.classList.toggle('active', n === step);
        s.classList.toggle('done', n < step);
    });
    // Sidebar steps
    for (var i = 1; i <= 3; i++) {
        var el = document.getElementById('sideStep' + i);
        if (!el) continue;
        el.classList.toggle('active', i === step);
        el.classList.toggle('done', i < step);
    }

    // Build review on step 3
    if (step === 3) buildReview();

    currentStep = step;
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function updateDobPlaceholder(type) {
    var inp  = document.getElementById('dobInput');
    var hint = document.getElementById('dobHint');
    if (type === 'BS') {
        inp.placeholder  = 'YYYY-MM-DD (e.g. 2068-05-15)';
        hint.innerHTML   = '<i class="fas fa-info-circle me-1"></i>BS format: 2068-05-15 (Nepali date)';
    } else {
        inp.placeholder  = 'YYYY-MM-DD (e.g. 2010-08-31)';
        hint.innerHTML   = '<i class="fas fa-info-circle me-1"></i>AD format: 2010-08-31 (English date)';
    }
}

function buildReview() {
    var f = document.getElementById('admissionForm');
    function v(name) {
        var el = f.querySelector('[name="' + name + '"]');
        if (!el) return '—';
        if (el.tagName === 'SELECT') return el.options[el.selectedIndex]?.text || '—';
        return el.value.trim() || '—';
    }
    var html = '<div class="adm-review-title"><i class="fas fa-clipboard-check me-2"></i>Application Summary — Please review before submitting</div>';
    html += '<div class="adm-review-grid">';
    var rows = [
        ['Student Name', v('student_name')],    ['Gender', v('gender')],
        ['Date of Birth', v('date_of_birth')],  ['Blood Group', v('blood_group')],
        ['Religion', v('religion')],            ['Nationality', v('nationality')],
        ['Class Applied', v('applying_for_class')], ['Academic Year', v('academic_year')],
        ['Previous School', v('previous_school')],  ['Previous Class', v('previous_class')],
        ['Father\'s Name', v('father_name')],   ['Mother\'s Name', v('mother_name')],
        ['Grandfather', v('grandfather_name')], ['Phone', v('guardian_phone')],
        ['WhatsApp', v('whatsapp_no')],         ['Email', v('guardian_email')],
        ['Address', v('address')],              ['', ''],
    ];
    rows.forEach(function(r) {
        if (!r[0]) { html += '<div class="adm-review-divider"></div><div class="adm-review-divider"></div>'; return; }
        html += '<div class="adm-rv-label">' + r[0] + '</div><div class="adm-rv-val">' + r[1] + '</div>';
    });
    html += '</div>';
    document.getElementById('admReviewBox').innerHTML = html;
}

function admSubmit() {
    // Photo required check
    var photoFile = document.getElementById('student_photo_input').files[0];
    if (!photoFile) {
        showAlert('Student passport photo is required. Please upload a photo before submitting.', 'danger');
        document.getElementById('photoBox').scrollIntoView({behavior: 'smooth', block: 'center'});
        document.getElementById('photoBox').style.borderColor = 'var(--secondary)';
        setTimeout(function(){ document.getElementById('photoBox').style.borderColor = ''; }, 3000);
        return;
    }

    // Document required check
    var docFile = document.getElementById('documents_input').files[0];
    if (!docFile) {
        showAlert('Documents upload is required. Please upload your documents before submitting.', 'danger');
        document.getElementById('docBox').scrollIntoView({behavior: 'smooth', block: 'center'});
        document.getElementById('docBox').style.borderColor = 'var(--secondary)';
        setTimeout(function(){ document.getElementById('docBox').style.borderColor = ''; }, 3000);
        return;
    }

    var btn = document.getElementById('admSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';

    var formData = new FormData(document.getElementById('admissionForm'));
    // attach actual file inputs
    var photoFile = document.getElementById('student_photo_input').files[0];
    var docFile   = document.getElementById('documents_input').files[0];
    if (photoFile) formData.set('student_photo', photoFile);
    if (docFile)   formData.set('documents', docFile);

    fetch('api/submit_admission.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Application';
            if (res.success) {
                document.querySelector('.adm-form-card').innerHTML =
                    '<div class="adm-success-box">' +
                    '<div class="adm-success-icon"><i class="fas fa-check-circle"></i></div>' +
                    '<h3>Application Submitted!</h3>' +
                    '<p>Your reference number:</p>' +
                    '<div class="adm-ref-no">' + res.ref_no + '</div>' +
                    '<p class="text-muted" style="font-size:14px;max-width:400px;margin:0 auto 24px;">We will review your application and contact you at the provided phone number within a few working days.</p>' +
                    '<a href="index.php" class="btn-primary-custom"><i class="fas fa-home me-2"></i>Back to Home</a>' +
                    '</div>';
                window.scrollTo({top: 0, behavior: 'smooth'});
            } else {
                showAlert(res.message || 'Submission failed. Please try again.', 'danger');
                btn.disabled = false;
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Application';
            showAlert('Server error. Please try again.', 'danger');
        });
}

function previewPhoto(input) {
    if (!input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('photoPreview').innerHTML =
            '<img src="' + e.target.result + '" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);">' +
            '<span style="font-size:12px;color:var(--primary);margin-top:6px;">' + input.files[0].name + '</span>';
    };
    reader.readAsDataURL(input.files[0]);
}

function previewDoc(input) {
    if (!input.files[0]) return;
    var name = input.files[0].name;
    var icon = name.endsWith('.pdf') ? 'fa-file-pdf' : 'fa-file-image';
    document.getElementById('docPreview').innerHTML =
        '<i class="fas ' + icon + '" style="font-size:36px;color:var(--primary);"></i>' +
        '<span style="font-size:12px;color:var(--primary);margin-top:6px;">' + name + '</span>';
}

function showAlert(msg, type) {
    document.getElementById('admAlert').innerHTML =
        '<div class="alert alert-' + type + ' alert-dismissible fade show mt-3">' +
        '<i class="fas fa-exclamation-circle me-2"></i>' + msg +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function clearAlert() { document.getElementById('admAlert').innerHTML = ''; }

// Remove invalid class on input
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.adm-input').forEach(function(el) {
        el.addEventListener('input', function() { this.classList.remove('adm-invalid'); });
        el.addEventListener('change', function() { this.classList.remove('adm-invalid'); });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
