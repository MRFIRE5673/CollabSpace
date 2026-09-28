<?php
// ─── Manager Dashboard ───────────────────────────────────────
$page_title = 'Manager Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!is_manager()) {
    redirect(get_role_redirect($_SESSION['user_role'] ?? 'member'));
}

$user = current_user();
$uid  = $user['id'];
$cid  = active_company_id();
$db   = getDB();

// ── Stats (Company Scoped) ──────────────────────────────────
$p_stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE company_id = ?"); $p_stmt->execute([$cid]);
$total_projects  = (int)$p_stmt->fetchColumn();

$ap_stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE company_id = ? AND status = 'active'"); $ap_stmt->execute([$cid]);
$active_projects = (int)$ap_stmt->fetchColumn();

$oh_stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE company_id = ? AND status = 'on_hold'"); $oh_stmt->execute([$cid]);
$on_hold         = (int)$oh_stmt->fetchColumn();

$t_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE company_id = ?"); $t_stmt->execute([$cid]);
$total_tasks     = (int)$t_stmt->fetchColumn();

$dt_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE company_id = ? AND status = 'done'"); $dt_stmt->execute([$cid]);
$done_tasks      = (int)$dt_stmt->fetchColumn();

$pt_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE company_id = ? AND status != 'done'"); $pt_stmt->execute([$cid]);
$pending_tasks   = (int)$pt_stmt->fetchColumn();

$tm_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND is_active = 1 AND role IN ('member','manager','project_manager','team_lead')"); $tm_stmt->execute([$cid]);
$total_members   = (int)$tm_stmt->fetchColumn();
$completion_pct  = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;

$ou_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND status = 'online' AND is_active = 1"); $ou_stmt->execute([$cid]);
$online_count    = (int)$ou_stmt->fetchColumn();

if (is_admin()) {
    $stmt = $db->prepare("
        SELECT p.*, u.name AS manager_name,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND company_id = ?) AS done_count,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND company_id = ?) AS task_count
        FROM projects p JOIN users u ON u.id = p.manager_id
        WHERE p.company_id = ?
        ORDER BY p.created_at DESC LIMIT 8
    ");
    $stmt->execute([$cid, $cid, $cid]);
    $projects = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT p.*, u.name AS manager_name,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND company_id = ?) AS done_count,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND company_id = ?) AS task_count
        FROM projects p JOIN users u ON u.id = p.manager_id
        WHERE p.company_id = ? AND (p.created_by = ? OR p.manager_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ? AND company_id = ?))
        ORDER BY p.created_at DESC LIMIT 8
    ");
    $stmt->execute([$cid, $cid, $cid, $uid, $uid, $uid, $cid]);
    $projects = $stmt->fetchAll();
}

// Team members and their task load
$team_stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.role, u.avatar, u.status,
           COUNT(t.id) AS open_tasks
    FROM users u
    LEFT JOIN tasks t ON t.assigned_to = u.id AND t.status != 'done' AND t.company_id = ?
    WHERE u.company_id = ? AND u.is_active = 1
    GROUP BY u.id, u.name, u.email, u.role, u.avatar, u.status
    ORDER BY open_tasks DESC LIMIT 10
");
$team_stmt->execute([$cid, $cid]);
$team = $team_stmt->fetchAll();

