<?php
$pageTitle = 'Home';
require_once 'includes/header.php';

$schoolName    = getSetting($conn, 'school_name');
$schoolMotto   = getSetting($conn, 'school_motto');
$schoolAddress = getSetting($conn, 'school_address');
$schoolPhone   = getSetting($conn, 'school_phone');
$schoolEmail   = getSetting($conn, 'school_email');
$facebookUrl   = getSetting($conn, 'facebook_url');
$estYear       = getSetting($conn, 'established_year');

$notices      = $conn->query("SELECT * FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$events       = $conn->query("SELECT * FROM events WHERE is_active=1 AND event_date>=CURDATE() ORDER BY event_date ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
// Teachers — random order on each refresh, prefer those with a real photo
// First try: teachers WITH custom photo (not default.png), random order
$teachers = $conn->query("
    SELECT u.full_name, u.photo, td.qualification, td.specialization
    FROM users u
    LEFT JOIN teacher_details td ON u.id = td.user_id
    WHERE u.role='teacher' AND u.status='active'
      AND u.photo IS NOT NULL AND u.photo != '' AND u.photo != 'default.png'
    ORDER BY RAND()
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Fallback: if fewer than 4 have photos, include all active teachers randomly
if (count($teachers) < 4) {
    $teachers = $conn->query("
        SELECT u.full_name, u.photo, td.qualification, td.specialization
        FROM users u
        LEFT JOIN teacher_details td ON u.id = td.user_id
        WHERE u.role='teacher' AND u.status='active'
        ORDER BY RAND()
        LIMIT 8
    ")->fetch_all(MYSQLI_ASSOC);
}
$galleryItems = $conn->query("SELECT * FROM gallery WHERE is_active=1 ORDER BY created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

$totalStudents = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$totalTeachers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='teacher' AND status='active'")->fetch_assoc()['c'];
$totalClasses  = $conn->query("SELECT COUNT(*) as c FROM classes WHERE status='active'")->fetch_assoc()['c'];

// Years of Excellence — use BS year so it matches the school's Nepali calendar
// established_year is stored as BS (2052), current BS year from nepali_date helper
$estYearBS    = (int)getSetting($conn, 'established_year_bs');
if (!$estYearBS) $estYearBS = (int)$estYear; // fallback if setting missing
$currentBS    = getCurrentBS();
$yearsRunning = max(1, $currentBS['year'] - $estYearBS);
?>

<!-- ╔══════════════════════════════════════╗
     ║         HERO SLIDER                  ║
     ╚══════════════════════════════════════╝ -->
<div id="heroSlider" class="carousel slide hero-slider" data-bs-ride="carousel" data-bs-interval="5500">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="1"></button>
        <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="2"></button>
    </div>
    <div class="carousel-inner">

        <!-- Slide 1 -->
        <div class="carousel-item active hero-slide-1">
            <img src="./image/image.png" alt="School"
                 onerror="this.src='https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=1400&q=80'">
            <div class="carousel-caption">
                <div class="hero-badge" data-animate>Est. <?= $estYearBS ?> BS</div>
                <h1 data-animate>Welcome to<br><span class="hero-highlight"><?= htmlspecialchars($schoolName) ?></span></h1>
                <p data-animate><?= htmlspecialchars($schoolMotto) ?></p>
                <div class="hero-location" data-animate>
                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($schoolAddress) ?>
                </div>
                <div class="hero-actions mt-4" data-animate>
                    <a href="about.php" class="hero-btn-primary">Learn More</a>
                    <a href="admissions.php" class="hero-btn-accent">Apply Now <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="carousel-item hero-slide-2">
            <img src="./image/image.png" alt="Teachers"
                 onerror="this.src='./image/image.png">
            <div class="carousel-caption">
                <div class="hero-badge">Academics</div>
                <h1>Quality Education<br><span class="hero-highlight">For Every Child</span></h1>
                <p>Nurturing young minds with modern curriculum and dedicated teachers in Sunsari, Nepal.</p>
                <div class="hero-actions mt-4">
                    <a href="teachers.php" class="hero-btn-primary">Meet Our Teachers</a>
                    <a href="results.php" class="hero-btn-accent">Check Results <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="carousel-item hero-slide-3">
            <img src="./image/bulding.jpg" alt="Scholarship"
                 onerror="this.src='https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=1400&q=80'">
            <div class="carousel-caption">
                <div class="hero-badge">Scholarship</div>
                <h1>Empowering Students<br><span class="hero-highlight">Through Scholarships</span></h1>
                <p>Supporting deserving students from Harinagar, Ghuski and surrounding areas to achieve their dreams.</p>
                <div class="hero-actions mt-4">
                    <a href="scholarship.php" class="hero-btn-primary">Apply for Scholarship</a>
                    <a href="contact.php" class="hero-btn-accent">Contact Us <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<!-- ╔══════════════════════════════════════╗
     ║       QUICK ACCESS LINKS             ║
     ╚══════════════════════════════════════╝ -->
<div class="quick-links">
    <div class="container">
        <div class="row g-3 justify-content-center">
            <div class="col-6 col-md-3">
                <a href="results.php" class="quick-link-card d-block text-decoration-none">
                    <i class="fas fa-poll-h"></i><span>Check Results</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="notices.php" class="quick-link-card d-block text-decoration-none">
                    <i class="fas fa-bullhorn"></i><span>Notices</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="admissions.php" class="quick-link-card d-block text-decoration-none">
                    <i class="fas fa-user-graduate"></i><span>Admissions</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="scholarship.php" class="quick-link-card d-block text-decoration-none">
                    <i class="fas fa-award"></i><span>Scholarship</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ╔══════════════════════════════════════╗
     ║          STATS COUNTER               ║
     ╚══════════════════════════════════════╝ -->
<div class="stats-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-6 col-lg-3 stat-item" data-animate>
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number" data-target="<?= max($totalStudents,1) ?>" data-suffix="+">0+</div>
                <div class="stat-label">Students Enrolled</div>
            </div>
            <div class="col-6 col-lg-3 stat-item" data-animate>
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number" data-target="<?= max($totalTeachers,1) ?>" data-suffix="+">0+</div>
                <div class="stat-label">Qualified Teachers</div>
            </div>
            <div class="col-6 col-lg-3 stat-item" data-animate>
                <div class="stat-icon"><i class="fas fa-school"></i></div>
                <div class="stat-number" data-target="<?= max($totalClasses,1) ?>" data-suffix="">0</div>
                <div class="stat-label">Classes Running</div>
            </div>
            <div class="col-6 col-lg-3 stat-item" data-animate>
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-number" data-target="<?= $yearsRunning ?>" data-suffix="+">0+</div>
                <div class="stat-label">Years of Excellence</div>
            </div>
        </div>
    </div>
</div>

<!-- ╔══════════════════════════════════════╗
     ║        ABOUT SCHOOL (MINI)           ║
     ╚══════════════════════════════════════╝ -->
<section class="about-home-section">
    <div class="container">
        <div class="row g-5 align-items-center">

            <!-- Left: Info cards -->
            <div class="col-lg-6" data-animate>
                <div class="section-title text-start mb-4">
                    <h2>About Our School</h2>
                </div>
                <p class="mb-4" style="font-size:15px;color:var(--text-body);line-height:1.85;">
                    <strong>Hilal Public Secondary School</strong> is a pioneer in Ghuski, a remote area in Sunsari District. Established in 1996, it now educates over 1,100 students from Nursery to Class 12. The school's mission is to promote quality education with moral values in marginalized communities, focusing on eradicating illiteracy among Muslim children. With qualified, dedicated teachers using modern teaching methods and annual plans for multidimensional growth, the school offers well-equipped classrooms, coaching, and vocational training in computers and sewing. For the past 13 years, it has achieved 100% pass rates in the SEE exams, producing graduates who have become doctors, engineers, administrators, and Islamic scholars. Hilal Public School believes that building a new nation starts with educating its people and is committed to creating a highly literate generation.
                </p>
                <p class="mb-4" style="font-size:15px;color:var(--text-body);line-height:1.85;">
                    Our experienced team of teachers, structured curriculum, and community-focused
                    approach make us one of the trusted schools in the Sunsari district.
                </p>
                <!-- Info chips -->
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <div class="info-chip"><i class="fas fa-map-marker-alt"></i> Harinagar-7, Ghuski, Sunsari</div>
                    <div class="info-chip"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($schoolPhone) ?></div>
                    <div class="info-chip"><i class="fas fa-envelope"></i> <?= htmlspecialchars($schoolEmail) ?></div>
                </div>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="about.php" class="btn-primary-custom">Know More</a>
                    <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank"
                       class="btn-fb"><i class="fab fa-facebook-f me-2"></i>Facebook Page</a>
                </div>
            </div>

            <!-- Right: Feature cards -->
            <div class="col-lg-6" data-animate>
                <div class="row g-3">
                    <?php
                    $features = [
                        ['icon'=>'fas fa-book-open',     'color'=>'#1b6b35', 'title'=>'Strong Curriculum',    'desc'=>'Comprehensive syllabus aligned with national education standards.'],
                        ['icon'=>'fas fa-user-tie',      'color'=>'#b5281f', 'title'=>'Expert Teachers',      'desc'=>'Qualified educators committed to every student\'s success.'],
                        ['icon'=>'fas fa-award',         'color'=>'#e8980a', 'title'=>'Scholarship Support',  'desc'=>'Financial aid for deserving students in our community.'],
                        ['icon'=>'fas fa-users',         'color'=>'#0284c7', 'title'=>'Safe Environment',     'desc'=>'Inclusive, safe and nurturing learning community.'],
                    ];
                    foreach($features as $f):
                    ?>
                    <div class="col-6">
                        <div class="feature-card">
                            <div class="feature-icon" style="background:<?= $f['color'] ?>15;color:<?= $f['color'] ?>;">
                                <i class="<?= $f['icon'] ?>"></i>
                            </div>
                            <h6><?= $f['title'] ?></h6>
                            <p><?= $f['desc'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ╔══════════════════════════════════════╗
     ║       NOTICES + EVENTS               ║
     ╚══════════════════════════════════════╝ -->
<section style="background:var(--light);padding:72px 0;">
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Latest Updates</h2>
            <p>Stay informed with our recent notices and upcoming events</p>
        </div>
        <div class="row g-4">

            <!-- Notice Board -->
            <div class="col-lg-6" data-animate>
                <div class="update-card h-100">
                    <div class="update-card-header green">
                        <span><i class="fas fa-bullhorn me-2"></i>Notice Board</span>
                        <a href="notices.php">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="update-card-body">
                        <?php if (empty($notices)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notices at the moment.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($notices as $n): ?>
                        <div class="notice-item">
                            <span class="notice-badge <?= $n['notice_type'] ?>">
                                <?= strtoupper(substr($n['notice_type'],0,3)) ?>
                            </span>
                            <div class="flex-grow-1">
                                <div class="notice-title">
                                    <?= htmlspecialchars($n['title']) ?>
                                    <?php if($n['attachment']): ?>
                                    <a href="uploads/notices/<?= $n['attachment'] ?>" target="_blank" class="ms-1">
                                        <i class="fas fa-paperclip" style="font-size:11px;color:var(--primary);"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="notice-date">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?php
                                        $nBS = adToBS(date('Y-m-d', strtotime($n['created_at'])));
                                        echo $nBS['day'] . ' ' . getNpMonthName($nBS['month']) . ' ' . $nBS['year'] . ' BS';
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Events -->
            <div class="col-lg-6" data-animate>
                <div class="update-card h-100">
                    <div class="update-card-header red">
                        <span><i class="fas fa-calendar-alt me-2"></i>Upcoming Events</span>
                        <a href="events.php">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="update-card-body">
                        <?php if (empty($events)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No upcoming events right now.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                        <div class="event-card">
                            <div class="event-date-box">
                                <?php
                                    $evBS = adToBS($ev['event_date']);
                                ?>
                                <div class="day"><?= $evBS['day'] ?></div>
                                <div class="month"><?= getNpMonthName($evBS['month']) ?></div>
                                <div style="font-size:10px;opacity:0.8;"><?= $evBS['year'] ?></div>
                            </div>
                            <div class="event-info">
                                <h5><?= htmlspecialchars($ev['title']) ?></h5>
                                <p>
                                    <?php if($ev['venue']): ?>
                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($ev['venue']) ?>
                                    <?php endif; ?>
                                    <?php if($ev['event_time']): ?>
                                    &nbsp;&bull;&nbsp;<i class="fas fa-clock me-1"></i><?= htmlspecialchars($ev['event_time']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ╔══════════════════════════════════════╗
     ║       RESULT CHECKER                 ║
     ╚══════════════════════════════════════╝ -->
<div class="result-checker">
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Left text -->
            <div class="col-lg-5 text-white" data-animate>
                <div class="result-info-badge mb-3">
                    <i class="fas fa-poll-h me-2"></i>Online Result System
                </div>
                <h2 class="fw-800 mb-3" style="font-size:32px;font-weight:800;">Check Your<br>Exam Result</h2>
                <p style="opacity:0.85;font-size:15px;line-height:1.8;" class="mb-4">
                    Students of Hilal Public Secondary School can check their
                    1st Terminal, 2nd Terminal, and Final Exam results online.
                    Just enter your details below.
                </p>
                <div class="result-steps">
                    <div class="result-step"><span>1</span> Select exam year &amp; type</div>
                    <div class="result-step"><span>2</span> Enter your Roll No &amp; Date of Birth</div>
                    <div class="result-step"><span>3</span> View &amp; print your result</div>
                </div>
            </div>
            <!-- Right form -->
            <div class="col-lg-7" data-animate>
                <div class="result-form-box">
                    <h3><i class="fas fa-search me-2"></i>Result Checker</h3>
                    <div id="resultAlert"></div>
                    <form id="resultCheckerForm" action="result_view.php" method="GET">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Exam Year <span class="text-danger">*</span></label>
                                <select class="form-select" id="exam_year" name="exam_year" required>
                                    <option value="">Select Year</option>
                                    <?php $yr = $conn->query("SELECT DISTINCT academic_year FROM exams ORDER BY academic_year DESC"); while($r=$yr->fetch_assoc()): ?>
                                    <option value="<?= $r['academic_year'] ?>"><?= $r['academic_year'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Exam Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="exam_type" name="exam_type" required>
                                    <option value="">Select Exam</option>
                                    <option value="1st_terminal">1st Terminal</option>
                                    <option value="2nd_terminal">2nd Terminal</option>
                                    <option value="final">Final Exam</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class <span class="text-danger">*</span></label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php $cls=$conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id"); while($c=$cls->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Roll / Symbol No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="roll_no" name="roll_no" placeholder="e.g. 15 or S-1234" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dob" name="dob" required
                                       placeholder="YYYY-MM-DD or DD/MM/YYYY">
                                <div class="form-text" style="color:rgba(0,0,0,0.5);">
                                    <i class="fas fa-info-circle me-1"></i>AD or BS — e.g. 2010-05-15 or 15/05/2010
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="result-submit-btn">
                                    <i class="fas fa-search me-2"></i>Check My Result
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ╔══════════════════════════════════════╗
     ║         ACADEMIC PROGRAMS            ║
     ╚══════════════════════════════════════╝ -->
<section>
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Academic Programs</h2>
            <p>We offer comprehensive education from early childhood through secondary level</p>
        </div>
        <div class="row g-4">
            <?php
            $programs = [
                ['icon'=>'fas fa-child',           'color'=>'#e8980a', 'bg'=>'#fef9ec',
                 'title'=>'Early Childhood',        'grades'=>'Nursery, LKG, UKG',
                 'desc'=>'Play-based learning that builds curiosity, creativity and foundational skills.'],
                ['icon'=>'fas fa-pencil-alt',       'color'=>'#1b6b35', 'bg'=>'#edf7f0',
                 'title'=>'Primary Level',          'grades'=>'Class 1 – Class 5',
                 'desc'=>'Core subjects with interactive teaching methods to build strong fundamentals.'],
                ['icon'=>'fas fa-flask',            'color'=>'#0284c7', 'bg'=>'#e8f4fb',
                 'title'=>'Lower Secondary',        'grades'=>'Class 6 – Class 8',
                 'desc'=>'Broadened curriculum including Science, Social Studies, Mathematics and Languages.'],
                ['icon'=>'fas fa-graduation-cap',   'color'=>'#b5281f', 'bg'=>'#fdf0ef',
                 'title'=>'Secondary (SEE)',         'grades'=>'Class 9 – Class 10',
                 'desc'=>'SEE preparation with focused academics and personality development.'],
                ['icon'=>'fas fa-university',       'color'=>'#7c3aed', 'bg'=>'#f3eeff',
                 'title'=>'Higher Secondary (+2)',   'grades'=>'Class 11 – Class 12',
                 'desc'=>'NEB affiliated +2 program preparing students for higher education and career.'],
            ];
            foreach($programs as $idx => $p):
            ?>
            <div class="col-md-6 col-lg-4" data-animate>
                <div class="program-card <?= $idx===4 ? 'program-card-featured' : '' ?>">
                    <div class="program-icon" style="background:<?= $p['bg'] ?>;color:<?= $p['color'] ?>;">
                        <i class="<?= $p['icon'] ?>"></i>
                    </div>
                    <div class="program-grade" style="color:<?= $p['color'] ?>;"><?= $p['grades'] ?></div>
                    <h5><?= $p['title'] ?></h5>
                    <p><?= $p['desc'] ?></p>
                    <a href="admissions.php" class="program-link" style="color:<?= $p['color'] ?>;">
                        Apply Now <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ╔══════════════════════════════════════╗
     ║           OUR TEACHERS               ║
     ╚══════════════════════════════════════╝ -->
<?php if (!empty($teachers)): ?>
<section style="background:var(--light);padding:72px 0;">
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Our Teachers</h2>
            <p>Meet our dedicated and experienced teaching staff</p>
        </div>
        <!-- Desktop: Bootstrap grid | Mobile: horizontal scroll strip -->
        <div class="teachers-scroll-wrap">
            <div class="row g-4 flex-nowrap flex-md-wrap teachers-scroll-row">
                <?php foreach ($teachers as $t): ?>
                <div class="col-auto col-md-4 col-lg-3 teachers-scroll-col" data-animate>
                    <div class="teacher-card">
                        <img src="uploads/teachers/<?= $t['photo'] ?? 'default.png' ?>"
                             alt="<?= htmlspecialchars($t['full_name']) ?>"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($t['full_name']) ?>&background=1b6b35&color=fff&size=96'">
                        <h5><?= htmlspecialchars($t['full_name']) ?></h5>
                        <div class="designation"><?= htmlspecialchars($t['specialization'] ?? 'Teacher') ?></div>
                        <div class="subject"><?= htmlspecialchars($t['qualification'] ?? '') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="text-center mt-5" data-animate>
            <a href="teachers.php" class="btn-primary-custom">View All Teachers</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ╔══════════════════════════════════════╗
     ║            GALLERY                   ║
     ╚══════════════════════════════════════╝ -->
<?php if (!empty($galleryItems)): ?>
<section>
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Photo Gallery</h2>
            <p>Capturing moments of learning, growth and celebration at our school</p>
        </div>
        <!-- Desktop: Bootstrap grid | Mobile: horizontal scroll strip -->
        <div class="gallery-scroll-wrap">
            <div class="row g-3 flex-nowrap flex-md-wrap gallery-scroll-row">
                <?php foreach ($galleryItems as $item): ?>
                <div class="col-auto col-md-4 col-lg-2 gallery-scroll-col" data-animate>
                    <div class="gallery-item" data-title="<?= htmlspecialchars($item['title']) ?>">
                        <img src="uploads/gallery/<?= $item['image'] ?>"
                             alt="<?= htmlspecialchars($item['title']) ?>"
                             onerror="this.src='https://via.placeholder.com/300x180/1b6b35/fff?text=Photo'">
                        <div class="gallery-overlay"><i class="fas fa-expand-alt"></i></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="text-center mt-5" data-animate>
            <a href="gallery.php" class="btn-primary-custom">View Full Gallery</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ╔══════════════════════════════════════╗
     ║        WHY CHOOSE US                 ║
     ╚══════════════════════════════════════╝ -->
<section style="background:var(--light);padding:72px 0;">
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Why Choose Hilal School?</h2>
            <p>What makes us the right choice for your child's education in Sunsari</p>
        </div>
        <div class="row g-4">
            <?php
            $whys = [
                ['icon'=>'fas fa-star',              'title'=>'Proven Results',           'desc'=>'Our students consistently achieve excellent results in SEE and other examinations.'],
                ['icon'=>'fas fa-heart',             'title'=>'Caring Community',         'desc'=>'A warm, inclusive school family where every student is known, valued and supported.'],
                ['icon'=>'fas fa-shield-alt',        'title'=>'Safe & Secure',            'desc'=>'Safe school environment where children learn and grow with confidence and comfort.'],
                ['icon'=>'fas fa-rupee-sign',        'title'=>'Affordable Fees',          'desc'=>'Quality education at affordable rates with scholarship options for deserving students.'],
                ['icon'=>'fas fa-book-reader',       'title'=>'Holistic Development',     'desc'=>'Beyond academics — sports, culture, arts and values to build complete personalities.'],
                ['icon'=>'fas fa-map-marker-alt',    'title'=>'Conveniently Located',     'desc'=>'Easily accessible in Harinagar-7, Ghuski, Sunsari — serving the local community.'],
            ];
            foreach($whys as $w):
            ?>
            <div class="col-md-6 col-lg-4" data-animate>
                <div class="why-card">
                    <div class="why-icon"><i class="<?= $w['icon'] ?>"></i></div>
                    <div>
                        <h6><?= $w['title'] ?></h6>
                        <p><?= $w['desc'] ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ╔══════════════════════════════════════╗
     ║        CONTACT STRIP                 ║
     ╚══════════════════════════════════════╝ -->
<div class="contact-strip">
    <div class="container">
        <div class="row g-3 align-items-center">
            <div class="col-lg-4 contact-strip-item" data-animate>
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <div class="contact-strip-label">Our Location</div>
                    <div class="contact-strip-value"><?= htmlspecialchars($schoolAddress) ?></div>
                </div>
            </div>
            <div class="col-lg-4 contact-strip-item" data-animate>
                <i class="fas fa-phone-alt"></i>
                <div>
                    <div class="contact-strip-label">Call Us</div>
                    <a href="tel:<?= htmlspecialchars($schoolPhone) ?>" class="contact-strip-value">
                        <?= htmlspecialchars($schoolPhone) ?>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 contact-strip-item" data-animate>
                <i class="fas fa-envelope"></i>
                <div>
                    <div class="contact-strip-label">Email Us</div>
                    <a href="mailto:<?= htmlspecialchars($schoolEmail) ?>" class="contact-strip-value">
                        <?= htmlspecialchars($schoolEmail) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== ADMISSION CTA BANNER ===== -->
<?php
// Admission window check for index page
$admIsOpen2    = getSetting($conn, 'admission_is_open');
$admOpenFrom2  = getSetting($conn, 'admission_open_from');
$admOpenUntil2 = getSetting($conn, 'admission_open_until');
$todayIdx      = date('Y-m-d');
if ($admOpenFrom2 && $admOpenUntil2) {
    $admActiveIdx = ($todayIdx >= $admOpenFrom2 && $todayIdx <= $admOpenUntil2);
} elseif ($admOpenFrom2 && !$admOpenUntil2) {
    $admActiveIdx = ($todayIdx >= $admOpenFrom2);
} elseif (!$admOpenFrom2 && $admOpenUntil2) {
    $admActiveIdx = ($todayIdx <= $admOpenUntil2);
} else {
    $admActiveIdx = (bool)$admIsOpen2;
}
$daysLeftIdx = null;
if ($admActiveIdx && $admOpenUntil2) {
    $daysLeftIdx = max(0, (int)ceil((strtotime($admOpenUntil2) - time()) / 86400));
}
?>
<div class="cta-banner">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8" data-animate>
                <?php if ($admActiveIdx): ?>
                <div class="cta-tag"><i class="fas fa-door-open me-1"></i>Admissions Open</div>
                <?php else: ?>
                <div class="cta-tag" style="background:rgba(255,255,255,0.25);color:white;">
                    <i class="fas fa-door-closed me-1"></i>Admissions Closed
                </div>
                <?php endif; ?>
                <h2>
                    <?php if ($admActiveIdx): ?>
                        Enroll Your Child at Hilal Public Secondary School
                    <?php else: ?>
                        Admissions Currently Closed
                    <?php endif; ?>
                </h2>
                <p>
                    <?php if ($admActiveIdx): ?>
                        Give your child the gift of quality education in a caring environment.
                        Serving students from Harinagar, Ghuski, and surrounding areas of Sunsari.
                        <?php if ($daysLeftIdx !== null): ?>
                        <strong style="color:var(--accent-light);">
                            Only <?= $daysLeftIdx ?> day<?= $daysLeftIdx==1?'':'s' ?> left to apply!
                        </strong>
                        <?php else: ?>
                        Seats are limited — apply today!
                        <?php endif; ?>
                    <?php else: ?>
                        Admissions are not open at the moment. Contact the school directly
                        or check our notice board for upcoming admission announcements.
                        <?php if ($admOpenFrom2 && $admOpenFrom2 > $todayIdx): ?>
                        <br><strong style="color:var(--accent-light);">
                            Next admissions open: <?= date('F d, Y', strtotime($admOpenFrom2)) ?>
                        </strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <div class="d-flex flex-wrap gap-2 mt-2" style="font-size:14px;opacity:0.9;">
                    <span><i class="fas fa-check-circle me-1"></i>Nursery to Class 10</span>
                    <span><i class="fas fa-check-circle me-1"></i>Qualified Teachers</span>
                    <span><i class="fas fa-check-circle me-1"></i>Scholarship Available</span>
                    <span><i class="fas fa-check-circle me-1"></i>Affordable Fees</span>
                </div>
            </div>
            <div class="col-lg-4 text-center text-lg-end" data-animate>
                <?php if ($admActiveIdx): ?>
                <a href="admissions.php" class="cta-btn-main">
                    <i class="fas fa-user-graduate me-2"></i>Apply for Admission
                </a>
                <?php else: ?>
                <a href="notices.php" class="cta-btn-main" style="background:rgba(255,255,255,0.2);color:white;box-shadow:none;">
                    <i class="fas fa-bullhorn me-2"></i>View Notices
                </a>
                <?php endif; ?>
                <br>
                <a href="contact.php" class="cta-btn-outline mt-3 d-inline-block">
                    <i class="fas fa-phone-alt me-2"></i>Contact School
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
