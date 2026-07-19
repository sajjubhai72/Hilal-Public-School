<?php
$pageTitle = 'Manage Gallery';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $galleryId = (int)($_POST['gallery_id'] ?? 0);

    if ($action === 'add') {
        $title    = sanitize($conn, $_POST['title']    ?? '');
        $category = sanitize($conn, $_POST['category'] ?? 'general');
        $desc     = sanitize($conn, $_POST['description'] ?? '');

        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            $message = 'Please select at least one image.'; $messageType = 'danger';
        } else {
            $uploaded = 0; $failed = 0;
            $files = $_FILES['images'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) { $failed++; continue; }
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { $failed++; continue; }
                if ($files['size'][$i] > 5*1024*1024) { $failed++; continue; }
                $filename = 'gallery_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], '../uploads/gallery/' . $filename)) {
                    // Use title + index for multiple, or just title for single
                    $t = $count > 1 ? ($title ? $title.' '.($i+1) : 'Photo '.($i+1)) : ($title ?: 'Photo');
                    $t = sanitize($conn, $t);
                    $stmt = $conn->prepare("INSERT INTO gallery (title,image,category,description,uploaded_by) VALUES (?,?,?,?,?)");
                    $stmt->bind_param("ssssi", $t, $filename, $category, $desc, $adminId);
                    $stmt->execute(); $stmt->close();
                    $uploaded++;
                } else { $failed++; }
            }
            if ($uploaded > 0) {
                $message = "$uploaded photo".($uploaded>1?'s':'')." uploaded!".($failed>0?" ($failed failed)":'');
                $messageType = 'success';
            } else {
                $message = 'Upload failed. Check file types and sizes.'; $messageType = 'danger';
            }
        }
    } elseif ($action === 'delete' && $galleryId) {
        $row = $conn->query("SELECT image FROM gallery WHERE id=$galleryId")->fetch_assoc();
        if ($row && file_exists('../uploads/gallery/'.$row['image'])) {
            unlink('../uploads/gallery/'.$row['image']);
        }
        $conn->query("DELETE FROM gallery WHERE id=$galleryId");
        $message = 'Photo deleted.'; $messageType = 'warning';
    } elseif ($action === 'toggle' && $galleryId) {
        $conn->query("UPDATE gallery SET is_active=IF(is_active=1,0,1) WHERE id=$galleryId");
        $message = 'Visibility updated.'; $messageType = 'success';
    }
}

$categories = $conn->query("SELECT DISTINCT category FROM gallery ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// ── Search / Sort / Pagination ─────────────────────────
$search     = sanitize($conn, $_GET['search']   ?? '');
$filterCat  = sanitize($conn, $_GET['category'] ?? '');
$sortCol    = sanitize($conn, $_GET['sort']      ?? 'created_at');
$sortDir    = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage    = in_array((int)($_GET['per_page'] ?? 20), [10,20,50,100]) ? (int)($_GET['per_page'] ?? 20) : 20;
$page       = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'title'      => 'g.title',
    'category'   => 'g.category',
    'uploader'   => 'u.full_name',
    'created_at' => 'g.created_at',
    'is_active'  => 'g.is_active',
];
$orderBy = $allowedSort[$sortCol] ?? 'g.created_at';

$where = 'WHERE 1=1';
if ($filterCat !== '') {
    $fc = $conn->real_escape_string($filterCat);
    $where .= " AND g.category='$fc'";
}
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (g.title LIKE '%$s%' OR g.category LIKE '%$s%')";
}

