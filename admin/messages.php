<?php
$pageTitle = 'Contact Messages';
require_once 'includes/auth.php';

// Generate CSRF token for form protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mark as read
if (isset($_GET['read'])) {
    $msgId = (int)$_GET['read'];
    $conn->query("UPDATE contact_messages SET is_read=1 WHERE id=$msgId");
    header('Location: messages.php' . ($_SERVER['QUERY_STRING'] ? '?' . http_build_query(array_diff_key($_GET, ['read'=>''])) : ''));
    exit();
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid form submission. Please try again.";
        $messageType = 'danger';
    } else {
        // Regenerate token after successful submission
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $msgId = (int)($_POST['msg_id'] ?? 0);
        if ($msgId) {
            if ($conn->query("DELETE FROM contact_messages WHERE id=$msgId")) {
                $message = 'Message deleted successfully.'; $messageType = 'success';
            } else {
                $message = 'Failed to delete message.'; $messageType = 'danger';
            }
        }
        
        // Redirect to prevent form resubmission (PRG pattern)
        if (isset($message)) {
            // Preserve current state
            $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $currentSearch = isset($_GET['search']) ? $_GET['search'] : '';
            $currentFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
            $currentDir = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';
            $currentPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            
            // Build redirect URL with current state
            $redirectParams = [];
            if ($currentPage > 1) $redirectParams[] = "page=$currentPage";
            if (!empty($currentSearch)) $redirectParams[] = "search=" . urlencode($currentSearch);
            if (!empty($currentFilter)) $redirectParams[] = "filter=" . urlencode($currentFilter);
            if ($currentSort !== 'created_at') $redirectParams[] = "sort=$currentSort";
            if ($currentDir !== 'DESC') $redirectParams[] = "dir=$currentDir";
            if ($currentPerPage !== 10) $redirectParams[] = "per_page=$currentPerPage";
            
            $redirectUrl = 'messages.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : '');
            
            echo "<script>
                sessionStorage.setItem('messageMessage', '" . addslashes($message) . "');
                sessionStorage.setItem('messageMessageType', '$messageType');
                window.location.href = '$redirectUrl';
            </script>";
            exit;
        }
    }
}

// Mark all read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE contact_messages SET is_read=1");
    header('Location: messages.php');
    exit();
}

// Search / Filter / Sort / Pagination
$search  = sanitize($conn, $_GET['search'] ?? '');
$filter  = sanitize($conn, $_GET['filter'] ?? '');
$sortCol = sanitize($conn, $_GET['sort']   ?? 'created_at');
$sortDir = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'sender_name'  => 'sender_name',
    'sender_email' => 'sender_email',
    'subject'      => 'subject',
    'created_at'   => 'created_at',
    'is_read'      => 'is_read',
];
$orderBy = $allowedSort[$sortCol] ?? 'created_at';

$where = 'WHERE 1=1';
if ($filter === 'unread') $where .= ' AND is_read=0';
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (sender_name LIKE '%$s%' OR sender_email LIKE '%$s%' OR subject LIKE '%$s%' OR message LIKE '%$s%')";
}

