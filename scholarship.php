<?php
$pageTitle = 'Scholarship Application';
require_once 'includes/header.php';

$classesResult = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id");
$classes = $classesResult->fetch_all(MYSQLI_ASSOC);
$acadYear = getSetting($conn, 'academic_year');

// ── Scholarship Window Check ──────────────────────────
$scholIsOpen    = getSetting($conn, 'scholarship_is_open');
$scholOpenFrom  = getSetting($conn, 'scholarship_open_from');
$scholOpenUntil = getSetting($conn, 'scholarship_open_until');
$scholOpenMsg   = getSetting($conn, 'scholarship_open_message')   ?: 'Scholarship applications are now open!';
$scholClosedMsg = getSetting($conn, 'scholarship_closed_message') ?: 'Scholarship applications are currently closed.';
$today = date('Y-m-d');

if ($scholOpenFrom && $scholOpenUntil) {
    $scholWindowOpen = ($today >= $scholOpenFrom && $today <= $scholOpenUntil);
} elseif ($scholOpenFrom) {
    $scholWindowOpen = ($today >= $scholOpenFrom);
} elseif ($scholOpenUntil) {
    $scholWindowOpen = ($today <= $scholOpenUntil);
} else {
    $scholWindowOpen = (bool)$scholIsOpen;
}

// Days calculations
$scholDaysLeft     = null;
$scholDaysUntilOpen = null;
if ($scholWindowOpen && $scholOpenUntil)
    $scholDaysLeft = max(0, (int)ceil((strtotime($scholOpenUntil) - time()) / 86400));
if (!$scholWindowOpen && $scholOpenFrom && $scholOpenFrom > $today)
    $scholDaysUntilOpen = (int)ceil((strtotime($scholOpenFrom) - time()) / 86400);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-award me-2"></i>Scholarship Application</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Scholarship</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Scholarship Types Info -->
