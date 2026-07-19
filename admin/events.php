<?php
$pageTitle = 'Manage Events';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $eventId = (int)($_POST['event_id'] ?? 0);

    if ($action === 'add' || $action === 'edit') {
        $title     = sanitize($conn, $_POST['title'] ?? '');
        $desc      = sanitize($conn, $_POST['description'] ?? '');
        $date      = sanitize($conn, $_POST['event_date'] ?? '');
        $endDate   = sanitize($conn, $_POST['event_end_date'] ?? '') ?: null;
        $time      = sanitize($conn, $_POST['event_time'] ?? '');
        $venue     = sanitize($conn, $_POST['venue'] ?? '');
        $type      = sanitize($conn, $_POST['event_type'] ?? 'other');
        $active    = (int)($_POST['is_active'] ?? 1);

        if (!$title || !$date) {
            $message = 'Title and date are required.'; $messageType = 'danger';
        } else {
            // Handle image upload
            $image = $_POST['existing_image'] ?? '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['image']['size'] <= 3*1024*1024) {
                    $image = 'event_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/events/' . $image);
                }
            }

            $endVal = $endDate ? "'$endDate'" : 'NULL';

            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO events (title,description,event_date,event_end_date,event_time,venue,image,event_type,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssssssii", $title, $desc, $date, $endDate, $time, $venue, $image, $type, $active, $adminId);
                $stmt->execute(); $stmt->close();
                $message = 'Event added!'; $messageType = 'success';
            } else {
                $stmt = $conn->prepare("UPDATE events SET title=?,description=?,event_date=?,event_end_date=?,event_time=?,venue=?,image=?,event_type=?,is_active=? WHERE id=?");
                $stmt->bind_param("sssssssiii", $title, $desc, $date, $endDate, $time, $venue, $image, $type, $active, $eventId);
                $stmt->execute(); $stmt->close();
                $message = 'Event updated!'; $messageType = 'success';
            }
        }
    } elseif ($action === 'delete' && $eventId) {
        $conn->query("DELETE FROM events WHERE id=$eventId");
        $message = 'Event deleted.'; $messageType = 'warning';
    }
}

$eventTypes = ['academic','cultural','sports','holiday','other'];

// ── Search / Sort / Pagination ─────────────────────────
$search     = sanitize($conn, $_GET['search']   ?? '');
$filterType = sanitize($conn, $_GET['type']     ?? '');
$sortCol    = sanitize($conn, $_GET['sort']     ?? 'event_date');
$sortDir    = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage    = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page       = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'title'      => 'title',
    'event_date' => 'event_date',
    'event_type' => 'event_type',
    'venue'      => 'venue',
    'is_active'  => 'is_active',
];
$orderBy = $allowedSort[$sortCol] ?? 'event_date';

$allowedTypes = ['academic','cultural','sports','holiday','other'];
$where = 'WHERE 1=1';
if ($filterType && in_array($filterType, $allowedTypes)) $where .= " AND event_type='$filterType'";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (title LIKE '%$s%' OR venue LIKE '%$s%' OR description LIKE '%$s%')";
}

