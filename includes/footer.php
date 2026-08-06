<?php
// ─── Shared Page Footer ──────────────────────────────────
?>
    </div><!-- /.app-content -->
  </main><!-- /.app-main -->
</div><!-- /.app-wrapper -->

<!-- OverlayScrollbars -->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es5.min.js" crossorigin="anonymous"></script>
<!-- Bootstrap Bundle (Popper included) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<!-- AdminLTE JS -->
<script src="js/adminlte.min.js"></script>
<!-- Dragula (drag-and-drop) -->
<script src="https://cdn.jsdelivr.net/npm/dragula@3.7.3/dist/dragula.min.js" crossorigin="anonymous"></script>
<!-- Custom JS -->
<script src="js/realtime.js"></script>
<script src="js/kanban.js"></script>

<script>
// ─── Theme Toggle (Pill Button) ───────────────────────────
(function () {
  const btn      = document.getElementById('theme-toggle');
  const icon     = document.getElementById('theme-icon');
  const label    = document.getElementById('theme-label');
  const html     = document.documentElement;
  const KEY      = 'lte-theme';

  function applyTheme(t) {
    html.setAttribute('data-bs-theme', t);
    html.style.colorScheme = t;
    try { localStorage.setItem(KEY, t); } catch (_) {}

    if (icon) {
      icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }
    if (label) {
      label.textContent = t === 'dark' ? 'Light' : 'Dark';
    }
  }

  // Init on load
  const current = html.getAttribute('data-bs-theme') || 'light';
  applyTheme(current);

  if (btn) {
    btn.addEventListener('click', () => {
      const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
    });
  }
})();

// ─── Animate progress bars on scroll / load ───────────────
(function () {
  const bars = document.querySelectorAll('.progress-bar');
  bars.forEach(bar => {
    const target = bar.style.width;
    bar.style.width = '0%';
    requestAnimationFrame(() => {
      setTimeout(() => { bar.style.transition = 'width .9s cubic-bezier(.4,0,.2,1)'; bar.style.width = target; }, 80);
    });
  });
})();

// ─── Mark Notification as Read ───────────────────────────
document.querySelectorAll('.notif-item').forEach(el => {
  el.addEventListener('click', function () {
    const id = this.dataset.id;
    if (id) fetch('api/notifications.php?action=read&id=' + id);
  });
});
document.querySelector('.mark-all-read')?.addEventListener('click', (e) => {
  e.preventDefault();
  fetch('api/notifications.php?action=read_all').then(() => {
    document.querySelectorAll('.notif-item').forEach(el => {
      el.classList.remove('bg-primary', 'bg-opacity-10');
    });
    const badge = document.getElementById('notif-count');
    if (badge) badge.classList.add('d-none');
  });
});
</script>

<?php if (isset($page_scripts)) echo $page_scripts; ?>
</body>
</html>
