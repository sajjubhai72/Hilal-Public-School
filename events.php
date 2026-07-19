<?php
$pageTitle = 'Events';
require_once 'includes/header.php';

$upcomingEvents = $conn->query("
    SELECT * FROM events
    WHERE is_active=1 AND event_date >= CURDATE()
    ORDER BY event_date ASC
")->fetch_all(MYSQLI_ASSOC);

$pastEvents = $conn->query("
    SELECT * FROM events
    WHERE is_active=1 AND event_date < CURDATE()
    ORDER BY event_date DESC
")->fetch_all(MYSQLI_ASSOC);

$typeBarClass   = ['academic'=>'ev-bar-academic',  'cultural'=>'ev-bar-cultural',  'sports'=>'ev-bar-sports',  'holiday'=>'ev-bar-holiday',  'other'=>'ev-bar-other'];
$typeBoxClass   = ['academic'=>'ev-box-academic',  'cultural'=>'ev-box-cultural',  'sports'=>'ev-box-sports',  'holiday'=>'ev-box-holiday',  'other'=>'ev-box-other'];
$typeBadgeClass = ['academic'=>'ev-badge-academic','cultural'=>'ev-badge-cultural','sports'=>'ev-badge-sports','holiday'=>'ev-badge-holiday','other'=>'ev-badge-other'];
$typeHeaderBg   = [
    'academic' => 'linear-gradient(135deg,#0d3d1c,#1b6b35)',
    'cultural' => 'linear-gradient(135deg,#7a1a12,#b5281f)',
    'sports'   => 'linear-gradient(135deg,#a85000,#e67e22)',
    'holiday'  => 'linear-gradient(135deg,#5b2080,#8e44ad)',
    'other'    => 'linear-gradient(135deg,#1a1a2e,#333)',
];

function evBsDate($dateStr) {
    if (!$dateStr) return '';
    $bs = adToBS($dateStr);
    return $bs['day'] . ' ' . getNpMonthName($bs['month']) . ' ' . $bs['year'] . ' BS';
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-calendar-alt me-2"></i>Events Calendar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Events</li>
            </ol>
        </nav>
    </div>
</div>

<section class="ev-page-section">
    <div class="container">

        <!-- ══ UPCOMING EVENTS ══ -->
        <div class="section-title" data-animate>
            <h2>Upcoming Events</h2>
            <p>Events you should not miss — mark your calendar</p>
        </div>

        <!-- Upcoming toolbar -->
        <div class="ev-toolbar" data-animate>
            <div class="ev-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="upSearch" placeholder="Search upcoming…" autocomplete="off">
            </div>
            <div class="ev-filters">
                <button class="ev-filter-pill active" data-grid="up" data-filter="all">All</button>
                <button class="ev-filter-pill" data-grid="up" data-filter="academic">Academic</button>
                <button class="ev-filter-pill" data-grid="up" data-filter="cultural">Cultural</button>
                <button class="ev-filter-pill" data-grid="up" data-filter="sports">Sports</button>
                <button class="ev-filter-pill" data-grid="up" data-filter="holiday">Holiday</button>
            </div>
            <div class="ev-toolbar-right">
                <div class="ev-perpage-wrap">
                    <select id="upPerPage">
                        <option value="8">8</option>
                        <option value="12" selected>12</option>
                        <option value="0">All</option>
                    </select>
                    <span>per page</span>
                </div>
            </div>
        </div>
        <div class="ev-count-bar"><span id="upCountText"></span></div>

        <!-- Upcoming grid -->
        <?php if(empty($upcomingEvents)): ?>
        <div class="ev-empty-box">
            <i class="fas fa-calendar-times"></i>
            <p>No upcoming events at the moment.</p>
        </div>
        <?php else: ?>
        <div class="ev-grid" id="upGrid">
            <?php foreach($upcomingEvents as $ev):
                $type   = $ev['event_type'] ?? 'other';
                $evBS   = adToBS($ev['event_date']);
                $hasImg = !empty($ev['image']);
                $imgP   = 'uploads/events/' . htmlspecialchars($ev['image'] ?? '');
                $bsStr  = evBsDate($ev['event_date']);
            ?>
            <div class="ev-card-wrap"
                 data-title="<?= strtolower(htmlspecialchars($ev['title'])) ?>"
                 data-type="<?= $type ?>"
                 data-date="<?= $ev['event_date'] ?>">
                <div class="ev-card <?= $hasImg ? 'ev-has-file' : '' ?>"
                     <?php if($hasImg): ?>onclick="openEvModal(
                         '<?= addslashes(htmlspecialchars($ev['title'])) ?>',
                         '<?= $imgP ?>',
                         '<?= addslashes($typeHeaderBg[$type] ?? $typeHeaderBg['other']) ?>',
                         '<?= addslashes(htmlspecialchars($ev['description'] ?? '')) ?>',
                         '<?= addslashes(htmlspecialchars($ev['venue'] ?? '')) ?>',
                         '<?= addslashes(htmlspecialchars($ev['event_time'] ?? '')) ?>',
                         '<?= addslashes($bsStr) ?>')"
                     <?php endif; ?>>
                    <?php if($hasImg): ?>
                    <div class="ev-img-wrap">
                        <img src="<?= $imgP ?>" alt="<?= htmlspecialchars($ev['title']) ?>" onerror="this.parentElement.style.display='none'">
                        <div class="ev-img-overlay"><i class="fas fa-expand-alt"></i> View</div>
                    </div>
                    <?php endif; ?>
                    <div class="ev-color-bar <?= $typeBarClass[$type] ?? 'ev-bar-other' ?>"></div>
                    <div class="ev-body">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="ev-date-box <?= $typeBoxClass[$type] ?? 'ev-box-other' ?>">
                                <div class="ev-date-day"><?= $evBS['day'] ?></div>
                                <div class="ev-date-month"><?= getNpMonthName($evBS['month']) ?></div>
                                <div class="ev-date-year"><?= $evBS['year'] ?></div>
                            </div>
                            <div class="flex-grow-1">
                                <span class="ev-type-badge <?= $typeBadgeClass[$type] ?? 'ev-badge-other' ?>"><?= ucfirst($type) ?></span>
                                <h5 class="ev-title"><?= htmlspecialchars($ev['title']) ?></h5>
                            </div>
                        </div>
                        <?php if($ev['description']): ?>
                        <p class="ev-desc"><?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 100, '…')) ?></p>
                        <?php endif; ?>
                        <div class="ev-meta">
                            <?php if($ev['venue']): ?><span><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($ev['venue']) ?></span><?php endif; ?>
                            <?php if($ev['event_time']): ?><span><i class="fas fa-clock me-1"></i><?= htmlspecialchars($ev['event_time']) ?></span><?php endif; ?>
                        </div>
                        <?php if($hasImg): ?><div class="ev-click-hint"><i class="fas fa-hand-pointer me-1"></i>Click to view & download</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="upEmptyState" class="ev-empty" style="display:none;"><i class="fas fa-search"></i><p>No events found.</p></div>
        <div class="ev-pagination-bar" id="upPagBar">
            <div class="ev-pag-info" id="upPagInfo"></div>
            <div class="ev-pag-btns" id="upPagBtns"></div>
        </div>
        <?php endif; ?>

        <!-- ══ PAST EVENTS ══ -->
        <?php if(!empty($pastEvents)): ?>
        <div class="ev-past-section">
            <div class="ev-past-header">
                <div class="ev-past-header-left">
                    <div class="ev-past-icon"><i class="fas fa-history"></i></div>
                    <div>
                        <h3>Past Events</h3>
                        <p><?= count($pastEvents) ?> events in the archive</p>
                    </div>
                </div>
            </div>

            <!-- Past toolbar -->
            <div class="ev-toolbar ev-past-toolbar">
                <div class="ev-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="pastSearch" placeholder="Search past events…" autocomplete="off">
                </div>
                <div class="ev-filters">
                    <button class="ev-filter-pill active" data-grid="past" data-filter="all">All</button>
                    <button class="ev-filter-pill" data-grid="past" data-filter="academic">Academic</button>
                    <button class="ev-filter-pill" data-grid="past" data-filter="cultural">Cultural</button>
                    <button class="ev-filter-pill" data-grid="past" data-filter="sports">Sports</button>
                    <button class="ev-filter-pill" data-grid="past" data-filter="holiday">Holiday</button>
                </div>
                <div class="ev-toolbar-right">
                    <div class="ev-perpage-wrap">
                        <select id="pastPerPage">
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="0">All</option>
                        </select>
                        <span>per page</span>
                    </div>
                </div>
            </div>
            <div class="ev-count-bar"><span id="pastCountText"></span></div>

            <!-- Past list -->
            <div class="ev-past-list" id="pastList">
                <?php foreach($pastEvents as $ev):
                    $type   = $ev['event_type'] ?? 'other';
                    $evBS   = adToBS($ev['event_date']);
                    $hasImg = !empty($ev['image']);
                    $imgP   = 'uploads/events/' . htmlspecialchars($ev['image'] ?? '');
                    $bsStr  = evBsDate($ev['event_date']);
                ?>
                <div class="ev-past-item"
                     data-title="<?= strtolower(htmlspecialchars($ev['title'])) ?>"
                     data-type="<?= $type ?>"
                     data-date="<?= $ev['event_date'] ?>">
                    <div class="ev-past-date-box <?= $typeBoxClass[$type] ?? 'ev-box-other' ?>">
                        <div class="ev-date-day"><?= $evBS['day'] ?></div>
                        <div class="ev-date-month"><?= getNpMonthName($evBS['month']) ?></div>
                        <div class="ev-date-year"><?= $evBS['year'] ?></div>
                    </div>
                    <div class="ev-past-item-info">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="ev-type-badge <?= $typeBadgeClass[$type] ?? 'ev-badge-other' ?>" style="font-size:10px;"><?= ucfirst($type) ?></span>
                            <h6 class="ev-past-title"><?= htmlspecialchars($ev['title']) ?></h6>
                        </div>
                        <div class="ev-meta mt-1">
                            <?php if($ev['venue']): ?><span><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($ev['venue']) ?></span><?php endif; ?>
                            <?php if($ev['event_time']): ?><span><i class="fas fa-clock me-1"></i><?= htmlspecialchars($ev['event_time']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if($hasImg): ?>
                    <button class="ev-past-view-btn" onclick="openEvModal(
                        '<?= addslashes(htmlspecialchars($ev['title'])) ?>',
                        '<?= $imgP ?>',
                        '<?= addslashes($typeHeaderBg[$type] ?? $typeHeaderBg['other']) ?>',
                        '<?= addslashes(htmlspecialchars($ev['description'] ?? '')) ?>',
                        '<?= addslashes(htmlspecialchars($ev['venue'] ?? '')) ?>',
                        '<?= addslashes(htmlspecialchars($ev['event_time'] ?? '')) ?>',
                        '<?= addslashes($bsStr) ?>')">
                        <i class="fas fa-eye me-1"></i>View
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="pastEmptyState" class="ev-empty" style="display:none;"><i class="fas fa-search"></i><p>No past events found.</p></div>
            <div class="ev-pagination-bar" id="pastPagBar">
                <div class="ev-pag-info" id="pastPagInfo"></div>
                <div class="ev-pag-btns" id="pastPagBtns"></div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content ev-modal-content">
            <div class="ev-modal-header" id="evModalHeader">
                <div><div class="ev-modal-date" id="evModalDate"></div><h4 class="ev-modal-title" id="evModalTitle"></h4></div>
                <button type="button" class="ev-modal-close" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="ev-modal-img-wrap"><img id="evModalImg" src="" alt=""></div>
            <div class="ev-modal-body" id="evModalBody"></div>
            <div class="ev-modal-footer">
                <a id="evDownloadBtn" href="#" download class="ev-download-btn"><i class="fas fa-download me-2"></i>Download Image</a>
                <button type="button" class="ev-close-btn" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openEvModal(title, imgPath, headerBg, desc, venue, time, bsDate) {
    document.getElementById('evModalHeader').style.background = headerBg;
    document.getElementById('evModalTitle').textContent = title;
    document.getElementById('evModalDate').textContent  = bsDate;
    var img = document.getElementById('evModalImg');
    img.src = imgPath; img.alt = title;
    var html = '';
    if (desc)  html += '<p class="ev-modal-desc">' + desc + '</p>';
    if (venue || time) {
        html += '<div class="ev-modal-meta">';
        if (venue) html += '<span><i class="fas fa-map-marker-alt me-1"></i>' + venue + '</span>';
        if (time)  html += '<span><i class="fas fa-clock me-1"></i>' + time + '</span>';
        html += '</div>';
    }
    document.getElementById('evModalBody').innerHTML = html;
    var dl = document.getElementById('evDownloadBtn');
    dl.href = imgPath;
    dl.setAttribute('download', imgPath.split('/').pop());
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}

