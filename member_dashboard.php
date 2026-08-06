<?php
// ─── Member Dashboard ────────────────────────────────────────
$page_title = 'My Board';
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!is_member()) {
    redirect(get_role_redirect($_SESSION['user_role'] ?? 'viewer'));
}

$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// ── My Stats ─────────────────────────────────────────────────
$my_open     = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status!='done'")->fetchColumn();
$my_done     = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status='done'")->fetchColumn();
$my_overdue  = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status!='done' AND due_date < CURDATE()")->fetchColumn();
$my_inreview = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status='in_review'")->fetchColumn();
$my_total    = $my_open + $my_done;
$my_pct      = $my_total > 0 ? round(($my_done / $my_total) * 100) : 0;

// My open tasks
$stmt = $db->prepare("
    SELECT t.*, p.name AS project_name
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE t.assigned_to = ?  AND t.status != 'done'
    ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC
    LIMIT 10
");
$stmt->execute([$uid]);
$my_tasks = $stmt->fetchAll();

// My projects
$stmt2 = $db->prepare("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') AS done_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count
    FROM projects p
    JOIN users u ON u.id = p.manager_id
    WHERE p.created_by = ? OR p.manager_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)
    ORDER BY p.created_at DESC LIMIT 6
");
$stmt2->execute([$uid, $uid, $uid]);
$my_projects = $stmt2->fetchAll();

// Recent activity for my projects
$activity = $db->prepare("
    SELECT a.*, u.name AS user_name, u.avatar
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    WHERE a.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)
    ORDER BY a.created_at DESC LIMIT 8
");
$activity->execute([$uid]);
$activities = $activity->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/includes/sidebar.php';
?>


  <div class="app-content">

    <!-- ── Hero Banner ──────────────────────────────────── -->
    <div class="page-hero hero-member">
      <div class="hero-orb"></div>
      <div class="hero-orb-2"></div>
      <div class="row align-items-center g-3">
        <div class="col-lg-8">
          <div class="hero-eyebrow">My Workspace</div>
          <h1 class="hero-title mb-2">
            <?php
              $hr = (int)date('H');
              echo $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
            ?>, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋
          </h1>
          <p class="hero-sub mb-3">You're collaborating live — keep up the great work!</p>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if ($my_overdue > 0): ?>
            <span class="hero-live-badge" style="border-color:rgba(255,107,107,.5);background:rgba(255,107,107,.2);">
              <i class="bi bi-exclamation-triangle-fill" style="font-size:.7rem;color:#ffb3b3;"></i>
              <?= $my_overdue ?> overdue
            </span>
            <?php endif; ?>
            <span class="hero-live-badge">
              <span class="live-dot"></span>
              <?= $my_open ?> open tasks
            </span>
            <span class="hero-live-badge">
              <i class="bi bi-graph-up-arrow" style="font-size:.7rem;"></i>
              <?= $my_pct ?>% complete
            </span>
          </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
          <a href="tasks.php" class="btn btn-light fw-semibold px-4">
            <i class="bi bi-check2-square me-1"></i>Task Board
          </a>
          <a href="chat.php" class="btn btn-outline-light fw-semibold px-4 ms-2">
            <i class="bi bi-chat-dots-fill me-1"></i>Team Chat
          </a>
        </div>
      </div>
    </div>

    <!-- ── Stat Cards ────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-sky text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-list-task"></i></div>
              <span class="stat-badge">open</span>
            </div>
            <div class="stat-number"><?= $my_open ?></div>
            <div class="stat-label">My Open Tasks</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-teal text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-check2-all"></i></div>
              <span class="stat-badge"><?= $my_pct ?>%</span>
            </div>
            <div class="stat-number"><?= $my_done ?></div>
            <div class="stat-label">Completed</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-amber text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-eye-fill"></i></div>
              <span class="stat-badge">review</span>
            </div>
            <div class="stat-number"><?= $my_inreview ?></div>
            <div class="stat-label">In Review</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card stat-rose text-white">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
              <span class="stat-badge">⚠</span>
            </div>
            <div class="stat-number"><?= $my_overdue ?></div>
            <div class="stat-label">Overdue</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── My Personal Progress ───────────────────────────── -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="fw-bold" style="font-family:'Outfit',sans-serif;font-size:.92rem;">My Task Progress</div>
            <div class="text-muted" style="font-size:.76rem;"><?= $my_done ?> done · <?= $my_open ?> remaining</div>
          </div>
          <div class="fw-bold fs-4" style="font-family:'Outfit',sans-serif;color:var(--cs-sky);"><?= $my_pct ?>%</div>
        </div>
        <div class="progress" style="height:10px;">
          <div class="progress-bar" style="width:<?= $my_pct ?>%;background:linear-gradient(90deg,#0284c7,#38bdf8);"
               role="progressbar"></div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- ── My Tasks ──────────────────────────────────────── -->
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-check2-square me-2 text-primary"></i>My Open Tasks</h6>
            <a href="tasks.php" class="btn btn-sm btn-outline-primary">Full Board</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($my_tasks)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-check2-all fs-1 d-block mb-2 text-success opacity-50"></i>
              <div class="fw-semibold mb-1">All caught up! 🎉</div>
              <div class="small">No open tasks right now.</div>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
              <?php
              $priorityColors = ['critical'=>'danger','high'=>'warning','medium'=>'info','low'=>'secondary'];
              $statusIcons    = ['todo'=>'bi-circle','in_progress'=>'bi-play-circle-fill','in_review'=>'bi-eye-fill'];
              foreach ($my_tasks as $t):
                $pc = $priorityColors[$t['priority']] ?? 'secondary';
                $si = $statusIcons[$t['status']] ?? 'bi-circle';
                $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d');
              ?>
              <div class="list-group-item border-0 border-bottom d-flex align-items-start gap-3 px-4 py-3">
                <i class="bi <?= $si ?> text-<?= $pc ?> fs-5 mt-1 flex-shrink-0"></i>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="fw-semibold text-truncate" style="font-size:.87rem;"><?= htmlspecialchars($t['title']) ?></div>
                  <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge bg-<?= $pc ?> bg-opacity-15 text-<?= $pc ?>" style="font-size:.62rem;"><?= ucfirst($t['priority']) ?></span>
                    <span class="text-muted" style="font-size:.74rem;"><?= htmlspecialchars($t['project_name']) ?></span>
                  </div>
                </div>
                <?php if ($t['due_date']): ?>
                <span class="flex-shrink-0 <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>" style="font-size:.72rem;">
                  <?= $overdue ? '⚠ ' : '' ?><?= date('d M', strtotime($t['due_date'])) ?>
                </span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── Right Column ───────────────────────────────── -->
      <div class="col-lg-5 d-flex flex-column gap-4">

        <!-- My Projects -->
        <div class="card">
          <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-kanban-fill me-2 text-success"></i>My Projects</h6>
          </div>
          <div class="card-body p-0">
            <?php if (empty($my_projects)): ?>
            <div class="text-center py-4 text-muted small">No projects assigned yet.</div>
            <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($my_projects as $p):
                $pct = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
                $statusColors = ['active'=>'success','planning'=>'info','on_hold'=>'warning','completed'=>'secondary','cancelled'=>'danger'];
                $sc = $statusColors[$p['status']] ?? 'secondary';
              ?>
              <div class="list-group-item border-0 border-bottom px-4 py-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <a href="project_details.php?id=<?= $p['id'] ?>"
                     class="fw-semibold text-decoration-none text-truncate" style="font-size:.85rem;">
                    <?= htmlspecialchars($p['name']) ?>
                  </a>
                  <span class="badge bg-<?= $sc ?> ms-2" style="font-size:.6rem;"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:5px;">
                    <div class="progress-bar bg-<?= $sc ?>" style="width:<?= $pct ?>%;"></div>
                  </div>
                  <span class="text-muted" style="font-size:.7rem;white-space:nowrap;"><?= $pct ?>%</span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Project Activity -->
        <div class="card flex-grow-1">
          <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-activity me-2 text-info"></i>Project Activity</h6>
          </div>
          <div class="card-body p-0">
            <?php if (empty($activities)): ?>
            <div class="text-center py-4 text-muted small">No recent activity.</div>
            <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($activities as $a): ?>
              <div class="list-group-item border-0 border-bottom d-flex gap-2 px-4 py-2">
                <?= get_avatar_html(['name'=>$a['user_name'],'avatar'=>$a['avatar']], '26px') ?>
                <div style="font-size:.78rem;" class="overflow-hidden">
                  <span class="fw-semibold"><?= htmlspecialchars($a['user_name']) ?></span>
                  <span class="text-muted"> <?= htmlspecialchars($a['description']) ?></span>
                  <div class="text-muted mt-1" style="font-size:.66rem;"><?= time_ago($a['created_at']) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /.app-content -->
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
