<?php
// ─── Dashboard ───────────────────────────────────────────────
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// ── Stats ────────────────────────────────────────────────────
$total_projects = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$my_tasks_count = (int)$db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status!='done'")->execute([$uid]) ?
    (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status!='done'")->fetchColumn() : 0;

$active_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$total_users     = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$completed_tasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();
$total_tasks     = (int)$db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();

// My open tasks
$my_tasks = $db->prepare("
    SELECT t.*, p.name AS project_name
    FROM tasks t JOIN projects p ON p.id = t.project_id
    WHERE t.assigned_to = ? AND t.status != 'done'
    ORDER BY t.priority DESC, t.due_date ASC LIMIT 8
");
$my_tasks->execute([$uid]);
$my_tasks = $my_tasks->fetchAll();

// Recent projects
$projects = $db->query("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count
    FROM projects p JOIN users u ON u.id=p.manager_id
    ORDER BY p.created_at DESC LIMIT 6
")->fetchAll();

// Recent activity
$activities = $db->query("
    SELECT a.*, u.name AS user_name, u.avatar, p.name AS project_name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    ORDER BY a.created_at DESC LIMIT 10
")->fetchAll();

// Online users
$online_users = $db->query("SELECT * FROM users WHERE status='online' AND is_active=1 ORDER BY name ASC LIMIT 12")->fetchAll();

// Action icons
$action_icons = [
    'task_created'       => ['bi-plus-circle-fill','primary'],
    'task_completed'     => ['bi-check-circle-fill','success'],
    'task_status_updated'=> ['bi-arrow-repeat','info'],
    'project_created'    => ['bi-kanban-fill','success'],
    'file_uploaded'      => ['bi-file-earmark-arrow-up-fill','warning'],
    'member_added'       => ['bi-person-plus-fill','primary'],
    'user_registered'    => ['bi-person-check-fill','info'],
    'comment_added'      => ['bi-chat-left-fill','secondary'],
];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="app-main">
  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h2 class="fw-bold mb-0 fs-5">
          <?php
            $hr = (int)date('H');
            echo $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
          ?>, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?> 👋
        </h2>
        <p class="text-muted small mb-0">Here's what's happening across your workspace today</p>
      </div>
      <?php if (is_manager()): ?>
      <a href="projects.php" class="btn btn-primary btn-sm" id="new-project-btn">
        <i class="bi bi-plus-lg me-1"></i>New Project
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="app-content">
    <!-- ── Stat Cards ──────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon"><i class="bi bi-kanban-fill"></i></div>
              <span class="badge bg-white bg-opacity-25 text-white small"><?= $active_projects ?> active</span>
            </div>
            <div class="stat-number"><?= $total_projects ?></div>
            <div class="small opacity-85 mt-1">Total Projects</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#0891b2,#0e7490);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon"><i class="bi bi-check2-square"></i></div>
              <span class="badge bg-white bg-opacity-25 text-white small">open</span>
            </div>
            <div class="stat-number"><?= $my_tasks_count ?></div>
            <div class="small opacity-85 mt-1">My Open Tasks</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#059669,#047857);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
              <?php $pct = $total_tasks > 0 ? round(($completed_tasks/$total_tasks)*100) : 0; ?>
              <span class="badge bg-white bg-opacity-25 text-white small"><?= $pct ?>%</span>
            </div>
            <div class="stat-number"><?= $completed_tasks ?></div>
            <div class="small opacity-85 mt-1">Tasks Completed</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#d97706,#b45309);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
              <span class="badge bg-white bg-opacity-25 text-white small">team</span>
            </div>
            <div class="stat-number"><?= $total_users ?></div>
            <div class="small opacity-85 mt-1">Team Members</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- ── Projects ────────────────────────────── -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-kanban me-2 text-primary"></i>Recent Projects</h3>
            <a href="projects.php" class="btn btn-sm btn-outline-primary" id="view-all-projects">View All</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($projects)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-kanban fs-1 d-block mb-2 opacity-25"></i>
              <p class="small">No projects yet. <a href="projects.php">Create one!</a></p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($projects as $p): ?>
              <a href="project_details.php?id=<?= $p['id'] ?>" class="list-group-item list-group-item-action px-4 py-3" id="project-item-<?= $p['id'] ?>">
                <div class="d-flex align-items-start gap-3">
                  <div class="rounded-2 flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold" style="width:40px;height:40px;font-size:.75rem;background:<?= htmlspecialchars($p['color'] ?? '#4f46e5') ?>;">
                    <?= strtoupper(substr($p['name'],0,2)) ?>
                  </div>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span class="fw-semibold small text-truncate"><?= htmlspecialchars($p['name']) ?></span>
                      <?= status_badge($p['status']) ?>
                      <?= priority_badge($p['priority']) ?>
                    </div>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="mt-2 d-flex align-items-center gap-3">
                      <div class="flex-grow-1">
                        <div class="progress" style="height:5px;">
                          <div class="progress-bar" style="width:<?= $p['progress'] ?>%;background:linear-gradient(90deg,#4f46e5,#7c3aed);"></div>
                        </div>
                      </div>
                      <span class="text-muted" style="font-size:.72rem;white-space:nowrap;"><?= $p['progress'] ?>% done</span>
                      <?php if ($p['due_date']): ?>
                      <span class="text-muted" style="font-size:.72rem;white-space:nowrap;"><i class="bi bi-calendar3 me-1"></i><?= date('M j', strtotime($p['due_date'])) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── Right Column ──────────────────────────── -->
      <div class="col-lg-4 d-flex flex-column gap-4">

        <!-- Online Users -->
        <div class="card">
          <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-circle-fill text-success me-2" style="font-size:.6rem;"></i>Online Now</h3>
            <span class="badge bg-success bg-opacity-15 text-success"><?= count($online_users) ?></span>
          </div>
          <div class="card-body py-2" id="online-users-list">
            <?php if (empty($online_users)): ?>
            <div class="text-center py-3 text-muted small">No one online right now</div>
            <?php else: ?>
            <?php foreach ($online_users as $ou): ?>
            <div class="d-flex align-items-center gap-2 py-1">
              <span class="online-indicator <?= $ou['status'] === 'online' ? '' : 'offline-indicator' ?>"></span>
              <span class="small"><?= htmlspecialchars($ou['name']) ?></span>
              <?php if ($ou['id'] == $uid): ?><span class="badge bg-secondary bg-opacity-50 ms-auto" style="font-size:.6rem;">You</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- My Tasks Quick View -->
        <div class="card flex-grow-1">
          <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-check2-square me-2 text-primary"></i>My Tasks</h3>
            <a href="tasks.php" class="btn btn-sm btn-outline-primary" id="view-my-tasks">View Board</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($my_tasks)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>
              <p class="small">All caught up! 🎉</p>
            </div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($my_tasks as $t): ?>
              <li class="list-group-item px-3 py-2 border-0 border-bottom">
                <div class="d-flex align-items-start gap-2">
                  <div class="priority-bar <?= $t['priority'] ?>" style="position:relative;width:4px;height:36px;border-radius:99px;margin-top:2px;flex-shrink:0;"></div>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold small text-truncate"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="x-small text-muted"><?= htmlspecialchars($t['project_name']) ?> · <?= status_badge($t['status']) ?></div>
                  </div>
                  <?php if ($t['due_date']): ?>
                  <span class="x-small text-muted flex-shrink-0"><?= date('M j', strtotime($t['due_date'])) ?></span>
                  <?php endif; ?>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Activity Feed ──────────────────────────── -->
    <div class="card mt-4">
      <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-activity me-2 text-primary"></i>Recent Activity</h3>
        <a href="activity.php" class="btn btn-sm btn-outline-primary" id="view-all-activity">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($activities)): ?>
        <div class="text-center py-4 text-muted small">No activity yet.</div>
        <?php else: ?>
        <?php foreach ($activities as $a): ?>
        <?php [$icon, $color] = $action_icons[$a['action']] ?? ['bi-bell','secondary']; ?>
        <div class="activity-item">
          <div class="activity-icon bg-<?= $color ?> bg-opacity-15 text-<?= $color ?>">
            <i class="bi <?= $icon ?>"></i>
          </div>
          <div class="activity-line">
            <div class="activity-text">
              <strong><?= htmlspecialchars($a['user_name']) ?></strong>
              <?= htmlspecialchars($a['description']) ?>
            </div>
            <div class="activity-time">
              <?= $a['project_name'] ? '<span class="text-primary">' . htmlspecialchars($a['project_name']) . '</span> · ' : '' ?>
              <?= time_ago($a['created_at']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.app-content -->
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
