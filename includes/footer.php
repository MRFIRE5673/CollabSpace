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
<!-- Dragula (drag-and-drop) -->
<script src="https://cdn.jsdelivr.net/npm/dragula@3.7.3/dist/dragula.min.js" crossorigin="anonymous"></script>
<!-- Custom JS -->
<script src="js/realtime.js?v=<?= time() ?>"></script>
<script src="js/kanban.js?v=<?= time() ?>"></script>

<script>
// ─── Sidebar Toggle ───────────────────────────────────────
document.querySelectorAll('[data-lte-toggle="sidebar"]').forEach(el => {
  el.addEventListener('click', function(e) {
    e.preventDefault();
    document.body.classList.toggle('sidebar-collapsed');
    document.body.classList.toggle('sidebar-open');
  });
});
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
      icon.className = t === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
    }
    if (label) {
      label.textContent = t === 'dark' ? 'Dark Mode' : 'Light Mode';
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

<!-- ── Floating Whiteboard Widget ────────────────────────── -->
<div class="position-fixed" style="bottom: 24px; right: 24px; z-index: 1040;" id="whiteboard-widget-btn">
  <button class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center p-0"
          style="width:54px;height:54px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:2px solid rgba(255,255,255,0.3);transition:transform .2s ease;"
          data-bs-toggle="modal" data-bs-target="#whiteboardModal" title="Open Interactive Whiteboard">
    <i class="bi bi-palette-fill fs-4 text-white"></i>
  </button>
</div>

<!-- Modal: Interactive Whiteboard -->
<div class="modal fade" id="whiteboardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen p-2 p-md-3">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;overflow:hidden;background:var(--cs-surface);">
      <div class="modal-header bg-body-tertiary border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-palette-fill text-primary fs-4"></i>
          <h5 class="modal-title fw-bold mb-0" style="font-family:'Outfit',sans-serif;">Interactive Team Whiteboard</h5>
          <span class="badge bg-primary bg-opacity-15 text-primary ms-2">Live Canvas</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <!-- Color Palette -->
          <div class="d-flex align-items-center gap-1 bg-body p-1 rounded-pill border me-2">
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn active" style="width:24px;height:24px;background:#6366f1;" data-color="#6366f1"></button>
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn" style="width:24px;height:24px;background:#10b981;" data-color="#10b981"></button>
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn" style="width:24px;height:24px;background:#f59e0b;" data-color="#f59e0b"></button>
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn" style="width:24px;height:24px;background:#ef4444;" data-color="#ef4444"></button>
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn" style="width:24px;height:24px;background:#ec4899;" data-color="#ec4899"></button>
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn" style="width:24px;height:24px;background:#ffffff;border:1px solid #ccc;" data-color="#ffffff"></button>
            <button class="btn btn-sm rounded-circle p-0 wb-color-btn" style="width:24px;height:24px;background:#1e293b;" data-color="#1e293b"></button>
          </div>

          <!-- Tools -->
          <div class="btn-group btn-group-sm me-2">
            <button class="btn btn-outline-secondary active" id="wb-tool-draw" onclick="setWBTool('draw')"><i class="bi bi-pencil-fill me-1"></i>Draw</button>
            <button class="btn btn-outline-secondary" id="wb-tool-erase" onclick="setWBTool('erase')"><i class="bi bi-eraser-fill me-1"></i>Eraser</button>
          </div>

          <input type="range" class="form-range me-2" id="wb-size-slider" min="2" max="30" value="4" style="width:80px;" title="Brush Size">

          <button class="btn btn-sm btn-outline-danger me-2" onclick="clearWhiteboard()"><i class="bi bi-trash me-1"></i>Clear</button>
          <button class="btn btn-sm btn-outline-success me-2" onclick="downloadWhiteboard()"><i class="bi bi-download me-1"></i>Export PNG</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0 position-relative d-flex align-items-center justify-content-center bg-body-tertiary" style="overflow:hidden;height:calc(100vh - 80px);">
        <canvas id="wb-canvas" style="cursor:crosshair;background:var(--cs-surface);width:100%;height:100%;"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
// Whiteboard Engine
(function() {
  let canvas, ctx;
  let isDrawing = false;
  let currentColor = '#6366f1';
  let currentTool = 'draw';
  let brushSize = 4;

  const modalEl = document.getElementById('whiteboardModal');
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', function () {
      canvas = document.getElementById('wb-canvas');
      if (!canvas) return;
      ctx = canvas.getContext('2d');

      const rect = canvas.getBoundingClientRect();
      if (canvas.width !== rect.width || canvas.height !== rect.height) {
        canvas.width = rect.width;
        canvas.height = rect.height;
      }
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
    });
  }

  window.setWBTool = function(tool) {
    currentTool = tool;
    document.getElementById('wb-tool-draw').classList.toggle('active', tool === 'draw');
    document.getElementById('wb-tool-erase').classList.toggle('active', tool === 'erase');
  };

  window.clearWhiteboard = function() {
    if (ctx && canvas) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
  };

  window.downloadWhiteboard = function() {
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = 'collabspace-whiteboard-' + Date.now() + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  };

  document.querySelectorAll('.wb-color-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.wb-color-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentColor = this.dataset.color;
      setWBTool('draw');
    });
  });

  const slider = document.getElementById('wb-size-slider');
  if (slider) {
    slider.addEventListener('input', function() { brushSize = this.value; });
  }

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
  }

  function startDraw(e) {
    if (!canvas || !ctx) return;
    isDrawing = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
  }

  function draw(e) {
    if (!isDrawing || !ctx) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.strokeStyle = currentTool === 'erase' ? '#1e293b' : currentColor;
    ctx.lineWidth = currentTool === 'erase' ? brushSize * 4 : brushSize;
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
  }

  function stopDraw() { isDrawing = false; }

  document.addEventListener('DOMContentLoaded', () => {
    const c = document.getElementById('wb-canvas');
    if (c) {
      c.addEventListener('mousedown', startDraw);
      c.addEventListener('mousemove', draw);
      c.addEventListener('mouseup', stopDraw);
      c.addEventListener('mouseleave', stopDraw);

      c.addEventListener('touchstart', startDraw, { passive: false });
      c.addEventListener('touchmove', draw, { passive: false });
      c.addEventListener('touchend', stopDraw);
    }
  });
})();
</script>

<?php if (isset($page_scripts)) echo $page_scripts; ?>
</body>
</html>
