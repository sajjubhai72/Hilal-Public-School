<?php
$pageTitle = 'Manage Notices';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $noticeId  = (int)($_POST['notice_id'] ?? 0);

    if ($action === 'add' || $action === 'edit') {
        $title   = sanitize($conn, $_POST['title'] ?? '');
        $content = sanitize($conn, $_POST['content'] ?? '');
        $type    = sanitize($conn, $_POST['notice_type'] ?? 'general');
        $active  = (int)($_POST['is_active'] ?? 1);

        if (!$title || !$content) {
            $message = 'Title and content are required.';
            $messageType = 'danger';
        } else {
            // Handle attachment
            $attachment = $_POST['existing_attachment'] ?? '';
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $allowedExt = ['pdf','doc','docx','jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExt) && $_FILES['attachment']['size'] <= 5*1024*1024) {
                    $attachment = 'notice_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['attachment']['tmp_name'], '../uploads/notices/' . $attachment);
                }
            }

            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO notices (title,content,notice_type,attachment,is_active,posted_by) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param("ssssis", $title, $content, $type, $attachment, $active, $adminId);
                $stmt->execute(); $stmt->close();
                $message = 'Notice posted successfully!'; $messageType = 'success';
            } else {
                $stmt = $conn->prepare("UPDATE notices SET title=?,content=?,notice_type=?,attachment=?,is_active=? WHERE id=?");
                $stmt->bind_param("ssssii", $title, $content, $type, $attachment, $active, $noticeId);
                $stmt->execute(); $stmt->close();
                $message = 'Notice updated!'; $messageType = 'success';
            }
        }
    } elseif ($action === 'delete' && $noticeId) {
        $conn->query("DELETE FROM notices WHERE id=$noticeId");
        $message = 'Notice deleted.'; $messageType = 'warning';
    } elseif ($action === 'toggle' && $noticeId) {
        $conn->query("UPDATE notices SET is_active = IF(is_active=1,0,1) WHERE id=$noticeId");
        $message = 'Notice visibility updated.'; $messageType = 'success';
    }
}

$noticeTypes = ['general','exam','holiday','admission','event','urgent'];

// ── Search / Sort / Pagination ─────────────────────────
$search     = sanitize($conn, $_GET['search']   ?? '');
$filterType = sanitize($conn, $_GET['type']     ?? '');
$sortCol    = sanitize($conn, $_GET['sort']     ?? 'created_at');
$sortDir    = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage    = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page       = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'title'        => 'n.title',
    'notice_type'  => 'n.notice_type',
    'posted_by'    => 'u.full_name',
    'created_at'   => 'n.created_at',
    'is_active'    => 'n.is_active',
];
$orderBy = $allowedSort[$sortCol] ?? 'n.created_at';

$allowedNoticeTypes = ['general','exam','holiday','admission','event','urgent'];
$where = 'WHERE 1=1';
if ($filterType && in_array($filterType, $allowedNoticeTypes)) $where .= " AND n.notice_type='$filterType'";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (n.title LIKE '%$s%' OR n.content LIKE '%$s%' OR u.full_name LIKE '%$s%')";
}

