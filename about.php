<?php
$pageTitle = 'About Us';
require_once 'includes/header.php';

$establishedYear = getSetting($conn, 'established_year');
$schoolMotto     = getSetting($conn, 'school_motto');
$totalStudents   = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE status='active'")->fetch_assoc()['cnt'];
$totalTeachers   = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='teacher' AND status='active'")->fetch_assoc()['cnt'];
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-school me-2"></i>About Us</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">About Us</li>
            </ol>
        </nav>
    </div>
</div>

<!-- About School -->
<section>
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6" data-animate>
                <div class="section-title text-start">
                    <h2>Welcome to Hilal Public Secondary School</h2>
                </div>
                <p class="mb-2">
                    <strong>Hilal Public Secondary School</strong> is a pioneer in Ghuski, a remote area in Sunsari District. Established in 1996, it now educates over 1,100 students from Nursery to Class 12. The school's mission is to promote quality education with moral values in marginalized communities, focusing on eradicating illiteracy among Muslim children. With qualified, dedicated teachers using modern teaching methods and annual plans for multidimensional growth, the school offers well-equipped classrooms, coaching, and vocational training in computers and sewing. For the past 13 years, it has achieved 100% pass rates in the SEE exams, producing graduates who have become doctors, engineers, administrators, and Islamic scholars. Hilal Public School believes that building a new nation starts with educating its people and is committed to creating a highly literate generation.
                </p>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:var(--light);">
                            <i class="fas fa-graduation-cap fa-2x text-primary-custom"></i>
                            <div>
                                <div class="fw-bold fs-5 text-primary-custom"><?= $totalStudents ?>+</div>
                                <div class="text-muted" style="font-size:13px;">Students</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:var(--light);">
                            <i class="fas fa-chalkboard-teacher fa-2x text-primary-custom"></i>
                            <div>
                                <div class="fw-bold fs-5 text-primary-custom"><?= $totalTeachers ?>+</div>
                                <div class="text-muted" style="font-size:13px;">Teachers</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-animate>
                <div class="position-relative">
                    <img src="./image/bulding.jpg" alt="School Building"
                         class="img-fluid rounded-3 shadow"
                         onerror="this.src='https://via.placeholder.com/600x400/1a5c2a/ffffff?text=Hilal+Public+Secondary+School'">
                    <div class="position-absolute bottom-0 start-0 m-3 p-3 rounded-3 text-white"
                         style="background:var(--primary);">
                        <div class="fw-bold">Est. <?= $establishedYear ?></div>
                        <div style="font-size:12px;">Years of Excellence</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission, Vision, Objectives -->
<section class="bg-light-custom">
    <div class="container">

        <!-- Tagline -->
        <div class="text-center mb-5" data-animate>
            <div class="about-tagline">
                <i class="fas fa-quote-left me-2 opacity-50"></i>
                An institution committed to excellence in Islamic and modern education.
                <i class="fas fa-quote-right ms-2 opacity-50"></i>
            </div>
        </div>

        <div class="row g-4 mb-5">

            <!-- Vision Card -->
            <div class="col-md-6" data-animate>
                <div class="vm-card vision-card">
                    <div class="vm-header">
                        <div class="vm-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h3>OUR VISION</h3>
                    </div>
                    <div class="vm-body">
                        <p>
                            To become a leading center of excellence in Nepal that nurtures students
                            with a balanced education rooted in Islamic values and enriched with modern
                            academic knowledge, producing morally upright, intellectually capable, and
                            socially responsible individuals.
                        </p>
                        <!-- Urdu version -->
                        <div class="vm-urdu" dir="rtl">
                            "دینی وعصری تعلیم کے حسین امتزاج سے ایک ایسی نسل تیار کرنا جو معیاری علم، مثالی قائدانہ کردار اور خدمت خلق میں نمایاں ہو۔"
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mission Card -->
            <div class="col-md-6" data-animate>
                <div class="vm-card mission-card">
                    <div class="vm-header">
                        <div class="vm-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3>OUR MISSION</h3>
                    </div>
                    <div class="vm-body">
                        <ul class="vm-mission-list">
                            <li>To provide quality education that integrates Islamic teachings with modern curriculum.</li>
                            <li>To develop students' character, discipline, and leadership grounded in Islamic morals.</li>
                            <li>To empower students — boys and girls alike — with academic, spiritual, and life skills needed to thrive in a rapidly changing world.</li>
                            <li>To create a safe, inclusive, and nurturing environment that respects diversity and promotes lifelong learning.</li>
                        </ul>
                        <!-- Urdu version -->
                        <div class="vm-urdu" dir="rtl">
                            "طلباء وطالبات کو قرآن وسنت کی روشنی میں تربیت دینا اور جدید علوم سے آراستہ کرکے ایک باکردار، باشعور اور مفید شہری بنانا۔"
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Objectives -->
        <div data-animate>
            <div class="objectives-header">
                <i class="fas fa-star me-2"></i>OUR MAIN OBJECTIVES
            </div>
            <div class="row g-3 mt-0">
                <?php
                $objectives = [
                    ['icon'=>'fas fa-award',       'color'=>'#1b6b35', 'title'=>'Academic Excellence',         'desc'=>'Ensuring high academic standards and consistent results.'],
                    ['icon'=>'fas fa-user-graduate','color'=>'#0284c7', 'title'=>'Holistic Development',        'desc'=>'Physical, spiritual, and social development of every student.'],
                    ['icon'=>'fas fa-users',        'color'=>'#7c3aed', 'title'=>'Community Engagement',        'desc'=>'Building strong ties between school, family, and community.'],
                    ['icon'=>'fas fa-mosque',       'color'=>'#b5281f', 'title'=>'Islamic Values & Tarbiyah',   'desc'=>'Instilling Islamic morals and character in every student.'],
                    ['icon'=>'fas fa-heart',        'color'=>'#e67e22', 'title'=>'Orphan & Underprivileged Support','desc'=>'Supporting students from marginalized and underprivileged families.'],
                    ['icon'=>'fas fa-hands-helping','color'=>'#16a085', 'title'=>'Awareness & Cooperation',     'desc'=>'Promoting service, awareness, and social responsibility.'],
                ];
                foreach($objectives as $obj):
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="objective-item">
                        <div class="objective-icon" style="background:<?= $obj['color'] ?>15;color:<?= $obj['color'] ?>;">
                            <i class="<?= $obj['icon'] ?>"></i>
                        </div>
                        <div>
                            <div class="objective-title">✓ <?= $obj['title'] ?></div>
                            <div class="objective-desc"><?= $obj['desc'] ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</section>

