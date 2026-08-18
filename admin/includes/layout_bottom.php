    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<!-- Bootstrap 5 JS — Local -->
<script src="../assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function(){
    // Sidebar toggle (desktop — collapse/expand)
    $('#sidebarToggle').on('click', function(){
        if($(window).width() > 991){
            $('body').toggleClass('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', $('body').hasClass('sidebar-collapsed'));
        } else {
            const isOpen = $('#adminSidebar').hasClass('show');
            
            if (isOpen) {
                // Close sidebar
                $('#adminSidebar').removeClass('show');
                $('#sidebarOverlay').removeClass('show');
                $('body').removeClass('sidebar-open');
            } else {
                // Open sidebar
                $('#adminSidebar').addClass('show');
                $('#sidebarOverlay').addClass('show');
                $('body').addClass('sidebar-open');
            }
        }
    });

    // Restore sidebar state
    if(localStorage.getItem('sidebarCollapsed') === 'true' && $(window).width() > 991){
        $('body').addClass('sidebar-collapsed');
    }

    // Close sidebar on overlay click (mobile)
    $('#sidebarOverlay').on('click', function(){
        closeMobileSidebar();
    });

    // Mobile sidebar navigation fixes
    function closeMobileSidebar() {
        if ($(window).width() <= 991) {
            $('#adminSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
            $('body').removeClass('sidebar-open');
        }
    }

    // Close sidebar when clicking navigation links on mobile
    $('.nav-item').on('click', function(e) {
        // Only close if it's an actual navigation (has href)
        if ($(this).attr('href') && $(this).attr('href') !== '#') {
            closeMobileSidebar();
        }
    });

    // Handle browser back/forward button
    $(window).on('pageshow', function(event) {
        // Close sidebar when page is shown (including back button)
        closeMobileSidebar();
        
        // If page was loaded from cache (back/forward button)
        if (event.originalEvent.persisted) {
            closeMobileSidebar();
        }
    });

    // Close sidebar on window resize to desktop
    $(window).on('resize', function() {
        if ($(window).width() > 991) {
            $('#adminSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
            $('body').removeClass('sidebar-open');
        }
    });

    // Close sidebar on popstate (browser navigation)
    $(window).on('popstate', function() {
        closeMobileSidebar();
    });

    // Ensure sidebar is closed on page load (mobile)
    $(window).on('load', function() {
        closeMobileSidebar();
    });

    // Enhanced escape key handling
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $(window).width() <= 991) {
            closeMobileSidebar();
        }
    });

    // Handle visibility change (tab switching)
    $(document).on('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            closeMobileSidebar();
        }
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function(){
        $('.alert-auto-dismiss').fadeOut(500);
    }, 5000);

    // Auto-inject CSRF token into every form
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (csrfToken) {
        document.querySelectorAll('form').forEach(function(form) {
            if (!form.querySelector('input[name="csrf_token"]')) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'csrf_token';
                inp.value = csrfToken;
                form.appendChild(inp);
            }
        });
    }

    // Sidebar tooltip — show on hover when collapsed
    (function(){
        var tip = document.createElement('div');
        tip.className = 'sidebar-tooltip';
        document.body.appendChild(tip);

        var timer;
        document.getElementById('adminSidebar').addEventListener('mouseover', function(e){
            if (!document.body.classList.contains('sidebar-collapsed')) return;
            var el = e.target.closest('[data-tooltip]');
            if (!el) return;
            clearTimeout(timer);
            var rect = el.getBoundingClientRect();
            tip.textContent = el.getAttribute('data-tooltip');
            tip.style.top = (rect.top + rect.height / 2) + 'px';
            tip.style.transform = 'translateY(-50%) translateX(-4px)';
            tip.classList.add('show');
            tip.style.transform = 'translateY(-50%) translateX(0)';
        });
        document.getElementById('adminSidebar').addEventListener('mouseout', function(e){
            var el = e.target.closest('[data-tooltip]');
            if (!el) return;
            tip.classList.remove('show');
        });
    })();
});
</script>
</body>
</html>
