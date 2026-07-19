<?php
$pageTitle = 'Manage Results & Publishing';
require_once 'includes/auth.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_exam') {
        $examName  = sanitize($conn, $_POST['exam_name'] ?? '');
        $examType  = sanitize($conn, $_POST['exam_type'] ?? '');
        $year      = sanitize($conn, $_POST['academic_year'] ?? '');
        $classId   = (int)($_POST['class_id'] ?? 0);
        $startDate = sanitize($conn, $_POST['start_date'] ?? '') ?: null;
        $endDate   = sanitize($conn, $_POST['end_date'] ?? '') ?: null;

        if ($examName && $examType && $year && $classId) {
            $stmt = $conn->prepare("INSERT INTO exams (exam_name,exam_type,academic_year,class_id,start_date,end_date,created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("sssissi", $examName, $examType, $year, $classId, $startDate, $endDate, $adminId);
            $stmt->execute(); $stmt->close();

            // Create publish status record
            $examId = $conn->insert_id;
            $conn->query("INSERT IGNORE INTO result_publish (exam_id,class_id,is_published) VALUES ($examId,$classId,0)");

            $message = 'Exam created!'; $messageType = 'success';
        }

    } elseif ($action === 'add_exam_all_classes') {
        $examName  = sanitize($conn, $_POST['exam_name'] ?? '');
        $examType  = sanitize($conn, $_POST['exam_type'] ?? '');
        $year      = sanitize($conn, $_POST['academic_year'] ?? '');
        $startDate = sanitize($conn, $_POST['start_date'] ?? '') ?: null;
        $endDate   = sanitize($conn, $_POST['end_date'] ?? '') ?: null;
        $selClasses = $_POST['selected_classes'] ?? [];

        if ($examName && $examType && $year && !empty($selClasses)) {
            $created = 0;
            $stmt = $conn->prepare("INSERT INTO exams (exam_name,exam_type,academic_year,class_id,start_date,end_date,created_by) VALUES (?,?,?,?,?,?,?)");
            foreach ($selClasses as $cid) {
                $cid = (int)$cid;
                if (!$cid) continue;
                $stmt->bind_param("sssissi", $examName, $examType, $year, $cid, $startDate, $endDate, $adminId);
                $stmt->execute();
                $examId = $conn->insert_id;
                $conn->query("INSERT IGNORE INTO result_publish (exam_id,class_id,is_published) VALUES ($examId,$cid,0)");
                $created++;
            }
            $stmt->close();
            $message = "Exam created for $created class(es)!";
            $messageType = 'success';
        } else {
            $message = 'Please fill all fields and select at least one class.';
            $messageType = 'danger';
        }

    } elseif ($action === 'publish') {
        $examId  = (int)($_POST['exam_id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        if ($examId && $classId) {
            $conn->query("INSERT INTO result_publish (exam_id,class_id,is_published,published_by,published_at)
                          VALUES ($examId,$classId,1,$adminId,NOW())
                          ON DUPLICATE KEY UPDATE is_published=1, published_by=$adminId, published_at=NOW()");
            $message = 'Results published successfully! Students can now view their results.';
            $messageType = 'success';
        }

    } elseif ($action === 'unpublish') {
        $examId  = (int)($_POST['exam_id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        $conn->query("UPDATE result_publish SET is_published=0 WHERE exam_id=$examId AND class_id=$classId");
        $message = 'Results unpublished.'; $messageType = 'warning';

    } elseif ($action === 'delete_exam') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $conn->query("DELETE FROM exams WHERE id=$examId");
        $message = 'Exam deleted.'; $messageType = 'warning';
    }

    // PRG — prevent re-submit on refresh
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['results_msg']      = $message;
    $_SESSION['results_msg_type'] = $messageType;
    header('Location: results.php');
    exit();
}

// Flash message
if (session_status() === PHP_SESSION_NONE) session_start();
$message     = $_SESSION['results_msg']      ?? '';
$messageType = $_SESSION['results_msg_type'] ?? 'success';
unset($_SESSION['results_msg'], $_SESSION['results_msg_type']);

// ── Search / Sort / Pagination ────────────────────────
$search  = sanitize($conn, $_GET['search']   ?? '');
$sortCol = sanitize($conn, $_GET['sort']     ?? 'e.id');
$sortDir = strtoupper(sanitize($conn, $_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$perPage = in_array((int)($_GET['per_page'] ?? 10), [10,25,50,100]) ? (int)($_GET['per_page'] ?? 10) : 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'exam_name'      => 'e.exam_name',
    'exam_type'      => 'e.exam_type',
    'class_name'     => 'c.class_name',
    'academic_year'  => 'e.academic_year',
    'result_count'   => 'result_count',
    'is_published'   => 'is_published',
    'e.id'           => 'e.id',
];
$orderBy = $allowedSort[$sortCol] ?? 'e.id';

$where = '';
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where = "WHERE (e.exam_name LIKE '%$s%' OR c.class_name LIKE '%$s%' OR e.academic_year LIKE '%$s%')";
}

// Total count
$totalCount = (int)$conn->query("
    SELECT COUNT(*) as c FROM exams e
    JOIN classes c ON e.class_id=c.id
    $where
")->fetch_assoc()['c'];

$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch exams with publish status
$exams = $conn->query("
    SELECT e.*, c.class_name,
           COALESCE(rp.is_published,0) as is_published,
           rp.published_at,
           u.full_name as published_by_name,
           COUNT(DISTINCT r.student_id) as result_count
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    LEFT JOIN result_publish rp ON e.id=rp.exam_id AND rp.class_id=e.class_id
    LEFT JOIN users u ON rp.published_by = u.id
    LEFT JOIN results r ON r.exam_id = e.id
    $where
    GROUP BY e.id
    ORDER BY $orderBy $sortDir
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

// URL + sort closures
$bp = ['search'=>$search,'sort'=>$sortCol,'dir'=>$sortDir,'per_page'=>$perPage,'page'=>$page];
$rUrl = function($ov) use ($bp) {
    $p = array_merge($bp, $ov);
    $p = array_filter($p, function($v){ return $v !== '' && $v !== null; });
    return 'results.php?' . http_build_query($p);
};
$rSort = function($col, $lbl) use ($sortCol, $sortDir, $rUrl) {
    $nd  = ($sortCol === $col && $sortDir === 'ASC') ? 'DESC' : 'ASC';
    $style = $sortCol === $col
        ? 'color:white;text-decoration:underline;text-underline-offset:3px;font-weight:700;'
        : 'color:white;text-decoration:none;';
    $url = $rUrl(['sort'=>$col,'dir'=>$nd,'page'=>1]);
    return "<a href=\"$url\" style=\"$style\">$lbl</a>";
};

$classes   = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$examTypes = ['1st_terminal'=>'1st Terminal','2nd_terminal'=>'2nd Terminal','final'=>'Final Exam'];
$currentYear = getSetting($conn, 'academic_year');

require_once 'includes/layout_top.php';
?>

<?php if($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <i class="fas fa-<?= $messageType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Info Note -->
<div class="alert alert-info mb-4" style="font-size:14px;">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Workflow:</strong> 1) Create exam → 2) Teachers enter marks in their panel → 3) Admin reviews → 4) Click <strong>Publish</strong> to make results visible to students.
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h6><i class="fas fa-poll-h me-2"></i>Exams & Results
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
                           placeholder="Search exam, class, year..."
                           value="<?= htmlspecialchars($search) ?>" style="width:210px;">
                </div>
                <button type="submit" class="btn-admin-primary btn-sm">Search</button>
                <?php if($search): ?>
                <a href="<?= $rUrl(['search'=>'','page'=>1]) ?>" class="btn-admin-warning btn-sm">
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
                    <?php foreach([10,25,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">per page</span>
            </form>
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addExamAllModal">
                <i class="fas fa-layer-group"></i>Create for All Classes
            </button>
            <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                <i class="fas fa-plus"></i>Create Single
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr style="background:#0F3A1A;">
                    <th style="background:#0F3A1A;color:white;">#</th>
                    <th style="background:#0F3A1A;color:white;">Exam Name</th>
                    <th style="background:#0F3A1A;color:white;">Type</th>
                    <th style="background:#0F3A1A;color:white;">Class</th>
                    <th style="background:#0F3A1A;color:white;">Year</th>
                    <th style="background:#0F3A1A;color:white;">Results</th>
                    <th style="background:#0F3A1A;color:white;">Status</th>
                    <th style="background:#0F3A1A;color:white;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($exams)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No exams found. Create one to get started.</td></tr>
                <?php else: ?>
                <?php foreach($exams as $i => $ex): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($ex['exam_name']) ?></div>
                        <?php if($ex['start_date']): ?>
                        <div style="font-size:12px;color:var(--text-muted);">
                            <?= date('M d', strtotime($ex['start_date'])) ?>
                            <?= $ex['end_date'] ? ' – '.date('M d, Y', strtotime($ex['end_date'])) : '' ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background:var(--primary);font-size:11px;">
                            <?= $examTypes[$ex['exam_type']] ?? $ex['exam_type'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($ex['class_name']) ?></td>
                    <td><?= $ex['academic_year'] ?></td>
                    <td>
                        <span class="fw-bold text-primary-custom"><?= $ex['result_count'] ?></span>
                        <span class="text-muted" style="font-size:12px;"> students</span>
                        <?php if($ex['result_count'] > 0): ?>
                        <br><a href="view_results.php?exam_id=<?= $ex['id'] ?>" class="text-primary" style="font-size:12px;">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($ex['is_published']): ?>
                        <span class="status-badge status-published">
                            <i class="fas fa-check me-1"></i>Published
                        </span>
                        <?php if($ex['published_at']): ?>
                        <div style="font-size:11px;color:var(--text-muted);"><?= date('M d, Y', strtotime($ex['published_at'])) ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="status-badge status-draft">
                            <i class="fas fa-lock me-1"></i>Unpublished
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if(!$ex['is_published'] && $ex['result_count'] > 0): ?>
                            <form method="POST" onsubmit="return confirm('Publish results for <?= htmlspecialchars($ex['exam_name']) ?>? Students will be able to view their results.')">
                                <input type="hidden" name="action" value="publish">
                                <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
                                <input type="hidden" name="class_id" value="<?= $ex['class_id'] ?>">
                                <button type="submit" class="btn-admin-success btn-sm">
                                    <i class="fas fa-globe"></i> Publish
                                </button>
                            </form>
                            <?php elseif($ex['is_published']): ?>
                            <form method="POST" onsubmit="return confirm('Unpublish? Students will no longer see these results.')">
                                <input type="hidden" name="action" value="unpublish">
                                <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
                                <input type="hidden" name="class_id" value="<?= $ex['class_id'] ?>">
                                <button type="submit" class="btn-admin-warning btn-sm">
                                    <i class="fas fa-eye-slash"></i> Unpublish
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">Waiting for marks...</span>
                            <?php endif; ?>

                            <form method="POST" onsubmit="return confirm('Delete this exam and all its results?')">
                                <input type="hidden" name="action" value="delete_exam">
                                <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
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
            <?php if($totalCount===0): ?>No exams found.
            <?php else: ?>
                Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong>
                of <strong><?= $totalCount ?></strong> exam<?= $totalCount!=1?'s':'' ?>
                <?= $search ? ' &mdash; <em>'.htmlspecialchars($search).'</em>' : '' ?>
            <?php endif; ?>
        </div>
        <?php if($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0" style="gap:3px;">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= $rUrl(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left" style="font-size:10px;"></i></a>
            </li>
            <?php
            $ps=max(1,$page-2); $pe=min($totalPages,$page+2);
            if($ps>1): ?>
            <li class="page-item"><a class="page-link" href="<?= $rUrl(['page'=>1]) ?>">1</a></li>
            <?php if($ps>2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for($p=$ps;$p<=$pe;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="<?= $rUrl(['page'=>$p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if($pe<$totalPages):
                if($pe<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $rUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                <a class="page-link" href="<?= $rUrl(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right" style="font-size:10px;"></i></a>
            </li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Add Exam Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Exam</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="add_exam">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                            <input type="text" name="exam_name" class="form-control" required
                                   placeholder="e.g. 1st Terminal Exam 2081">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Exam Type <span class="text-danger">*</span></label>
                            <select name="exam_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach($examTypes as $val => $lbl): ?>
                                <option value="<?= $val ?>"><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <input type="text" name="academic_year" class="form-control"
                                   value="<?= $currentYear ?>" required maxlength="10">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php foreach($classes as $cls): ?>
                                <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save me-1"></i>Create Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Exam for All Classes Modal -->
<div class="modal fade" id="addExamAllModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:white;">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Create Exam for Multiple Classes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="add_exam_all_classes">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                            <input type="text" name="exam_name" class="form-control" required
                                   placeholder="e.g. 1st Terminal Exam 2083">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Exam Type <span class="text-danger">*</span></label>
                            <select name="exam_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach($examTypes as $val => $lbl): ?>
                                <option value="<?= $val ?>"><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <input type="text" name="academic_year" class="form-control"
                                   value="<?= $currentYear ?>" required maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>

                        <!-- Class Selection -->
                        <div class="col-12">
                            <label class="form-label fw-bold">
                                Select Classes <span class="text-danger">*</span>
                                <span class="text-muted fw-normal" style="font-size:12px;"> — tick the classes to create exam for</span>
                            </label>
                            <div class="mb-2 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAllClasses(true)">
                                    <i class="fas fa-check-square me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllClasses(false)">
                                    <i class="fas fa-square me-1"></i>Deselect All
                                </button>
                            </div>
                            <div class="row g-2" id="classCheckboxes">
                                <?php foreach($classes as $cls): ?>
                                <div class="col-6 col-md-4">
                                    <label class="d-flex align-items-center gap-2 p-2 rounded-2 border"
                                           style="cursor:pointer;background:#fafafa;font-size:13px;"
                                           onmouseover="this.style.background='#edf7f0'"
                                           onmouseout="this.style.background='#fafafa'">
                                        <input type="checkbox" name="selected_classes[]"
                                               value="<?= $cls['id'] ?>"
                                               class="class-checkbox form-check-input mt-0" checked>
                                        <span><?= htmlspecialchars($cls['class_name']) ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="mt-3 p-3 rounded-2" style="background:#f0f7f0;font-size:13px;">
                        <i class="fas fa-info-circle me-1 text-success"></i>
                        This will create <strong id="selectedClassCount"><?= count($classes) ?></strong>
                        exam record<?= count($classes) != 1 ? 's' : '' ?> — one for each selected class.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-primary">
                        <i class="fas fa-layer-group me-1"></i>Create Exams
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAllClasses(check) {
    document.querySelectorAll('.class-checkbox').forEach(cb => cb.checked = check);
    updateClassCount();
}
function updateClassCount() {
    const count = document.querySelectorAll('.class-checkbox:checked').length;
    const el = document.getElementById('selectedClassCount');
    if (el) el.textContent = count;
}
document.querySelectorAll('.class-checkbox').forEach(cb => {
    cb.addEventListener('change', updateClassCount);
});
</script>

<?php require_once 'includes/layout_bottom.php'; ?>
