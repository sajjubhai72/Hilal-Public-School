<?php
$pageTitle = 'Manage Admissions';
require_once 'includes/auth.php';
require_once '../includes/nepali_date.php';

// Generate CSRF token for form protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── CSV Export — must be before any HTML output ────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=admissions_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Ref No','Student Name','DOB','DOB Type','Gender','Class','Year','Father Name','Mother Name','Phone','Email','Address','Previous School','Previous Class','Status','Submitted','Remarks']);
    $allAdm = $conn->query("SELECT * FROM admissions ORDER BY submitted_at DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($allAdm as $row) {
        fputcsv($out, [
            'ADM-'.date('Y',strtotime($row['submitted_at'])).'-'.str_pad($row['id'],4,'0',STR_PAD_LEFT),
            $row['student_name'],
            $row['date_of_birth'],
            $row['dob_type'] ?? 'AD',
            ucfirst($row['gender']),
            $row['applying_for_class'],
            $row['academic_year'],
            $row['father_name'],
            $row['mother_name']     ?? '',
            $row['guardian_phone'],
            $row['guardian_email']  ?? '',
            $row['address'],
            $row['previous_school'] ?? '',
            $row['previous_class']  ?? '',
            ucfirst($row['status']),
            $row['submitted_at'],
            $row['remarks']         ?? '',
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
        $admId  = (int)($_POST['admission_id'] ?? 0);

        if ($action === 'update_status' && $admId) {
            $status  = sanitize($conn, $_POST['status'] ?? 'pending');
            $remarks = sanitize($conn, $_POST['remarks'] ?? '');
            $conn->query("UPDATE admissions SET status='$status', remarks='$remarks', reviewed_by=$adminId, reviewed_at=NOW() WHERE id=$admId");
            $message = 'Status updated successfully!'; $messageType = 'success';
        } elseif ($action === 'delete' && $admId) {
            // Get documents to delete files
            $admission = $conn->query("SELECT documents, student_photo FROM admissions WHERE id=$admId")->fetch_assoc();
            
            if ($conn->query("DELETE FROM admissions WHERE id=$admId")) {
                // Delete uploaded files
                if ($admission['documents']) {
                    $docPath = '../uploads/admissions/' . $admission['documents'];
                    if (file_exists($docPath)) unlink($docPath);
                }
                if ($admission['student_photo']) {
                    $photoPath = '../uploads/admissions/' . $admission['student_photo'];
                    if (file_exists($photoPath)) unlink($photoPath);
                }
                $message = 'Admission record deleted successfully.'; $messageType = 'success';
            } else {
                $message = 'Failed to delete admission record.'; $messageType = 'danger';
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
            
            $redirectUrl = 'admissions.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : '');
            
            echo "<script>
                sessionStorage.setItem('admissionMessage', '" . addslashes($message) . "');
                sessionStorage.setItem('admissionMessageType', '$messageType');
                window.location.href = '$redirectUrl';
            </script>";
            exit;
        }
    }
}

// Filters + Search + Sort + Pagination
$filterStatus = sanitize($conn, $_GET['status'] ?? '');
$search       = sanitize($conn, $_GET['search'] ?? '');
$sortCol      = sanitize($conn, $_GET['sort']   ?? 'submitted_at');
$sortDir      = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage      = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page         = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'student_name'  => 'a.student_name',
    'applying_for_class' => 'a.applying_for_class',
    'father_name'   => 'a.father_name',
    'submitted_at'  => 'a.submitted_at',
    'status'        => 'a.status',
];
$orderBy = $allowedSort[$sortCol] ?? 'a.submitted_at';

$where = "WHERE 1=1";
$allowed_status = ['pending','approved','rejected'];
if ($filterStatus && in_array($filterStatus, $allowed_status)) $where .= " AND a.status='$filterStatus'";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (a.student_name LIKE '%$s%' OR a.guardian_phone LIKE '%$s%' OR a.father_name LIKE '%$s%' OR a.applying_for_class LIKE '%$s%')";
}

