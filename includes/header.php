<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/nepali_date.php';

// Default values (fallback if database fails)
$defaults = [
    'school_name'    => 'Hilal Public Secondary School',
    'school_motto'   => 'Education for Excellence',
    'school_phone'   => '+977-980-7071324',
    'school_email'   => 'hilalpublicschool096@gmail.com',
    'school_address' => 'Harinagar RM-7, Hilal Nagar, Ghuski, Sunsari (Nepal)',
    'school_website' => 'https://hilalpublicschool.edu.np',
    'facebook_url'   => 'https://facebook.com/hilalpublicschool',
    'youtube_url'    => 'https://youtube.com/@hilalpublicschool',
    'school_logo'    => 'assets/images/logo.jpg'
];

// Try to get from database, use defaults if fails
$schoolName    = getSetting($conn, 'school_name', $defaults['school_name']);
$schoolMotto   = getSetting($conn, 'school_motto', $defaults['school_motto']);
$schoolPhone   = getSetting($conn, 'school_phone', $defaults['school_phone']);
$schoolEmail   = getSetting($conn, 'school_email', $defaults['school_email']);
$schoolAddress = getSetting($conn, 'school_address', $defaults['school_address']);
$schoolWebsite = getSetting($conn, 'school_website', $defaults['school_website']);
$facebookUrl   = getSetting($conn, 'facebook_url', $defaults['facebook_url']);
$youtubeUrl    = getSetting($conn, 'youtube_url', $defaults['youtube_url']);
$schoolLogo    = getSetting($conn, 'school_logo', $defaults['school_logo']);

$currentPage = basename($_SERVER['PHP_SELF']);
$base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/', 1) - 1);

// ── Per-page SEO ──────────────────────────────────────
$siteUrl     = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$currentUrl  = $siteUrl.$_SERVER['REQUEST_URI'];
$logoUrl     = $siteUrl.'/assets/images/'.($schoolLogo?:'logo.jpg');

// Default keywords + page-specific
$baseKeywords = 'Hilal Public School, HPSS, Hilal School, Hilal Public Secondary School, Harinagar School, Ghuski School, Sunsari School, Nepal School';
$pageKeywords = $baseKeywords;
$pageDesc     = $schoolName.' — '.$schoolMotto.'. Located at '.$schoolAddress.'.';
$ogType       = 'website';

