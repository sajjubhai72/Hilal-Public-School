<?php
$pageTitle = 'Manage Teachers';
require_once 'includes/auth.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']          ?? '';
    $userId     = (int)($_POST['user_id']   ?? 0);
    $fullName   = sanitize($conn, $_POST['full_name']      ?? '');
    $email      = sanitize($conn, $_POST['email']          ?? '');
    $username   = sanitize($conn, $_POST['username']       ?? '');
    $phone      = sanitize($conn, $_POST['phone']          ?? '');
    $qual       = sanitize($conn, $_POST['qualification']  ?? '');
    $spec       = sanitize($conn, $_POST['specialization'] ?? '');
    $expYears   = (int)($_POST['experience_years']         ?? 0);
    $bio        = sanitize($conn, $_POST['bio']            ?? '');
    $facebook   = sanitize($conn, $_POST['facebook']       ?? '');
    $joinedDate = sanitize($conn, $_POST['joined_date']    ?? '');

    if ($action === 'add') {
        $password = $_POST['password'] ?? '';
        if (!$fullName || !$email || !$username || !$password) {
            $message = 'Please fill all required fields.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email address.';
            $messageType = 'danger';
        } else {
            $chk = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
            $chk->bind_param("ss", $email, $username);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = 'Email or username already exists.';
                $messageType = 'danger';
            } else {
                $chk->close();
                $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
                $photo = 'default.png';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['photo']['size'] <= 2097152) {
                        $photo = 'teacher_'.time().'_'.rand(100,999).'.'.$ext;
                        move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/teachers/'.$photo);
                    }
                }
                $st = $conn->prepare("INSERT INTO users (full_name,email,username,password,role,phone,photo) VALUES (?,?,?,?,'teacher',?,?)");
                $st->bind_param("ssssss", $fullName, $email, $username, $hashedPwd, $phone, $photo);
                if ($st->execute()) {
                    $newId = $st->insert_id;
                    $jd = $joinedDate ?: null;
                    $st2 = $conn->prepare("INSERT INTO teacher_details (user_id,qualification,specialization,experience_years,bio,facebook,joined_date) VALUES (?,?,?,?,?,?,?)");
                    $st2->bind_param("ississs", $newId, $qual, $spec, $expYears, $bio, $facebook, $jd);
                    $st2->execute(); $st2->close();
                    $message = 'Teacher added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add teacher.';
                    $messageType = 'danger';
                }
                $st->close();
            }
        }

    } elseif ($action === 'edit' && $userId) {
        $np = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png','webp']) && $_FILES['photo']['size'] <= 2097152) {
                $np = 'teacher_'.time().'_'.rand(100,999).'.'.$ext;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/teachers/'.$np)) $np = '';
            }
        }
        $npwd = $_POST['new_password'] ?? '';
        if ($npwd && $np) {
            $pw = password_hash($npwd, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,password=?,photo=? WHERE id=? AND role='teacher'");
            $st->bind_param("sssssi", $fullName,$email,$phone,$pw,$np,$userId);
        } elseif ($npwd) {
            $pw = password_hash($npwd, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,password=? WHERE id=? AND role='teacher'");
            $st->bind_param("ssssi", $fullName,$email,$phone,$pw,$userId);
        } elseif ($np) {
            $st = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,photo=? WHERE id=? AND role='teacher'");
            $st->bind_param("ssssi", $fullName,$email,$phone,$np,$userId);
        } else {
            $st = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=? AND role='teacher'");
            $st->bind_param("sssi", $fullName,$email,$phone,$userId);
        }
        $st->execute(); $st->close();
        $jd = $joinedDate ?: null;
        $st3 = $conn->prepare("INSERT INTO teacher_details (user_id,qualification,specialization,experience_years,bio,facebook,joined_date)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE qualification=VALUES(qualification),specialization=VALUES(specialization),
            experience_years=VALUES(experience_years),bio=VALUES(bio),facebook=VALUES(facebook),joined_date=VALUES(joined_date)");
        $st3->bind_param("ississs", $userId,$qual,$spec,$expYears,$bio,$facebook,$jd);
        $st3->execute(); $st3->close();
        $message = 'Teacher updated successfully!';
        $messageType = 'success';

    } elseif ($action === 'toggle_status' && $userId) {
        $conn->query("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=$userId AND role='teacher'");
        $message = 'Teacher status updated.';
        $messageType = 'success';
    } elseif ($action === 'delete' && $userId) {
        $conn->query("DELETE FROM users WHERE id=$userId AND role='teacher'");
        $message = 'Teacher deleted.';
        $messageType = 'warning';
    }
}

// Search / Sort / Pagination
$search  = sanitize($conn, $_GET['search']   ?? '');
$sortCol = sanitize($conn, $_GET['sort']     ?? 'full_name');
$sortDir = strtoupper(sanitize($conn, $_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$perPage = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'full_name'        => 'u.full_name',
    'email'            => 'u.email',
    'specialization'   => 'td.specialization',
    'qualification'    => 'td.qualification',
    'experience_years' => 'td.experience_years',
    'phone'            => 'u.phone',
    'status'           => 'u.status',
];
$orderBy = $allowedSort[$sortCol] ?? 'u.full_name';

$where = '';
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where = "AND (u.full_name LIKE '%$s%' OR u.email LIKE '%$s%'
              OR u.username LIKE '%$s%' OR td.specialization LIKE '%$s%'
              OR u.phone LIKE '%$s%')";
}

