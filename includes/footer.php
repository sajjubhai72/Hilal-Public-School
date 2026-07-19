<?php
$schoolName    = getSetting($conn, 'school_name');
$schoolAddress = getSetting($conn, 'school_address');
$schoolPhone   = getSetting($conn, 'school_phone');
$schoolEmail   = getSetting($conn, 'school_email');
$facebookUrl   = getSetting($conn, 'facebook_url');
$youtubeUrl    = getSetting($conn, 'youtube_url');
$base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/', 1) - 1);
?>
<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="container">
        <div class="row g-4 g-md-5">

            <!-- School Info -->
            <div class="col-12 col-md-6 col-lg-4 footer-col">
                <div class="d-flex align-items-center gap-3 mb-3 justify-content-center justify-content-md-start">
                    <img src="<?= $base ?>assets/images/logo.png" alt="Logo"
                         style="width:52px;height:52px;object-fit:contain;flex-shrink:0;"
                         onerror="this.style.display='none'">
                    <h5 class="mb-0"><?= htmlspecialchars($schoolName) ?></h5>
                </div>
                <p>Providing quality education and shaping the future of young minds since our establishment.</p>
                <div class="footer-social mt-3">
                    <?php if($facebookUrl): ?>
                    <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if($youtubeUrl): ?>
                    <a href="<?= htmlspecialchars($youtubeUrl) ?>" target="_blank" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <?php endif; ?>
                    <?php if($schoolEmail): ?>
                    <a href="mailto:<?= htmlspecialchars($schoolEmail) ?>" aria-label="Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-6 col-md-3 col-lg-2 footer-col">
                <h5>Quick Links</h5>
                <a href="<?= $base ?>index.php"><i class="fas fa-chevron-right me-1"></i>Home</a>
                <a href="<?= $base ?>about.php"><i class="fas fa-chevron-right me-1"></i>About Us</a>
                <a href="<?= $base ?>teachers.php"><i class="fas fa-chevron-right me-1"></i>Teachers</a>
                <a href="<?= $base ?>results.php"><i class="fas fa-chevron-right me-1"></i>Results</a>
                <a href="<?= $base ?>notices.php"><i class="fas fa-chevron-right me-1"></i>Notices</a>
                <a href="<?= $base ?>gallery.php"><i class="fas fa-chevron-right me-1"></i>Gallery</a>
            </div>

            <!-- Services -->
            <div class="col-6 col-md-3 col-lg-2 footer-col">
                <h5>Services</h5>
                <a href="<?= $base ?>admissions.php"><i class="fas fa-chevron-right me-1"></i>Admissions</a>
                <a href="<?= $base ?>scholarship.php"><i class="fas fa-chevron-right me-1"></i>Scholarship</a>
                <a href="<?= $base ?>events.php"><i class="fas fa-chevron-right me-1"></i>Events</a>
                <a href="<?= $base ?>contact.php"><i class="fas fa-chevron-right me-1"></i>Contact Us</a>
                <a href="<?= $base ?>admin/login.php"><i class="fas fa-chevron-right me-1"></i>Admin Login</a>
            </div>

            <!-- Contact Info -->
            <div class="col-12 col-md-6 col-lg-4 footer-col">
                <h5>Contact Us</h5>
                <?php if($schoolAddress): ?>
                <div class="footer-contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= nl2br(htmlspecialchars($schoolAddress)) ?></span>
                </div>
                <?php endif; ?>
                <?php if($schoolPhone): ?>
                <div class="footer-contact-item">
                    <i class="fas fa-phone-alt"></i>
                    <a href="tel:<?= htmlspecialchars($schoolPhone) ?>"><?= htmlspecialchars($schoolPhone) ?></a>
                </div>
                <?php endif; ?>
                <?php if($schoolEmail): ?>
                <div class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:<?= htmlspecialchars($schoolEmail) ?>"><?= htmlspecialchars($schoolEmail) ?></a>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-center">
                <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($schoolName) ?>. All Rights Reserved.</p>
                <p class="mb-0" style="font-size:12px;opacity:0.5;">Hilal Public Secondary School, Harinagar, Sunsari Nepal</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS — Local -->
<script src="<?= $base ?>assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= $base ?>assets/js/main.js"></script>

</body>
</html>
