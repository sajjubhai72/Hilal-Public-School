<?php
$pageTitle = 'School Settings';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'school_name','school_name_nepali','school_motto','school_address',
        'school_phone','school_email','school_website','established_year',
        'academic_year','facebook_url','youtube_url'
    ];
    foreach ($fields as $key) {
        $val = sanitize($conn, $_POST[$key] ?? '');
        $conn->query("INSERT INTO school_settings (setting_key,setting_value) VALUES ('$key','$val')
                      ON DUPLICATE KEY UPDATE setting_value='$val'");
    }

    // Handle logo upload
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif']) && $_FILES['school_logo']['size'] <= 2*1024*1024) {
            $logoName = 'logo.' . $ext;
            move_uploaded_file($_FILES['school_logo']['tmp_name'], '../assets/images/' . $logoName);
            $conn->query("INSERT INTO school_settings (setting_key,setting_value) VALUES ('school_logo','$logoName')
                          ON DUPLICATE KEY UPDATE setting_value='$logoName'");
        }
    }

    $message = 'Settings saved successfully!'; $messageType = 'success';
}

// Get all settings
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM school_settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function setting($key, $settings, $default = '') {
    return htmlspecialchars($settings[$key] ?? $default);
}

require_once 'includes/layout_top.php';
?>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="admin-form">
    <div class="row g-4">

        <!-- School Info -->
        <div class="col-lg-8">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h6><i class="fas fa-school me-2"></i>School Information</h6>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">School Name (English) <span class="text-danger">*</span></label>
                            <input type="text" name="school_name" class="form-control"
                                   value="<?= setting('school_name',$settings) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Academic Year</label>
                            <input type="text" name="academic_year" class="form-control"
                                   value="<?= setting('academic_year',$settings,'2081') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">School Name (Nepali)</label>
                            <input type="text" name="school_name_nepali" class="form-control"
                                   value="<?= setting('school_name_nepali',$settings) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Motto / Tagline</label>
                            <input type="text" name="school_motto" class="form-control"
                                   value="<?= setting('school_motto',$settings) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="school_address" class="form-control" rows="2"><?= setting('school_address',$settings) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="school_phone" class="form-control"
                                   value="<?= setting('school_phone',$settings) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="school_email" class="form-control"
                                   value="<?= setting('school_email',$settings) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website URL</label>
                            <input type="text" name="school_website" class="form-control"
                                   value="<?= setting('school_website',$settings) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Established Year</label>
                            <input type="text" name="established_year" class="form-control"
                                   value="<?= setting('established_year',$settings,'2050') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook URL</label>
                            <input type="url" name="facebook_url" class="form-control"
                                   value="<?= setting('facebook_url',$settings) ?>" placeholder="https://facebook.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">YouTube URL</label>
                            <input type="url" name="youtube_url" class="form-control"
                                   value="<?= setting('youtube_url',$settings) ?>" placeholder="https://youtube.com/...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logo & Actions -->
        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h6><i class="fas fa-image me-2"></i>School Logo</h6>
                </div>
                <div class="admin-card-body text-center">
                    <img src="../assets/images/<?= setting('school_logo',$settings,'logo.png') ?>"
                         alt="Current Logo" id="logoPreview"
                         style="max-height:120px;max-width:100%;object-fit:contain;border:2px solid var(--border);padding:10px;border-radius:8px;margin-bottom:15px;"
                         onerror="this.src='https://via.placeholder.com/120/1a5c2a/fff?text=Logo'">
                    <div>
                        <label class="form-label">Upload New Logo (max 2MB)</label>
                        <input type="file" name="school_logo" class="form-control" accept="image/*" id="logoInput">
                        <div class="form-text">JPG, PNG, WebP — Will replace current logo</div>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h6><i class="fas fa-lock me-2"></i>Change Admin Password</h6>
                </div>
                <div class="admin-card-body">
                    <p class="text-muted" style="font-size:13px;">
                        To change your password, go to your profile or contact the system administrator.
                    </p>
                    <a href="#" class="btn-admin-warning w-100" style="justify-content:center;"
                       data-bs-toggle="modal" data-bs-target="#changePwdModal">
                        <i class="fas fa-key me-1"></i>Change Password
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn-admin-primary" style="padding:12px 40px;font-size:15px;">
                <i class="fas fa-save me-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>

<!-- Change Password Modal -->
<div class="modal fade" id="changePwdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="change_password.php" class="admin-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#logoInput').on('change', function(){
    const file = this.files[0];
    if(file){ const reader = new FileReader(); reader.onload = e => $('#logoPreview').attr('src',e.target.result); reader.readAsDataURL(file); }
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
