<?php
// ─── Workspaces Page ─────────────────────────────────────────
$page_title = 'Workspaces';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#4f46e5';
        if ($name) {
            $db->prepare("INSERT INTO workspaces (name, description, color, created_by) VALUES (?,?,?,?)")->execute([$name, $desc, $color, $uid]);
            $wid = $db->lastInsertId();
            $db->prepare("INSERT IGNORE INTO workspace_members (workspace_id, user_id, role) VALUES (?,?,'owner')")->execute([$wid, $uid]);
        }
    }
    if ($act === 'delete') {
        $wid = (int)($_POST['workspace_id'] ?? 0);
        if ($wid) $db->prepare("DELETE FROM workspaces WHERE id=?")->execute([$wid]);
    }
    header('Location: workspaces.php');
    exit;
}

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

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>


  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Workspaces</h2>
        <p class="text-muted small mb-0"><?= count($workspaces) ?> workspace<?= count($workspaces)!=1?'s':'' ?></p>
      </div>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWsModal" id="create-ws-btn">
        <i class="bi bi-plus-lg me-1"></i>New Workspace
      </button>
    </div>
  </div>

  <div class="app-content">
    <?php if (empty($workspaces)): ?>
    <div class="text-center py-5">
      <i class="bi bi-grid-1x2 fs-1 d-block mb-3 opacity-25"></i>
      <h5 class="text-muted">No workspaces yet</h5>
      <?php if (is_admin()): ?>
      <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createWsModal">Create First Workspace</button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($workspaces as $w): ?>
      <div class="col-sm-6 col-lg-4" id="ws-card-<?= $w['id'] ?>">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="rounded-2 d-flex align-items-center justify-content-center text-white fw-bold" style="width:50px;height:50px;font-size:1rem;background:<?= htmlspecialchars($w['color']) ?>;">
                <?= strtoupper(substr($w['name'],0,2)) ?>
              </div>
              <?php if (is_admin()): ?>
              <div class="dropdown">
                <button class="btn btn-sm btn-link text-muted p-1" data-bs-toggle="dropdown" id="ws-menu-<?= $w['id'] ?>">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                  <li><a class="dropdown-item" href="projects.php?workspace_id=<?= $w['id'] ?>"><i class="bi bi-kanban me-2"></i>View Projects</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <form method="POST" onsubmit="return confirm('Delete workspace?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="workspace_id" value="<?= $w['id'] ?>">
                      <button type="submit" class="dropdown-item text-danger" id="del-ws-<?= $w['id'] ?>"><i class="bi bi-trash me-2"></i>Delete</button>
                    </form>
                  </li>
                </ul>
              </div>
              <?php endif; ?>
            </div>
            <h3 class="h6 fw-bold mb-1"><?= htmlspecialchars($w['name']) ?></h3>
            <p class="small text-muted mb-3"><?= htmlspecialchars($w['description'] ?? '') ?></p>
            <div class="d-flex gap-3">
              <div class="text-center">
                <div class="fw-bold"><?= $w['project_count'] ?></div>
                <div class="x-small text-muted">Projects</div>
              </div>
              <div class="text-center">
                <div class="fw-bold"><?= $w['member_count'] ?></div>
                <div class="x-small text-muted">Members</div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-top py-2 px-3 d-flex align-items-center justify-content-between">
            <span class="x-small text-muted">by <?= htmlspecialchars($w['creator_name']) ?></span>
            <a href="projects.php?workspace_id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary" id="ws-view-<?= $w['id'] ?>">View Projects</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- Create Workspace Modal -->
<?php if (is_admin()): ?>
<div class="modal fade" id="createWsModal" tabindex="-1" aria-labelledby="createWsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="create-ws-form">
        <input type="hidden" name="action" value="create">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold" id="createWsModalLabel"><i class="bi bi-plus-circle me-2 text-primary"></i>Create Workspace</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Workspace Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. TechCorp HQ" required id="ws-name-input">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="What is this workspace for?" id="ws-desc-input"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Color</label>
            <div class="d-flex gap-2 flex-wrap" id="ws-color-picker">
              <?php foreach (['#4f46e5','#7c3aed','#0891b2','#059669','#d97706','#dc2626','#db2777','#0f172a'] as $c): ?>
              <label class="d-flex align-items-center justify-content-center rounded-2" style="width:32px;height:32px;background:<?= $c ?>;cursor:pointer;">
                <input type="radio" name="color" value="<?= $c ?>" class="d-none" <?= $c==='#4f46e5'?'checked':'' ?>>
                <i class="bi bi-check text-white" style="display:none;"></i>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="create-ws-submit"><i class="bi bi-check-lg me-1"></i>Create</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$page_scripts = <<<JS
<script>
// Color picker checkmarks
document.querySelectorAll('#ws-color-picker label').forEach(label => {
  label.addEventListener('click', () => {
    document.querySelectorAll('#ws-color-picker i').forEach(i => i.style.display = 'none');
    label.querySelector('i').style.display = 'block';
  });
});
const firstLabel = document.querySelector('#ws-color-picker label');
if (firstLabel) firstLabel.querySelector('i').style.display = 'block';
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
