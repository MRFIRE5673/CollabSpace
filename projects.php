<?php
// ─── Projects Page ───────────────────────────────────────────
$page_title = 'Projects';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Handle POST (legacy fallback — AJAX preferred)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $pid = (int)($_POST['project_id'] ?? 0);
        if ($pid && is_admin()) {
            $db->prepare("DELETE FROM projects WHERE id=?")->execute([$pid]);
        }
        header('Location: projects.php');
        exit;
    }
}

// Fetch with search/filter
$search   = trim($_GET['q'] ?? '');
$filter_s = $_GET['status'] ?? '';
$filter_p = $_GET['priority'] ?? '';

$where  = '1=1';
$params = [];
if ($search)   { $where .= " AND p.name LIKE ?";   $params[] = "%$search%"; }
if ($filter_s) { $where .= " AND p.status=?";       $params[] = $filter_s; }
if ($filter_p) { $where .= " AND p.priority=?";     $params[] = $filter_p; }

if (!is_admin()) {
    $where .= " AND (p.created_by = ? OR p.manager_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?))";
    $params[] = $uid;
    $params[] = $uid;
    $params[] = $uid;
}

$stmt = $db->prepare("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') AS done_count,
           (SELECT COUNT(*) FROM project_members WHERE project_id=p.id) AS member_count
    FROM projects p
    JOIN users u ON u.id = p.manager_id
    WHERE $where
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Fetch all users for manager dropdown
$all_users  = $db->query("SELECT id, name, role FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
$workspaces = $db->query("SELECT * FROM workspaces ORDER BY name")->fetchAll();

$status_colors   = ['planning'=>'info','active'=>'primary','on_hold'=>'warning','completed'=>'success','cancelled'=>'danger'];
$priority_colors = ['low'=>'success','medium'=>'info','high'=>'warning','critical'=>'danger'];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-kanban-fill me-2 text-primary"></i>Projects</h2>
        <p class="text-muted small mb-0"><?= count($projects) ?> project<?= count($projects) != 1 ? 's' : '' ?> found</p>
      </div>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createProjectModal" id="create-project-btn">
        <i class="bi bi-plus-lg me-1"></i>New Project
      </button>
    </div>
  </div>

  <div class="app-content">

    <!-- Search & Filters -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="projects-filter-form">
          <div class="input-group input-group-sm" style="max-width:280px;">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" class="form-control border-0 bg-body-secondary" placeholder="Search projects…" value="<?= htmlspecialchars($search) ?>" id="projects-search-input">
          </div>
          <select name="status" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:160px;" id="projects-status-filter">
            <option value="">All Status</option>
            <?php foreach (['planning','active','on_hold','completed','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter_s===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="priority" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:160px;" id="projects-priority-filter">
            <option value="">All Priority</option>
            <?php foreach (['low','medium','high','critical'] as $p): ?>
            <option value="<?= $p ?>" <?= $filter_p===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-primary" id="projects-filter-submit">Filter</button>
          <?php if ($search || $filter_s || $filter_p): ?>
          <a href="projects.php" class="btn btn-sm btn-outline-secondary" id="projects-filter-clear">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Projects Grid -->
    <?php if (empty($projects)): ?>
    <div class="text-center py-5" id="projects-empty-state">
      <i class="bi bi-kanban fs-1 d-block mb-3 opacity-25"></i>
      <h5 class="text-muted">No projects found</h5>
      <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createProjectModal">Create your first project</button>
    </div>
    <?php else: ?>
    <div class="row g-3" id="projects-grid">
      <?php foreach ($projects as $p): ?>
      <?php
        $done_pct = $p['task_count'] > 0 ? round(($p['done_count']/$p['task_count'])*100) : ($p['progress'] ?? 0);
        $pcol = $priority_colors[$p['priority']] ?? 'secondary';
      ?>
      <div class="col-sm-6 col-lg-4" id="project-card-<?= $p['id'] ?>">
        <div class="card h-100 position-relative overflow-hidden">
          <div class="position-absolute top-0 start-0 end-0" style="height:4px;background:linear-gradient(90deg,#4f46e5,#7c3aed);"></div>
          <div class="card-body pt-4">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-2 d-flex align-items-center justify-content-center text-white fw-bold" style="width:42px;height:42px;font-size:.8rem;background:<?= htmlspecialchars($p['color'] ?? '#4f46e5') ?>;">
                  <?= strtoupper(substr($p['name'],0,2)) ?>
                </div>
                <div>
                  <?= status_badge($p['status']) ?>
                </div>
              </div>
              <div class="dropdown">
                <button class="btn btn-sm btn-link text-muted p-1" data-bs-toggle="dropdown" id="project-menu-<?= $p['id'] ?>">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                  <li><a class="dropdown-item" href="project_details.php?id=<?= $p['id'] ?>"><i class="bi bi-eye me-2"></i>View Details</a></li>
                  <?php if (is_manager()): ?>
                  <li><a class="dropdown-item" href="tasks.php?project_id=<?= $p['id'] ?>"><i class="bi bi-kanban me-2"></i>Task Board</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <?php if (is_admin()): ?>
                  <li>
                    <form method="POST" onsubmit="return confirm('Delete this project?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                    </form>
                  </li>
                  <?php endif; ?>
                  <?php endif; ?>
                </ul>
              </div>
            </div>

            <h5 class="fw-bold mb-1 fs-6"><?= htmlspecialchars($p['name']) ?></h5>
            <p class="text-muted small mb-3 lh-sm" style="min-height:36px;"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 90)) ?><?= strlen($p['description'] ?? '') > 90 ? '…' : '' ?></p>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="x-small text-muted">Progress</span>
                <span class="x-small fw-semibold"><?= $done_pct ?>%</span>
              </div>
              <div class="progress">
                <div class="progress-bar progress-bar-animated-custom" style="width:<?= $done_pct ?>%;background:linear-gradient(90deg,#4f46e5,#7c3aed);"></div>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex gap-3">
                <span class="x-small text-muted"><i class="bi bi-check2-square me-1"></i><?= $p['done_count'] ?>/<?= $p['task_count'] ?> tasks</span>
                <span class="x-small text-muted"><i class="bi bi-people me-1"></i><?= $p['member_count'] ?> members</span>
              </div>
              <?= priority_badge($p['priority']) ?>
            </div>
          </div>
          <div class="card-footer bg-transparent border-top py-2 px-3 d-flex align-items-center justify-content-between">
            <span class="x-small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['manager_name']) ?></span>
            <?php if ($p['due_date']): ?>
            <span class="x-small text-muted <?= strtotime($p['due_date']) < time() && $p['status'] != 'completed' ? 'text-danger' : '' ?>">
              <i class="bi bi-calendar3 me-1"></i><?= date('M j, Y', strtotime($p['due_date'])) ?>
            </span>
            <?php endif; ?>
          </div>
          <a href="project_details.php?id=<?= $p['id'] ?>" class="stretched-link"></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- Create Project Modal (AJAX — no page reload) -->
<div class="modal fade" id="createProjectModal" tabindex="-1" aria-labelledby="createProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <form id="create-project-form" onsubmit="return handleCreateProject(event)">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold" id="createProjectModalLabel"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Create New Project</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Project Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Website Redesign" required id="project-name-input">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="What is this project about?" id="project-desc-input"></textarea>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Status</label>
              <select name="status" class="form-select" id="project-status-select">
                <option value="planning">Planning</option>
                <option value="active" selected>Active</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Priority</label>
              <select name="priority" class="form-select" id="project-priority-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Start Date</label>
              <input type="date" name="start_date" class="form-control" id="project-start-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Due Date</label>
              <input type="date" name="due_date" class="form-control" id="project-due-date">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Project Manager</label>
              <select name="manager_id" class="form-select" id="project-manager-select">
                <?php foreach ($all_users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $u['id']==$uid?'selected':'' ?>><?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Workspace</label>
              <select name="workspace_id" class="form-select" id="project-workspace-select">
                <option value="">No Workspace</option>
                <?php foreach ($workspaces as $w): ?>
                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div id="project-create-status" class="mt-3"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="create-project-submit"><i class="bi bi-check-lg me-1"></i>Create Project</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'PGJS'
<script>
async function handleCreateProject(e) {
  e.preventDefault();
  const btn    = document.getElementById('create-project-submit');
  const status = document.getElementById('project-create-status');
  const form   = document.getElementById('create-project-form');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

  const fd = new FormData(form);
  fd.append('action', 'create');

  try {
    const res  = await fetch('api/projects.php?action=create', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success && data.project) {
      const p   = data.project;
      let grid  = document.getElementById('projects-grid');
      const emptyState = document.getElementById('projects-empty-state');
      if (emptyState) emptyState.classList.add('d-none');
      if (!grid) {
        grid = document.createElement('div');
        grid.className = 'row g-3';
        grid.id = 'projects-grid';
        document.querySelector('.app-content').appendChild(grid);
      }
      const statusMap   = {planning:'info',active:'primary',on_hold:'warning',completed:'success',cancelled:'danger'};
      const priorityMap = {low:'success',medium:'info',high:'warning',critical:'danger'};
      const sc = statusMap[p.status] || 'secondary';
      const pc = priorityMap[p.priority] || 'secondary';
      const col = document.createElement('div');
      col.className = 'col-sm-6 col-lg-4';
      col.id = 'project-card-' + p.id;
      col.innerHTML = `
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden" style="border-radius:16px;background:var(--cs-surface);">
          <div class="position-absolute top-0 start-0 end-0" style="height:4px;background:linear-gradient(90deg,#4f46e5,#7c3aed);"></div>
          <div class="card-body pt-4">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <span class="badge bg-${sc} bg-opacity-15 text-${sc} fw-semibold">${p.status.replace('_',' ')}</span>
              <span class="badge bg-${pc} bg-opacity-15 text-${pc} fw-semibold">${p.priority}</span>
            </div>
            <h6 class="fw-bold mb-1">${escHtml(p.name)}</h6>
            <p class="small text-muted mb-3">${escHtml(p.description || '')}</p>
            <div class="progress mb-3" style="height:6px;border-radius:99px;"><div class="progress-bar bg-primary" style="width:0%"></div></div>
            <div class="d-flex justify-content-between x-small text-muted">
              <span>0/0 tasks</span><span>${escHtml(p.manager_name)}</span>
            </div>
          </div>
          <a href="project_details.php?id=${p.id}" class="stretched-link"></a>
        </div>`;
      grid.prepend(col);
      form.reset();
      bootstrap.Modal.getInstance(document.getElementById('createProjectModal')).hide();
    } else {
      status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">${data.message || 'Create failed.'}</div>`;
    }
  } catch(err) {
    status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Connection error.</div>`;
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Project';
  return false;
}
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
PGJS;
include __DIR__ . '/includes/footer.php';
?>