$totalCount = (int)$conn->query("
    SELECT COUNT(*) as c FROM users u
    LEFT JOIN teacher_details td ON u.id=td.user_id
    WHERE u.role='teacher' $where
")->fetch_assoc()['c'];

$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$teachers = $conn->query("
    SELECT u.*, td.qualification, td.specialization, td.experience_years,
           td.bio, td.facebook, td.joined_date
    FROM users u
    LEFT JOIN teacher_details td ON u.id=td.user_id
    WHERE u.role='teacher' $where
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$classes = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Closures for URL building (avoids function redeclaration on POST)
$bp = ['search'=>$search,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$tUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'teachers.php?' . http_build_query($p);
};
$tSort = function($col, $lbl) use ($sortCol, $sortDir, $tUrl) {
    $nd  = ($sortCol === $col && $sortDir === 'ASC') ? 'DESC' : 'ASC';
    $style = $sortCol === $col
        ? 'color:white;text-decoration:underline;text-underline-offset:3px;'
        : 'color:white;text-decoration:none;';
    $url = $tUrl(['sort'=>$col,'dir'=>$nd,'page'=>1]);
    return "<a href=\"$url\" style=\"$style\">$lbl</a>";
};

require_once 'includes/layout_top.php';
?>
<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-chalkboard-teacher me-2"></i>Teachers
            <span style="background:var(--primary);color:white;padding:2px 10px;border-radius:12px;font-size:12px;margin-left:6px;"><?= $totalCount ?></span>
        </h6>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form class="d-flex gap-2 align-items-center" method="GET">
                <input type="hidden" name="sort"     value="<?= htmlspecialchars($sortCol) ?>">
                <input type="hidden" name="dir"      value="<?= htmlspecialchars($sortDir) ?>">
                <input type="hidden" name="per_page" value="<?= $perPage ?>">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Name, email, phone, subject..."
                           value="<?= htmlspecialchars($search) ?>" style="width:220px;">
                </div>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if ($search): ?>
                <a href="<?= $tUrl(['search'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
            <form method="GET" class="d-flex align-items-center gap-1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="sort"   value="<?= htmlspecialchars($sortCol) ?>">
                <input type="hidden" name="dir"    value="<?= htmlspecialchars($sortDir) ?>">
                <input type="hidden" name="page"   value="1">
                <select name="per_page" class="form-select form-select-sm" style="width:80px;" onchange="this.form.submit()">
                    <?php foreach ([10,25,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">per page</span>
            </form>
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="fas fa-plus"></i>Add Teacher
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="width:40px;background:#0F3A1A;color:white;">#</th>
                    <th style="width:50px;background:#0F3A1A;color:white;">Photo</th>
                    <th style="background:#0F3A1A;color:white;">Full Name</th>
                    <th style="background:#0F3A1A;color:white;">Email</th>
                    <th style="background:#0F3A1A;color:white;">Specialization</th>
                    <th style="background:#0F3A1A;color:white;">Qualification</th>
                    <th style="background:#0F3A1A;color:white;">Exp.</th>
                    <th style="background:#0F3A1A;color:white;">Phone</th>
                    <th style="background:#0F3A1A;color:white;">Status</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No teachers found.</td></tr>
                <?php else: ?>
                <?php foreach ($teachers as $i => $t): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <img src="../uploads/teachers/<?= htmlspecialchars($t['photo'] ?? 'default.png') ?>"
                             alt="" class="avatar-sm"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($t['full_name']) ?>&background=1a5c2a&color=fff&size=38'">
                    </td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($t['full_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);">@<?= htmlspecialchars($t['username']) ?></div>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($t['email']) ?></td>
                    <td><?= htmlspecialchars($t['specialization'] ?? '--') ?></td>
                    <td><?= htmlspecialchars($t['qualification']  ?? '--') ?></td>
                    <td><?= $t['experience_years'] ? $t['experience_years'].' yrs' : '--' ?></td>
                    <td><?= htmlspecialchars($t['phone'] ?? '--') ?></td>
                    <td><span class="status-badge status-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <button class="btn-admin-warning btn-sm edit-teacher-btn"
                                    data-teacher='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="teachers.php" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-admin-success btn-sm"
                                        title="<?= $t['status']==='active'?'Deactivate':'Activate' ?>">
                                    <i class="fas fa-<?= $t['status']==='active'?'ban':'check' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Delete this teacher? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-admin-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $from = $totalCount===0 ? 0 : $offset+1; $to = min($offset+$perPage,$totalCount); ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-3 py-3"
         style="border-top:1px solid var(--border);font-size:13px;">
        <div class="text-muted">
            <?php if ($totalCount===0): ?>No teachers found.
            <?php else: ?>
                Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
                of <strong><?= $totalCount ?></strong> teacher<?= $totalCount!=1?'s':'' ?>
                <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= $tUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if ($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $tUrl(['page'=>1]) ?>">1</a></li>
            <?php if ($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for ($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $tUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if ($pe<$totalPages):
                if ($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $tUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $tUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Teacher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="teachers.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required maxlength="50" placeholder="e.g. RamSharma">
                            <div class="form-text">Teacher le login garda yo use garcha.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="addTeacherPwd" class="form-control" required minlength="6" placeholder="Min 6 characters">
                                <button type="button" class="btn btn-outline-secondary" id="toggleAddPwd">
                                    <i class="fas fa-eye" id="addPwdEye"></i>
                                </button>
                            </div>
                            <div class="form-text"><i class="fas fa-info-circle me-1 text-primary"></i>Note this down to share with teacher.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Joined Date</label>
                            <input type="text" name="joined_date" class="form-control" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-control" placeholder="e.g. Mathematics" maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Sc. Mathematics" maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Experience (Years)</label>
                            <input type="number" name="experience_years" class="form-control" min="0" max="50" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook URL</label>
                            <input type="url" name="facebook" class="form-control" placeholder="https://facebook.com/...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-control" rows="3" maxlength="500" placeholder="Brief introduction..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Profile Photo (JPG/PNG, max 2MB)</label>
                            <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--accent);">
                <h5 class="modal-title" style="color:var(--dark);"><i class="fas fa-edit me-2"></i>Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="teachers.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password <small class="text-muted">(blank = no change)</small></label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="editTeacherPwd" class="form-control" minlength="6" placeholder="Leave blank = no change">
                                <button type="button" class="btn btn-outline-secondary" id="toggleEditPwd">
                                    <i class="fas fa-eye" id="editPwdEye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" id="edit_spec" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qualification</label>
                            <input type="text" name="qualification" id="edit_qual" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Experience (Years)</label>
                            <input type="number" name="experience_years" id="edit_exp" class="form-control" min="0" max="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Joined Date</label>
                            <input type="text" name="joined_date" id="edit_joined" class="form-control" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook URL</label>
                            <input type="url" name="facebook" id="edit_fb" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" id="edit_bio" class="form-control" rows="3" maxlength="500"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">New Profile Photo (JPG/PNG, max 2MB)</label>
                            <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $(document).on('click', '.edit-teacher-btn', function(){
        var t = $(this).data('teacher');
        $('#edit_user_id').val(t.id);
        $('#edit_full_name').val(t.full_name);
        $('#edit_email').val(t.email);
        $('#edit_phone').val(t.phone || '');
        $('#edit_spec').val(t.specialization || '');
        $('#edit_qual').val(t.qualification || '');
        $('#edit_exp').val(t.experience_years || 0);
        $('#edit_joined').val(t.joined_date || '');
        $('#edit_fb').val(t.facebook || '');
        $('#edit_bio').val(t.bio || '');
        $('#editTeacherModal').modal('show');
    });
    $('#toggleAddPwd').on('click', function(){
        var i=$('#addTeacherPwd');
        i.attr('type',i.attr('type')==='password'?'text':'password');
        $('#addPwdEye').toggleClass('fa-eye fa-eye-slash');
    });
    $('#toggleEditPwd').on('click', function(){
        var i=$('#editTeacherPwd');
        i.attr('type',i.attr('type')==='password'?'text':'password');
        $('#editPwdEye').toggleClass('fa-eye fa-eye-slash');
    });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
