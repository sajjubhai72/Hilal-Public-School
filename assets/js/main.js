/* =====================================================
   HILAL PUBLIC SECONDARY SCHOOL — Main JS
   ===================================================== */

$(document).ready(function () {

    /* ── Counter Animation ─────────────────────────── */
    function animateCounters() {
        $('.stat-number[data-target]').each(function () {
            const target = parseInt($(this).data('target'));
            const step   = target / (2000 / 16);
            let current  = 0;
            const el     = $(this);
            const timer  = setInterval(function () {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.text(Math.floor(current) + (el.data('suffix') || ''));
            }, 16);
        });
    }
    let counterDone = false;
    $(window).on('scroll.counter', function () {
        if (!counterDone && $('.stats-section').length) {
            const top = $('.stats-section').offset().top;
            if ($(window).scrollTop() + $(window).height() > top + 100) {
                counterDone = true;
                animateCounters();
                $(window).off('scroll.counter');
            }
        }
    });

    /* ── Scroll To Top ─────────────────────────────── */
    $('body').append('<button class="scroll-top-btn" id="scrollTopBtn"><i class="fas fa-arrow-up"></i></button>');
    $(window).on('scroll', function () {
        $(this).scrollTop() > 300 ? $('#scrollTopBtn').fadeIn(300) : $('#scrollTopBtn').fadeOut(300);
    });
    $('#scrollTopBtn').on('click', function () {
        $('html, body').animate({ scrollTop: 0 }, 600);
    });

    /* ── Gallery Lightbox ──────────────────────────── */
    if ($('.gallery-item').length) {
        $('body').append(`
            <div id="galleryModal" class="modal fade" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content bg-dark border-0">
                        <div class="modal-header border-0">
                            <h6 class="modal-title text-white" id="galleryModalTitle"></h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-2">
                            <img src="" id="galleryModalImg" class="w-100" style="border-radius:8px;" alt="Gallery">
                        </div>
                    </div>
                </div>
            </div>`);
        $(document).on('click', '.gallery-item', function () {
            $('#galleryModalImg').attr('src', $(this).find('img').attr('src'));
            $('#galleryModalTitle').text($(this).data('title') || '');
            $('#galleryModal').modal('show');
        });
    }

    /* ── Result Checker Form — Direct GET submit ───── */
    if ($('#resultCheckerForm').length) {
        $('#resultCheckerForm').on('submit', function (e) {
            const examYear = $('#exam_year').val();
            const examType = $('#exam_type').val();
            const classId  = $('#class_id').val();
            const rollNo   = $.trim($('#roll_no').val());
            const dob      = $.trim($('#dob').val());

            if (!examYear || !examType || !classId || !rollNo || !dob) {
                e.preventDefault();
                showAlert('#resultAlert', 'danger', 'Please fill all fields correctly.');
                return false;
            }
            // Valid — let form submit naturally (no preventDefault)
            const btn = $(this).find('[type=submit]');
            btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Searching...');
            // Don't disable — allow form to submit
        });
    }

    /* ── Contact Form ──────────────────────────────── */
    if ($('#contactForm').length) {
        $('#contactForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $(this).find('[type=submit]');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Sending...');
            $.ajax({
                url: 'api/send_contact.php', method: 'POST',
                data: $(this).serialize(), dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Send Message');
                    if (res.success) {
                        showAlert('#contactAlert', 'success', 'Message sent! We will get back to you soon.');
                        $('#contactForm')[0].reset();
                    } else {
                        showAlert('#contactAlert', 'danger', res.message || 'Failed to send.');
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Send Message');
                    showAlert('#contactAlert', 'danger', 'Server error. Please try again.');
                }
            });
        });
    }

    /* ── Admission Form ────────────────────────────── */
    if ($('#admissionForm').length) {
        $('#admissionForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $(this).find('[type=submit]');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
            $.ajax({
                url: 'api/submit_admission.php', method: 'POST',
                data: new FormData(this), processData: false, contentType: false, dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Application');
                    if (res.success) {
                        showAlert('#admissionAlert', 'success', 'Application submitted! Ref: <strong>' + res.ref_no + '</strong>');
                        $('#admissionForm')[0].reset();
                    } else { showAlert('#admissionAlert', 'danger', res.message || 'Submission failed.'); }
                },
                error: function () {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Application');
                    showAlert('#admissionAlert', 'danger', 'Server error.');
                }
            });
        });
    }

    /* ── Scholarship Form ──────────────────────────── */
    if ($('#scholarshipForm').length) {
        $('#scholarshipForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $(this).find('[type=submit]');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
            $.ajax({
                url: 'api/submit_scholarship.php', method: 'POST',
                data: new FormData(this), processData: false, contentType: false, dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Application');
                    if (res.success) {
                        showAlert('#scholarshipAlert', 'success', 'Scholarship application submitted!');
                        $('#scholarshipForm')[0].reset();
                    } else { showAlert('#scholarshipAlert', 'danger', res.message || 'Submission failed.'); }
                },
                error: function () {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Application');
                    showAlert('#scholarshipAlert', 'danger', 'Server error.');
                }
            });
        });
    }

    /* ── Utility: Show Alert ───────────────────────── */
    function showAlert(selector, type, message) {
        $(selector).html(`
            <div class="alert alert-${type} alert-dismissible fade show">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`);
        setTimeout(() => $(selector).find('.alert').alert('close'), 6000);
    }

    /* ── Scroll Animate ────────────────────────────── */
    function checkVisibility() {
        $('[data-animate]').each(function () {
            if ($(window).scrollTop() + $(window).height() > $(this).offset().top + 80) {
                $(this).addClass('animated');
            }
        });
    }
    $(window).on('scroll', checkVisibility);
    checkVisibility();

    /* ── Char counter for textarea ─────────────────── */
    $('textarea[name="message"]').on('input', function () {
        const len = $(this).val().length;
        $('#charCount').text(len + ' / 1000');
        len > 900 ? $('#charCount').addClass('text-danger') : $('#charCount').removeClass('text-danger');
    });

});

/* ── Scroll top button CSS ─────────────────────────── */
const s = document.createElement('style');
s.textContent = `
.scroll-top-btn {
    position:fixed; bottom:28px; right:28px; width:42px; height:42px;
    background:var(--primary); color:white; border:none; border-radius:50%;
    font-size:15px; cursor:pointer; display:none; z-index:9999;
    box-shadow:0 3px 15px rgba(0,0,0,0.2); transition:all 0.3s;
}
.scroll-top-btn:hover { background:var(--accent); color:var(--dark); transform:translateY(-3px); }
[data-animate] { opacity:0; transform:translateY(22px); transition:opacity 0.55s ease,transform 0.55s ease; }
[data-animate].animated { opacity:1; transform:translateY(0); }
@media print {
    .main-navbar,.top-bar,footer,.scroll-top-btn,.page-header { display:none!important; }
}`;
document.head.appendChild(s);