// Generic paginator for both sections
function makePager(opts) {
    // opts: { wrapsSelector, searchId, filterGrid, perPageId,
    //         countId, emptyId, pagInfoId, pagBtnsId, itemMode }
    // itemMode: 'card' = .ev-card-wrap, 'list' = .ev-past-item
    var wraps      = Array.from(document.querySelectorAll(opts.wrapsSelector));
    var searchInp  = document.getElementById(opts.searchId);
    var perPageSel = document.getElementById(opts.perPageId);
    var countEl    = document.getElementById(opts.countId);
    var emptyEl    = document.getElementById(opts.emptyId);
    var pagInfo    = document.getElementById(opts.pagInfoId);
    var pagBtns    = document.getElementById(opts.pagBtnsId);
    var currentFilter = 'all';
    var currentPage   = 1;

    if (!wraps.length) return;

    // Filter pills for this grid
    document.querySelectorAll('.ev-filter-pill[data-grid="' + opts.filterGrid + '"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.ev-filter-pill[data-grid="' + opts.filterGrid + '"]')
                .forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            currentPage = 1; render();
        });
    });

    if (searchInp)  searchInp.addEventListener('keyup',  function() { currentPage=1; render(); });
    if (perPageSel) perPageSel.addEventListener('change', function() { currentPage=1; render(); });

    function getFiltered() {
        var q = searchInp ? searchInp.value.trim().toLowerCase() : '';
        return wraps.filter(function(w) {
            return (!q || w.dataset.title.includes(q))
                && (currentFilter === 'all' || w.dataset.type === currentFilter);
        });
    }

    function render() {
        var filtered = getFiltered();
        var perPage  = perPageSel ? (parseInt(perPageSel.value) || 0) : 0;
        var total    = filtered.length;
        var pages    = perPage > 0 ? Math.ceil(total / perPage) : 1;
        if (currentPage > pages) currentPage = 1;
        var start    = perPage > 0 ? (currentPage - 1) * perPage : 0;
        var end      = perPage > 0 ? start + perPage : total;

        wraps.forEach(function(w) { w.style.display = 'none'; });
        filtered.slice(start, end).forEach(function(w) { w.style.display = ''; });

        if (emptyEl) emptyEl.style.display = total === 0 ? 'flex' : 'none';
        if (countEl) countEl.textContent = total === 0
            ? 'No results'
            : 'Showing ' + (start+1) + '–' + Math.min(end,total) + ' of ' + total + ' events';

        buildPag(pages);
    }

    function buildPag(pages) {
        if (!pagBtns) return;
        if (pages <= 1) { if(pagInfo) pagInfo.textContent=''; pagBtns.innerHTML=''; return; }
        if(pagInfo) pagInfo.textContent = 'Page ' + currentPage + ' of ' + pages;
        var html = '';
        html += btn('prev', '<i class="fas fa-chevron-left"></i>', currentPage===1);
        var s = Math.max(1, currentPage-2), e = Math.min(pages, currentPage+2);
        if (s>1) html += btn('…','…',true,'pg-ellipsis');
        for(var p=s;p<=e;p++) html += btn(p, p, false, p===currentPage?'active':'');
        if (e<pages) html += btn('…','…',true,'pg-ellipsis');
        html += btn('next', '<i class="fas fa-chevron-right"></i>', currentPage===pages);
        pagBtns.innerHTML = html;
        pagBtns.querySelectorAll('.pg-btn:not([disabled])').forEach(function(b){
            b.addEventListener('click', function(){
                var p = this.dataset.p;
                if(p==='prev') currentPage--;
                else if(p==='next') currentPage++;
                else currentPage = parseInt(p);
                render();
                var el = document.getElementById(opts.pagInfoId);
                if(el) el.scrollIntoView({behavior:'smooth',block:'nearest'});
            });
        });
    }

    function btn(p, label, disabled, extra) {
        return '<button class="pg-btn ' + (extra||'') + '" data-p="' + p + '"' + (disabled?' disabled':'') + '>' + label + '</button>';
    }

    render();
}

// Init both pagers
makePager({
    wrapsSelector: '#upGrid .ev-card-wrap',
    searchId:  'upSearch',   filterGrid: 'up',
    perPageId: 'upPerPage',  countId: 'upCountText',
    emptyId:   'upEmptyState', pagInfoId: 'upPagInfo', pagBtnsId: 'upPagBtns'
});
makePager({
    wrapsSelector: '#pastList .ev-past-item',
    searchId:  'pastSearch',   filterGrid: 'past',
    perPageId: 'pastPerPage',  countId: 'pastCountText',
    emptyId:   'pastEmptyState', pagInfoId: 'pastPagInfo', pagBtnsId: 'pastPagBtns'
});
</script>

<?php require_once 'includes/footer.php'; ?>