$totalCount = (int)$conn->query("SELECT COUNT(*) as c FROM events $where")->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$events = $conn->query("
    SELECT * FROM events $where
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

// URL closure
$bp = ['search'=>$search,'type'=>$filterType,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$eUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'events.php?' . http_build_query($p);
};

require_once 'includes/layout_top.php';
?>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-calendar-alt me-2"></i>Events
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
                           placeholder="Search events..."
                           value="<?= htmlspecialchars($search) ?>" style="width:180px;">
                </div>
                <select name="type" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Types</option>
                    <?php foreach($eventTypes as $et): ?>
                    <option value="<?= $et ?>" <?= $filterType===$et?'selected':'' ?>><?= ucfirst($et) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if($search || $filterType): ?>
                <a href="<?= $eUrl(['search'=>'','type'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
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
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                <i class="fas fa-plus"></i>Add Event
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;">#</th>
                    <th style="background:#0F3A1A;color:white;">Event</th>
                    <th style="background:#0F3A1A;color:white;">Date</th>
                    <th style="background:#0F3A1A;color:white;">Type</th>
                    <th style="background:#0F3A1A;color:white;">Venue</th>
                    <th style="background:#0F3A1A;color:white;">Status</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($events)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No events found.</td></tr>
                <?php else: ?>
                <?php foreach($events as $i => $ev): ?>
                <?php $isPast = strtotime($ev['event_date']) < time(); ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if($ev['image']): ?>
                            <img src="../uploads/events/<?= $ev['image'] ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($ev['title']) ?></div>
                                <?php if($ev['event_time']): ?>
                                <div style="font-size:12px;color:var(--text-muted);"><i class="fas fa-clock me-1"></i><?= $ev['event_time'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13px;"><?= date('M d, Y', strtotime($ev['event_date'])) ?></div>
                        <?php if($ev['event_end_date'] && $ev['event_end_date'] !== $ev['event_date']): ?>
                        <div style="font-size:11px;color:var(--text-muted);">to <?= date('M d', strtotime($ev['event_end_date'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge" style="background:var(--primary);font-size:11px;"><?= ucfirst($ev['event_type']) ?></span></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($ev['venue'] ?: '—') ?></td>
                    <td>
                        <?php if($isPast): ?>
                        <span class="status-badge" style="background:#e2e3e5;color:#383d41;">Past</span>
                        <?php elseif($ev['is_active']): ?>
                        <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                        <span class="status-badge status-inactive">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-admin-warning btn-sm edit-event-btn"
                                    data-event='<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this event?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
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
            <?php if($totalCount===0): ?>No events found.
            <?php else: ?>
                Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
                of <strong><?= $totalCount ?></strong> event<?= $totalCount!=1?'s':'' ?>
                <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
            <?php endif; ?>
        </div>
        <?php if($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= $eUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $eUrl(['page'=>1]) ?>">1</a></li>
            <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $eUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if($pe<$totalPages):
                if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $eUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $eUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Add Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="events.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event Date <span class="text-danger">*</span></label>
                            <input type="date" name="event_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="event_end_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time</label>
                            <input type="text" name="event_time" class="form-control" placeholder="e.g. 10:00 AM">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" class="form-control" maxlength="200">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Event Type</label>
                            <select name="event_type" class="form-select">
                                <?php foreach($eventTypes as $et): ?>
                                <option value="<?= $et ?>"><?= ucfirst($et) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Hidden</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" maxlength="1000"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Event Image (optional, max 3MB)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--accent);">
                <h5 class="modal-title" style="color:var(--dark);"><i class="fas fa-edit me-2"></i>Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="events.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="event_id" id="ee_id">
                <input type="hidden" name="existing_image" id="ee_img">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="ee_title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event Date <span class="text-danger">*</span></label>
                            <input type="date" name="event_date" id="ee_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="event_end_date" id="ee_edate" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time</label>
                            <input type="text" name="event_time" id="ee_time" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" id="ee_venue" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select name="event_type" id="ee_type" class="form-select">
                                <?php foreach($eventTypes as $et): ?>
                                <option value="<?= $et ?>"><?= ucfirst($et) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="ee_active" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Hidden</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="ee_desc" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Replace Image (optional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
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
    $(document).on('click', '.edit-event-btn', function(){
        const ev = $(this).data('event');
        $('#ee_id').val(ev.id); $('#ee_img').val(ev.image || '');
        $('#ee_title').val(ev.title); $('#ee_date').val(ev.event_date);
        $('#ee_edate').val(ev.event_end_date || ''); $('#ee_time').val(ev.event_time || '');
        $('#ee_venue').val(ev.venue || ''); $('#ee_type').val(ev.event_type);
        $('#ee_active').val(ev.is_active); $('#ee_desc').val(ev.description || '');
        $('#editEventModal').modal('show');
    });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
