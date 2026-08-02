<?php
// ─── Member Dashboard ────────────────────────────────────────
$page_title = 'My Board';
require_once __DIR__ . '/includes/auth.php';
require_login();

// Only members (and above) can see this page
if (!is_member()) {
    redirect(get_role_redirect($_SESSION['user_role'] ?? 'viewer'));
}

$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// ── My Stats ──────────────────────────────────────────────────
$my_open      = (int)$db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status!='done'")->execute([$uid])
                ? (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status!='done'")->fetchColumn() : 0;
$my_done      = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status='done'")->fetchColumn();
$my_overdue   = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status!='done' AND due_date < CURDATE()")->fetchColumn();
$my_inreview  = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status='in_review'")->fetchColumn();

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
    JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
    ORDER BY p.created_at DESC LIMIT 6
");
$stmt2->execute([$uid]);
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

<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h3 class="mb-0 fw-bold">
            <i class="bi bi-person-workspace me-2 text-primary"></i>My Board
          </h3>
          <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($user['name']) ?> &middot; <?= date('l, d F Y') ?></p>
        </div>
        <div class="col-sm-6 d-flex gap-2 justify-content-sm-end mt-2 mt-sm-0">
          <a href="tasks.php" class="btn btn-primary btn-sm"><i class="bi bi-check2-square me-1"></i>Task Board</a>
          <a href="chat.php"  class="btn btn-outline-secondary btn-sm"><i class="bi bi-chat-dots-fill me-1"></i>Team Chat</a>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      <!-- ── Stats Row ──────────────────────────────────────── -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #4f46e5 !important;">
            <div class="display-6 fw-bold text-primary"><?= $my_open ?></div>
            <div class="small text-muted">My Open Tasks</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #22c55e !important;">
            <div class="display-6 fw-bold text-success"><?= $my_done ?></div>
            <div class="small text-muted">Completed</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #f59e0b !important;">
            <div class="display-6 fw-bold text-warning"><?= $my_inreview ?></div>
            <div class="small text-muted">In Review</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #ef4444 !important;">
            <div class="display-6 fw-bold text-danger"><?= $my_overdue ?></div>
            <div class="small text-muted">Overdue</div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- ── My Tasks ──────────────────────────────────────── -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-check2-square me-2 text-primary"></i>My Open Tasks</h6>
              <a href="tasks.php" class="btn btn-sm btn-outline-primary">Full Board</a>
            </div>
            <div class="card-body p-0">
              <?php if (empty($my_tasks)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-check2-all fs-1 d-block mb-2 text-success"></i>
                All caught up! No open tasks. 🎉
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
                <div class="list-group-item border-0 d-flex align-items-start gap-3 px-4 py-3">
                  <i class="bi <?= $si ?> text-<?= $pc ?> fs-5 mt-1 flex-shrink-0"></i>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold text-truncate"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                      <span class="badge bg-<?= $pc ?> bg-opacity-10 text-<?= $pc ?>" style="font-size:.65rem;"><?= ucfirst($t['priority']) ?></span>
                      <span class="small text-muted"><?= htmlspecialchars($t['project_name']) ?></span>
                    </div>
                  </div>
                  <?php if ($t['due_date']): ?>
                  <span class="small <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?> flex-shrink-0">
                    <?= $overdue ? '⚠️ ' : '' ?><?= date('d M', strtotime($t['due_date'])) ?>
                  </span>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ── Right Column ──────────────────────────────────── -->
        <div class="col-lg-5">
          <!-- My Projects -->
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-kanban-fill me-2 text-success"></i>My Projects</h6>
            </div>
            <div class="card-body p-0">
              <?php if (empty($my_projects)): ?>
              <div class="text-center py-4 text-muted small">No projects assigned yet.</div>
              <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($my_projects as $p):
                  $pct = $p['task_count'] > 0 ? round(($p['done_count']/$p['task_count'])*100) : 0;
                  $statusColors = ['active'=>'success','planning'=>'info','on_hold'=>'warning','completed'=>'secondary','cancelled'=>'danger'];
                  $sc = $statusColors[$p['status']] ?? 'secondary';
                ?>
                <div class="list-group-item border-0 px-4 py-2">
                  <div class="d-flex justify-content-between mb-1">
                    <a href="project_details.php?id=<?= $p['id'] ?>" class="small fw-semibold text-decoration-none text-truncate"><?= htmlspecialchars($p['name']) ?></a>
                    <span class="badge bg-<?= $sc ?> ms-2" style="font-size:.6rem;"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:5px;border-radius:4px;">
                      <div class="progress-bar bg-<?= $sc ?>" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <span class="small text-muted" style="white-space:nowrap;font-size:.7rem;"><?= $pct ?>%</span>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-info"></i>Project Activity</h6>
            </div>
            <div class="card-body p-0">
              <?php if (empty($activities)): ?>
              <div class="text-center py-4 text-muted small">No recent activity.</div>
              <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($activities as $a): ?>
                <div class="list-group-item border-0 d-flex gap-2 px-4 py-2">
                  <?= get_avatar_html(['name'=>$a['user_name'],'avatar'=>$a['avatar']], '26px') ?>
                  <div class="small overflow-hidden">
                    <span class="fw-semibold"><?= htmlspecialchars($a['user_name']) ?></span>
                    <span class="text-muted"> <?= htmlspecialchars($a['description']) ?></span>
                    <div class="text-muted" style="font-size:.68rem;"><?= time_ago($a['created_at']) ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