$totalCount = (int)$conn->query("
    SELECT COUNT(*) as c FROM notices n JOIN users u ON n.posted_by=u.id $where
")->fetch_assoc()['c'];

$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$notices = $conn->query("
    SELECT n.*, u.full_name as posted_by_name
    FROM notices n JOIN users u ON n.posted_by=u.id
    $where
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

// URL closure
$bp = ['search'=>$search,'type'=>$filterType,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$nUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'notices.php?' . http_build_query($p);
};

require_once 'includes/layout_top.php';
?>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-bullhorn me-2"></i>Notices
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
                           placeholder="Search notices..."
                           value="<?= htmlspecialchars($search) ?>" style="width:180px;">
                </div>
                <select name="type" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Types</option>
                    <?php foreach($noticeTypes as $nt): ?>
                    <option value="<?= $nt ?>" <?= $filterType===$nt?'selected':'' ?>><?= ucfirst($nt) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if($search || $filterType): ?>
                <a href="<?= $nUrl(['search'=>'','type'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
            <form method="GET" class="d-flex align-items-center gap-1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="type"   value="<?= htmlspecialchars($filterType) ?>">
                <input type="hidden" name="sort"   value="<?= htmlspecialchars($sortCol) ?>">
                <input type="hidden" name="dir"    value="<?= htmlspecialchars($sortDir) ?>">
                <input type="hidden" name="page"   value="1">
                <select name="per_page" class="form-select form-select-sm" style="width:80px;" onchange="this.form.submit()">
                    <?php foreach([10,25,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">per page</span>
            </form>
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                <i class="fas fa-plus"></i>Post Notice
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;">#</th>
                    <th style="background:#0F3A1A;color:white;">Title</th>
                    <th style="background:#0F3A1A;color:white;">Type</th>
                    <th style="background:#0F3A1A;color:white;">Attachment</th>
                    <th style="background:#0F3A1A;color:white;">Posted By</th>
                    <th style="background:#0F3A1A;color:white;">Date</th>
                    <th style="background:#0F3A1A;color:white;">Visible</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($notices)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No notices found.</td></tr>
                <?php else: ?>
                <?php foreach($notices as $i => $n): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars(mb_substr($n['content'],0,60)) ?>...</div>
                    </td>
                    <td><span class="notice-badge <?= $n['notice_type'] ?>" style="font-size:11px;"><?= strtoupper($n['notice_type']) ?></span></td>
                    <td>
                        <?php if($n['attachment']): ?>
                        <a href="../uploads/notices/<?= $n['attachment'] ?>" target="_blank" class="btn-admin-primary" style="font-size:11px;padding:4px 10px;">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($n['posted_by_name']) ?></td>
                    <td style="font-size:12px;"><?= date('M d, Y', strtotime($n['created_at'])) ?></td>
                    <td>
                        <form method="POST" action="notices.php" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                            <button type="submit" class="btn-admin-<?= $n['is_active']?'success':'danger' ?> btn-sm" title="Toggle visibility">
                                <i class="fas fa-<?= $n['is_active']?'eye':'eye-slash' ?>"></i>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-admin-warning btn-sm edit-notice-btn"
                                    data-notice='<?= htmlspecialchars(json_encode($n), ENT_QUOTES) ?>'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this notice?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
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

    <!-- Pagination -->
    <?php $from = $totalCount===0 ? 0 : $offset+1; $to = min($offset+$perPage,$totalCount); ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-3 py-3"
         style="border-top:1px solid var(--border);font-size:13px;">
        <div class="text-muted">
            <?php if($totalCount===0): ?>No notices found.
            <?php else: ?>
                Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
                of <strong><?= $totalCount ?></strong> notice<?= $totalCount!=1?'s':'' ?>
                <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
            <?php endif; ?>
        </div>
        <?php if($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= $nUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $nUrl(['page'=>1]) ?>">1</a></li>
            <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $nUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if($pe<$totalPages):
                if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $nUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $nUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Add Notice Modal -->
<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Post New Notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="notices.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notice Type</label>
                            <select name="notice_type" class="form-select">
                                <?php foreach($noticeTypes as $nt): ?>
                                <option value="<?= $nt ?>"><?= ucfirst($nt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control" rows="5" required maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Attachment (PDF/DOC/JPG, max 5MB)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visibility</label>
                            <select name="is_active" class="form-select">
                                <option value="1">Visible (Published)</option>
                                <option value="0">Hidden (Draft)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-paper-plane me-1"></i>Post Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Notice Modal -->
<div class="modal fade" id="editNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--accent);">
                <h5 class="modal-title" style="color:var(--dark);"><i class="fas fa-edit me-2"></i>Edit Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="notices.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="notice_id" id="en_id">
                <input type="hidden" name="existing_attachment" id="en_existing_att">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="en_title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notice Type</label>
                            <select name="notice_type" id="en_type" class="form-select">
                                <?php foreach($noticeTypes as $nt): ?>
                                <option value="<?= $nt ?>"><?= ucfirst($nt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea name="content" id="en_content" class="form-control" rows="5" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Replace Attachment (optional)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visibility</label>
                            <select name="is_active" id="en_active" class="form-select">
                                <option value="1">Visible</option>
                                <option value="0">Hidden</option>
                            </select>
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
    $(document).on('click', '.edit-notice-btn', function(){
        const n = $(this).data('notice');
        $('#en_id').val(n.id);
        $('#en_title').val(n.title);
        $('#en_content').val(n.content);
        $('#en_type').val(n.notice_type);
        $('#en_active').val(n.is_active);
        $('#en_existing_att').val(n.attachment || '');
        $('#editNoticeModal').modal('show');
    });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
