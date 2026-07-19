<?php
$pageTitle = 'Our Teachers';
require_once 'includes/header.php';

$teachers = $conn->query("
    SELECT u.id, u.full_name, u.photo, u.phone,
           td.qualification, td.specialization, td.experience_years,
           td.bio, td.joined_date, td.facebook,
           GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.id SEPARATOR ', ') AS subjects
    FROM users u
    LEFT JOIN teacher_details td ON u.id = td.user_id
    LEFT JOIN teacher_classes tc ON u.id = tc.teacher_id
    LEFT JOIN subjects s ON tc.subject_id = s.id
    WHERE u.role = 'teacher' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

$totalTeachers = count($teachers);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-chalkboard-teacher me-2"></i>Our Teachers</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Teachers</li>
            </ol>
        </nav>
    </div>
</div>

<section class="teachers-page-section">
    <div class="container">

        <div class="section-title" data-animate>
            <h2>Meet Our Teaching Staff</h2>
            <p>Our dedicated team of <?= $totalTeachers ?> qualified educators committed to student success</p>
        </div>

        <?php if (empty($teachers)): ?>
        <div class="text-center py-5">
            <i class="fas fa-user-slash fa-4x text-muted mb-3 d-block"></i>
            <h5 class="text-muted">No teachers found.</h5>
        </div>
        <?php else: ?>

        <!-- Search + filter bar -->
        <div class="tp-toolbar" data-animate>
            <div class="tp-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="teacherSearch" placeholder="Search by name or subject…" autocomplete="off">
            </div>
            <div class="tp-count" id="teacherCount"><?= $totalTeachers ?> teachers</div>
        </div>

        <!-- Grid -->
        <div class="tp-grid" id="teacherGrid">
            <?php foreach($teachers as $t):
                $photo = !empty($t['photo']) && $t['photo'] !== 'default.png'
                    ? 'uploads/teachers/' . htmlspecialchars($t['photo'])
                    : null;
                $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($t['full_name'])
                           . '&background=1b6b35&color=fff&size=160&bold=true';
                $spec  = htmlspecialchars($t['specialization'] ?? 'Teacher');
                $qual  = htmlspecialchars($t['qualification'] ?? '');
                $exp   = (int)($t['experience_years'] ?? 0);
                $subj  = $t['subjects'] ?? '';
                $bio   = $t['bio'] ? htmlspecialchars(mb_strimwidth($t['bio'], 0, 100, '…')) : '';
            ?>
            <div class="tp-card"
                 data-name="<?= strtolower($t['full_name']) ?>"
                 data-subject="<?= strtolower($subj) ?>"
                 data-spec="<?= strtolower($t['specialization'] ?? '') ?>">

                <!-- Horizontal: photo + info side by side -->
                <div class="tp-card-body">
                    <img class="tp-photo"
                         src="<?= $photo ?? $avatarUrl ?>"
                         alt="<?= htmlspecialchars($t['full_name']) ?>"
                         onerror="this.src='<?= $avatarUrl ?>'">
                    <div class="tp-body">
                        <div class="tp-name"><?= htmlspecialchars($t['full_name']) ?></div>
                        <div class="tp-spec"><?= $spec ?></div>
                        <?php if($qual): ?>
                        <div class="tp-qual"><i class="fas fa-graduation-cap"></i><?= $qual ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="tp-divider">

                <!-- Details -->
                <div class="tp-details">
                    <?php if($subj): ?>
                    <div class="tp-detail-row">
                        <i class="fas fa-book"></i>
                        <strong>Subjects:</strong> <?= htmlspecialchars($subj) ?>
                    </div>
                    <?php endif; ?>
                    <?php if($exp > 0): ?>
                    <div class="tp-detail-row">
                        <i class="fas fa-briefcase"></i>
                        <strong>Experience:</strong> <?= $exp ?> years
                    </div>
                    <?php endif; ?>
                    <?php if($t['joined_date']): ?>
                    <div class="tp-detail-row">
                        <i class="fas fa-calendar"></i>
                        <strong>Joined:</strong> <?= date('M Y', strtotime($t['joined_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if($bio): ?>
                <p class="tp-bio"><?= $bio ?></p>
                <?php endif; ?>

                <?php if($t['facebook'] || $t['phone']): ?>
                <div class="tp-footer">
                    <?php if($t['facebook']): ?>
                    <a href="<?= htmlspecialchars($t['facebook']) ?>" target="_blank" class="tp-social fb" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if($t['phone']): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $t['phone']) ?>" target="_blank" class="tp-social wa" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty search state -->
        <div class="tp-empty" id="teacherEmpty" style="display:none;">
            <i class="fas fa-search"></i>
            <p>No teachers found matching your search.</p>
        </div>

        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var input   = document.getElementById('teacherSearch');
    var cards   = document.querySelectorAll('.tp-card');
    var counter = document.getElementById('teacherCount');
    var empty   = document.getElementById('teacherEmpty');
    var total   = cards.length;

    if (!input) return;

    input.addEventListener('keyup', function () {
        var q = this.value.trim().toLowerCase();
        var visible = 0;

        cards.forEach(function (card) {
            var match = !q
                || card.dataset.name.includes(q)
                || card.dataset.subject.includes(q)
                || card.dataset.spec.includes(q);

            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        counter.textContent = visible + (q ? ' results' : ' teachers');
        empty.style.display = visible === 0 ? 'flex' : 'none';
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