switch ($currentPage) {
    case 'index.php':
        $pageKeywords = $baseKeywords.', Hilal, hilal school admission, hilal school results, best school sunsari';
        $pageDesc     = $schoolName.' — '.$schoolMotto.'. Admission open. Check results, notices and events online.';
        break;
    case 'results.php':
    case 'result_view.php':
        $pageKeywords = $baseKeywords.', hilal results, HPSS result, hilal school result, check result online, school result nepal';
        $pageDesc     = 'Check your results online at '.$schoolName.'. View terminal and annual exam results.';
        break;
    case 'admissions.php':
        $pageKeywords = $baseKeywords.', hilal admission, HPSS admission, school admission sunsari, school admission harinagar';
        $pageDesc     = 'Apply for admission at '.$schoolName.'. Online admission form available. Apply now.';
        break;
    case 'notices.php':
        $pageKeywords = $baseKeywords.', hilal notice, HPSS notice, school notice, hilal school notice board';
        $pageDesc     = 'Latest notices and announcements from '.$schoolName.'. Stay updated.';
        break;
    case 'events.php':
        $pageKeywords = $baseKeywords.', hilal events, HPSS events, school events, hilal school program';
        $pageDesc     = 'Upcoming and recent events at '.$schoolName.'. View all school programs and activities.';
        break;
    case 'gallery.php':
        $pageKeywords = $baseKeywords.', hilal school gallery, HPSS photos, school photos sunsari';
        $pageDesc     = 'Photo gallery of '.$schoolName.'. View school events, activities and campus photos.';
        break;
    case 'teachers.php':
        $pageKeywords = $baseKeywords.', hilal school teachers, HPSS staff, school teachers sunsari';
        $pageDesc     = 'Meet the dedicated teaching staff of '.$schoolName.'.';
        break;
    case 'scholarship.php':
        $pageKeywords = $baseKeywords.', hilal scholarship, HPSS scholarship, school scholarship nepal, scholarship sunsari';
        $pageDesc     = 'Apply for scholarship at '.$schoolName.'. Financial assistance available for deserving students.';
        break;
    case 'about.php':
        $pageKeywords = $baseKeywords.', about hilal school, hilal school history, HPSS about';
        $pageDesc     = 'Learn about '.$schoolName.'. Our history, mission and vision.';
        break;
    case 'contact.php':
        $pageKeywords = $baseKeywords.', hilal school contact, HPSS contact, contact school sunsari';
        $pageDesc     = 'Contact '.$schoolName.'. Phone, email and location details.';
        break;
}
$pageTitle_seo = (isset($pageTitle) ? htmlspecialchars($pageTitle).' — ' : '').htmlspecialchars($schoolName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ── Primary SEO ──────────────────────────────── -->
    <title><?= $pageTitle_seo ?></title>
    <meta name="description"  content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="keywords"     content="<?= htmlspecialchars($pageKeywords) ?>">
    <meta name="author"       content="<?= htmlspecialchars($schoolName) ?>">
    <meta name="robots"       content="index, follow">
    <link rel="canonical"     href="<?= htmlspecialchars($currentUrl) ?>">

    <!-- ── Open Graph (Facebook, WhatsApp, Viber) ───── -->
    <meta property="og:type"         content="<?= $ogType ?>">
    <meta property="og:title"        content="<?= $pageTitle_seo ?>">
    <meta property="og:description"  content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:url"          content="<?= htmlspecialchars($currentUrl) ?>">
    <meta property="og:image"        content="<?= htmlspecialchars($logoUrl) ?>">
    <meta property="og:image:width"  content="300">
    <meta property="og:image:height" content="300">
    <meta property="og:site_name"    content="<?= htmlspecialchars($schoolName) ?>">
    <meta property="og:locale"       content="en_US">

    <!-- ── Schema.org JSON-LD (Google structured data) ─ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "School",
        "name": "<?= htmlspecialchars($schoolName) ?>",
        "description": "<?= htmlspecialchars($schoolMotto) ?>",
        "url": "<?= $siteUrl ?>",
        "logo": "<?= htmlspecialchars($logoUrl) ?>",
        "telephone": "<?= htmlspecialchars($schoolPhone) ?>",
        "email": "<?= htmlspecialchars($schoolEmail) ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "<?= htmlspecialchars($schoolAddress) ?>",
            "addressLocality": "Sunsari",
            "addressRegion": "Province 1",
            "addressCountry": "NP"
        },
        "sameAs": [
            <?= $facebookUrl ? '"'.htmlspecialchars($facebookUrl).'"' : '' ?>
        ]
    }
    </script>

    <!-- ── Favicon ──────────────────────────────────── -->
    <link rel="icon" type="image/jpeg" href="<?= $base ?>assets/images/<?= htmlspecialchars($schoolLogo ?: 'logo.jpg') ?>">
    <link rel="shortcut icon" href="<?= $base ?>assets/images/<?= htmlspecialchars($schoolLogo ?: 'logo.jpg') ?>">
    <link rel="apple-touch-icon" href="<?= $base ?>assets/images/<?= htmlspecialchars($schoolLogo ?: 'logo.jpg') ?>">

    <!-- Bootstrap 5 — Local -->
    <link rel="stylesheet" href="<?= $base ?>assets/vendors/bootstrap/bootstrap.min.css">
    <!-- Font Awesome — Local -->
    <link rel="stylesheet" href="<?= $base ?>assets/vendors/fontawesome/css/all.min.css">
    <!-- Poppins Font — Local -->
    <link rel="stylesheet" href="<?= $base ?>assets/vendors/fonts/poppins.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
    <!-- jQuery in HEAD — so inline scripts work -->
    <script src="<?= $base ?>assets/vendors/bootstrap/jquery.min.js"></script>
