<?php
// ─── Workspaces Hub ──────────────────────────────────────────
$page_title = 'Workspaces';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

if (is_admin()) {
    $workspaces = $db->query("
        SELECT w.*,
               u.name AS creator_name,
               (SELECT COUNT(*) FROM workspace_members WHERE workspace_id=w.id) AS member_count,
               (SELECT COUNT(*) FROM projects WHERE workspace_id=w.id) AS project_count
        FROM workspaces w JOIN users u ON u.id=w.created_by
        ORDER BY w.created_at DESC
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT w.*,
               u.name AS creator_name,
               (SELECT COUNT(*) FROM workspace_members WHERE workspace_id=w.id) AS member_count,
               (SELECT COUNT(*) FROM projects WHERE workspace_id=w.id) AS project_count
        FROM workspaces w JOIN users u ON u.id=w.created_by
        WHERE w.created_by = ? OR w.id IN (SELECT workspace_id FROM workspace_members WHERE user_id = ?)
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$uid, $uid]);
    $workspaces = $stmt->fetchAll();
}

$ws_colors = ['#4f46e5','#7c3aed','#0891b2','#059669','#d97706','#dc2626','#db2777','#0f172a'];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="app-content-header py-3 px-4 border-bottom">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h2 class="fw-bold mb-0 fs-4" style="font-family:'Outfit',sans-serif;">
        <i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Workspaces
      </h2>
      <p class="text-muted small mb-0" id="ws-count-subtitle"><?= count($workspaces) ?> workspace<?= count($workspaces) != 1 ? 's' : '' ?></p>
    </div>
    <button class="btn btn-primary px-4 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createWsModal" id="create-ws-btn">
      <i class="bi bi-plus-lg me-1"></i> New Workspace
    </button>
  </div>
</div>

