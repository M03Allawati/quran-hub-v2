(function() {
    function initMobileMenu() {
        // Remove existing if any
        document.querySelectorAll('.mobile-menu-toggle, .sidebar-overlay').forEach(e => e.remove());
        
        // Create button
        const btn = document.createElement('button');
        btn.className = 'mobile-menu-toggle';
        btn.innerHTML = '☰';
        btn.setAttribute('aria-label', 'Menu');
        btn.type = 'button';
        document.body.appendChild(btn);
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) {
            console.log('No sidebar found');
            return;
        }
        
        function openMenu() {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
            btn.innerHTML = '✕';
            document.body.style.overflow = 'hidden';
        }
        
        function closeMenu() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            btn.innerHTML = '☰';
            document.body.style.overflow = '';
        }
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('mobile-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });
        
        overlay.addEventListener('click', closeMenu);
        
        // Close on link click
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeMenu();
                }
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();
