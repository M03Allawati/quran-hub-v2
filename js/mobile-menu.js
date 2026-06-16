// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    // Create hamburger button if not exists
    if (!document.querySelector('.mobile-menu-toggle')) {
        const btn = document.createElement('button');
        btn.className = 'mobile-menu-toggle';
        btn.innerHTML = '☰';
        btn.setAttribute('aria-label', 'Toggle menu');
        document.body.insertBefore(btn, document.body.firstChild);
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        // Toggle sidebar
        btn.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                btn.innerHTML = sidebar.classList.contains('mobile-open') ? '✕' : '☰';
            }
        });
        
        // Close on overlay click
        overlay.addEventListener('click', function() {
            document.querySelector('.sidebar')?.classList.remove('mobile-open');
            overlay.classList.remove('active');
            btn.innerHTML = '☰';
        });
        
        // Close on link click (mobile UX)
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    document.querySelector('.sidebar')?.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    btn.innerHTML = '☰';
                }
            });
        });
    }
});
