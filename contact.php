<?php
$pageTitle = 'Contact Us';
require_once 'includes/header.php';

// Static contact info - no database dependency
$schoolAddress   = 'Dharan-8, Sunsari, Nepal';
$schoolPhone     = '+977-980-7071324';
$schoolEmail     = 'hilalpublicschool095@gmail.com';
$schoolName      = 'Hilal Public Secondary School';
$contactPerson1  = 'Admin Office';
$contactPhone1   = '+977-980-7071324';
$contactPerson2  = 'Principal';
$contactPhone2   = '+977-980-7071324';
$principalName   = 'Mr. Principal';
$facebookUrl     = 'https://facebook.com/hilalpublicschool';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-envelope me-2"></i>Contact Us</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Contact Us</li>
            </ol>
        </nav>
    </div>
</div>

<section>
    <div class="container">
        <div class="section-title" data-animate>
            <h2>Get In Touch</h2>
            <p>We'd love to hear from you. Reach out to us with any questions, admissions inquiries, or feedback.</p>
        </div>

        <div class="row g-5">
            <!-- Contact Info Cards -->
            <div class="col-lg-4" data-animate>
                <!-- Address -->
                <div class="d-flex gap-3 mb-4 p-4 rounded-3" style="background:var(--light);">
                    <div style="width:50px;height:50px;background:var(--primary);border-radius:12px;
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-map-marker-alt text-white"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Our Address</h6>
                        <p class="text-muted mb-0" style="font-size:14px;"><?= htmlspecialchars($schoolAddress) ?></p>
                    </div>
                </div>

                <!-- Contacts -->
                <div class="d-flex gap-3 mb-4 p-4 rounded-3" style="background:var(--light);">
                    <div style="width:50px;height:50px;background:var(--secondary);border-radius:12px;
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-phone-alt text-white"></i>
                    </div>
                    <div style="flex:1;">
                        <h6 class="fw-bold mb-2">Contact Numbers</h6>
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">School</div>
                                <a href="tel:<?= htmlspecialchars($schoolPhone) ?>"
                                   style="font-size:14px;font-weight:600;color:var(--primary);">
                                    <?= htmlspecialchars($schoolPhone) ?>
                                </a>
                            </div>
                        </div>
                        <?php if($contactPhone1): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <div style="font-size:12px;font-weight:700;color:var(--text-muted);">
                                    <?= htmlspecialchars($contactPerson1) ?>
                                </div>
                                <a href="tel:<?= htmlspecialchars($contactPhone1) ?>"
                                   style="font-size:14px;font-weight:600;color:var(--primary);">
                                    <?= htmlspecialchars($contactPhone1) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if($contactPhone2): ?>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div style="font-size:12px;font-weight:700;color:var(--text-muted);">
                                    <?= htmlspecialchars($contactPerson2) ?>
                                </div>
                                <a href="tel:<?= htmlspecialchars($contactPhone2) ?>"
                                   style="font-size:14px;font-weight:600;color:var(--primary);">
                                    <?= htmlspecialchars($contactPhone2) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Email -->
                <div class="d-flex gap-3 mb-4 p-4 rounded-3" style="background:var(--light);">
                    <div style="width:50px;height:50px;background:var(--accent);border-radius:12px;
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-envelope" style="color:var(--dark);"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Email Address</h6>
                        <a href="mailto:<?= $schoolEmail ?>" class="text-muted" style="font-size:14px;">
                            <?= htmlspecialchars($schoolEmail) ?>
                        </a>
                    </div>
                </div>

                <!-- School Hours -->
                <div class="d-flex gap-3 p-4 rounded-3" style="background:var(--light);">
                    <div style="width:50px;height:50px;background:var(--primary-dark);border-radius:12px;
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">School Hours</h6>
                        <p class="text-muted mb-0" style="font-size:14px;line-height:1.8;">
                            Sun – Thu: 7:00 AM – 1:00 PM<br>
                            <span style="color:var(--secondary);">Friday: Closed<br>Saturday: Closed</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="col-lg-8" data-animate>
                <div class="form-card">
                    <h3 class="fw-bold text-primary-custom mb-4">
                        <i class="fas fa-paper-plane me-2"></i>Send Us a Message
                    </h3>
                    <div id="contactAlert"></div>
                    <form id="contactForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                <input type="text" name="sender_name" class="form-control"
                                       placeholder="Enter your full name" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="sender_email" class="form-control"
                                       placeholder="Enter your email" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="sender_phone" class="form-control"
                                       placeholder="Your phone number" maxlength="20">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <select name="subject" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <option value="General Inquiry">General Inquiry</option>
                                    <option value="Admission Inquiry">Admission Inquiry</option>
                                    <option value="Scholarship Inquiry">Scholarship Inquiry</option>
                                    <option value="Result Inquiry">Result Inquiry</option>
                                    <option value="Fee Inquiry">Fee Inquiry</option>
                                    <option value="Teacher/Staff Inquiry">Teacher/Staff Inquiry</option>
                                    <option value="Complaint">Complaint</option>
                                    <option value="Suggestion">Suggestion</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" rows="5"
                                          placeholder="Write your message here..." required maxlength="1000"></textarea>
                                <div class="form-text text-end" id="charCount">0 / 1000</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-primary-custom"
                                        style="border:none;cursor:pointer;padding:12px 35px;font-size:15px;">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="pt-0">
    <div class="container">
        <div class="rounded-3 overflow-hidden shadow" style="height:400px;background:var(--light);
             display:flex;align-items:center;justify-content:center;">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d661.6598075970815!2d87.10068496977686!3d26.46089821665724!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2snp!4v1781724584598!5m2!1sen!2snp"
                width="100%" height="400" style="border:0;" allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
        <p class="text-center text-muted mt-2" style="font-size:13px;">
            <i class="fas fa-info-circle me-1"></i>
            School location map embedded above.
        </p>
    </div>
</section>

<script>
$(document).ready(function(){
    // Character counter for textarea
    $('textarea[name="message"]').on('input', function(){
        const len = $(this).val().length;
        $('#charCount').text(len + ' / 1000');
        if(len > 900) $('#charCount').addClass('text-danger');
        else $('#charCount').removeClass('text-danger');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>