$totalCount = (int)$conn->query("SELECT COUNT(*) as c FROM gallery g JOIN users u ON g.uploaded_by=u.id $where")->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$items = $conn->query("
    SELECT g.*, u.full_name as uploader
    FROM gallery g JOIN users u ON g.uploaded_by=u.id
    $where
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$bp = ['search'=>$search,'category'=>$filterCat,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$gUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'gallery.php?' . http_build_query($p);
};

require_once 'includes/layout_top.php';
?>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-images me-2"></i>Gallery
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
                           placeholder="Search title..."
                           value="<?= htmlspecialchars($search) ?>" style="width:160px;">
                </div>
                <select name="category" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category']) ?>"
                            <?= $filterCat===$cat['category']?'selected':'' ?>>
                        <?= ucfirst(htmlspecialchars($cat['category'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if($search || $filterCat): ?>
                <a href="<?= $gUrl(['search'=>'','category'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
            <form method="GET" class="d-flex align-items-center gap-1">
                <input type="hidden" name="search"   value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="category" value="<?= htmlspecialchars($filterCat) ?>">
                <input type="hidden" name="sort"     value="<?= htmlspecialchars($sortCol) ?>">
                <input type="hidden" name="dir"      value="<?= htmlspecialchars($sortDir) ?>">
                <input type="hidden" name="page"     value="1">
                <select name="per_page" class="form-select form-select-sm" style="width:80px;" onchange="this.form.submit()">
                    <?php foreach([10,20,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">per page</span>
            </form>
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-upload"></i>Upload Photos
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;">#</th>
                    <th style="background:#0F3A1A;color:white;">Photo</th>
                    <th style="background:#0F3A1A;color:white;">Title</th>
                    <th style="background:#0F3A1A;color:white;">Category</th>
                    <th style="background:#0F3A1A;color:white;">Uploaded By</th>
                    <th style="background:#0F3A1A;color:white;">Date</th>
                    <th style="background:#0F3A1A;color:white;">Visible</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($items)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No photos found.</td></tr>
                <?php else: ?>
                <?php foreach($items as $i => $item): ?>
                <tr class="<?= !$item['is_active'] ? 'opacity-50' : '' ?>">
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <img src="../uploads/gallery/<?= htmlspecialchars($item['image']) ?>"
                             alt="<?= htmlspecialchars($item['title']) ?>"
                             style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);"
                             onerror="this.src='https://via.placeholder.com/60x45/e0e0e0/999?text=N/A'">
                    </td>
                    <td class="fw-semibold"><?= htmlspecialchars($item['title']) ?></td>
                    <td>
                        <span style="background:var(--primary-soft);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                            <?= ucfirst(htmlspecialchars($item['category'])) ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($item['uploader']) ?></td>
                    <td style="font-size:12px;"><?= date('M d, Y', strtotime($item['created_at'])) ?></td>
                    <td>
                        <?php if($item['is_active']): ?>
                        <span class="status-badge status-active">Visible</span>
                        <?php else: ?>
                        <span class="status-badge status-inactive">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="gallery_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-admin-<?= $item['is_active']?'warning':'success' ?> btn-sm"
                                        title="<?= $item['is_active']?'Hide':'Show' ?>">
                                    <i class="fas fa-<?= $item['is_active']?'eye-slash':'eye' ?>"></i>
                                </button>
                            </form>
                            <a href="../uploads/gallery/<?= htmlspecialchars($item['image']) ?>"
                               target="_blank" class="btn-admin-primary btn-sm" title="View Full">
                                <i class="fas fa-expand"></i>
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this photo?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="gallery_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-admin-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
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
            <?php if($totalCount===0): ?>No photos found.
            <?php else: ?>
                Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
                of <strong><?= $totalCount ?></strong> photo<?= $totalCount!=1?'s':'' ?>
                <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
            <?php endif; ?>
        </div>
        <?php if($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= $gUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $gUrl(['page'=>1]) ?>">1</a></li>
            <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $gUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if($pe<$totalPages):
                if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $gUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $gUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Photos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="gallery.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title / Album Name</label>
                            <input type="text" name="title" class="form-control" maxlength="200"
                                   placeholder="e.g. Sports Day 2083">
                            <div class="form-text">Multiple photos: title will be numbered (Photo 1, Photo 2...)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control"
                                   placeholder="e.g. sports, events, classroom" list="cat-list">
                            <datalist id="cat-list">
                                <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>">
                                <?php endforeach; ?>
                                <option value="general">
                                <option value="events">
                                <option value="sports">
                                <option value="cultural">
                                <option value="classroom">
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="300"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Select Photos <span class="text-danger">*</span></label>
                            <input type="file" name="images[]" id="galleryFileInput"
                                   class="form-control" accept="image/*" multiple required>
                            <div class="form-text">JPG, PNG, WebP — max 5MB each — select multiple with Ctrl/Shift</div>
                        </div>
                        <!-- Multi-preview -->
                        <div class="col-12">
                            <div id="multiPreviewBox" class="d-flex flex-wrap gap-2" style="min-height:0;"></div>
                            <div id="previewCount" class="text-muted mt-1" style="font-size:12px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary">
                        <i class="fas fa-upload me-1"></i>Upload <span id="uploadCountLabel"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    // Multi-image preview
    $('#galleryFileInput').on('change', function(){
        const files = this.files;
        const box   = document.getElementById('multiPreviewBox');
        const count = document.getElementById('previewCount');
        const lbl   = document.getElementById('uploadCountLabel');
        box.innerHTML = '';
        if (!files.length) { count.textContent=''; lbl.textContent=''; return; }
        count.textContent = files.length + ' photo' + (files.length>1?'s':'') + ' selected';
        lbl.textContent   = files.length + ' Photo' + (files.length>1?'s':'');
        Array.from(files).forEach(function(file){
            const reader = new FileReader();
            reader.onload = function(e){
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:6px;border:2px solid var(--border);';
                box.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });

    // Reset on modal close
    $('#uploadModal').on('hidden.bs.modal', function(){
        document.getElementById('galleryFileInput').value = '';
        document.getElementById('multiPreviewBox').innerHTML = '';
        document.getElementById('previewCount').textContent = '';
        document.getElementById('uploadCountLabel').textContent = '';
    });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