</head>
<body>

<!-- ===== TOP BAR ===== -->
<div class="top-bar">
    <div class="container">
        <div style="display:flex;flex-direction:row;align-items:center;justify-content:space-between;flex-wrap:nowrap;gap:8px;">
            <!-- Left: phone, email, social icons -->
            <div style="display:flex;flex-direction:row;align-items:center;gap:6px;flex-wrap:nowrap;">
                <?php if($schoolPhone): ?>
                <a href="tel:<?= $schoolPhone ?>" class="top-bar-item" title="<?= htmlspecialchars($schoolPhone) ?>">
                    <i class="fas fa-phone-alt"></i>
                    <span class="d-none d-lg-inline ms-1"><?= htmlspecialchars($schoolPhone) ?></span>
                </a> |
                <?php endif; ?>
                <?php if($schoolEmail): ?>
                <a href="mailto:<?= $schoolEmail ?>" class="top-bar-item" title="<?= htmlspecialchars($schoolEmail) ?>">
                    <i class="fas fa-envelope"></i>
                    <span class="d-none d-xl-inline ms-1"><?= htmlspecialchars($schoolEmail) ?></span>
                </a> |
                <?php endif; ?>
                <?php if($facebookUrl): ?>
                <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" class="top-bar-item" title="Facebook">
                    <i class="fab fa-facebook-f"></i> <span class="d-none d-sm-inline">acebook</span>
                </a> |
                <?php endif; ?>
                <?php if($youtubeUrl): ?>
                <a href="<?= htmlspecialchars($youtubeUrl) ?>" target="_blank" class="top-bar-item" title="YouTube">
                    <i class="fab fa-youtube"></i>
                </a>
                <?php endif; ?>
            </div>
            <!-- Right: login -->
            <a href="<?= $base ?>admin/login.php" class="top-admin-btn" style="white-space:nowrap;flex-shrink:0;">
                <i class="fas fa-lock me-1"></i>Login
            </a>
        </div>
    </div>
</div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg main-navbar sticky-top">
    <div class="container">

        <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>index.php">
            <img src="<?= $base ?>assets/images/logo.png" alt="<?= htmlspecialchars($schoolName) ?> Logo"
                 class="school-logo" onerror="this.style.display='none'">
            <div class="school-info">
                <span class="school-name"><?= htmlspecialchars($schoolName) ?></span>
                <span class="school-sub"><?= htmlspecialchars($schoolMotto) ?></span>
            </div>
        </a>

        <!-- Offcanvas Toggle (mobile) -->
        <button class="navbar-toggler-custom d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>

        <!-- Desktop Nav -->
        <div class="collapse navbar-collapse" id="desktopNav">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='index.php'?'active':'' ?>" href="<?= $base ?>index.php">
                        Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='about.php'?'active':'' ?>" href="<?= $base ?>about.php">
                        About
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($currentPage,['teachers.php','results.php'])?'active':'' ?>"
                       href="#" data-bs-toggle="dropdown">Academics</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $base ?>teachers.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Teachers</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>results.php">
                            <i class="fas fa-poll me-2"></i>Results</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='notices.php'?'active':'' ?>" href="<?= $base ?>notices.php">Notices</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='admissions.php'?'active':'' ?>" href="<?= $base ?>admissions.php">Admissions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='scholarship.php'?'active':'' ?>" href="<?= $base ?>scholarship.php">Scholarship</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='events.php'?'active':'' ?>" href="<?= $base ?>events.php">Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage=='gallery.php'?'active':'' ?>" href="<?= $base ?>gallery.php">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-contact <?= $currentPage=='contact.php'?'active':'' ?>" href="<?= $base ?>contact.php">Contact</a>
                </li>
            </ul>
        </div>

    </div>
</nav>

