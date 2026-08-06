<?php
// ─── Task Board (Global Kanban) ──────────────────────────────
$page_title = 'Task Board';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$project_id = (int)($_GET['project_id'] ?? 0);
$filter_priority = $_GET['priority'] ?? '';
$filter_assignee = (int)($_GET['assignee'] ?? 0);

// Fetch projects for filter
if (!is_admin()) {
    $projects_list = $db->prepare("SELECT id, name FROM projects WHERE created_by=? OR manager_id=? OR id IN (SELECT project_id FROM project_members WHERE user_id=?) ORDER BY name");
    $projects_list->execute([$uid, $uid, $uid]);
    $projects_list = $projects_list->fetchAll();

    $where .= ' AND (t.assigned_to=? OR t.created_by=? OR t.project_id IN (SELECT id FROM projects WHERE manager_id=? OR created_by=? OR id IN (SELECT project_id FROM project_members WHERE user_id=?)))';
    $params[] = $uid; $params[] = $uid; $params[] = $uid; $params[] = $uid; $params[] = $uid;
} else {
    $projects_list = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
}

$stmt = $db->prepare("
    SELECT t.*, u.name AS assignee_name, p.name AS project_name
    FROM tasks t
    LEFT JOIN users u ON u.id=t.assigned_to
    JOIN projects p ON p.id=t.project_id
    WHERE $where
    ORDER BY t.position ASC, t.created_at DESC
");
$stmt->execute($params);
$all_tasks = $stmt->fetchAll();

$kanban_cols = ['todo'=>[],'in_progress'=>[],'in_review'=>[],'done'=>[]];
foreach ($all_tasks as $t) {
    $kanban_cols[$t['status']][] = $t;
}

$all_users = $db->query("SELECT id, name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
$selected_project = $project_id ? $db->prepare("SELECT name FROM projects WHERE id=?")->execute([$project_id]) ? $db->query("SELECT name FROM projects WHERE id=$project_id")->fetchColumn() : '' : '';

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>


  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-check2-square me-2 text-primary"></i>Task Board</h2>
        <p class="text-muted small mb-0"><?= count($all_tasks) ?> task<?= count($all_tasks)!=1?'s':'' ?> total</p>
      </div>
      <?php if (is_manager()): ?>
      <button class="btn btn-primary btn-sm" onclick="openCreateTaskModal('todo')" id="global-add-task-btn">
        <i class="bi bi-plus-lg me-1"></i>New Task
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="app-content">

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="tasks-filter-form">
          <select name="project_id" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:200px;" id="tasks-project-filter">
            <option value="">All Projects</option>
            <?php foreach ($projects_list as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $project_id==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="priority" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:160px;" id="tasks-priority-filter">
            <option value="">All Priority</option>
            <?php foreach (['low','medium','high','critical'] as $p): ?>
            <option value="<?= $p ?>" <?= $filter_priority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (is_manager()): ?>
          <select name="assignee" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:200px;" id="tasks-assignee-filter">
            <option value="">All Members</option>
            <?php foreach ($all_users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $filter_assignee==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
          <button type="submit" class="btn btn-sm btn-primary" id="tasks-filter-submit">Filter</button>
          <?php if ($project_id || $filter_priority || $filter_assignee): ?>
          <a href="tasks.php" class="btn btn-sm btn-outline-secondary" id="tasks-filter-clear">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
      <?php
        $col_stats = [
          'todo'        => ['To Do',       'secondary', 'bi-circle'],
          'in_progress' => ['In Progress', 'primary',   'bi-arrow-repeat'],
          'in_review'   => ['In Review',   'warning',   'bi-eye'],
          'done'        => ['Done',         'success',   'bi-check-circle-fill'],
        ];
      ?>
      <?php foreach ($col_stats as $s => [$label, $col, $icon]): ?>
      <div class="col-6 col-lg-3">
        <div class="card text-center py-3">
          <div class="card-body p-2">
            <i class="bi <?= $icon ?> fs-3 text-<?= $col ?> d-block mb-1"></i>
            <div class="h3 fw-bold mb-0"><?= count($kanban_cols[$s]) ?></div>
            <div class="small text-muted"><?= $label ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Kanban Board -->
    <div class="kanban-wrapper">
      <?php
        $col_configs = [
          'todo'        => ['To Do',       'bi-circle',       '#6b7280'],
          'in_progress' => ['In Progress', 'bi-arrow-repeat', '#4f46e5'],
          'in_review'   => ['In Review',   'bi-eye',          '#d97706'],
          'done'        => ['Done',         'bi-check-circle', '#059669'],
        ];
      ?>
      <?php foreach ($col_configs as $status => [$label, $icon, $color]): ?>
      <div class="kanban-col" data-status="<?= $status ?>">
        <div class="kanban-col-header">
          <i class="bi <?= $icon ?>"></i>
          <?= $label ?>
          <span class="badge ms-auto kanban-count" style="background:<?= $color ?>22;color:<?= $color ?>;"><?= count($kanban_cols[$status]) ?></span>
        </div>
        <div class="kanban-cards" id="kanban-global-<?= $status ?>">
          <?php foreach ($kanban_cols[$status] as $t): ?>
          <div class="task-card" data-task-id="<?= $t['id'] ?>">
            <div class="priority-bar <?= $t['priority'] ?>"></div>
            <div class="ps-2">
              <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
              <div class="task-meta mb-1 d-flex align-items-center gap-1">
                <i class="bi bi-kanban"></i>
                <span><?= htmlspecialchars($t['project_name']) ?></span>
              </div>
              <?php if ($t['description']): ?>
              <p class="task-meta mb-1"><?= htmlspecialchars(substr($t['description'],0,60)) ?>…</p>
              <?php endif; ?>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <div class="d-flex align-items-center gap-2">
                  <?= priority_badge($t['priority']) ?>
                  <?php if ($t['due_date']): ?>
                  <span class="x-small text-muted <?= strtotime($t['due_date'])<time()&&$t['status']!='done'?'text-danger':'' ?>">
                    <i class="bi bi-calendar3"></i> <?= date('M j', strtotime($t['due_date'])) ?>
                  </span>
                  <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-1">
                  <?php if ($t['assignee_name']): ?>
                  <span class="x-small text-muted"><?= htmlspecialchars(explode(' ',$t['assignee_name'])[0]) ?></span>
                  <?php endif; ?>
                  <?php if (is_manager()): ?>
                  <button class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="deleteTask(<?= $t['id'] ?>)" id="del-task-g-<?= $t['id'] ?>"><i class="bi bi-x-circle-fill"></i></button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (is_manager()): ?>
        <button class="kanban-add-btn btn btn-link text-muted w-100 py-2 border-top" style="border-radius:0 0 16px 16px;font-size:.8rem;" id="kanban-add-g-<?= $status ?>">
          <i class="bi bi-plus-lg me-1"></i>Add Task
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</main>

<!-- Create Task Modal -->
<?php if (is_manager()): ?>
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabelGlobal" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="create-task-form-global" onsubmit="submitGlobalTask(event)">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold" id="createTaskModalLabelGlobal"><i class="bi bi-plus-circle me-2 text-primary"></i>Create Task</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Task Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required id="modal-task-title">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Project <span class="text-danger">*</span></label>
            <select name="project_id" class="form-select" required id="modal-task-project">
              <option value="">Select project…</option>
              <?php foreach ($projects_list as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $project_id==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Assign To</label>
              <select name="assigned_to" class="form-select" id="modal-task-assignee">
                <option value="">Unassigned</option>
                <?php foreach ($all_users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Priority</label>
              <select name="priority" class="form-select" id="modal-task-priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Status</label>
              <select name="status" class="form-select" id="modal-task-status">
                <option value="todo">To Do</option>
                <option value="in_progress">In Progress</option>
                <option value="in_review">In Review</option>
                <option value="done">Done</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Due Date</label>
              <input type="date" name="due_date" class="form-control" id="modal-task-due">
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="create-task-global-submit"><i class="bi bi-plus-lg me-1"></i>Create Task</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$page_scripts = <<<JS
<script>
function submitGlobalTask(e) {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  fetch('api/tasks.php?action=create', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showToast('Task created!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('createTaskModal')).hide();
        setTimeout(() => location.reload(), 800);
      } else {
        showToast(res.message || 'Failed.', 'danger');
      }
    });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
