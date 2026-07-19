    </div>
</div>

<script src="../assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    $('#sidebarToggle').on('click', function(){
        if($(window).width() > 991){
            $('body').toggleClass('sidebar-collapsed');
        } else {
            $('#adminSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        }
    });
    $('#sidebarOverlay').on('click', function(){
        $('#adminSidebar').removeClass('show');
        $(this).removeClass('show');
    });
    setTimeout(function(){ $('.alert-auto-dismiss').fadeOut(500); }, 5000);

    // Auto-inject CSRF token into every form
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (csrfToken) {
        document.querySelectorAll('form').forEach(function(form) {
            if (!form.querySelector('input[name="csrf_token"]')) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'csrf_token'; inp.value = csrfToken;
                form.appendChild(inp);
            }
        });
    }
});
</script>
</body>
</html>