<!-- Why Choose Us -->
<section>
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Why Choose Us?</h2>
            <p>What makes Hilal Public Secondary School the right choice for your child</p>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['icon'=>'fas fa-book-open',      'color'=>'var(--primary)',   'title'=>'Quality Curriculum',      'desc'=>'Comprehensive and updated curriculum aligned with national education standards.'],
                ['icon'=>'fas fa-user-tie',        'color'=>'var(--secondary)', 'title'=>'Expert Teachers',         'desc'=>'Qualified and experienced teachers dedicated to student success and growth.'],
                ['icon'=>'fas fa-laptop',          'color'=>'var(--accent)',    'title'=>'Modern Facilities',       'desc'=>'Well-equipped classrooms, computer lab, and learning resources for effective education.'],
                ['icon'=>'fas fa-award',           'color'=>'var(--primary)',   'title'=>'Scholarship Programs',    'desc'=>'Financial assistance available for deserving students to ensure no one is left behind.'],
                ['icon'=>'fas fa-users',           'color'=>'var(--secondary)', 'title'=>'Inclusive Environment',   'desc'=>'A welcoming and inclusive school community that celebrates diversity and teamwork.'],
                ['icon'=>'fas fa-calendar-check',  'color'=>'var(--accent)',    'title'=>'Extra Activities',        'desc'=>'Sports, cultural programs, and events to develop well-rounded personalities.'],
            ];
            foreach($features as $f):
            ?>
            <div class="col-md-6 col-lg-4" data-animate>
                <div class="d-flex gap-4 p-4 rounded-3 h-100" style="background:var(--light);">
                    <div style="flex-shrink:0;">
                        <div style="width:55px;height:55px;background:<?= $f['color'] ?>;border-radius:12px;
                                    display:flex;align-items:center;justify-content:center;">
                            <i class="<?= $f['icon'] ?> fa-lg text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-2"><?= $f['title'] ?></h5>
                        <p class="text-muted mb-0" style="font-size:14px;"><?= $f['desc'] ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Messages Carousel -->
