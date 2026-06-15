// ============================================================
//  Digital Quran Center Hub — Main JS
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Auto-dismiss flash messages ─────────────────────────
  const flash = document.querySelector('.alert[style*="margin:0"]');
  if (flash) setTimeout(() => flash.style.display='none', 5000);

  // ── Close modals on outside click ───────────────────────
  document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });
  });

  // ── Escape key closes modals ────────────────────────────
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('[id$="Modal"]').forEach(m => m.style.display='none');
    }
  });

  // ── Active nav link highlight ───────────────────────────
  const currentPath = window.location.pathname;
  document.querySelectorAll('.navbar-nav a').forEach(a => {
    if (a.getAttribute('href') === currentPath) a.classList.add('active');
  });

  // ── Table row highlight on click ────────────────────────
  document.querySelectorAll('tbody tr').forEach(tr => {
    tr.style.cursor = 'pointer';
  });

  // ── Attendance radio visual feedback ────────────────────
  document.querySelectorAll('.att-radio').forEach(r => {
    r.addEventListener('change', function() {
      const row = this.closest('tr');
      row.style.background = '';
      if (this.value === 'present') row.style.background = 'rgba(212,250,212,.3)';
      if (this.value === 'absent')  row.style.background = 'rgba(255,200,200,.3)';
      if (this.value === 'late')    row.style.background = 'rgba(255,243,200,.3)';
    });
  });

  // ── Progress slider update ───────────────────────────────
  const slider = document.querySelector('input[type="range"]');
  if (slider) {
    const valEl = document.getElementById('pctVal');
    slider.addEventListener('input', () => {
      if (valEl) valEl.textContent = slider.value + '%';
    });
  }

  // ── Confirm before delete forms ──────────────────────────
  document.querySelectorAll('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', e => {
      if (!confirm(f.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Live search filter for tables ───────────────────────
  const liveSearch = document.getElementById('liveSearch');
  if (liveSearch) {
    liveSearch.addEventListener('input', function() {
      const q = this.value.toLowerCase();
      document.querySelectorAll('tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  // ── Animate stat values ──────────────────────────────────
  document.querySelectorAll('.stat-value').forEach(el => {
    const target = parseInt(el.textContent.replace(/\D/g,''));
    if (isNaN(target) || target === 0) return;
    let current = 0;
    const step  = Math.ceil(target / 30);
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = el.textContent.replace(/\d+/, current);
      if (current >= target) clearInterval(timer);
    }, 30);
  });

  // ── Animate progress bars ────────────────────────────────
  document.querySelectorAll('.progress-bar-fill').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = w; }, 200);
  });

});
