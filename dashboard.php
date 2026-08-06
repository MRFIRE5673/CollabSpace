<?php
// ─── Dashboard (Admin Only) ──────────────────────────────────
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_login();
// Non-admins are redirected to their own role page
if (!is_admin()) { redirect(get_role_redirect($_SESSION['user_role'] ?? 'member')); }
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// ── Stats ────────────────────────────────────────────────────
$total_projects  = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$active_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$total_users     = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$total_tasks     = (int)$db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$completed_tasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();
$pending_tasks   = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status!='done'")->fetchColumn();
$online_count    = (int)$db->query("SELECT COUNT(*) FROM users WHERE status='online' AND is_active=1")->fetchColumn();

$my_tasks_count  = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status!='done'")->fetchColumn();

// My open tasks
$my_tasks_stmt = $db->prepare("
    SELECT t.*, p.name AS project_name
    FROM tasks t JOIN projects p ON p.id = t.project_id
    WHERE t.assigned_to = ? AND t.status != 'done'
    ORDER BY t.priority DESC, t.due_date ASC LIMIT 8
");
$my_tasks_stmt->execute([$uid]);
$my_tasks = $my_tasks_stmt->fetchAll();

// Recent projects
$projects = $db->query("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') AS done_count,
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

$action_icons = [
    'task_created'        => ['bi-plus-circle-fill',          'primary'],
    'task_completed'      => ['bi-check-circle-fill',         'success'],
    'task_status_updated' => ['bi-arrow-repeat',              'info'],
    'project_created'     => ['bi-kanban-fill',               'success'],
    'file_uploaded'       => ['bi-file-earmark-arrow-up-fill','warning'],
    'member_added'        => ['bi-person-plus-fill',          'primary'],
    'user_registered'     => ['bi-person-check-fill',         'info'],
    'comment_added'       => ['bi-chat-left-fill',            'secondary'],
];

$completion_pct = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
$hr = (int)date('H');
$greeting = $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="app-main">
  <div class="app-content">

    <!-- ── Hero Banner ──────────────────────────────────── -->
    <div class="page-hero hero-admin">
      <div class="hero-orb"></div>
      <div class="hero-orb-2"></div>
      <div class="row align-items-center g-3">
        <div class="col-lg-8">
          <div class="hero-eyebrow">Admin Control Center</div>
          <h1 class="hero-title mb-2">
            <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋
          </h1>
          <p class="hero-sub mb-3">Here's what's happening across your workspace right now.</p>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="hero-live-badge">
              <span class="live-dot"></span>
              <?= $online_count ?> online now
            </span>
            <span class="hero-live-badge">
              <i class="bi bi-kanban-fill" style="font-size:.7rem;"></i>
              <?= $active_projects ?> active projects
            </span>
            <span class="hero-live-badge">
              <i class="bi bi-graph-up-arrow" style="font-size:.7rem;"></i>
              <?= $completion_pct ?>% tasks done
            </span>
          </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
          <?php if (is_manager()): ?>
          <a href="projects.php" class="btn btn-light fw-semibold px-4" id="new-project-btn">
            <i class="bi bi-plus-lg me-1"></i> New Project
          </a>
          <?php endif; ?>
          <a href="users.php" class="btn btn-outline-light fw-semibold px-4 ms-2">
            <i class="bi bi-people-fill me-1"></i> Users
          </a>
        </div>
      </div>
    </div>

    <!-- ── Stat Cards ────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-indigo text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-kanban-fill"></i></div>
              <span class="stat-badge"><?= $active_projects ?> active</span>
            </div>
            <div class="stat-number"><?= $total_projects ?></div>
            <div class="stat-label">Total Projects</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-emerald text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
              <span class="stat-badge"><?= $completion_pct ?>%</span>
            </div>
            <div class="stat-number"><?= $completed_tasks ?></div>
            <div class="stat-label">Tasks Completed</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-violet text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
              <span class="stat-badge"><?= $online_count ?> online</span>
            </div>
            <div class="stat-number"><?= $total_users ?></div>
            <div class="stat-label">Team Members</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-amber text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
              <span class="stat-badge">open</span>
            </div>
            <div class="stat-number"><?= $pending_tasks ?></div>
            <div class="stat-label">Open Tasks</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Overall Progress ───────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="fw-bold" style="font-family:'Outfit',sans-serif;font-size:.92rem;">Overall Task Completion</div>
            <div class="text-muted" style="font-size:.76rem;"><?= $completed_tasks ?> of <?= $total_tasks ?> tasks done · <?= $pending_tasks ?> remaining</div>
          </div>
          <div class="text-center">
            <div class="fw-bold fs-4" style="font-family:'Outfit',sans-serif;color:var(--cs-primary);"><?= $completion_pct ?>%</div>
          </div>
        </div>
        <div class="progress" style="height:10px;">
          <div class="progress-bar progress-bar-gradient" role="progressbar"
               style="width:<?= $completion_pct ?>%;"
               aria-valuenow="<?= $completion_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex flex-wrap gap-4 mt-3">
          <span class="d-flex align-items-center gap-2" style="font-size:.78rem;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--cs-emerald);display:inline-block;"></span>
            <span class="text-muted"><?= $completed_tasks ?> Completed</span>
          </span>
          <span class="d-flex align-items-center gap-2" style="font-size:.78rem;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--cs-amber);display:inline-block;"></span>
            <span class="text-muted"><?= $pending_tasks ?> In Progress</span>
          </span>
          <span class="d-flex align-items-center gap-2" style="font-size:.78rem;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--cs-primary);display:inline-block;"></span>
            <span class="text-muted"><?= $total_tasks ?> Total</span>
          </span>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- ── Recent Projects ────────────────────────────── -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="bi bi-kanban-fill me-2 text-primary"></i>Recent Projects</h6>
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
              <?php foreach ($projects as $p):
                $pDone = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
                $statusColors = ['active'=>'success','planning'=>'info','on_hold'=>'warning','completed'=>'secondary','cancelled'=>'danger'];
                $sc = $statusColors[$p['status']] ?? 'secondary';
              ?>
              <a href="project_details.php?id=<?= $p['id'] ?>"
                 class="list-group-item list-group-item-action px-4 py-3 border-0 border-bottom"
                 id="project-item-<?= $p['id'] ?>">
                <div class="d-flex align-items-start gap-3">
                  <div class="rounded-2 flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold"
                       style="width:42px;height:42px;font-size:.72rem;background:<?= htmlspecialchars($p['color'] ?? '#5c49e0') ?>;border-radius:10px !important;letter-spacing:-.01em;">
                    <?= strtoupper(substr($p['name'], 0, 2)) ?>
                  </div>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($p['name']) ?></span>
                      <?= status_badge($p['status']) ?>
                      <?= priority_badge($p['priority']) ?>
                    </div>
                    <div class="text-muted mb-2" style="font-size:.76rem;"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 80)) ?><?= strlen($p['description'] ?? '') > 80 ? '…' : '' ?></div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="flex-grow-1">
                        <div class="progress" style="height:5px;">
                          <div class="progress-bar" style="width:<?= $pDone ?>%;background:linear-gradient(90deg,#5c49e0,#8b5cf6);"></div>
                        </div>
                      </div>
                      <span class="text-muted" style="font-size:.72rem;white-space:nowrap;"><?= $pDone ?>% · <?= $p['task_count'] ?> tasks</span>
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

      <!-- ── Right Column ───────────────────────────────── -->
      <div class="col-lg-4 d-flex flex-column gap-4">

        <!-- Online Now -->
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">
              <span class="online-dot me-2" style="width:8px;height:8px;"></span>Online Now
            </h6>
            <span class="badge bg-success bg-opacity-15 text-success fw-semibold"><?= count($online_users) ?></span>
          </div>
          <div class="card-body py-2" id="online-users-list">
            <?php if (empty($online_users)): ?>
            <div class="text-center py-3 text-muted small">No one online right now</div>
            <?php else: ?>
            <?php foreach ($online_users as $ou): ?>
            <div class="d-flex align-items-center gap-2 py-2">
              <?= get_avatar_html($ou, '28px') ?>
              <span class="small flex-grow-1"><?= htmlspecialchars($ou['name']) ?></span>
              <?php if ($ou['id'] == $uid): ?>
              <span class="badge bg-secondary bg-opacity-50" style="font-size:.58rem;">You</span>
              <?php endif; ?>
              <span class="online-indicator"></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- My Tasks -->
        <div class="card flex-grow-1">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="bi bi-check2-square me-2 text-primary"></i>My Tasks</h6>
            <a href="tasks.php" class="btn btn-sm btn-outline-primary" id="view-my-tasks">View Board</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($my_tasks)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25 text-success"></i>
              <p class="small">All caught up! 🎉</p>
            </div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($my_tasks as $t):
                $priorityColors = ['critical'=>'danger','high'=>'warning','medium'=>'info','low'=>'secondary'];
                $pc = $priorityColors[$t['priority']] ?? 'secondary';
                $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d');
              ?>
              <li class="list-group-item px-4 py-2 border-0 border-bottom">
                <div class="d-flex align-items-start gap-2">
                  <div class="priority-bar <?= $t['priority'] ?>" style="position:relative;width:4px;height:36px;border-radius:99px;margin-top:2px;flex-shrink:0;"></div>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold text-truncate" style="font-size:.84rem;"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($t['project_name']) ?> · <?= status_badge($t['status']) ?></div>
                  </div>
                  <?php if ($t['due_date']): ?>
                  <span class="flex-shrink-0 <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>" style="font-size:.7rem;">
                    <?= $overdue ? '⚠ ' : '' ?><?= date('M j', strtotime($t['due_date'])) ?>
                  </span>
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

    <!-- ── Activity Feed ──────────────────────────────────── -->
    <div class="card mt-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="bi bi-activity me-2 text-primary"></i>Live Activity Feed</h6>
        <a href="activity.php" class="btn btn-sm btn-outline-primary" id="view-all-activity">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($activities)): ?>
        <div class="text-center py-4 text-muted small">No activity yet.</div>
        <?php else: ?>
        <?php foreach ($activities as $a): ?>
        <?php [$icon, $color] = $action_icons[$a['action']] ?? ['bi-bell', 'secondary']; ?>
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
              <?= $a['project_name'] ? '<span class="text-primary fw-semibold">' . htmlspecialchars($a['project_name']) . '</span> · ' : '' ?>
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