// Recent activity
$act_stmt = $db->prepare("
    SELECT a.*, u.name AS user_name, u.avatar, p.name AS project_name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE a.company_id = ?
    ORDER BY a.created_at DESC LIMIT 10
");
$act_stmt->execute([$cid]);
$activities = $act_stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>


  <div class="app-content">

    <!-- ── Hero Banner ──────────────────────────────────── -->
    <div class="page-hero hero-manager">
      <div class="hero-orb"></div>
      <div class="hero-orb-2"></div>
      <div class="row align-items-center g-3">
        <div class="col-lg-8">
          <div class="hero-eyebrow">Manager Workspace</div>
          <h1 class="hero-title mb-2">
            <?php
              $hr = (int)date('H');
              echo $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
            ?>, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋
          </h1>
          <p class="hero-sub mb-3">Your team is collaborating in real time — keep the momentum going.</p>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="hero-live-badge">
              <span class="live-dot"></span>
              <?= $online_count ?> online now
            </span>
            <span class="hero-live-badge">
              <i class="bi bi-kanban-fill" style="font-size:.7rem;"></i>
              <?= $active_projects ?> active
            </span>
            <span class="hero-live-badge">
              <i class="bi bi-people-fill" style="font-size:.7rem;"></i>
              <?= $total_members ?> team members
            </span>
          </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
          <a href="projects.php" class="btn btn-light fw-semibold px-4">
            <i class="bi bi-kanban-fill me-1"></i>All Projects
          </a>
          <a href="tasks.php" class="btn btn-outline-light fw-semibold px-4 ms-2">
            <i class="bi bi-check2-square me-1"></i>Task Board
          </a>
        </div>
      </div>
    </div>

    <!-- ── Stat Cards ────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
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
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-emerald text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-lightning-charge-fill"></i></div>
              <span class="stat-badge"><?= $on_hold ?> on hold</span>
            </div>
            <div class="stat-number"><?= $active_projects ?></div>
            <div class="stat-label">Active Projects</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-amber text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-check2-square"></i></div>
              <span class="stat-badge"><?= $completion_pct ?>% done</span>
            </div>
            <div class="stat-number"><?= $pending_tasks ?></div>
            <div class="stat-label">Open Tasks</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-violet text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
              <span class="stat-badge"><?= $online_count ?> online</span>
            </div>
            <div class="stat-number"><?= $total_members ?></div>
            <div class="stat-label">Team Members</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Progress Bar ───────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="fw-bold" style="font-family:'Outfit',sans-serif;font-size:.92rem;">Overall Team Completion</div>
            <div class="text-muted" style="font-size:.76rem;"><?= $done_tasks ?> done · <?= $pending_tasks ?> in progress · <?= $total_tasks ?> total</div>
          </div>
          <div class="fw-bold fs-4" style="font-family:'Outfit',sans-serif;color:var(--cs-emerald);"><?= $completion_pct ?>%</div>
        </div>
        <div class="progress" style="height:10px;">
          <div class="progress-bar" style="width:<?= $completion_pct ?>%;background:linear-gradient(90deg,#059669,#10b981);"
               role="progressbar" aria-valuenow="<?= $completion_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- ── Projects Overview ──────────────────────────── -->
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-kanban-fill me-2 text-success"></i>Projects Overview</h6>
            <a href="projects.php" class="btn btn-sm btn-outline-success">View All</a>
          </div>
          <div class="card-body p-0">
            <div class="list-group list-group-flush">
              <?php foreach ($projects as $p):
                $pDone = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
                $statusColors = ['active'=>'success','planning'=>'info','on_hold'=>'warning','completed'=>'secondary','cancelled'=>'danger'];
                $sc = $statusColors[$p['status']] ?? 'secondary';
              ?>
              <div class="list-group-item border-0 border-bottom px-4 py-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <a href="project_details.php?id=<?= $p['id'] ?>"
                       class="fw-semibold text-decoration-none" style="font-size:.88rem;"><?= htmlspecialchars($p['name']) ?></a>
                    <div class="text-muted" style="font-size:.72rem;margin-top:2px;">
                      <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($p['manager_name']) ?>
                    </div>
                  </div>
                  <span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:6px;">
                    <div class="progress-bar bg-<?= $sc ?>" style="width:<?= $pDone ?>%;"></div>
                  </div>
                  <span class="text-muted" style="font-size:.72rem;white-space:nowrap;"><?= $pDone ?>% · <?= $p['task_count'] ?> tasks</span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Right Column ───────────────────────────────── -->
      <div class="col-lg-5 d-flex flex-column gap-4">

        <!-- Team Workload -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Team Workload</h6>
          </div>
          <div class="card-body p-0">
            <div class="list-group list-group-flush">
              <?php foreach ($team as $m): ?>
              <div class="list-group-item border-0 border-bottom d-flex align-items-center gap-3 px-4 py-2">
                <?= get_avatar_html($m, '36px') ?>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="fw-semibold text-truncate" style="font-size:.84rem;"><?= htmlspecialchars($m['name']) ?></div>
                  <div class="text-muted text-truncate" style="font-size:.7rem;"><?= htmlspecialchars($m['email']) ?></div>
                </div>
                <span class="badge <?= $m['open_tasks'] > 5 ? 'bg-danger' : ($m['open_tasks'] > 2 ? 'bg-warning' : 'bg-success') ?>">
                  <?= $m['open_tasks'] ?> tasks
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="card flex-grow-1">
          <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-activity me-2 text-info"></i>Recent Activity</h6>
          </div>
          <div class="card-body p-0">
            <div class="list-group list-group-flush">
              <?php foreach (array_slice($activities, 0, 6) as $a): ?>
              <div class="list-group-item border-0 border-bottom d-flex align-items-start gap-2 px-4 py-2">
                <?= get_avatar_html(['name'=>$a['user_name'],'avatar'=>$a['avatar']], '28px') ?>
                <div class="flex-grow-1 overflow-hidden" style="font-size:.8rem;">
                  <span class="fw-semibold"><?= htmlspecialchars($a['user_name']) ?></span>
                  <span class="text-muted"> <?= htmlspecialchars($a['description']) ?></span>
                  <div class="text-muted mt-1" style="font-size:.68rem;"><?= time_ago($a['created_at']) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /.app-content -->
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