<section class="bg-light-custom">
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Messages from Leadership</h2>
            <p>Words of guidance and inspiration from our school's leadership team</p>
        </div>

        <div id="leadershipCarousel" class="carousel slide leadership-carousel" data-bs-ride="carousel" data-bs-interval="5000" data-animate>

            <!-- Indicators -->
            <!-- <div class="carousel-indicators leadership-indicators">
                <button type="button" data-bs-target="#leadershipCarousel" data-bs-slide-to="0" class="active" aria-label="Principal"></button>
                <button type="button" data-bs-target="#leadershipCarousel" data-bs-slide-to="1" aria-label="Chairperson"></button>
                <button type="button" data-bs-target="#leadershipCarousel" data-bs-slide-to="2" aria-label="Managing Director"></button>
                <button type="button" data-bs-target="#leadershipCarousel" data-bs-slide-to="3" aria-label="Vice Principal"></button>
            </div> -->

            <div class="carousel-inner">

                <!-- Slide 1: Principal -->
                <div class="carousel-item active">
                    <div class="leadership-card">
                        <div class="leadership-photo-wrap">
                            <img src="./image/principa.jpeg" alt="Principal"
                                 onerror="this.src='https://ui-avatars.com/api/?name=Principal&background=1b6b35&color=fff&size=120'">
                            <div class="leadership-role-badge" style="background:var(--primary);">
                                <i class="fas fa-user-tie me-1"></i>Principal
                            </div>
                        </div>
                        <div class="leadership-message">
                            <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                            <p>
                                "At Hilal Public Secondary School, we believe that education is not just about academics —
                                it is about building character, fostering curiosity, and preparing our students for life.
                                We are committed to providing every child with the tools they need to succeed,
                                and we are proud of the dedicated team of educators who make this vision a reality every day."
                            </p>
                            <div class="leadership-name">Principal</div>
                            <div class="leadership-school">Hilal Public Secondary School</div>
                        </div>
                    </div>
                </div>

                <!-- Slide 2: Chairperson -->
                <div class="carousel-item">
                    <div class="leadership-card">
                        <div class="leadership-photo-wrap">
                            <img src="./image/Chairperson.jpeg" alt="Chairperson"
                                 onerror="this.src='https://ui-avatars.com/api/?name=Chairperson&background=b5281f&color=fff&size=120'">
                            <div class="leadership-role-badge" style="background:#b5281f;">
                                <i class="fas fa-crown me-1"></i>Chairperson
                            </div>
                        </div>
                        <div class="leadership-message">
                            <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                            <p>
                                "Our school stands as a beacon of hope and knowledge for the communities of Harinagar and Ghuski.
                                We have always believed that every child, regardless of background or circumstance,
                                deserves access to quality education. Together with our dedicated staff and supportive community,
                                we continue to grow stronger each year."
                            </p>
                            <div class="leadership-name">Chairperson</div>
                            <div class="leadership-school">Hilal Public Secondary School</div>
                        </div>
                    </div>
                </div>

                <!-- Slide 3: Managing Director -->
                <div class="carousel-item">
                    <div class="leadership-card">
                        <div class="leadership-photo-wrap">
                            <img src="./image/director.jpeg" alt="Managing Director"
                                 onerror="this.src='https://ui-avatars.com/api/?name=Managing+Director&background=0284c7&color=fff&size=120'">
                            <div class="leadership-role-badge" style="background:#0284c7;">
                                <i class="fas fa-briefcase me-1"></i>Managing Director
                            </div>
                        </div>
                        <div class="leadership-message">
                            <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                            <p>
                                "Our vision has always been to build an institution that bridges the gap between
                                traditional Islamic education and modern academic excellence. The results we have
                                achieved — 100% SEE pass rates for over 13 consecutive years — stand as a testament
                                to the commitment of our teachers, students, and the entire Hilal family."
                            </p>git status
                            <div class="leadership-name">Managing Director</div>
                            <div class="leadership-school">Hilal Public Secondary School</div>
                        </div>
                    </div>
                </div>

                <!-- Slide 4: Vice Principal -->
                <div class="carousel-item">
                    <div class="leadership-card">
                        <div class="leadership-photo-wrap">
                            <img src="./image/vice-principal.jpeg" alt="Vice Principal"
                                 onerror="this.src='https://ui-avatars.com/api/?name=Vice+Principal&background=7c3aed&color=fff&size=120'">
                            <div class="leadership-role-badge" style="background:#7c3aed;">
                                <i class="fas fa-chalkboard-teacher me-1"></i>Vice Principal
                            </div>
                        </div>
                        <div class="leadership-message">
                            <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                            <p>
                                "The strength of our school lies in the dedication of our teachers and the enthusiasm
                                of our students. We are focused on creating a learning environment where every student
                                feels inspired, supported, and challenged to reach their full potential.
                                Academic excellence and moral values go hand in hand at Hilal."
                            </p>
                            <div class="leadership-name">Vice Principal</div>
                            <div class="leadership-school">Hilal Public Secondary School</div>
                        </div>
                    </div>
                </div>

            </div><!-- /.carousel-inner -->

            <!-- Prev/Next controls -->
            <button class="carousel-control-prev leadership-prev" type="button" data-bs-target="#leadershipCarousel" data-bs-slide="prev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-control-next leadership-next" type="button" data-bs-target="#leadershipCarousel" data-bs-slide="next">
                <i class="fas fa-chevron-right"></i>
            </button>

        </div><!-- /#leadershipCarousel -->
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
