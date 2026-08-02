<?php
// ─── Manager Dashboard ───────────────────────────────────────
$page_title = 'Manager Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_login();

// Only manager and admin can access this page
if (!is_manager()) {
    redirect(get_role_redirect($_SESSION['user_role'] ?? 'member'));
}

$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// ── Stats ─────────────────────────────────────────────────────
$total_projects  = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$active_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$on_hold         = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='on_hold'")->fetchColumn();
$total_tasks     = (int)$db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$done_tasks      = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();
$pending_tasks   = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status!='done'")->fetchColumn();
$total_members   = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND role IN ('member','manager')")->fetchColumn();
$completion_pct  = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;

// All projects with manager info
$projects = $db->query("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') AS done_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count
    FROM projects p JOIN users u ON u.id=p.manager_id
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

// Team members and their task load
$team = $db->query("
    SELECT u.id, u.name, u.email, u.role, u.avatar, u.status,
           COUNT(t.id) AS open_tasks
    FROM users u
    LEFT JOIN tasks t ON t.assigned_to = u.id AND t.status != 'done'
    WHERE u.is_active = 1 AND u.role IN ('member','manager')
    GROUP BY u.id
    ORDER BY open_tasks DESC LIMIT 8
")->fetchAll();

// Recent activity
$activities = $db->query("
    SELECT a.*, u.name AS user_name, u.avatar, p.name AS project_name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    ORDER BY a.created_at DESC LIMIT 10
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h3 class="mb-0 fw-bold">
            <i class="bi bi-diagram-3-fill me-2 text-success"></i>Manager Dashboard
          </h3>
          <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($user['name']) ?> &middot; <?= date('l, d F Y') ?></p>
        </div>
        <div class="col-sm-6 d-flex gap-2 justify-content-sm-end mt-2 mt-sm-0">
          <a href="projects.php" class="btn btn-success btn-sm"><i class="bi bi-kanban-fill me-1"></i>All Projects</a>
          <a href="tasks.php"    class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-square me-1"></i>Task Board</a>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      <!-- ── Stats Row ──────────────────────────────────────── -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #4f46e5 !important;">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px;background:#4f46e5;flex-shrink:0;font-size:1.4rem;">
                <i class="bi bi-kanban-fill"></i>
              </div>
              <div>
                <div class="fs-4 fw-bold lh-1"><?= $total_projects ?></div>
                <div class="small text-muted">Total Projects</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #22c55e !important;">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px;background:#22c55e;flex-shrink:0;font-size:1.4rem;">
                <i class="bi bi-lightning-charge-fill"></i>
              </div>
              <div>
                <div class="fs-4 fw-bold lh-1"><?= $active_projects ?></div>
                <div class="small text-muted">Active Projects</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f59e0b !important;">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px;background:#f59e0b;flex-shrink:0;font-size:1.4rem;">
                <i class="bi bi-check2-square"></i>
              </div>
              <div>
                <div class="fs-4 fw-bold lh-1"><?= $pending_tasks ?></div>
                <div class="small text-muted">Open Tasks</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #06b6d4 !important;">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px;background:#06b6d4;flex-shrink:0;font-size:1.4rem;">
                <i class="bi bi-people-fill"></i>
              </div>
              <div>
                <div class="fs-4 fw-bold lh-1"><?= $total_members ?></div>
                <div class="small text-muted">Team Members</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Overall Progress ───────────────────────────────── -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Overall Team Completion</span>
            <span class="badge bg-success"><?= $completion_pct ?>%</span>
          </div>
          <div class="progress" style="height:10px;border-radius:8px;">
            <div class="progress-bar bg-success" style="width:<?= $completion_pct ?>%;border-radius:8px;" role="progressbar"
                 aria-valuenow="<?= $completion_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <div class="d-flex gap-3 mt-2 small text-muted">
            <span><i class="bi bi-check-circle-fill text-success me-1"></i><?= $done_tasks ?> completed</span>
            <span><i class="bi bi-clock-fill text-warning me-1"></i><?= $pending_tasks ?> in progress</span>
            <span><i class="bi bi-collection-fill text-primary me-1"></i><?= $total_tasks ?> total</span>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- ── Projects ──────────────────────────────────────── -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-kanban-fill me-2 text-success"></i>Projects Overview</h6>
              <a href="projects.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <?php foreach ($projects as $p):
                  $pDone = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
                  $statusColors = ['active'=>'success','planning'=>'info','on_hold'=>'warning','completed'=>'secondary','cancelled'=>'danger'];
                  $sc = $statusColors[$p['status']] ?? 'secondary';
                ?>
                <div class="list-group-item border-0 px-4 py-3">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                      <a href="project_details.php?id=<?= $p['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($p['name']) ?></a>
                      <div class="small text-muted">Manager: <?= htmlspecialchars($p['manager_name']) ?></div>
                    </div>
                    <span class="badge bg-<?= $sc ?> ms-2"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px;border-radius:4px;">
                      <div class="progress-bar bg-<?= $sc ?>" style="width:<?= $pDone ?>%;border-radius:4px;"></div>
                    </div>
                    <span class="small text-muted" style="white-space:nowrap;"><?= $pDone ?>% · <?= $p['task_count'] ?> tasks</span>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Team Load ─────────────────────────────────────── -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i>Team Workload</h6>
            </div>
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <?php foreach ($team as $m): ?>
                <div class="list-group-item border-0 d-flex align-items-center gap-3 px-4 py-2">
                  <?= get_avatar_html($m, '36px') ?>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="small fw-semibold text-truncate"><?= htmlspecialchars($m['name']) ?></div>
                    <div class="x-small text-muted text-truncate"><?= htmlspecialchars($m['email']) ?></div>
                  </div>
                  <span class="badge <?= $m['open_tasks'] > 5 ? 'bg-danger' : ($m['open_tasks'] > 2 ? 'bg-warning' : 'bg-success') ?>">
                    <?= $m['open_tasks'] ?> tasks
                  </span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- ── Recent Activity ───────────────────────────── -->
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-info"></i>Recent Activity</h6>
            </div>
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <?php foreach (array_slice($activities, 0, 6) as $a): ?>
                <div class="list-group-item border-0 d-flex align-items-start gap-2 px-4 py-2">
                  <?= get_avatar_html(['name'=>$a['user_name'],'avatar'=>$a['avatar']], '28px') ?>
                  <div class="flex-grow-1 small overflow-hidden">
                    <span class="fw-semibold"><?= htmlspecialchars($a['user_name']) ?></span>
                    <span class="text-muted"> <?= htmlspecialchars($a['description']) ?></span>
                    <div class="text-muted" style="font-size:.7rem;"><?= time_ago($a['created_at']) ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
