(function() {
    'use strict';
    
    function initMobileMenu() {
        // Remove any existing
        document.querySelectorAll('.mobile-menu-toggle, .sidebar-overlay').forEach(e => e.remove());
        
        // Find sidebar - try multiple selectors
        let sidebar = document.querySelector('aside.sidebar') 
                   || document.querySelector('.sidebar')
                   || document.querySelector('[class*="sidebar"]');
        
        if (!sidebar) {
            // No sidebar = public page (home/login) - still show menu with navbar links
            createPublicMenu();
            return;
        }
        
        // Create hamburger
        const btn = document.createElement('button');
        btn.className = 'mobile-menu-toggle';
        btn.innerHTML = '☰';
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Menu');
        document.body.appendChild(btn);
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        function open() {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
            btn.innerHTML = '✕';
            document.body.style.overflow = 'hidden';
        }
        function close() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            btn.innerHTML = '☰';
            document.body.style.overflow = '';
        }
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.contains('mobile-open') ? close() : open();
        });
        
        overlay.addEventListener('click', close);
        
        // Close on link click
        sidebar.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', function() {
                if (window.innerWidth <= 768) close();
            });
        });
    }
    
    function createPublicMenu() {
        // For pages without sidebar (home, login) - convert navbar to mobile menu
        const navbar = document.querySelector('.navbar');
        const navItems = document.querySelector('.navbar-nav');
        
        if (!navbar || !navItems) return;
        
        // Create a sidebar from navbar items
        const mobileSidebar = document.createElement('aside');
        mobileSidebar.className = 'sidebar mobile-public-sidebar';
        mobileSidebar.innerHTML = navItems.innerHTML;
        mobileSidebar.style.cssText = 'list-style:none;padding-top:60px;';
        document.body.appendChild(mobileSidebar);
        
        // Style the cloned links
        mobileSidebar.querySelectorAll('a').forEach(a => {
            a.style.cssText = 'display:block;padding:12px 16px;color:#4C1D95;text-decoration:none;border-bottom:1px solid #f0f0f0;font-size:14px;';
        });
        
        // Create hamburger
        const btn = document.createElement('button');
        btn.className = 'mobile-menu-toggle';
        btn.innerHTML = '☰';
        btn.type = 'button';
        document.body.appendChild(btn);
        
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        function open() {
            mobileSidebar.classList.add('mobile-open');
            overlay.classList.add('active');
            btn.innerHTML = '✕';
        }
        function close() {
            mobileSidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            btn.innerHTML = '☰';
        }
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            mobileSidebar.classList.contains('mobile-open') ? close() : open();
        });
        overlay.addEventListener('click', close);
        mobileSidebar.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', close);
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();
