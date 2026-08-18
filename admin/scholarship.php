<?php
$pageTitle = 'Scholarship Applications';
require_once 'includes/auth.php';

// Generate CSRF token for form protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── CSV Export ─────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=scholarships_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, [
        '#','Applicant Name','DOB','DOB Type','Gender','Class','Scholarship Type',
        'Father Name','Mother Name','Guardian Phone','Address',
        'Annual Income','Reason','Status','Submitted','Remarks'
    ]);
    $all = $conn->query("SELECT * FROM scholarship_applications ORDER BY submitted_at DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($all as $i => $row) {
        fputcsv($out, [
            $i + 1,
            $row['applicant_name'],
            $row['date_of_birth'],
            $row['dob_type'] ?? 'AD',
            ucfirst($row['gender']),
            $row['current_class'],
            $row['scholarship_type'] ?? '',
            $row['father_name'],
            $row['mother_name']    ?? '',
            $row['guardian_phone'],
            $row['address'],
            $row['annual_income']  ?? '',
            $row['reason'],
            ucfirst(str_replace('_',' ',$row['status'])),
            $row['submitted_at'],
            $row['remarks']        ?? '',
        ]);
    }
    fclose($out);
    exit();
}

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid form submission. Please try again.";
        $messageType = 'danger';
    } else {
        // Regenerate token after successful submission
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $action = $_POST['action'] ?? '';
        $appId  = (int)($_POST['app_id'] ?? 0);

        if ($action === 'update_status' && $appId) {
            $status  = sanitize($conn, $_POST['status'] ?? 'pending');
            $remarks = sanitize($conn, $_POST['remarks'] ?? '');
            $conn->query("UPDATE scholarship_applications SET status='$status', remarks='$remarks', reviewed_by=$adminId, reviewed_at=NOW() WHERE id=$appId");
            $message = 'Status updated successfully!'; $messageType = 'success';
        } elseif ($action === 'delete' && $appId) {
            // Get documents to delete files
            $application = $conn->query("SELECT supporting_documents FROM scholarship_applications WHERE id=$appId")->fetch_assoc();
            
            if ($conn->query("DELETE FROM scholarship_applications WHERE id=$appId")) {
                // Delete uploaded files
                if ($application['supporting_documents']) {
                    $docPath = '../uploads/scholarship/' . $application['supporting_documents'];
                    if (file_exists($docPath)) unlink($docPath);
                }
                $message = 'Scholarship application deleted successfully.'; $messageType = 'success';
            } else {
                $message = 'Failed to delete scholarship application.'; $messageType = 'danger';
            }
        }
        
        // Redirect to prevent form resubmission (PRG pattern)
        if ($message) {
            // Preserve current state
            $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $currentSearch = isset($_GET['search']) ? $_GET['search'] : '';
            $currentStatus = isset($_GET['status']) ? $_GET['status'] : '';
            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'submitted_at';
            $currentDir = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';
            $currentPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            
            // Build redirect URL with current state
            $redirectParams = [];
            if ($currentPage > 1) $redirectParams[] = "page=$currentPage";
            if (!empty($currentSearch)) $redirectParams[] = "search=" . urlencode($currentSearch);
            if (!empty($currentStatus)) $redirectParams[] = "status=" . urlencode($currentStatus);
            if ($currentSort !== 'submitted_at') $redirectParams[] = "sort=$currentSort";
            if ($currentDir !== 'DESC') $redirectParams[] = "dir=$currentDir";
            if ($currentPerPage !== 10) $redirectParams[] = "per_page=$currentPerPage";
            
            $redirectUrl = 'scholarship.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : '');
            
            echo "<script>
                sessionStorage.setItem('scholarshipMessage', '" . addslashes($message) . "');
                sessionStorage.setItem('scholarshipMessageType', '$messageType');
                window.location.href = '$redirectUrl';
            </script>";
            exit;
        }
    }
}

$filterStatus = sanitize($conn, $_GET['status']   ?? '');
$search       = sanitize($conn, $_GET['search']   ?? '');
$sortCol      = sanitize($conn, $_GET['sort']     ?? 'submitted_at');
$sortDir      = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage      = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page         = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'applicant_name' => 's.applicant_name',
    'current_class'  => 's.current_class',
    'scholarship_type' => 's.scholarship_type',
    'guardian_phone' => 's.guardian_phone',
    'submitted_at'   => 's.submitted_at',
    'status'         => 's.status',
];
$orderBy = $allowedSort[$sortCol] ?? 's.submitted_at';

$allowedScholStatus = ['pending','approved','rejected','under_review'];
$where = "WHERE 1=1";
if ($filterStatus && in_array($filterStatus, $allowedScholStatus)) $where .= " AND s.status='$filterStatus'";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (s.applicant_name LIKE '%$s%' OR s.guardian_phone LIKE '%$s%' OR s.current_class LIKE '%$s%' OR s.scholarship_type LIKE '%$s%')";
}

