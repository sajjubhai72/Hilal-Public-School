<?php
$pageTitle = 'Notices';
require_once 'includes/header.php';

// Fetch all active notices — DataTables handles filter/sort/pagination client-side
$notices = $conn->query("SELECT * FROM notices WHERE is_active=1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$noticeTypes = ['general', 'exam', 'holiday', 'admission', 'event', 'urgent'];

// Icon + color map
$typeIcon = [
    'general'   => ['icon'=>'fa-bullhorn',           'color'=>'#1b6b35'],
    'exam'      => ['icon'=>'fa-file-alt',            'color'=>'#0284c7'],
    'holiday'   => ['icon'=>'fa-umbrella-beach',      'color'=>'#e8980a'],
    'admission' => ['icon'=>'fa-user-graduate',       'color'=>'#7c3aed'],
    'event'     => ['icon'=>'fa-calendar-check',      'color'=>'#16a085'],
    'urgent'    => ['icon'=>'fa-exclamation-triangle','color'=>'#b5281f'],
];
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-bullhorn me-2"></i>Notice Board</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Notices</li>
            </ol>
        </nav>
    </div>
</div>

<section class="notices-section">
    <div class="container">

        <!-- Stats bar -->
        <div class="notices-stats-bar" data-animate>
            <div class="notices-stat">
                <span class="notices-stat-num"><?= count($notices) ?></span>
                <span class="notices-stat-label">Total Notices</span>
            </div>
            <?php
            $urgentCount = count(array_filter($notices, fn($n) => $n['notice_type'] === 'urgent'));
            $examCount   = count(array_filter($notices, fn($n) => $n['notice_type'] === 'exam'));
            ?>
            <?php if($urgentCount): ?>
            <div class="notices-stat">
                <span class="notices-stat-num" style="color:#b5281f;"><?= $urgentCount ?></span>
                <span class="notices-stat-label">Urgent</span>
            </div>
            <?php endif; ?>
            <?php if($examCount): ?>
            <div class="notices-stat">
                <span class="notices-stat-num" style="color:#0284c7;"><?= $examCount ?></span>
                <span class="notices-stat-label">Exam Notices</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Type filter pills -->
        <div class="notices-filter-bar" data-animate>
            <span class="filter-label"><i class="fas fa-filter me-1"></i>Filter:</span>
            <button class="notice-pill active" data-filter="all">All</button>
            <?php foreach($noticeTypes as $type): ?>
            <button class="notice-pill" data-filter="<?= $type ?>">
                <?= ucfirst($type) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Table card -->
        <div class="notices-table-card" data-animate>

            <!-- Top toolbar: search + per-page -->
            <div class="notices-toolbar">
                <div class="notices-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="noticeSearch" class="notices-search-input" placeholder="Search notices...">
                </div>
                <div class="notices-perpage-wrap">
                    <label>Show</label>
                    <select id="noticePerPage" class="notices-perpage-select">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="-1">All</option>
                    </select>
                    <label>entries</label>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table id="noticesTable" class="notices-table w-100">
                    <thead>
                        <tr>
                            <th class="col-sn">#</th>
                            <th class="col-type">Type</th>
                            <th class="col-title">Title</th>
                            <th class="col-content">Content</th>
                            <th class="col-date">Date</th>
                            <th class="col-attach no-sort">Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($notices as $i => $n):
                            $ti = $typeIcon[$n['notice_type']] ?? $typeIcon['general'];
                        ?>
                        <tr data-type="<?= $n['notice_type'] ?>">
                            <td class="col-sn"><?= $i + 1 ?></td>
                            <td class="col-type" data-order="<?= $n['notice_type'] ?>">
                                <span class="notice-type-badge type-<?= $n['notice_type'] ?>">
                                    <i class="fas <?= $ti['icon'] ?> me-1"></i><?= ucfirst($n['notice_type']) ?>
                                </span>
                            </td>
                            <td class="col-title">
                                <div class="notice-title-cell">
                                    <?= htmlspecialchars($n['title']) ?>
                                    <?php if($n['notice_type'] === 'urgent'): ?>
                                    <span class="urgent-dot" title="Urgent"></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-content">
                                <div class="notice-content-preview" title="<?= htmlspecialchars($n['content']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($n['content'], 0, 120, '…')) ?>
                                </div>
                            </td>
                            <td class="col-date" data-order="<?= strtotime($n['created_at']) ?>">
                                <?php
                                    $nBS = adToBS(date('Y-m-d', strtotime($n['created_at'])));
                                ?>
                                <div class="notice-date-cell">
                                    <span class="nd-bs"><?= $nBS['day'] . ' ' . getNpMonthName($nBS['month']) . ' ' . $nBS['year'] ?> BS</span>
                                    <span class="nd-ad"><?= date('M d, Y', strtotime($n['created_at'])) ?></span>
                                </div>
                            </td>
                            <td class="col-attach">
                                <?php if($n['attachment']): ?>
                                <a href="uploads/notices/<?= htmlspecialchars($n['attachment']) ?>" target="_blank" class="notice-dl-btn">
                                    <i class="fas fa-download me-1"></i>Download
                                </a>
                                <?php else: ?>
                                <span class="no-attach">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bottom: info + pagination -->
            <div class="notices-footer-bar">
                <div id="noticeInfo" class="notices-info-text"></div>
                <div id="noticePagination" class="notices-pagination"></div>
            </div>

        </div><!-- /.notices-table-card -->

    </div>
</section>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function () {

    var table = $('#noticesTable').DataTable({
        responsive: false,
        pageLength: 10,
        ordering: false,               // completely disable sort — no icons at all
        order: [[4, 'desc']],          // newest first by date column
        dom: 'rt',                     // only render table+no default controls (we use custom ones)
        language: {
            emptyTable:  'No notices found.',
            zeroRecords: 'No notices match your search.',
        },
        columnDefs: [
            { orderable: false, targets: '_all' },
            { searchable: false, targets: [0, 5] }
        ],
        drawCallback: function () {
            updateFooter(this.api());
        }
    });

    // ── Custom search ──────────────────────────────────
    $('#noticeSearch').on('keyup', function () {
        table.search(this.value).draw();
    });

    // ── Per-page select ────────────────────────────────
    $('#noticePerPage').on('change', function () {
        table.page.len(parseInt(this.value)).draw();
    });

    // ── Type filter pills ──────────────────────────────
    var activeFilter = 'all';
    $('.notice-pill').on('click', function () {
        $('.notice-pill').removeClass('active');
        $(this).addClass('active');
        activeFilter = $(this).data('filter');

        if (activeFilter === 'all') {
            $.fn.dataTable.ext.search.pop();   // remove custom filter
        } else {
            $.fn.dataTable.ext.search.pop();
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                return $(table.row(dataIndex).node()).data('type') === activeFilter;
            });
        }
        table.draw();
    });

    // ── Custom footer: info text + pagination ──────────
    function updateFooter(api) {
        var info  = api.page.info();
        var start = info.start + 1;
        var end   = info.end;
        var total = info.recordsDisplay;
        var filtered = (info.recordsDisplay !== info.recordsTotal)
            ? ' (filtered from ' + info.recordsTotal + ' total)' : '';

        $('#noticeInfo').text(
            total === 0 ? 'No entries found' :
            'Showing ' + start + ' to ' + end + ' of ' + total + ' notices' + filtered
        );

        // Build pagination buttons
        var pages     = info.pages;
        var current   = info.page;
        var html      = '';

        if (pages <= 1) { $('#noticePagination').html(''); return; }

        html += '<button class="pg-btn" data-page="prev" ' + (current === 0 ? 'disabled' : '') + '>'
              + '<i class="fas fa-chevron-left"></i></button>';

        // Page number buttons (show max 5 around current)
        var start_p = Math.max(0, current - 2);
        var end_p   = Math.min(pages - 1, current + 2);

        if (start_p > 0)       html += '<button class="pg-btn pg-ellipsis" disabled>…</button>';
        for (var p = start_p; p <= end_p; p++) {
            html += '<button class="pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + (p + 1) + '</button>';
        }
        if (end_p < pages - 1) html += '<button class="pg-btn pg-ellipsis" disabled>…</button>';

        html += '<button class="pg-btn" data-page="next" ' + (current === pages - 1 ? 'disabled' : '') + '>'
              + '<i class="fas fa-chevron-right"></i></button>';

        $('#noticePagination').html(html);

        // Click handlers
        $('#noticePagination').off('click', '.pg-btn').on('click', '.pg-btn', function () {
            var p = $(this).data('page');
            if (p === 'prev')  table.page('previous').draw('page');
            else if (p === 'next') table.page('next').draw('page');
            else               table.page(parseInt(p)).draw('page');
        });
    }

    // Init footer
    updateFooter(table.api ? table : { api: function() { return table; } });
});
</script>

<?php require_once 'includes/footer.php'; ?>