<!-- ===== OFFCANVAS MOBILE NAV ===== -->
<div class="offcanvas offcanvas-start mobile-nav" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">

    <!-- Offcanvas Header -->
    <div class="offcanvas-header mobile-nav-header">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= $base ?>assets/images/logo.png" alt="Logo"
                 style="width:42px;height:42px;object-fit:contain;"
                 onerror="this.style.display='none'">
            <div>
                <div style="font-size:14px;font-weight:700;color:white;line-height:1.2;">
                    <?= htmlspecialchars($schoolName) ?>
                </div>
                <div style="font-size:11px;color:rgba(255,255,255,0.65);">
                    <?= htmlspecialchars($schoolMotto) ?>
                </div>
            </div>
        </div>
        <button type="button" class="mobile-nav-close" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Offcanvas Body -->
    <div class="offcanvas-body mobile-nav-body">
        <ul class="mobile-nav-list">
            <li>
                <a href="<?= $base ?>index.php" class="mobile-nav-link <?= $currentPage=='index.php'?'active':'' ?>">
                    <i class="fas fa-home"></i>Home
                </a>
            </li>
            <li>
                <a href="<?= $base ?>about.php" class="mobile-nav-link <?= $currentPage=='about.php'?'active':'' ?>">
                    <i class="fas fa-info-circle"></i>About Us
                </a>
            </li>
            <li class="mobile-nav-divider">Academics</li>
            <li>
                <a href="<?= $base ?>teachers.php" class="mobile-nav-link <?= $currentPage=='teachers.php'?'active':'' ?>">
                    <i class="fas fa-chalkboard-teacher"></i>Teachers
                </a>
            </li>
            <li>
                <a href="<?= $base ?>results.php" class="mobile-nav-link <?= $currentPage=='results.php'?'active':'' ?>">
                    <i class="fas fa-poll-h"></i>Results
                </a>
            </li>
            <li class="mobile-nav-divider">School</li>
            <li>
                <a href="<?= $base ?>notices.php" class="mobile-nav-link <?= $currentPage=='notices.php'?'active':'' ?>">
                    <i class="fas fa-bullhorn"></i>Notices
                </a>
            </li>
            <li>
                <a href="<?= $base ?>admissions.php" class="mobile-nav-link <?= $currentPage=='admissions.php'?'active':'' ?>">
                    <i class="fas fa-user-graduate"></i>Admissions
                </a>
            </li>
            <li>
                <a href="<?= $base ?>scholarship.php" class="mobile-nav-link <?= $currentPage=='scholarship.php'?'active':'' ?>">
                    <i class="fas fa-award"></i>Scholarship
                </a>
            </li>
            <li>
                <a href="<?= $base ?>events.php" class="mobile-nav-link <?= $currentPage=='events.php'?'active':'' ?>">
                    <i class="fas fa-calendar-alt"></i>Events
                </a>
            </li>
            <li>
                <a href="<?= $base ?>gallery.php" class="mobile-nav-link <?= $currentPage=='gallery.php'?'active':'' ?>">
                    <i class="fas fa-images"></i>Gallery
                </a>
            </li>
            <li>
                <a href="<?= $base ?>contact.php" class="mobile-nav-link <?= $currentPage=='contact.php'?'active':'' ?>">
                    <i class="fas fa-envelope"></i>Contact
                </a>
            </li>
        </ul>

        <!-- Mobile Bottom Actions -->
        <div class="mobile-nav-footer">
            <?php if($schoolPhone): ?>
            <a href="tel:<?= $schoolPhone ?>" class="mobile-nav-action">
                <i class="fas fa-phone-alt"></i><?= htmlspecialchars($schoolPhone) ?>
            </a>
            <?php endif; ?>
            <?php if($schoolEmail): ?>
            <a href="mailto:<?= $schoolEmail ?>" class="mobile-nav-action">
                <i class="fas fa-envelope"></i><?= htmlspecialchars($schoolEmail) ?>
            </a>
            <?php endif; ?>
            <a href="<?= $base ?>admin/login.php" class="mobile-nav-action admin-action">
                <i class="fas fa-lock"></i>LogIn
            </a>
        </div>
    </div>
</div>
