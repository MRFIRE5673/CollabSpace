<?php
// ─── Workspace Detail Hub ─────────────────────────────────────
$page_title = 'Workspace';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$wid = (int)($_GET['id'] ?? 0);
if (!$wid) { header('Location: workspaces.php'); exit; }

$stmt = $db->prepare("
    SELECT w.*, u.name AS creator_name
    FROM workspaces w JOIN users u ON u.id=w.created_by
    WHERE w.id=?
");
$stmt->execute([$wid]);
$ws = $stmt->fetch();
if (!$ws) { header('Location: workspaces.php'); exit; }

// Access check: must be member, owner, or admin
$isMember = is_admin();
if (!$isMember) {
    $mc = $db->prepare("SELECT 1 FROM workspace_members WHERE workspace_id=? AND user_id=?");
    $mc->execute([$wid, $uid]);
    $isMember = (bool)$mc->fetchColumn();
}
if (!$isMember) { header('Location: workspaces.php'); exit; }

$isOwner = ($ws['created_by'] == $uid || is_admin());

// Fetch projects in this workspace
$projects_stmt = $db->prepare("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') AS done_count,
           (SELECT COUNT(*) FROM project_members WHERE project_id=p.id) AS member_count
    FROM projects p
    JOIN users u ON u.id=p.manager_id
    WHERE p.workspace_id=?
    ORDER BY p.created_at DESC
");
$projects_stmt->execute([$wid]);
$projects = $projects_stmt->fetchAll();

// Fetch workspace members
$members_stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.status, wm.role
    FROM workspace_members wm JOIN users u ON u.id=wm.user_id
    WHERE wm.workspace_id=? ORDER BY wm.role DESC, u.name ASC
");
$members_stmt->execute([$wid]);
$members = $members_stmt->fetchAll();

// All users (for project manager dropdown)
$all_users = $db->query("SELECT id, name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();

$page_title = $ws['name'] . ' | Workspace';
$status_colors = ['planning'=>'info','active'=>'primary','on_hold'=>'warning','completed'=>'success','cancelled'=>'danger'];
$priority_colors = ['low'=>'success','medium'=>'info','high'=>'warning','critical'=>'danger'];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="app-content-header py-3 px-4 border-bottom" style="background:var(--cs-surface);">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <a href="workspaces.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i>Workspaces
      </a>
      <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:46px;height:46px;background:<?= htmlspecialchars($ws['color']) ?>;font-size:1rem;">
        <?= strtoupper(substr($ws['name'], 0, 2)) ?>
      </div>
      <div>
        <h2 class="fw-bold mb-0 fs-4" style="font-family:'Outfit';"><?= htmlspecialchars($ws['name']) ?></h2>
        <p class="text-muted small mb-0"><?= count($projects) ?> project<?= count($projects)!=1?'s':'' ?> · <?= count($members) ?> member<?= count($members)!=1?'s':'' ?></p>
      </div>
    </div>
    <div class="d-flex gap-2">
      <?php if ($isOwner): ?>
      <button class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#inviteMemberModal">
        <i class="bi bi-person-plus-fill me-1"></i>Invite Member
      </button>
      <?php endif; ?>
      <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createProjectModal">
        <i class="bi bi-plus-lg me-1"></i>New Project
      </button>
    </div>
  </div>
  <?php if ($ws['description']): ?>
  <p class="text-muted small mt-2 mb-0 ps-1"><?= htmlspecialchars($ws['description']) ?></p>
  <?php endif; ?>
</div>

<div class="app-content p-4">
  <div class="row g-4">

    <!-- Projects Area (left/main) -->
    <div class="col-lg-8">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold mb-0" style="font-family:'Outfit'"><i class="bi bi-kanban-fill me-2 text-primary"></i>Projects</h5>
        <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createProjectModal">
          <i class="bi bi-plus-lg me-1"></i>Add Project
        </button>
      </div>

      <!-- Empty projects state -->
      <div id="projects-empty" class="text-center py-5 card border-0 shadow-sm <?= !empty($projects) ? 'd-none' : '' ?>" style="border-radius:16px;">
        <div class="card-body">
          <i class="bi bi-kanban text-muted opacity-25 fs-1 d-block mb-3"></i>
          <h6 class="fw-bold mb-1">No projects yet</h6>
          <p class="small text-muted mb-3">Create your first project in this workspace.</p>
          <button class="btn btn-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            <i class="bi bi-plus-lg me-1"></i>Create Project
          </button>
        </div>
      </div>

      <div class="row g-3" id="ws-projects-grid">
        <?php foreach ($projects as $p):
          $pct = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
          $sc  = $status_colors[$p['status']] ?? 'secondary';
          $pc  = $priority_colors[$p['priority']] ?? 'secondary';
        ?>
        <div class="col-sm-6" id="proj-card-<?= $p['id'] ?>">
          <div class="card h-100 border-0 shadow-sm" style="border-radius:16px;background:var(--cs-surface);">
            <div class="card-body p-4">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                  <h6 class="fw-bold mb-1" style="font-family:'Outfit';">
                    <a href="project_details.php?id=<?= $p['id'] ?>" class="text-decoration-none text-body"><?= htmlspecialchars($p['name']) ?></a>
                  </h6>
                  <div class="d-flex gap-1 flex-wrap">
                    <?= status_badge($p['status']) ?>
                    <?= priority_badge($p['priority']) ?>
                  </div>
                </div>
                <a href="project_details.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size:.72rem;">Open</a>
              </div>
              <?php if ($p['description']): ?>
              <p class="small text-muted mb-3 text-truncate"><?= htmlspecialchars($p['description']) ?></p>
              <?php endif; ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between x-small text-muted mb-1">
                  <span>Progress</span><span><?= $pct ?>%</span>
                </div>
                <div class="progress" style="height:6px;border-radius:99px;">
                  <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
              <div class="d-flex justify-content-between x-small text-muted">
                <span><i class="bi bi-check2-square me-1"></i><?= $p['task_count'] ?> tasks</span>
                <span><i class="bi bi-people me-1"></i><?= $p['member_count'] ?> members</span>
                <span>by <?= htmlspecialchars($p['manager_name']) ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Members Panel (right) -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm" style="border-radius:16px;background:var(--cs-surface);">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between py-3 px-4">
          <h6 class="fw-bold mb-0" style="font-family:'Outfit';"><i class="bi bi-people-fill me-2 text-primary"></i>Members</h6>
          <?php if ($isOwner): ?>
          <button class="btn btn-outline-primary btn-sm py-0 px-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#inviteMemberModal" style="font-size:.72rem;">
            <i class="bi bi-person-plus me-1"></i>Invite
          </button>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <div id="ws-members-list">
            <?php foreach ($members as $m): ?>
            <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom" id="member-row-<?= $m['id'] ?>">
              <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;background:linear-gradient(135deg,#4f46e5,#7c3aed);font-size:.72rem;">
                <?= strtoupper(substr($m['name'], 0, 2)) ?>
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold small text-truncate"><?= htmlspecialchars($m['name']) ?></div>
                <div class="x-small text-muted text-truncate"><?= htmlspecialchars($m['email']) ?></div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge <?= $m['role']==='owner' ? 'bg-primary' : 'bg-info' ?>" style="font-size:.65rem;"><?= ucfirst($m['role']) ?></span>
                <?php if ($isOwner && $m['id'] != $uid && $m['role'] !== 'owner'): ?>
                <button class="btn btn-link text-danger p-0 border-0" onclick="removeMember(<?= $wid ?>, <?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>')" title="Remove member">
                  <i class="bi bi-x-circle-fill" style="font-size:.9rem;"></i>
                </button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal: Create Project in Workspace (AJAX) -->
<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" style="font-family:'Outfit';"><i class="bi bi-kanban-fill me-2 text-primary"></i>Create Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="create-project-form" onsubmit="return handleCreateProject(event)">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Project Name <span class="text-danger">*</span></label>
            <input type="text" id="proj-name-input" class="form-control" placeholder="e.g. Q4 Marketing Campaign" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea id="proj-desc-input" class="form-control" rows="2" placeholder="Brief project description"></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Status</label>
              <select id="proj-status-input" class="form-select form-select-sm">
                <option value="planning">Planning</option>
                <option value="active" selected>Active</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Priority</label>
              <select id="proj-priority-input" class="form-select form-select-sm">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Project Manager</label>
            <select id="proj-manager-input" class="form-select form-select-sm">
              <?php foreach ($all_users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= $u['id'] == $uid ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Start Date</label>
              <input type="date" id="proj-start-input" class="form-control form-control-sm">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Due Date</label>
              <input type="date" id="proj-due-input" class="form-control form-control-sm">
            </div>
          </div>
          <div id="proj-create-status" class="mb-3"></div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4 fw-semibold" id="create-proj-submit">
              <i class="bi bi-check-lg me-1"></i>Create Project
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Invite Member -->
<div class="modal fade" id="inviteMemberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" style="font-family:'Outfit';"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Invite Member</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p class="small text-muted mb-3">Enter a user's name or email to invite them to this workspace:</p>
        <form id="invite-member-form" onsubmit="return handleInviteMember(event)">
          <div class="input-group mb-3">
            <input type="text" id="invite-query-input" class="form-control" placeholder="Name or email address…" required autocomplete="off">
            <button type="submit" class="btn btn-primary px-3">
              <i class="bi bi-person-plus-fill me-1"></i>Invite
            </button>
          </div>
          <div id="invite-status" class="mb-2"></div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$ws_init = "<script>const WS_ID = " . (int)$wid . ";</script>";
$page_scripts = $ws_init . <<<'JS'
<script>

// Create Project AJAX
async function handleCreateProject(e) {
  e.preventDefault();
  const btn    = document.getElementById('create-proj-submit');
  const status = document.getElementById('proj-create-status');
  const name   = document.getElementById('proj-name-input').value.trim();
  if (!name) return false;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

  const fd = new FormData();
  fd.append('action', 'create');
  fd.append('workspace_id', WS_ID);
  fd.append('name', name);
  fd.append('description', document.getElementById('proj-desc-input').value.trim());
  fd.append('status', document.getElementById('proj-status-input').value);
  fd.append('priority', document.getElementById('proj-priority-input').value);
  fd.append('manager_id', document.getElementById('proj-manager-input').value);
  fd.append('start_date', document.getElementById('proj-start-input').value);
  fd.append('due_date', document.getElementById('proj-due-input').value);

  try {
    const res  = await fetch('api/projects.php?action=create', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success && data.project) {
      const p = data.project;
      document.getElementById('projects-empty').classList.add('d-none');
      const grid = document.getElementById('ws-projects-grid');
      const col  = document.createElement('div');
      col.className = 'col-sm-6';
      col.id = 'proj-card-' + p.id;
      col.innerHTML = `
        <div class="card h-100 border-0 shadow-sm" style="border-radius:16px;background:var(--cs-surface);">
          <div class="card-body p-4">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div>
                <h6 class="fw-bold mb-1"><a href="project_details.php?id=\${p.id}" class="text-decoration-none text-body">\${escapeHtml(p.name)}</a></h6>
                <div class="d-flex gap-1">
                  <span class="badge bg-primary bg-opacity-15 text-primary" style="font-size:.65rem;">\${p.status}</span>
                  <span class="badge bg-info bg-opacity-15 text-info" style="font-size:.65rem;">\${p.priority}</span>
                </div>
              </div>
              <a href="project_details.php?id=\${p.id}" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size:.72rem;">Open</a>
            </div>
            <div class="mb-3">
              <div class="d-flex justify-content-between x-small text-muted mb-1"><span>Progress</span><span>0%</span></div>
              <div class="progress" style="height:6px;border-radius:99px;"><div class="progress-bar bg-primary" style="width:0%"></div></div>
            </div>
            <div class="d-flex justify-content-between x-small text-muted">
              <span>0 tasks</span><span>by \${escapeHtml(p.manager_name || 'You')}</span>
            </div>
          </div>
        </div>`;
      grid.prepend(col);
      document.getElementById('create-project-form').reset();
      bootstrap.Modal.getInstance(document.getElementById('createProjectModal')).hide();
    } else {
      status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">\${data.message || 'Create failed.'}</div>`;
    }
  } catch(err) {
    status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Connection error.</div>`;
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Project';
  return false;
}

// Invite Member AJAX
async function handleInviteMember(e) {
  e.preventDefault();
  const query  = document.getElementById('invite-query-input').value.trim();
  const status = document.getElementById('invite-status');
  if (!query) return false;
  status.innerHTML = `<div class="alert alert-info py-2 small mb-0"><span class="spinner-border spinner-border-sm me-1"></span>Searching...</div>`;

  const fd = new FormData();
  fd.append('action', 'invite_member');
  fd.append('workspace_id', WS_ID);
  fd.append('query', query);

  try {
    const res  = await fetch('api/workspaces.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success && data.user) {
      const u = data.user;
      status.innerHTML = `<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle-fill me-1"></i>Invited \${escapeHtml(u.name)}!</div>`;
      document.getElementById('invite-query-input').value = '';

      if (!document.getElementById('member-row-' + u.id)) {
        const memberList = document.getElementById('ws-members-list');
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-3 px-4 py-3 border-bottom';
        row.id = 'member-row-' + u.id;
        row.innerHTML = `
          <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;background:linear-gradient(135deg,#4f46e5,#7c3aed);font-size:.72rem;">\${u.name.substring(0,2).toUpperCase()}</div>
          <div class="flex-grow-1 overflow-hidden">
            <div class="fw-semibold small text-truncate">\${escapeHtml(u.name)}</div>
            <div class="x-small text-muted text-truncate">\${escapeHtml(u.email)}</div>
          </div>
          <span class="badge bg-secondary bg-opacity-15 text-secondary" style="font-size:.62rem;">Member</span>
          <button class="btn btn-link text-danger p-0" onclick="removeMember(\${WS_ID}, \${u.id}, '\${escapeHtml(u.name)}')">
            <i class="bi bi-x-lg" style="font-size:.75rem;"></i>
          </button>`;
        memberList.appendChild(row);
      }
    } else {
      status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">\${data.message || 'User not found.'}</div>`;
    }
  } catch {
    status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Connection error.</div>`;
  }
  return false;
}

// Remove Member AJAX
async function removeMember(wsId, userId, name) {
  if (!confirm(`Remove \${name} from this workspace?`)) return;
  const fd = new FormData();
  fd.append('action', 'remove_member');
  fd.append('workspace_id', wsId);
  fd.append('user_id', userId);
  try {
    const res  = await fetch('api/workspaces.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      const row = document.getElementById('member-row-' + userId);
      if (row) row.remove();
    } else {
      alert(data.message || 'Could not remove member.');
    }
  } catch { alert('Error.'); }
}

function escapeHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