<section class="bg-light-custom py-4">
    <div class="container">
        <div class="row g-3 justify-content-center">
            <?php
            $types = [
                ['icon'=>'fas fa-heart-broken', 'color'=>'#b5281f', 'label'=>'Orphan (Yateem)'],
                ['icon'=>'fas fa-hand-holding-heart','color'=>'#e67e22','label'=>'Poor (Gharib)'],
                ['icon'=>'fas fa-ellipsis-h','color'=>'#1b6b35','label'=>'Other'],
            ];
            foreach($types as $t):
            ?>
            <div class="col-4 col-md-2">
                <div class="text-center p-3 rounded-3 bg-white shadow-sm">
                    <i class="<?= $t['icon'] ?> fa-2x mb-2 d-block" style="color:<?= $t['color'] ?>;"></i>
                    <div style="font-size:12.5px;font-weight:700;color:var(--text-dark);"><?= $t['label'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section>
<div class="container">
<div id="scholarshipAlert"></div>

<?php if (!$scholWindowOpen): ?>
<!-- ══ WINDOW CLOSED ══════════════════════════════════ -->
<div class="text-center py-5">
    <div style="max-width:520px;margin:0 auto;background:white;border-radius:16px;
                padding:48px 40px;box-shadow:0 4px 24px rgba(0,0,0,0.08);border-top:5px solid #b5281f;">
        <div style="width:72px;height:72px;background:#fdf0ef;border-radius:50%;
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 20px;font-size:30px;color:#b5281f;">
            <i class="fas fa-door-closed"></i>
        </div>
        <h3 style="color:#b5281f;font-weight:800;margin-bottom:12px;">Scholarship Applications Closed</h3>
        <p style="color:#555;font-size:15px;line-height:1.7;margin-bottom:20px;">
            <?= htmlspecialchars($scholClosedMsg) ?>
        </p>
        <?php if ($scholDaysUntilOpen): ?>
        <?php $bsFrom = adToBS($scholOpenFrom); $bsFromStr = $bsFrom['day'].' '.getNpMonthName($bsFrom['month']).' '.$bsFrom['year'].' BS'; ?>
        <div style="background:#edf7f0;border:1px solid #b8ddc8;border-radius:10px;padding:14px 20px;margin-bottom:20px;">
            <i class="fas fa-calendar-alt me-2 text-success"></i>
            Opens in <strong><?= $scholDaysUntilOpen ?> day<?= $scholDaysUntilOpen==1?'':'s' ?></strong>
            on <strong><?= $bsFromStr ?></strong>
        </div>
        <?php elseif ($scholOpenFrom && $scholOpenFrom > $today): ?>
        <?php $bsFrom = adToBS($scholOpenFrom); $bsFromStr = $bsFrom['day'].' '.getNpMonthName($bsFrom['month']).' '.$bsFrom['year'].' BS'; ?>
        <div style="background:#edf7f0;border:1px solid #b8ddc8;border-radius:10px;padding:14px 20px;margin-bottom:20px;">
            <i class="fas fa-calendar-alt me-2 text-success"></i>
            <strong>Opens on:</strong> <?= $bsFromStr ?>
        </div>
        <?php endif; ?>
        <a href="index.php" class="btn-primary-custom" style="text-decoration:none;">
            <i class="fas fa-home me-2"></i>Back to Home
        </a>
    </div>
</div>

<?php else: ?>
<!-- ══ OPEN MESSAGE ═══════════════════════════════════ -->
<div class="admission-status-banner open" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding:16px 20px;border-radius:12px;">
    <div class="d-flex align-items-center gap-2">
        <span class="adm-status-dot open"></span>
        <strong><?= htmlspecialchars($scholOpenMsg) ?></strong>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if ($scholDaysLeft !== null): ?>
        <span class="adm-deadline"><i class="fas fa-clock me-1"></i>
            <?= $scholDaysLeft == 0 ? 'Last day today!' : "Closes in <strong>$scholDaysLeft day".($scholDaysLeft==1?'':'s')."</strong>" ?>
        </span>
        <?php endif; ?>
        <?php if ($scholOpenUntil): ?>
        <?php $bsUntil = adToBS($scholOpenUntil); $bsUntilStr = $bsUntil['day'].' '.getNpMonthName($bsUntil['month']).' '.$bsUntil['year'].' BS'; ?>
        <span style="font-size:13px;opacity:0.9;">Deadline: <strong><?= $bsUntilStr ?></strong></span>
        <?php endif; ?>
    </div>
</div>

<form id="scholarshipForm" enctype="multipart/form-data" class="admin-form" style="--bs-gutter-x:1.5rem;">

    <!-- ╔═══════════════════════════════════╗ -->
    <!-- ║   SECTION 1: Scholarship Details  ║ -->
    <!-- ╚═══════════════════════════════════╝ -->
    <div class="schol-section mb-4">
        <div class="schol-section-title">
            <i class="fas fa-award me-2"></i>Scholarship Information
        </div>
        <div class="schol-section-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Scholarship Type <span class="text-danger">*</span></label>
                    <select name="scholarship_type" class="form-select" required id="scholType">
                        <option value="">-- Select Type --</option>
                        <option value="Orphan">Orphan (Yateem / यतीम)</option>
                        <option value="Poor">Poor (Gharib / गरीब)</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-4" id="aliveParentsDiv" style="display:none;">
                    <label class="form-label fw-bold">Alive Parents</label>
                    <select name="alive_parents" class="form-select" id="aliveParents">
                        <option value="Father&Mother">Father &amp; Mother Both</option>
                        <option value="Father">Father Only</option>
                        <option value="Mother">Mother Only</option>
                        <option value="None">None (Full Orphan)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Student Type <span class="text-danger">*</span></label>
                    <select name="student_type" class="form-select" required>
                        <option value="old">Old Student (Existing)</option>
                        <option value="new">New Student</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ╔═══════════════════════════════════╗ -->
    <!-- ║   SECTION 2: Student Info         ║ -->
    <!-- ╚═══════════════════════════════════╝ -->
    <div class="schol-section mb-4">
        <div class="schol-section-title">
            <i class="fas fa-user-graduate me-2"></i>Student Information
        </div>
        <div class="schol-section-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Student's Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="applicant_name" class="form-control" required maxlength="100"
                           placeholder="Student's full name">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Date of Birth <span class="text-danger">*</span></label>
                    <!-- BS / AD toggle -->
                    <div class="d-flex gap-3 mb-2">
                        <label class="d-flex align-items-center gap-1" style="font-size:13px;font-weight:600;cursor:pointer;">
                            <input type="radio" name="dob_type" value="BS" checked
                                   onchange="updateScholDobPlaceholder(this.value)">
                            <span style="color:#1a5c2a;">BS (Nepali)</span>
                        </label>
                        <label class="d-flex align-items-center gap-1" style="font-size:13px;font-weight:600;cursor:pointer;">
                            <input type="radio" name="dob_type" value="AD"
                                   onchange="updateScholDobPlaceholder(this.value)">
                            <span style="color:#0284c7;">AD (English)</span>
                        </label>
                    </div>
                    <input type="text" name="date_of_birth" id="scholDobInput" class="form-control" required
                           placeholder="YYYY-MM-DD (e.g. 2068-05-15)">
                    <div class="form-text" id="scholDobHint"><i class="fas fa-info-circle me-1"></i>BS format: 2068-05-15</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Class <span class="text-danger">*</span></label>
                    <select name="current_class" class="form-select" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $cls): ?>
                        <option value="<?= htmlspecialchars($cls['class_name']) ?>">
                            <?= htmlspecialchars($cls['class_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Section</label>
                    <input type="text" name="section" class="form-control"
                           placeholder="e.g. A, B, C" maxlength="5" style="text-transform:uppercase;">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Roll No. / Symbol No.</label>
                    <input type="text" name="roll_symbol_no" class="form-control"
                           placeholder="Roll or Symbol number" maxlength="30">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Current Address <span class="text-danger">*</span></label>
                    <input type="text" name="address" class="form-control" required maxlength="300"
                           placeholder="Current address">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Old / Permanent Address</label>
                    <input type="text" name="old_address" class="form-control" required maxlength="300"
                           placeholder="Permanent or old address">
                </div>
            </div>
        </div>
    </div>

    <!-- ╔═══════════════════════════════════╗ -->
    <!-- ║   SECTION 3: Family Info          ║ -->
    <!-- ╚═══════════════════════════════════╝ -->
    <div class="schol-section mb-4">
        <div class="schol-section-title">
            <i class="fas fa-users me-2"></i>Family Information
        </div>
        <div class="schol-section-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Father's Name <span class="text-danger">*</span></label>
                    <input type="text" name="father_name" class="form-control" required maxlength="100"
                           placeholder="Father's full name">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Grandfather's Name <span class="text-danger">*</span></label>
                    <input type="text" name="grandfather_name" class="form-control" required maxlength="100"
                           placeholder="Grandfather's full name">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Great Grandfather's Name</label>
                    <input type="text" name="great_grandfather_name" class="form-control" maxlength="100"
                           placeholder="Great grandfather's full name">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Mother's Name <span class="text-danger">*</span></label>
                    <input type="text" name="mother_name" class="form-control" required maxlength="100"
                           placeholder="Mother's full name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Father's Main Occupation <span class="text-danger">*</span></label>
                    <input type="text" name="father_occupation" class="form-control" required maxlength="200"
                           placeholder="e.g. Farmer, Business, Labour">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Guardian Phone <span class="text-danger">*</span></label>
                    <input type="tel" name="guardian_phone" class="form-control" required maxlength="20"
                           placeholder="Contact number">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Annual Family Income <span class="text-danger">*</span></label>
                    <input type="text" name="annual_income" class="form-control" required maxlength="100"
                           placeholder="e.g. Rs. 1,20,000">
                </div>
                <!-- Family Members -->
                <div class="col-12">
                    <label class="form-label fw-bold">No. of Family Members</label>
                    <div class="d-flex gap-4 align-items-center mt-1 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <label style="font-size:14px;font-weight:600;color:var(--text-muted);">Male:</label>
                            <input type="number" name="family_male" class="form-control" min="0" max="30" value="0"
                                   style="width:80px;">
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label style="font-size:14px;font-weight:600;color:var(--text-muted);">Female:</label>
                            <input type="number" name="family_female" class="form-control" min="0" max="30" value="0"
                                   style="width:80px;">
                        </div>
                        <div class="text-muted" style="font-size:13px;" id="totalFamilyDisplay">
                            Total: <strong id="totalFamily">0</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ╔══════════════════════════════════════════╗ -->
    <!-- ║ SECTION 4: Siblings Studying in School  ║ -->
    <!-- ╚══════════════════════════════════════════╝ -->
    <div class="schol-section mb-4">
        <div class="schol-section-title">
            <i class="fas fa-school me-2"></i>
            Students Studying in This School
        </div>
        <div class="schol-section-body">
            <!-- Note -->
            <div class="schol-note mb-3">
                <i class="fas fa-info-circle me-2" style="color:var(--secondary);"></i>
                <span class="nepali-note">येउटै आमाबुबाको हुनु अनिवार्य छ।</span>
                (Must be from the same parents)
            </div>
            <!-- Count -->
            <div class="mb-3">
                <label class="form-label fw-bold">No. of students studying in this school:</label>
                <input type="number" name="siblings_count" id="siblingsCount" class="form-control"
                       min="1" max="10" value="1" style="width:100px;display:inline-block;" required>
                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="updateSiblingTable()">
                    <i class="fas fa-sync me-1"></i>Update Table
                </button>
            </div>

            <!-- Sibling Table -->
            <div class="table-responsive">
                <table class="table table-bordered schol-table" id="siblingTable">
                    <thead style="background:var(--primary);color:white;">
                        <tr>
                            <th style="width:40px;">SN</th>
                            <th>Name</th>
                            <th>Father's Name</th>
                            <th>Class &amp; Section</th>
                            <th>Roll No.</th>
                            <th>Result %</th>
                        </tr>
                    </thead>
                    <tbody id="siblingTableBody">
                        <tr>
                            <td class="text-center fw-bold">1</td>
                            <td><input type="text" name="sib_name[]" class="form-control form-control-sm" placeholder="Full name"></td>
                            <td><input type="text" name="sib_father[]" class="form-control form-control-sm" placeholder="Father's name"></td>
                            <td><input type="text" name="sib_class[]" class="form-control form-control-sm" placeholder="e.g. Class 6 - A"></td>
                            <td><input type="text" name="sib_roll[]" class="form-control form-control-sm" placeholder="Roll no."></td>
                            <td><input type="text" name="sib_result[]" class="form-control form-control-sm" placeholder="e.g. 85%"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ╔═══════════════════════════════════╗ -->
    <!-- ║   SECTION 5: Reason + Documents   ║ -->
    <!-- ╚═══════════════════════════════════╝ -->
    <div class="schol-section mb-4">
        <div class="schol-section-title">
            <i class="fas fa-comment-alt me-2"></i>Reason & Supporting Documents
        </div>
        <div class="schol-section-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-bold">Reason for Scholarship <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="4" required maxlength="500"
                              placeholder="Explain your situation and why you deserve this scholarship..."></textarea>
                    <div class="form-text">Maximum 500 characters</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Upload Supporting Documents (PDF/JPG, max 5MB) <span class="text-danger">*</span></label>
                    <input type="file" name="documents" class="form-control"
                           accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-text">Marksheet, Income certificate, or any supporting document.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Student Passport Photo <span class="text-danger">*</span></label>
                    <input type="file" name="student_photo" id="scholPhotoInput"
                           class="form-control" accept=".jpg,.jpeg,.png" required>
                    <div class="form-text">Clear passport size photo — JPG/PNG, max 2MB.</div>
                    <div class="mt-2" id="scholPhotoPreview" style="display:none;">
                        <img id="scholPhotoImg" style="width:90px;height:110px;object-fit:cover;border-radius:6px;border:2px solid var(--border);">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="text-center">
        <button type="button" id="scholReviewBtn" class="btn-primary-custom"
                style="border:none;cursor:pointer;padding:14px 48px;font-size:16px;font-weight:700;">
            <i class="fas fa-eye me-2"></i>Review &amp; Submit Application
        </button>
    </div>

</form>
</div>
<?php endif; // scholarship window check ?>
</section>

<!-- CSS -->
<style>
.schol-section {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    border: 1px solid var(--border);
}
.schol-section-title {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    color: white;
    padding: 12px 20px;
    font-size: 15px;
    font-weight: 700;
}
.schol-section-body {
    padding: 20px;
}
.schol-note {
    background: #fff9e6;
    border: 1.5px solid #f0d060;
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 14px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.nepali-note {
    font-size: 15px;
    font-weight: 700;
    color: #7a4f00;
}
.schol-table thead th {
    font-size: 12.5px;
    padding: 8px 6px;
    text-align: center;
}
.schol-table tbody td {
    padding: 5px 4px;
    vertical-align: middle;
}
.schol-table .form-control-sm { font-size: 12.5px; }
.form-label.fw-bold { font-size: 13.5px; color: var(--text-dark); }

@media (max-width: 575px) {
    .schol-section-body { padding: 14px 12px; }
    .schol-table { min-width: 600px; }
}
</style>

<!-- JS -->
<script>
$(document).ready(function(){

    // Show/hide alive parents based on scholarship type
    $('#scholType').on('change', function(){
        if($(this).val() === 'Orphan'){
            $('#aliveParentsDiv').show();
        } else {
            $('#aliveParentsDiv').hide();
        }
    });

    // Student photo preview
    $('#scholPhotoInput').on('change', function(){
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e){
                $('#scholPhotoImg').attr('src', e.target.result);
                $('#scholPhotoPreview').show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Family member total
    $('input[name="family_male"], input[name="family_female"]').on('input', function(){
        const m = parseInt($('input[name="family_male"]').val()) || 0;
        const f = parseInt($('input[name="family_female"]').val()) || 0;
        $('#totalFamily').text(m + f);
    });

    // Review button click — show review modal
    $('#scholReviewBtn').on('click', function(){
        // Validate all required fields
        let valid = true;
        $('#scholarshipForm [required]').each(function(){
            if(!$(this).val().trim()){
                $(this).addClass('is-invalid');
                valid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        if(!valid){
            showAlert('#scholarshipAlert','danger','<i class="fas fa-exclamation-circle me-2"></i>Please fill all required fields marked with <span class="text-danger fw-bold">*</span>');
            $('html,body').animate({scrollTop: $('#scholarshipForm .is-invalid:first').offset().top - 120}, 400);
            return;
        }

        // Collect ALL field values
        const f = (name) => $(`[name="${name}"]`).val() || '—';
        const fSel = (name) => $(`[name="${name}"] option:selected`).text() || '—';

        // Sibling rows
        let sibRows = '';
        let hasSib = false;
        $('#siblingTableBody tr').each(function(i){
            const n = $(this).find('[name="sib_name[]"]').val();
            const fa = $(this).find('[name="sib_father[]"]').val();
            const cl = $(this).find('[name="sib_class[]"]').val();
            const ro = $(this).find('[name="sib_roll[]"]').val();
            const re = $(this).find('[name="sib_result[]"]').val();
            if(n || fa || cl){
                hasSib = true;
                sibRows += `<tr style="font-size:12.5px;">
                    <td class="text-center">${i+1}</td>
                    <td>${n||'—'}</td><td>${fa||'—'}</td>
                    <td>${cl||'—'}</td><td>${ro||'—'}</td><td>${re||'—'}</td>
                </tr>`;
            }
        });

        const html = `
        <div style="font-size:13.5px;line-height:1.7;">

            <div class="review-section-head">🏆 Scholarship Information</div>
            <div class="review-table">
                <div class="review-row"><span class="review-label">Scholarship Type</span><span class="review-val fw-bold">${fSel('scholarship_type')}</span></div>
                <div class="review-row"><span class="review-label">Alive Parents</span><span class="review-val">${f('alive_parents')}</span></div>
                <div class="review-row"><span class="review-label">Student Type</span><span class="review-val">${fSel('student_type')}</span></div>
            </div>

            <div class="review-section-head">🎓 Student Information</div>
            <div class="review-table">
                <div class="review-row"><span class="review-label">Full Name</span><span class="review-val fw-bold">${f('applicant_name')}</span></div>
                <div class="review-row"><span class="review-label">Date of Birth</span><span class="review-val">${f('date_of_birth')}</span></div>
                <div class="review-row"><span class="review-label">Gender</span><span class="review-val">${fSel('gender')}</span></div>
                <div class="review-row"><span class="review-label">Class</span><span class="review-val fw-bold">${fSel('current_class')}</span></div>
                <div class="review-row"><span class="review-label">Section</span><span class="review-val">${f('section')}</span></div>
                <div class="review-row"><span class="review-label">Roll / Symbol No</span><span class="review-val">${f('roll_symbol_no')}</span></div>
                <div class="review-row"><span class="review-label">Current Address</span><span class="review-val">${f('address')}</span></div>
                <div class="review-row"><span class="review-label">Old / Permanent Address</span><span class="review-val">${f('old_address')}</span></div>
            </div>

            <div class="review-section-head">👨‍👩‍👧 Family Information</div>
            <div class="review-table">
                <div class="review-row"><span class="review-label">Father's Name</span><span class="review-val fw-bold">${f('father_name')}</span></div>
                <div class="review-row"><span class="review-label">Grandfather's Name</span><span class="review-val">${f('grandfather_name')}</span></div>
                <div class="review-row"><span class="review-label">Great Grandfather</span><span class="review-val">${f('great_grandfather_name')}</span></div>
                <div class="review-row"><span class="review-label">Mother's Name</span><span class="review-val">${f('mother_name')}</span></div>
                <div class="review-row"><span class="review-label">Father's Occupation</span><span class="review-val">${f('father_occupation')}</span></div>
                <div class="review-row"><span class="review-label">Guardian Phone</span><span class="review-val fw-bold">${f('guardian_phone')}</span></div>
                <div class="review-row"><span class="review-label">Annual Income</span><span class="review-val">${f('annual_income')}</span></div>
                <div class="review-row"><span class="review-label">Family Members</span><span class="review-val">${f('family_male')} Male + ${f('family_female')} Female = <strong>${parseInt($('[name="family_male"]').val()||0)+parseInt($('[name="family_female"]').val()||0)}</strong> Total</span></div>
            </div>

            ${hasSib ? `
            <div class="review-section-head">🏫 Students in School <small style="font-weight:400;font-size:11px;">(येउटै आमाबुबाको)</small></div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-2" style="font-size:12px;">
                    <thead style="background:var(--primary);color:white;">
                        <tr><th>#</th><th>Name</th><th>Father</th><th>Class</th><th>Roll</th><th>Result%</th></tr>
                    </thead>
                    <tbody>${sibRows}</tbody>
                </table>
            </div>` : ''}

            <div class="review-section-head">📝 Reason</div>
            <div class="review-table">
                <div class="review-row"><span class="review-val" style="width:100%;white-space:pre-wrap;">${f('reason')}</span></div>
            </div>

            <div class="review-confirm-note mt-3">
                <i class="fas fa-exclamation-circle me-2" style="color:var(--secondary);"></i>
                <strong>Please review all information carefully before confirming.</strong>
                Once submitted, contact the school to make any changes.
            </div>
        </div>`;

        $('#reviewModalBody').html(html);
        $('#reviewModal').modal('show');
    });  // end #scholReviewBtn click
    $('#confirmSubmitBtn').on('click', function(){
        $('#reviewModal').modal('hide');
        const form   = document.getElementById('scholarshipForm');
        const btn    = $(form).find('[type=submit]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');

        // Collect sibling data
        const sibData = [];
        $('#siblingTableBody tr').each(function(){
            sibData.push({
                name:   $(this).find('[name="sib_name[]"]').val(),
                father: $(this).find('[name="sib_father[]"]').val(),
                class:  $(this).find('[name="sib_class[]"]').val(),
                roll:   $(this).find('[name="sib_roll[]"]').val(),
                result: $(this).find('[name="sib_result[]"]').val(),
            });
        });
        // Remove old hidden field if any
        $(form).find('[name="siblings_data"]').remove();
        $('<input>').attr({type:'hidden',name:'siblings_data',value:JSON.stringify(sibData)}).appendTo(form);

        const formData = new FormData(form);
        $.ajax({
            url: 'api/submit_scholarship.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res){
                btn.prop('disabled',false).html('<i class="fas fa-paper-plane me-2"></i>Submit Scholarship Application');
                if(res.success){
                    showAlert('#scholarshipAlert','success','✓ Scholarship application submitted successfully! We will contact you soon.');
                    form.reset();
                    updateSiblingTable();
                    $('html,body').animate({scrollTop:0},400);
                } else {
                    showAlert('#scholarshipAlert','danger', res.message || 'Submission failed.');
                }
            },
            error: function(){
                btn.prop('disabled',false).html('<i class="fas fa-paper-plane me-2"></i>Submit Scholarship Application');
                showAlert('#scholarshipAlert','danger','Server error. Please try again.');
            }
        });
    });

    function showAlert(sel, type, msg){
        $(sel).html(`<div class="alert alert-${type} alert-dismissible fade show">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
        $('html,body').animate({scrollTop:$(sel).offset().top - 80}, 400);
    }
});

function updateScholDobPlaceholder(type) {
    var inp  = document.getElementById('scholDobInput');
    var hint = document.getElementById('scholDobHint');
    if (type === 'BS') {
        inp.placeholder = 'YYYY-MM-DD (e.g. 2068-05-15)';
        hint.innerHTML  = '<i class="fas fa-info-circle me-1"></i>BS format: 2068-05-15 (Nepali date)';
    } else {
        inp.placeholder = 'YYYY-MM-DD (e.g. 2010-08-31)';
        hint.innerHTML  = '<i class="fas fa-info-circle me-1"></i>AD format: 2010-08-31 (English date)';
    }
}

// Update sibling table rows
function updateSiblingTable(){
    const count = parseInt($('#siblingsCount').val()) || 1;
    let html = '';
    for(let i = 1; i <= count; i++){
        html += `<tr>
            <td class="text-center fw-bold">${i}</td>
            <td><input type="text" name="sib_name[]" class="form-control form-control-sm" placeholder="Full name"></td>
            <td><input type="text" name="sib_father[]" class="form-control form-control-sm" placeholder="Father's name"></td>
            <td><input type="text" name="sib_class[]" class="form-control form-control-sm" placeholder="e.g. Class 6 - A"></td>
            <td><input type="text" name="sib_roll[]" class="form-control form-control-sm" placeholder="Roll no."></td>
            <td><input type="text" name="sib_result[]" class="form-control form-control-sm" placeholder="e.g. 85%"></td>
        </tr>`;
    }
    $('#siblingTableBody').html(html);
}
</script>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:white;">
                <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Review Your Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reviewModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button type="button" id="confirmSubmitBtn" class="btn-primary-custom"
                        style="border:none;cursor:pointer;padding:10px 28px;">
                    <i class="fas fa-check me-2"></i>Confirm &amp; Submit
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.review-table { margin-bottom: 14px; }
.review-row {
    display: flex; gap: 10px; padding: 7px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13.5px;
}
.review-row:last-child { border-bottom: none; }
.review-label { width: 160px; flex-shrink: 0; font-weight: 700; color: var(--text-muted); }
.review-val   { flex: 1; color: var(--text-dark); font-weight: 500; }
.review-confirm-note {
    background: #fff9e6; border: 1px solid #f0d060;
    border-radius: 8px; padding: 10px 14px;
    font-size: 13px; color: #7a4f00;
}
.review-section-head {
    font-size: 13px; font-weight: 800;
    color: var(--primary);
    background: var(--primary-soft);
    padding: 7px 12px; border-radius: 6px;
    margin: 14px 0 6px;
}
</style>

<?php require_once 'includes/footer.php'; ?>