<div class="app-content p-4">

  <!-- Empty State -->
  <div id="ws-empty-state" class="text-center py-5 <?= !empty($workspaces) ? 'd-none' : '' ?>">
    <div class="mb-4">
      <div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-primary bg-opacity-10" style="width:80px;height:80px;">
        <i class="bi bi-grid-1x2 text-primary" style="font-size:2rem;"></i>
      </div>
    </div>
    <h4 class="fw-bold mb-2" style="font-family:'Outfit'">No workspaces yet</h4>
    <p class="text-muted mb-4">Create your first workspace to organize projects and collaborate with your team.</p>
    <button class="btn btn-primary px-4 py-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#createWsModal">
      <i class="bi bi-plus-lg me-1"></i> Create First Workspace
    </button>
  </div>

  <!-- Workspace Cards Grid -->
  <div class="row g-4" id="ws-grid">
    <?php foreach ($workspaces as $w):
      $initials = strtoupper(substr($w['name'], 0, 2));
      $color    = htmlspecialchars($w['color']);
      $isOwner  = ($w['created_by'] == $uid || is_admin());
    ?>
    <div class="col-sm-6 col-lg-4" id="ws-card-<?= $w['id'] ?>">
      <div class="card h-100 border-0 shadow-sm" style="border-radius:18px;overflow:hidden;background:var(--cs-surface);">

        <!-- Color Banner -->
        <div class="d-flex align-items-center justify-content-between px-4 py-3" style="background:<?= $color ?>;min-height:72px;">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center fw-bold text-white bg-white bg-opacity-25" style="width:44px;height:44px;font-size:1.1rem;letter-spacing:-.02em;">
              <?= $initials ?>
            </div>
            <div>
              <div class="fw-bold text-white fs-6 mb-0" style="font-family:'Outfit';"><?= htmlspecialchars($w['name']) ?></div>
              <div class="text-white opacity-75" style="font-size:.72rem;">by <?= htmlspecialchars($w['creator_name']) ?></div>
            </div>
          </div>
          <?php if ($isOwner): ?>
          <div class="dropdown">
            <button class="btn p-1 border-0 text-white opacity-75" data-bs-toggle="dropdown">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end border-0 shadow" style="border-radius:12px;">
              <li><a class="dropdown-item py-2" href="workspace_detail.php?id=<?= $w['id'] ?>"><i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Open Workspace</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item py-2 text-danger" onclick="deleteWorkspace(<?= $w['id'] ?>, '<?= htmlspecialchars(addslashes($w['name'])) ?>')"><i class="bi bi-trash me-2"></i>Delete</button></li>
            </ul>
          </div>
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="card-body p-4">
          <?php if ($w['description']): ?>
          <p class="text-muted small mb-3"><?= htmlspecialchars($w['description']) ?></p>
          <?php else: ?>
          <p class="text-muted small mb-3 fst-italic opacity-50">No description provided.</p>
          <?php endif; ?>

          <div class="d-flex gap-4 mb-4">
            <div class="text-center">
              <div class="fw-bold fs-5" style="color:<?= $color ?>;"><?= $w['project_count'] ?></div>
              <div class="x-small text-muted">Projects</div>
            </div>
            <div class="text-center">
              <div class="fw-bold fs-5"><?= $w['member_count'] ?></div>
              <div class="x-small text-muted">Members</div>
            </div>
          </div>

          <a href="workspace_detail.php?id=<?= $w['id'] ?>" class="btn btn-primary w-100 rounded-3 fw-semibold" id="ws-open-<?= $w['id'] ?>">
            <i class="bi bi-box-arrow-in-right me-1"></i> Open Workspace
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal: Create Workspace (AJAX — all users) -->
<div class="modal fade" id="createWsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" style="font-family:'Outfit';">
          <i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Create New Workspace
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="create-ws-form" onsubmit="return handleCreateWorkspace(event)">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Workspace Name <span class="text-danger">*</span></label>
            <input type="text" id="ws-name-input" class="form-control" placeholder="e.g. TechCorp HQ" required autocomplete="off">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea id="ws-desc-input" class="form-control" rows="3" placeholder="What is this workspace for?"></textarea>
          </div>
          <div class="mb-4">
            <label class="form-label small fw-semibold">Color</label>
            <div class="d-flex gap-2 flex-wrap" id="ws-color-picker">
              <?php foreach ($ws_colors as $c): ?>
              <label class="ws-color-swatch d-flex align-items-center justify-content-center rounded-3 position-relative" style="width:36px;height:36px;background:<?= $c ?>;cursor:pointer;" title="<?= $c ?>">
                <input type="radio" name="ws_color" value="<?= $c ?>" class="d-none" <?= $c==='#4f46e5'?'checked':'' ?>>
                <i class="bi bi-check2 text-white fw-bold" style="font-size:1.1rem;display:<?= $c==='#4f46e5'?'block':'none' ?>;"></i>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div id="ws-create-status" class="mb-3"></div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4 fw-semibold" id="create-ws-submit">
              <i class="bi bi-check-lg me-1"></i> Create Workspace
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
// Color Picker
document.querySelectorAll('.ws-color-swatch').forEach(swatch => {
  swatch.addEventListener('click', () => {
    document.querySelectorAll('.ws-color-swatch i').forEach(i => i.style.display = 'none');
    swatch.querySelector('i').style.display = 'block';
  });
});