$totalCount = (int)$conn->query("SELECT COUNT(*) as c FROM admissions a $where")->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$admissions = $conn->query("
    SELECT a.*, u.full_name as reviewer_name
    FROM admissions a
    LEFT JOIN users u ON a.reviewed_by = u.id
    $where
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

// Summary counts
$counts = $conn->query("SELECT status, COUNT(*) as cnt FROM admissions GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$countMap = array_column($counts, 'cnt', 'status');

// URL closure
$bp = ['search'=>$search,'status'=>$filterStatus,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$aUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'admissions.php?' . http_build_query($p);
};

require_once 'includes/layout_top.php';
?>

<!-- Alert Messages -->
<div id="alertContainer"></div>

<script>
// Show alert messages from session storage
document.addEventListener('DOMContentLoaded', function() {
    const message = sessionStorage.getItem('admissionMessage');
    const type = sessionStorage.getItem('admissionMessageType');
    
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
        sessionStorage.removeItem('admissionMessage');
        sessionStorage.removeItem('admissionMessageType');
        
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

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $summaryItems = [
        ['label'=>'Total',    'key'=>'all',       'color'=>'var(--primary)',   'icon'=>'fa-list'],
        ['label'=>'Pending',  'key'=>'pending',   'color'=>'#e67e22',          'icon'=>'fa-clock'],
        ['label'=>'Approved', 'key'=>'approved',  'color'=>'#27ae60',          'icon'=>'fa-check-circle'],
        ['label'=>'Rejected', 'key'=>'rejected',  'color'=>'var(--secondary)', 'icon'=>'fa-times-circle'],
    ];
    $totalAll = array_sum($countMap);
    foreach($summaryItems as $s):
        $cnt = $s['key'] === 'all' ? $totalAll : ($countMap[$s['key']] ?? 0);
    ?>
    <div class="col-6 col-md-3">
        <a href="admissions.php<?= $s['key']!=='all'?'?status='.$s['key']:'' ?>" class="text-decoration-none">
            <div class="stat-card" style="border-left-color:<?= $s['color'] ?>;">
                <div class="icon-box" style="background:<?= $s['color'] ?>;"><i class="fas <?= $s['icon'] ?>"></i></div>
                <div class="stat-info"><div class="number"><?= $cnt ?></div><div class="label"><?= $s['label'] ?></div></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-user-plus me-2"></i>Admission Applications
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
                <select name="status" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Status</option>
                    <option value="pending"  <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
                    <option value="approved" <?= $filterStatus==='approved'?'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if($search || $filterStatus): ?>
                <a href="<?= $aUrl(['search'=>'','status'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
                <a href="admissions.php?export=excel" class="btn-admin-success btn-sm">
                    <i class="fas fa-file-excel"></i> Export
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
                    <th style="background:#0F3A1A;color:white;">Ref No</th>
                    <th style="background:#0F3A1A;color:white;">Student</th>
                    <th style="background:#0F3A1A;color:white;">Class</th>
                    <th style="background:#0F3A1A;color:white;">Parent</th>
                    <th style="background:#0F3A1A;color:white;">Phone</th>
                    <th style="background:#0F3A1A;color:white;">Date</th>
                    <th style="background:#0F3A1A;color:white;">Status</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($admissions)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No admissions found.</td></tr>
                <?php else: ?>
                <?php foreach($admissions as $i => $adm): ?>
                <tr>
                    <td>
                        <div class="fw-bold" style="color:var(--text-muted);font-size:12px;">
                            ADM-<?= date('Y', strtotime($adm['submitted_at'])) ?>-<?= str_pad($adm['id'],4,'0',STR_PAD_LEFT) ?>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            $docFile = $adm['documents'] ?? '';
                            $ext = strtolower(pathinfo($docFile, PATHINFO_EXTENSION));
                            $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
                            ?>
                            <?php if($docFile && $isImg): ?>
                            <img src="../uploads/admissions/<?= htmlspecialchars($docFile) ?>"
                                 alt="photo"
                                 style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--border);flex-shrink:0;"
                                 onerror="this.style.display='none'">
                            <?php else: ?>
                            <div style="width:40px;height:40px;border-radius:6px;background:var(--primary-soft);
                                        display:flex;align-items:center;justify-content:center;flex-shrink:0;
                                        font-size:16px;font-weight:700;color:var(--primary);">
                                <?= strtoupper(mb_substr($adm['student_name'],0,1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($adm['student_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    DOB: <?php
                                        $dob = $adm['date_of_birth'];
                                        $dobType = $adm['dob_type'] ?? 'AD';
                                        if ($dobType === 'BS') {
                                            echo htmlspecialchars($dob) . ' <span style="background:#edf7f0;color:#1a5c2a;padding:1px 5px;border-radius:4px;font-size:10px;font-weight:700;">BS</span>';
                                        } else {
                                            echo date('M d, Y', strtotime($dob)) . ' <span style="background:#e8f4fb;color:#0284c7;padding:1px 5px;border-radius:4px;font-size:10px;font-weight:700;">AD</span>';
                                        }
                                    ?> &bull; <?= ucfirst($adm['gender']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($adm['applying_for_class']) ?><br>
                        <small class="text-muted"><?= $adm['academic_year'] ?></small></td>
                    <td>
                        <div><?= htmlspecialchars($adm['father_name']) ?></div>
                        <?php if($adm['guardian_email']): ?>
                        <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($adm['guardian_email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($adm['guardian_phone']) ?></td>
                    <td style="font-size:12px;"><?= date('M d, Y', strtotime($adm['submitted_at'])) ?></td>
                    <td><span class="status-badge status-<?= $adm['status'] ?>"><?= ucfirst($adm['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-admin-primary btn-sm view-adm-btn"
                                    data-adm='<?= htmlspecialchars(json_encode($adm), ENT_QUOTES) ?>'
                                    title="View & Update">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if($adm['documents']): ?>
                            <a href="../uploads/admissions/<?= $adm['documents'] ?>" target="_blank"
                               class="btn-admin-success btn-sm" title="View Document"><i class="fas fa-file"></i></a>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this admission record? This action cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="admission_id" value="<?= $adm['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="btn-admin-danger btn-sm" title="Delete Record">
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
                <a class="page-link" href="<?= $aUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $aUrl(['page'=>1]) ?>">1</a></li>
            <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $aUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if($pe<$totalPages):
                if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $aUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $aUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- View/Update Modal -->
<div class="modal fade" id="viewAdmModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>Admission Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="admDetailBody"></div>
            <div class="modal-footer">
                <form method="POST" class="d-flex gap-2 w-100 align-items-center admin-form">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="admission_id" id="adm_modal_id">
                    <select name="status" id="adm_status_select" class="form-select" style="width:150px;">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="text" name="remarks" id="adm_remarks" class="form-control"
                           placeholder="Remarks (optional)">
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
    $(document).on('click', '.view-adm-btn', function(){
        const a = $(this).data('adm');
        $('#adm_modal_id').val(a.id);
        $('#adm_status_select').val(a.status);
        $('#adm_remarks').val(a.remarks || '');

        const html = `
            <div class="row g-3" style="font-size:14px;">
                <div class="col-md-6"><strong>Student Name:</strong><div>${a.student_name}</div></div>
                <div class="col-md-3"><strong>DOB:</strong><div>${a.date_of_birth}</div></div>
                <div class="col-md-3"><strong>Gender:</strong><div>${a.gender}</div></div>
                <div class="col-md-6"><strong>Applying for Class:</strong><div>${a.applying_for_class} (${a.academic_year})</div></div>
                <div class="col-md-6"><strong>Previous School:</strong><div>${a.previous_school || '—'} (Class: ${a.previous_class || '—'})</div></div>
                <div class="col-md-6"><strong>Father's Name:</strong><div>${a.father_name}</div></div>
                <div class="col-md-6"><strong>Mother's Name:</strong><div>${a.mother_name || '—'}</div></div>
                <div class="col-md-6"><strong>Phone:</strong><div>${a.guardian_phone}</div></div>
                <div class="col-md-6"><strong>Email:</strong><div>${a.guardian_email || '—'}</div></div>
                <div class="col-12"><strong>Address:</strong><div>${a.address}</div></div>
                <div class="col-12"><strong>Submitted:</strong><div>${a.submitted_at}</div></div>
            </div>
        `;
        $('#admDetailBody').html(html);
        $('#viewAdmModal').modal('show');
    });
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
