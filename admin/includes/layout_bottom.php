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
            $('#adminSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        }
    });

    // Restore sidebar state
    if(localStorage.getItem('sidebarCollapsed') === 'true' && $(window).width() > 991){
        $('body').addClass('sidebar-collapsed');
    }

    // Close sidebar on overlay click (mobile)
    $('#sidebarOverlay').on('click', function(){
        $('#adminSidebar').removeClass('show');
        $(this).removeClass('show');
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