$totalCount  = (int)$conn->query("SELECT COUNT(*) as c FROM contact_messages $where")->fetch_assoc()['c'];
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$messages    = $conn->query("SELECT * FROM contact_messages $where ORDER BY $orderBy $sortDir LIMIT $perPage OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
$unreadCount = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'];

$bp   = ['search'=>$search,'filter'=>$filter,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$mUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'messages.php?' . http_build_query($p);
};

require_once 'includes/layout_top.php';
?>

<!-- Alert Messages -->
<div id="alertContainer"></div>

<script>
// Show alert messages from session storage
document.addEventListener('DOMContentLoaded', function() {
    const message = sessionStorage.getItem('messageMessage');
    const type = sessionStorage.getItem('messageMessageType');
    
    if (message) {
        const alertClass = type === 'success' ? 'alert-success' : (type === 'danger' ? 'alert-danger' : 'alert-warning');
        const alertIcon = type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle');
        
        document.getElementById('alertContainer').innerHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show mb-4">
                <i class="fas ${alertIcon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Clear the messages
        sessionStorage.removeItem('messageMessage');
        sessionStorage.removeItem('messageMessageType');
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
});
</script>

<!-- Toolbar -->
<div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
    <!-- Search -->
    <form class="d-flex gap-2 align-items-center" method="GET">
        <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
        <input type="hidden" name="sort"     value="<?= htmlspecialchars($sortCol) ?>">
        <input type="hidden" name="dir"      value="<?= htmlspecialchars($sortDir) ?>">
        <input type="hidden" name="per_page" value="<?= $perPage ?>">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Name, email, subject..."
                   value="<?= htmlspecialchars($search) ?>" style="width:200px;">
        </div>
        <button type="submit" class="btn-admin-primary btn-sm">Search</button>
        <?php if($search): ?>
        <a href="<?= $mUrl(['search'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
    </form>
    <!-- Filter buttons -->
    <a href="<?= $mUrl(['filter'=>'','page'=>1]) ?>"
       class="btn-admin-primary <?= !$filter?'':'opacity-75' ?>">
        All (<?= $conn->query("SELECT COUNT(*) as c FROM contact_messages")->fetch_assoc()['c'] ?>)
    </a>
    <a href="<?= $mUrl(['filter'=>'unread','page'=>1]) ?>"
       class="btn-admin-danger <?= $filter==='unread'?'':'opacity-75' ?>">
        Unread (<?= $unreadCount ?>)
    </a>
    <!-- Per page -->
    <form method="GET" class="d-flex align-items-center gap-1">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="hidden" name="sort"   value="<?= htmlspecialchars($sortCol) ?>">
        <input type="hidden" name="dir"    value="<?= htmlspecialchars($sortDir) ?>">
        <input type="hidden" name="page"   value="1">
        <select name="per_page" class="form-select form-select-sm" style="width:80px;" onchange="this.form.submit()">
            <?php foreach([10,25,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
        <span style="font-size:12px;color:var(--text-muted);">per page</span>
    </form>
    <?php if($unreadCount > 0): ?>
    <a href="messages.php?mark_all_read=1" class="btn-admin-success ms-auto">
        <i class="fas fa-check-double me-1"></i>Mark All Read
    </a>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-envelope me-2"></i>Messages
            <span style="background:var(--primary);color:white;padding:2px 10px;border-radius:12px;font-size:12px;margin-left:6px;"><?= $totalCount ?></span>
        </h6>
    </div>
    <?php if(empty($messages)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>No messages found.
    </div>
    <?php else: ?>
    <?php foreach($messages as $msg): ?>
    <div class="p-4 border-bottom <?= !$msg['is_read']?'':'opacity-75' ?>"
         style="<?= !$msg['is_read']?'background:rgba(26,92,42,0.04);':'' ?>">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="d-flex gap-3">
                <div style="width:45px;height:45px;background:<?= !$msg['is_read']?'var(--primary)':'#ccc' ?>;
                            border-radius:50%;display:flex;align-items:center;justify-content:center;
                            color:white;font-weight:700;font-size:16px;flex-shrink:0;">
                    <?= strtoupper(substr($msg['sender_name'],0,1)) ?>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-bold"><?= htmlspecialchars($msg['sender_name']) ?></span>
                        <?php if(!$msg['is_read']): ?>
                        <span class="status-badge" style="background:#cce5ff;color:#004085;font-size:10px;">New</span>
                        <?php endif; ?>
                        <span class="text-muted" style="font-size:12px;">
                            <i class="fas fa-tag me-1"></i><?= htmlspecialchars($msg['subject']) ?>
                        </span>
                    </div>
                    <div class="text-muted" style="font-size:13px;">
                        <a href="mailto:<?= htmlspecialchars($msg['sender_email']) ?>" style="color:var(--primary);">
                            <?= htmlspecialchars($msg['sender_email']) ?>
                        </a>
                        <?php if($msg['sender_phone']): ?>
                        &nbsp;|&nbsp;
                        <a href="tel:<?= htmlspecialchars($msg['sender_phone']) ?>" style="color:var(--text-muted);">
                            <?= htmlspecialchars($msg['sender_phone']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <p class="mt-2 mb-0" style="font-size:14px;color:var(--text-dark);line-height:1.7;">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </p>
                    <div class="text-muted mt-2" style="font-size:12px;">
                        <i class="fas fa-clock me-1"></i><?= date('F d, Y h:i A', strtotime($msg['created_at'])) ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="mailto:<?= htmlspecialchars($msg['sender_email']) ?>?subject=Re: <?= urlencode($msg['subject']) ?>"
                   class="btn-admin-primary btn-sm"><i class="fas fa-reply me-1"></i>Reply</a>
                <?php if(!$msg['is_read']): ?>
                <a href="messages.php?read=<?= $msg['id'] ?>" class="btn-admin-success btn-sm">
                    <i class="fas fa-check me-1"></i>Mark Read
                </a>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this message? This action cannot be undone.')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="btn-admin-danger btn-sm" title="Delete Message">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1 || $totalCount > 0): ?>
<?php $from = $totalCount===0 ? 0 : $offset+1; $to = min($offset+$perPage,$totalCount); ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3"
     style="font-size:13px;">
    <div class="text-muted">
        <?php if($totalCount===0): ?>No messages found.
        <?php else: ?>
            Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
            of <strong><?= $totalCount ?></strong> message<?= $totalCount!=1?'s':'' ?>
            <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
        <?php endif; ?>
    </div>
    <?php if($totalPages > 1): ?>
    <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <a class="page-link" href="<?= $mUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
        </li>
        <?php
        $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
        if($ps>1): ?>
        <li class="page-item"><a class="page-link" href="<?= $mUrl(['page'=>1]) ?>">1</a></li>
        <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
        endif;
        for($p=$ps;$p<=$pe;$p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>">
            <a class="page-link" href="<?= $mUrl(['page'=>$p]) ?>"><?= $p ?></a>
        </li>
        <?php endfor;
        if($pe<$totalPages):
            if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= $mUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="<?= $mUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
        </li>
    </ul></nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/layout_bottom.php'; ?>
