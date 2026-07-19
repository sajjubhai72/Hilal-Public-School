<?php
$pageTitle = 'Gallery';
require_once 'includes/header.php';

$categories   = $conn->query("SELECT DISTINCT category FROM gallery WHERE is_active=1 ORDER BY category")->fetch_all(MYSQLI_ASSOC);
$galleryItems = $conn->query("SELECT * FROM gallery WHERE is_active=1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-images me-2"></i>Photo Gallery</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Gallery</li>
            </ol>
        </nav>
    </div>
</div>

<section class="gal-section">
    <div class="container">

        <!-- Toolbar -->
        <div class="gal-toolbar" data-animate>

            <!-- Search -->
            <div class="gal-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="galSearch" placeholder="Search photos…" autocomplete="off">
            </div>

            <!-- Category filter pills -->
            <div class="gal-filter-bar">
                <button class="gal-pill active" data-filter="all">
                    All <span class="gal-pill-count"><?= count($galleryItems) ?></span>
                </button>
                <?php foreach($categories as $cat):
                    $cnt = count(array_filter($galleryItems, fn($g) => $g['category'] === $cat['category']));
                ?>
                <button class="gal-pill" data-filter="<?= htmlspecialchars($cat['category']) ?>">
                    <?= ucfirst(htmlspecialchars($cat['category'])) ?>
                    <span class="gal-pill-count"><?= $cnt ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Sort + per page -->
            <div class="gal-toolbar-right">
                <div class="gal-sort-wrap">
                    <i class="fas fa-sort-amount-down"></i>
                    <select id="galSort">
                        <option value="desc">Newest First</option>
                        <option value="asc">Oldest First</option>
                    </select>
                </div>
                <div class="gal-perpage-wrap">
                    <select id="galPerPage">
                        <option value="12">12</option>
                        <option value="24">24</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                    <span>per page</span>
                </div>
            </div>
        </div>

        <!-- Count bar -->
        <div class="gal-count-bar" data-animate>
            <span id="galCountText"></span>
        </div>

        <!-- Masonry Grid — JS injects visible items here -->
        <?php if(empty($galleryItems)): ?>
        <div class="gal-empty">
            <i class="fas fa-images"></i>
            <p>No photos available yet.</p>
        </div>
        <?php else: ?>

        <!-- Hidden data store — all items, never shown directly -->
        <div id="galDataStore" style="display:none;">
            <?php foreach($galleryItems as $i => $item): ?>
            <div class="gal-data-item"
                 data-idx="<?= $i ?>"
                 data-category="<?= htmlspecialchars($item['category']) ?>"
                 data-title="<?= strtolower(htmlspecialchars($item['title'])) ?>"
                 data-display-title="<?= htmlspecialchars($item['title']) ?>"
                 data-img="uploads/gallery/<?= htmlspecialchars($item['image']) ?>"
                 data-cat="<?= ucfirst(htmlspecialchars($item['category'])) ?>"
                 data-date="<?= date('M Y', strtotime($item['created_at'])) ?>"
                 data-ts="<?= strtotime($item['created_at']) ?>">
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Masonry render target -->
        <div class="gal-masonry" id="galGrid"></div>

        <!-- Empty search state -->
        <div class="gal-empty" id="galEmpty" style="display:none;">
            <i class="fas fa-search"></i>
            <p>No photos found.</p>
        </div>

        <!-- Pagination -->
        <div class="gal-pag-bar" id="galPagBar">
            <div class="gal-pag-info" id="galPagInfo"></div>
            <div class="gal-pag-btns" id="galPagBtns"></div>
        </div>

        <?php endif; ?>
    </div>
</section>

<!-- Lightbox Modal -->
<div class="modal fade" id="galModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered gal-modal-dialog">
        <div class="modal-content gal-modal-content">
            <button class="gal-modal-close" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            <div class="gal-modal-img-wrap">
                <img id="galModalImg" src="" alt="">
                <button class="gal-nav-btn gal-prev" id="galPrev"><i class="fas fa-chevron-left"></i></button>
                <button class="gal-nav-btn gal-next" id="galNext"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="gal-modal-footer">
                <div class="gal-modal-info">
                    <div class="gal-modal-title" id="galModalTitle"></div>
                    <div class="gal-modal-meta"  id="galModalMeta"></div>
                </div>
                <div class="gal-modal-actions">
                    <a id="galDownloadBtn" href="#" download class="gal-dl-btn">
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <span class="gal-counter" id="galCounter"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // ── Data ───────────────────────────────────────────
    var rawItems = Array.from(document.querySelectorAll('.gal-data-item'));

    var currentFilter = 'all';
    var currentPage   = 1;
    var currentSort   = 'desc';
    var currentPerPage= 50;
    var currentPageItems = []; // items shown on current page (for lightbox)

    var grid      = document.getElementById('galGrid');
    var countEl   = document.getElementById('galCountText');
    var emptyEl   = document.getElementById('galEmpty');
    var pagInfo   = document.getElementById('galPagInfo');
    var pagBtns   = document.getElementById('galPagBtns');
    var searchInp = document.getElementById('galSearch');
    var sortSel   = document.getElementById('galSort');
    var perPageSel= document.getElementById('galPerPage');

    // ── Filter + Search + Sort ─────────────────────────
    function getFiltered() {
        var q = searchInp.value.trim().toLowerCase();
        return rawItems.filter(function(d) {
            var catMatch = currentFilter === 'all' || d.dataset.category === currentFilter;
            var qMatch   = !q || d.dataset.title.includes(q) || d.dataset.cat.toLowerCase().includes(q);
            return catMatch && qMatch;
        });
    }

    function getSorted(arr) {
        return arr.slice().sort(function(a, b) {
            return currentSort === 'asc'
                ? parseInt(a.dataset.ts) - parseInt(b.dataset.ts)
                : parseInt(b.dataset.ts) - parseInt(a.dataset.ts);
        });
    }

    // ── Render ─────────────────────────────────────────
    function render() {
        var filtered = getSorted(getFiltered());
        var total    = filtered.length;
        var pages    = Math.max(1, Math.ceil(total / currentPerPage));
        if (currentPage > pages) currentPage = 1;

        var start = (currentPage - 1) * currentPerPage;
        var end   = Math.min(start + currentPerPage, total);
        currentPageItems = filtered.slice(start, end);

        // Build masonry HTML
        var html = '';
        currentPageItems.forEach(function(d, i) {
            html += '<div class="gal-item"'
                + ' data-page-idx="' + i + '"'
                + ' data-img="'   + d.dataset.img + '"'
                + ' data-display-title="' + d.dataset.displayTitle + '"'
                + ' data-cat="'   + d.dataset.cat + '"'
                + ' data-date="'  + d.dataset.date + '"'
                + ' onclick="openGalModal(' + i + ')">'
                + '<img src="' + d.dataset.img + '" alt="' + d.dataset.displayTitle + '"'
                + ' onerror="this.src=\'https://via.placeholder.com/400x300/1a5c2a/ffffff?text=Photo\'">'
                + '<div class="gal-overlay"><div class="gal-overlay-inner">'
                + '<i class="fas fa-expand-alt"></i>'
                + '<span>' + d.dataset.displayTitle + '</span>'
                + '</div></div>'
                + '<div class="gal-cat-badge">' + d.dataset.cat + '</div>'
                + '</div>';
        });
        grid.innerHTML = html;

        // Count
        countEl.textContent = total === 0 ? '0 photos'
            : 'Showing ' + (start+1) + '–' + end + ' of ' + total + ' photos';

        // Empty
        emptyEl.style.display = total === 0 ? 'flex' : 'none';
        grid.style.display    = total === 0 ? 'none' : '';
        document.getElementById('galPagBar').style.display = total === 0 ? 'none' : '';

        buildPagination(pages, total);
    }

    // ── Pagination ─────────────────────────────────────
    function buildPagination(pages, total) {
        if (pages <= 1) { pagInfo.textContent = ''; pagBtns.innerHTML = ''; return; }
        pagInfo.textContent = 'Page ' + currentPage + ' of ' + pages;

        var html = '';
        html += pgBtn('prev', '<i class="fas fa-chevron-left"></i>', currentPage === 1);
        var s = Math.max(1, currentPage - 2), e = Math.min(pages, currentPage + 2);
        if (s > 1) html += pgBtn('e1', '…', true, 'pg-ellipsis');
        for (var p = s; p <= e; p++)
            html += pgBtn(p, p, false, p === currentPage ? 'active' : '');
        if (e < pages) html += pgBtn('e2', '…', true, 'pg-ellipsis');
        html += pgBtn('next', '<i class="fas fa-chevron-right"></i>', currentPage === pages);
        pagBtns.innerHTML = html;

        pagBtns.querySelectorAll('.pg-btn:not([disabled])').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var p = this.dataset.p;
                if (p === 'prev') currentPage--;
                else if (p === 'next') currentPage++;
                else currentPage = parseInt(p);
                render();
                grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    function pgBtn(p, label, disabled, extra) {
        return '<button class="pg-btn ' + (extra||'') + '" data-p="' + p + '"'
            + (disabled ? ' disabled' : '') + '>' + label + '</button>';
    }

    // ── Events ─────────────────────────────────────────
    document.querySelectorAll('.gal-pill').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.gal-pill').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            currentPage = 1; render();
        });
    });

    searchInp.addEventListener('keyup',   function() { currentPage = 1; render(); });
    sortSel.addEventListener('change',    function() { currentSort = this.value;          currentPage = 1; render(); });
    perPageSel.addEventListener('change', function() { currentPerPage = parseInt(this.value); currentPage = 1; render(); });

    render(); // initial render

    // ── Lightbox ───────────────────────────────────────
    var currentLbIdx = 0;

    window.openGalModal = function(idx) {
        currentLbIdx = idx;
        showLbItem(idx);
        new bootstrap.Modal(document.getElementById('galModal')).show();
    };

    function showLbItem(idx) {
        var items = document.querySelectorAll('.gal-item');
        var item  = items[idx];
        if (!item) return;
        document.getElementById('galModalImg').src = item.dataset.img;
        document.getElementById('galModalImg').alt = item.dataset.displayTitle;
        document.getElementById('galModalTitle').textContent = item.dataset.displayTitle;
        document.getElementById('galModalMeta').textContent  = item.dataset.cat + '  •  ' + item.dataset.date;
        document.getElementById('galDownloadBtn').href = item.dataset.img;
        document.getElementById('galDownloadBtn').setAttribute('download', item.dataset.img.split('/').pop());
        document.getElementById('galCounter').textContent = (idx + 1) + ' / ' + items.length;
        document.getElementById('galPrev').disabled = idx === 0;
        document.getElementById('galNext').disabled = idx === items.length - 1;
        currentLbIdx = idx;
    }

    document.getElementById('galPrev').addEventListener('click', function() {
        if (currentLbIdx > 0) showLbItem(currentLbIdx - 1);
    });
    document.getElementById('galNext').addEventListener('click', function() {
        var items = document.querySelectorAll('.gal-item');
        if (currentLbIdx < items.length - 1) showLbItem(currentLbIdx + 1);
    });

    document.addEventListener('keydown', function(e) {
        var modal = document.getElementById('galModal');
        if (!modal.classList.contains('show')) return;
        if (e.key === 'ArrowLeft')  document.getElementById('galPrev').click();
        if (e.key === 'ArrowRight') document.getElementById('galNext').click();
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
