<?php
$pageTitle = 'Check Results';
require_once 'includes/header.php';

// Get available exam years
$yearsResult = $conn->query("SELECT DISTINCT academic_year FROM exams ORDER BY academic_year DESC");
$years = $yearsResult->fetch_all(MYSQLI_ASSOC);

// Get classes
$classesResult = $conn->query("SELECT * FROM classes WHERE status='active' ORDER BY id");
$classes = $classesResult->fetch_all(MYSQLI_ASSOC);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-poll-h me-2"></i>Check Results</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Results</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Result Checker -->
<section style="background: linear-gradient(135deg, var(--primary-dark), var(--primary)); padding: 60px 0;">
    <div class="container">
        <div class="result-form-box" data-animate>
            <div class="text-center mb-4">
                <img src="assets/images/logo.png" alt="Logo" style="width:65px;height:65px;object-fit:contain;"
                     onerror="this.style.display='none'">
                <h3 class="mt-3"><i class="fas fa-poll-h me-2"></i>Result Checker</h3>
                <p class="text-muted" style="font-size:14px;">Enter your details below to view your exam result</p>
            </div>

            <div id="resultAlert"></div>

            <form id="resultCheckerForm" action="result_view.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Exam Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="exam_year" name="exam_year" required>
                            <option value="">-- Select Year --</option>
                            <?php foreach($years as $yr): ?>
                            <option value="<?= $yr['academic_year'] ?>"><?= $yr['academic_year'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Exam Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="exam_type" name="exam_type" required>
                            <option value="">-- Select Exam --</option>
                            <option value="1st_terminal">1st Terminal Exam</option>
                            <option value="2nd_terminal">2nd Terminal Exam</option>
                            <option value="final">Final Exam</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Class <span class="text-danger">*</span></label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Roll No / Symbol No <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="roll_no" name="roll_no"
                               placeholder="Enter your Roll No or Symbol No" required maxlength="30">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="dob" name="dob" required
                               placeholder="YYYY-MM-DD or DD/MM/YYYY">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            AD or BS — e.g. 2010-05-15 &nbsp;or&nbsp; 15/05/2010 &nbsp;|&nbsp; Used for identity verification.
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn w-100 fw-bold py-3"
                                style="background:var(--primary);color:white;border:none;border-radius:8px;font-size:16px;">
                            <i class="fas fa-search me-2"></i>Check My Result
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Result Output removed — opens in result_view.php -->
    </div>
</section>

<!-- Info Section -->
<section class="bg-light-custom py-5">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-md-10">
                <div class="row g-4">
                    <div class="col-md-4 text-center" data-animate>
                        <div style="width:70px;height:70px;background:var(--primary);border-radius:50%;
                                    display:flex;align-items:center;justify-content:center;margin:0 auto 15px;">
                            <i class="fas fa-list-ol fa-2x text-white"></i>
                        </div>
                        <h5 class="fw-bold">Step 1</h5>
                        <p class="text-muted" style="font-size:14px;">Select your exam year, exam type, and class.</p>
                    </div>
                    <div class="col-md-4 text-center" data-animate>
                        <div style="width:70px;height:70px;background:var(--secondary);border-radius:50%;
                                    display:flex;align-items:center;justify-content:center;margin:0 auto 15px;">
                            <i class="fas fa-id-card fa-2x text-white"></i>
                        </div>
                        <h5 class="fw-bold">Step 2</h5>
                        <p class="text-muted" style="font-size:14px;">Enter your Roll No or Symbol No and Date of Birth.</p>
                    </div>
                    <div class="col-md-4 text-center" data-animate>
                        <div style="width:70px;height:70px;background:var(--accent);border-radius:50%;
                                    display:flex;align-items:center;justify-content:center;margin:0 auto 15px;">
                            <i class="fas fa-file-alt fa-2x" style="color:var(--dark)"></i>
                        </div>
                        <h5 class="fw-bold">Step 3</h5>
                        <p class="text-muted" style="font-size:14px;">Click "Check My Result" to view and print your result.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- GPA Table -->
        <div class="row justify-content-center mt-4">
            <div class="col-md-8" data-animate>
                <div class="card">
                    <div class="card-header-custom"><i class="fas fa-table me-2"></i>GPA Grading System</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0 text-center" style="font-size:14px;">
                                <thead style="background:var(--light);">
                                    <tr>
                                        <th>Marks Range</th>
                                        <th>Grade</th>
                                        <th>Grade Point</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>90 – 100</td><td><span class="grade-badge grade-A-plus">A+</span></td><td>4.0</td><td>Outstanding</td></tr>
                                    <tr><td>80 – 89</td><td><span class="grade-badge grade-A">A</span></td><td>3.6</td><td>Excellent</td></tr>
                                    <tr><td>70 – 79</td><td><span class="grade-badge grade-B-plus">B+</span></td><td>3.2</td><td>Very Good</td></tr>
                                    <tr><td>60 – 69</td><td><span class="grade-badge grade-B">B</span></td><td>2.8</td><td>Good</td></tr>
                                    <tr><td>50 – 59</td><td><span class="grade-badge grade-C-plus">C+</span></td><td>2.4</td><td>Satisfactory</td></tr>
                                    <tr><td>40 – 49</td><td><span class="grade-badge grade-C">C</span></td><td>2.0</td><td>Acceptable</td></tr>
                                    <tr><td>30 – 39</td><td><span class="grade-badge grade-D">D</span></td><td>1.6</td><td>Insufficient</td></tr>
                                    <tr><td>Below 30</td><td><span class="grade-badge grade-NG">NG</span></td><td>0.0</td><td>Not Graded</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