$totalCount = (int)$conn->query("SELECT COUNT(*) as c FROM scholarship_applications s $where")->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$applications = $conn->query("
    SELECT s.*, u.full_name as reviewer_name
    FROM scholarship_applications s
    LEFT JOIN users u ON s.reviewed_by = u.id
    $where
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$counts   = $conn->query("SELECT status, COUNT(*) as cnt FROM scholarship_applications GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$countMap = array_column($counts, 'cnt', 'status');

$bp = ['search'=>$search,'status'=>$filterStatus,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$sUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'scholarship.php?' . http_build_query($p);
};

require_once 'includes/layout_top.php';
?>

<!-- Alert Messages -->
<div id="alertContainer"></div>

<script>
// Show alert messages from session storage
document.addEventListener('DOMContentLoaded', function() {
    const message = sessionStorage.getItem('scholarshipMessage');
    const type = sessionStorage.getItem('scholarshipMessageType');
    
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
        sessionStorage.removeItem('scholarshipMessage');
        sessionStorage.removeItem('scholarshipMessageType');
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
});
</script>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Summary -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label'=>'Total',        'key'=>'all',          'color'=>'var(--primary)',   'icon'=>'fa-list'],
        ['label'=>'Pending',      'key'=>'pending',      'color'=>'#e67e22',          'icon'=>'fa-clock'],
        ['label'=>'Under Review', 'key'=>'under_review', 'color'=>'#2980b9',          'icon'=>'fa-search'],
        ['label'=>'Approved',     'key'=>'approved',     'color'=>'#27ae60',          'icon'=>'fa-check-circle'],
        ['label'=>'Rejected',     'key'=>'rejected',     'color'=>'var(--secondary)', 'icon'=>'fa-times-circle'],
    ];
    foreach($cards as $c):
        $cnt = $c['key'] === 'all' ? array_sum($countMap) : ($countMap[$c['key']] ?? 0);
    ?>
    <div class="col-6 col-md">
        <a href="scholarship.php<?= $c['key']!=='all'?'?status='.$c['key']:'' ?>" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:<?= $c['color'] ?>;">
                <div class="icon-box" style="background:<?= $c['color'] ?>;"><i class="fas <?= $c['icon'] ?>"></i></div>
                <div class="stat-info"><div class="number"><?= $cnt ?></div><div class="label"><?= $c['label'] ?></div></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-award me-2"></i>Scholarship Applications
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
                           placeholder="Name, phone, class..."
                           value="<?= htmlspecialchars($search) ?>" style="width:190px;">
                </div>
                <select name="status" class="form-select form-select-sm" style="width:140px;">
                    <option value="">All Status</option>
                    <option value="pending"      <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
                    <option value="under_review" <?= $filterStatus==='under_review'?'selected':'' ?>>Under Review</option>
                    <option value="approved"     <?= $filterStatus==='approved'?'selected':'' ?>>Approved</option>
                    <option value="rejected"     <?= $filterStatus==='rejected'?'selected':'' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if($search || $filterStatus): ?>
                <a href="<?= $sUrl(['search'=>'','status'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
                <a href="scholarship.php?export=csv" class="btn-admin-success btn-sm">
                    <i class="fas fa-file-csv me-1"></i>Export CSV
                </a>
            </form>
            <form method="GET" class="d-flex align-items-center gap-1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
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
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;">#</th>
                    <th style="background:#0F3A1A;color:white;">Applicant</th>
                    <th style="background:#0F3A1A;color:white;">DOB</th>
                    <th style="background:#0F3A1A;color:white;">Class</th>
                    <th style="background:#0F3A1A;color:white;">Type</th>
                    <th style="background:#0F3A1A;color:white;">Phone</th>
                    <th style="background:#0F3A1A;color:white;">Date</th>
                    <th style="background:#0F3A1A;color:white;">Status</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($applications)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No applications found.</td></tr>
                <?php else: ?>
                <?php foreach($applications as $i => $app): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            $sPhoto = $app['student_photo'] ?? '';
                            $ext    = strtolower(pathinfo($sPhoto, PATHINFO_EXTENSION));
                            $isImg  = in_array($ext, ['jpg','jpeg','png','webp']);
                            ?>
                            <?php if($sPhoto && $isImg): ?>
                            <img src="../uploads/scholarship/<?= htmlspecialchars($sPhoto) ?>"
                                 alt="photo"
                                 style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--border);flex-shrink:0;"
                                 onerror="this.style.display='none'">
                            <?php else: ?>
                            <div style="width:40px;height:40px;border-radius:6px;background:var(--primary-soft);
                                        display:flex;align-items:center;justify-content:center;flex-shrink:0;
                                        font-size:16px;font-weight:700;color:var(--primary);">
                                <?= strtoupper(mb_substr($app['applicant_name'],0,1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($app['applicant_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($app['scholarship_type']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                        $dob     = $app['date_of_birth'] ?? '';
                        $dobType = $app['dob_type']      ?? 'AD';
                        if ($dob && $dob !== '0000-00-00') {
                            echo htmlspecialchars($dob);
                            echo ' <span style="background:'.($dobType==='BS'?'#edf7f0':'#e8f4fb').';color:'.($dobType==='BS'?'#1a5c2a':'#0284c7').';padding:1px 5px;border-radius:4px;font-size:10px;font-weight:700;">'.$dobType.'</span>';
                        } else {
                            echo '<span class="text-muted">--</span>';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($app['current_class']) ?></td>
                    <td><span class="status-badge" style="background:var(--light);color:var(--text-dark);"><?= htmlspecialchars($app['scholarship_type']) ?></span></td>
                    <td><?= htmlspecialchars($app['guardian_phone']) ?></td>
                    <td style="font-size:12px;"><?= date('M d, Y', strtotime($app['submitted_at'])) ?></td>
                    <td><span class="status-badge status-<?= str_replace('_','-',$app['status']) ?>"><?= ucfirst(str_replace('_',' ',$app['status'])) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-admin-primary btn-sm view-app-btn"
                                    data-app='<?= htmlspecialchars(json_encode($app), ENT_QUOTES) ?>'>
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if($app['documents']): ?>
                            <a href="../uploads/scholarship/<?= $app['documents'] ?>" target="_blank"
                               class="btn-admin-success btn-sm"><i class="fas fa-file"></i></a>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this scholarship application? This action cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="btn-admin-danger btn-sm" title="Delete Application">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
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
            <?php if($totalCount===0): ?>No applications found.
            <?php else: ?>
                Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
                of <strong><?= $totalCount ?></strong> application<?= $totalCount!=1?'s':'' ?>
                <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
            <?php endif; ?>
        </div>
        <?php if($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= $sUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $sUrl(['page'=>1]) ?>">1</a></li>
            <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $sUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if($pe<$totalPages):
                if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $sUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $sUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewAppModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-award me-2"></i>Scholarship Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appDetailBody"></div>
            <div class="modal-footer">
                <form method="POST" class="d-flex gap-2 w-100 align-items-center admin-form">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="app_id" id="app_modal_id">
                    <select name="status" id="app_status" class="form-select" style="width:160px;">
                        <option value="pending">Pending</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="text" name="remarks" id="app_remarks" class="form-control" placeholder="Remarks">
                    <button type="submit" class="btn-admin-primary" style="white-space:nowrap;">
                        <i class="fas fa-save me-1"></i>Update
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $(document).on('click', '.view-app-btn', function(){
        const a = $(this).data('app');
        $('#app_modal_id').val(a.id);
        $('#app_status').val(a.status);
        $('#app_remarks').val(a.remarks || '');
        const html = `
            <div class="row g-3" style="font-size:14px;">
                ${a.student_photo ? `<div class="col-12 text-center mb-2">
                    <img src="../uploads/scholarship/${a.student_photo}"
                         style="width:90px;height:110px;object-fit:cover;border-radius:8px;border:2px solid var(--border);"
                         onerror="this.style.display='none'">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Student Photo</div>
                </div>` : ''}
                <div class="col-md-6"><strong>Name:</strong><div>${a.applicant_name}</div></div>
                <div class="col-md-3"><strong>DOB:</strong><div>${a.date_of_birth} <span style="background:${a.dob_type==='BS'?'#edf7f0':'#e8f4fb'};color:${a.dob_type==='BS'?'#1a5c2a':'#0284c7'};padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700;">${a.dob_type||'AD'}</span></div></div>
                <div class="col-md-3"><strong>Gender:</strong><div>${a.gender}</div></div>
                <div class="col-md-3"><strong>Class:</strong><div>${a.current_class}</div></div>
                <div class="col-md-3"><strong>Roll No:</strong><div>${a.roll_no || '—'}</div></div>
                <div class="col-md-6"><strong>Scholarship Type:</strong><div>${a.scholarship_type}</div></div>
                <div class="col-md-6"><strong>Annual Income:</strong><div>${a.annual_income || '—'}</div></div>
                <div class="col-md-6"><strong>Father's Name:</strong><div>${a.father_name}</div></div>
                <div class="col-md-6"><strong>Phone:</strong><div>${a.guardian_phone}</div></div>
                <div class="col-12"><strong>Address:</strong><div>${a.address}</div></div>
                <div class="col-12"><strong>Reason for Scholarship:</strong>
                    <div style="background:#f8f9fa;padding:10px;border-radius:8px;margin-top:5px;">${a.reason}</div></div>
                <div class="col-12"><strong>Submitted:</strong><div>${a.submitted_at}</div></div>
            </div>
        `;
        $('#appDetailBody').html(html);
        $('#viewAppModal').modal('show');
    });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