// AJAX Create Workspace
async function handleCreateWorkspace(e) {
  e.preventDefault();
  const btn  = document.getElementById('create-ws-submit');
  const name = document.getElementById('ws-name-input').value.trim();
  const desc = document.getElementById('ws-desc-input').value.trim();
  const colorInput = document.querySelector('#ws-color-picker input[name="ws_color"]:checked');
  const color = colorInput ? colorInput.value : '#4f46e5';
  const status = document.getElementById('ws-create-status');

  if (!name) return false;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
  status.innerHTML = '';

  const fd = new FormData();
  fd.append('action', 'create');
  fd.append('name', name);
  fd.append('description', desc);
  fd.append('color', color);

  try {
    const res  = await fetch('api/workspaces.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success && data.workspace) {
      const w = data.workspace;
      const initials = w.name.substring(0, 2).toUpperCase();

      // Hide empty state
      const emptyState = document.getElementById('ws-empty-state');
      if (emptyState) emptyState.classList.add('d-none');

      // Prepend new card to grid
      const grid = document.getElementById('ws-grid');
      const col  = document.createElement('div');
      col.className = 'col-sm-6 col-lg-4';
      col.id = 'ws-card-' + w.id;
      col.innerHTML = `
        <div class="card h-100 border-0 shadow-sm" style="border-radius:18px;overflow:hidden;background:var(--cs-surface);">
          <div class="d-flex align-items-center justify-content-between px-4 py-3" style="background:${w.color};min-height:72px;">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center fw-bold text-white bg-white bg-opacity-25" style="width:44px;height:44px;font-size:1.1rem;">${initials}</div>
              <div>
                <div class="fw-bold text-white fs-6 mb-0">${escapeHtml(w.name)}</div>
                <div class="text-white opacity-75" style="font-size:.72rem;">by ${escapeHtml(w.creator_name)}</div>
              </div>
            </div>
            <div class="dropdown">
              <button class="btn p-1 border-0 text-white opacity-75" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
              <ul class="dropdown-menu dropdown-menu-end border-0 shadow" style="border-radius:12px;">
                <li><a class="dropdown-item py-2" href="workspace_detail.php?id=${w.id}"><i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Open Workspace</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item py-2 text-danger" onclick="deleteWorkspace(${w.id},'${escapeHtml(w.name)}')"><i class="bi bi-trash me-2"></i>Delete</button></li>
              </ul>
            </div>
          </div>
          <div class="card-body p-4">
            ${w.description ? `<p class="text-muted small mb-3">${escapeHtml(w.description)}</p>` : '<p class="text-muted small mb-3 fst-italic opacity-50">No description provided.</p>'}
            <div class="d-flex gap-4 mb-4">
              <div class="text-center"><div class="fw-bold fs-5" style="color:${w.color};">0</div><div class="x-small text-muted">Projects</div></div>
              <div class="text-center"><div class="fw-bold fs-5">1</div><div class="x-small text-muted">Members</div></div>
            </div>
            <a href="workspace_detail.php?id=${w.id}" class="btn btn-primary w-100 rounded-3 fw-semibold">
              <i class="bi bi-box-arrow-in-right me-1"></i> Open Workspace
            </a>
          </div>
        </div>`;
      grid.prepend(col);

      // Update subtitle count
      const subtitle = document.getElementById('ws-count-subtitle');
      if (subtitle) {
        const cur = parseInt(subtitle.textContent) || 0;
        subtitle.textContent = (cur + 1) + ' workspace' + (cur + 1 !== 1 ? 's' : '');
      }

      // Reset & close modal
      document.getElementById('create-ws-form').reset();
      document.querySelectorAll('.ws-color-swatch i').forEach(i => i.style.display = 'none');
      document.querySelector('.ws-color-swatch i').style.display = 'block';
      bootstrap.Modal.getInstance(document.getElementById('createWsModal')).hide();

    } else {
      status.innerHTML = `<div class="alert alert-danger py-2 small">${data.message || 'Create failed.'}</div>`;
    }
  } catch (err) {
    status.innerHTML = `<div class="alert alert-danger py-2 small">Connection error. Try again.</div>`;
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Create Workspace';
  return false;
}

// AJAX Delete Workspace
async function deleteWorkspace(id, name) {
  if (!confirm(`Delete workspace "${name}"? All projects in this workspace will be unlinked.`)) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('workspace_id', id);
  try {
    const res  = await fetch('api/workspaces.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      const card = document.getElementById('ws-card-' + id);
      if (card) { card.style.opacity = '0'; card.style.transform = 'scale(0.85)'; setTimeout(() => card.remove(), 220); }
    } else {
      alert(data.message || 'Delete failed.');
    }
  } catch { alert('Connection error.'); }
}

function escapeHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
