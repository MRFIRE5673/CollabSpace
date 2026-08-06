<?php
// ─── Viewer Dashboard ────────────────────────────────────────
$page_title = 'Viewer Portal';
require_once __DIR__ . '/includes/auth.php';
require_login();

// All logged-in users can access viewer portal (it's the most restricted role)
// Higher roles are redirected to their own page, but can browse here too
$user = current_user();
$db   = getDB();

// ── Read-Only Stats ───────────────────────────────────────────
$total_projects  = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$active_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$total_tasks     = (int)$db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$done_tasks      = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();
$completion_pct  = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;
$total_members   = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();

// Projects summary (read-only)
$projects = $db->query("
    SELECT p.*, u.name AS manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done')  AS done_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count
    FROM projects p JOIN users u ON u.id=p.manager_id
    ORDER BY p.created_at DESC
")->fetchAll();

// Team overview (no PII)
$team = $db->query("
    SELECT id, name, role, avatar, status,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to=users.id AND status='done') AS done_tasks
    FROM users WHERE is_active=1 ORDER BY role, name LIMIT 12
")->fetchAll();

// Recent public activity
$activities = $db->query("
    SELECT a.description, a.action, a.created_at, u.name AS user_name, u.avatar, p.name AS project_name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    ORDER BY a.created_at DESC LIMIT 12
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>


  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h3 class="mb-0 fw-bold">
            <i class="bi bi-eye-fill me-2 text-secondary"></i>Viewer Portal
          </h3>
          <p class="text-muted small mb-0">Read-only access &middot; <?= htmlspecialchars($user['name']) ?></p>
        </div>
        <div class="col-sm-6 d-flex gap-2 justify-content-sm-end mt-2 mt-sm-0">
          <span class="badge bg-secondary fs-6 px-3 py-2"><i class="bi bi-eye-fill me-1"></i>Read Only Access</span>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      <!-- ── Read-Only Notice ──────────────────────────────── -->
      <div class="alert alert-info d-flex align-items-center gap-3 mb-4 border-0 shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill fs-4"></i>
        <div>
          <strong>Viewer Access:</strong> You have read-only access to workspace data. Contact an admin to request elevated permissions.
        </div>
      </div>

      <!-- ── Stats Row ──────────────────────────────────────── -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card border-0 shadow-sm text-center py-3">
            <i class="bi bi-kanban-fill text-primary fs-3 mb-1"></i>
            <div class="fs-3 fw-bold"><?= $total_projects ?></div>
            <div class="small text-muted">Projects</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card border-0 shadow-sm text-center py-3">
            <i class="bi bi-lightning-charge-fill text-success fs-3 mb-1"></i>
            <div class="fs-3 fw-bold"><?= $active_projects ?></div>
            <div class="small text-muted">Active</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card border-0 shadow-sm text-center py-3">
            <i class="bi bi-check2-square text-info fs-3 mb-1"></i>
            <div class="fs-3 fw-bold"><?= $completion_pct ?>%</div>
            <div class="small text-muted">Task Completion</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card border-0 shadow-sm text-center py-3">
            <i class="bi bi-people-fill text-warning fs-3 mb-1"></i>
            <div class="fs-3 fw-bold"><?= $total_members ?></div>
            <div class="small text-muted">Team Members</div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- ── Projects Overview (Read-Only) ────────────────── -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-kanban-fill me-2 text-primary"></i>Projects Overview <span class="badge bg-secondary ms-1" style="font-size:.6rem;">READ ONLY</span></h6>
            </div>
            <div class="card-body p-0">
              <?php if (empty($projects)): ?>
              <div class="text-center py-5 text-muted">No projects yet.</div>
              <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($projects as $p):
                  $pct = $p['task_count'] > 0 ? round(($p['done_count']/$p['task_count'])*100) : 0;
                  $statusColors = ['active'=>'success','planning'=>'info','on_hold'=>'warning','completed'=>'secondary','cancelled'=>'danger'];
                  $sc = $statusColors[$p['status']] ?? 'secondary';
                ?>
                <div class="list-group-item border-0 px-4 py-3">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                      <div class="small text-muted">Manager: <?= htmlspecialchars($p['manager_name']) ?></div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                      <span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span>
                      <?php $pc = ['low'=>'success','medium'=>'info','high'=>'warning','critical'=>'danger']; ?>
                      <span class="badge bg-<?= $pc[$p['priority']] ?? 'secondary' ?> bg-opacity-75" style="font-size:.6rem;"><?= ucfirst($p['priority']) ?> priority</span>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px;border-radius:4px;">
                      <div class="progress-bar bg-<?= $sc ?>" style="width:<?= $pct ?>%;border-radius:4px;"></div>
                    </div>
                    <span class="small text-muted" style="white-space:nowrap;"><?= $pct ?>% · <?= $p['task_count'] ?> tasks</span>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ── Right Column ──────────────────────────────────── -->
        <div class="col-lg-5">
          <!-- Team Overview -->
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-warning"></i>Team <span class="badge bg-secondary ms-1" style="font-size:.6rem;">READ ONLY</span></h6>
            </div>
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <?php foreach ($team as $m):
                  $roleBadge = ['admin'=>'danger','manager'=>'success','member'=>'primary','viewer'=>'secondary'];
                  $rb = $roleBadge[$m['role']] ?? 'secondary';
                ?>
                <div class="list-group-item border-0 d-flex align-items-center gap-3 px-4 py-2">
                  <?= get_avatar_html($m, '32px') ?>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="small fw-semibold text-truncate"><?= htmlspecialchars($m['name']) ?></div>
                    <span class="badge bg-<?= $rb ?>" style="font-size:.55rem;"><?= ucfirst($m['role']) ?></span>
                  </div>
                  <div class="text-end">
                    <div class="small text-success fw-semibold"><?= $m['done_tasks'] ?> done</div>
                    <div class="x-small text-muted">
                      <span class="rounded-circle d-inline-block me-1" style="width:7px;height:7px;background:<?= $m['status']==='online'?'#22c55e':'#6b7280'; ?>;"></span>
                      <?= ucfirst($m['status']) ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Recent Activity (read-only) -->
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3">
              <h6 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-info"></i>Activity Feed</h6>
            </div>
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <?php foreach ($activities as $a): ?>
                <div class="list-group-item border-0 d-flex gap-2 px-4 py-2">
                  <?= get_avatar_html(['name'=>$a['user_name'],'avatar'=>$a['avatar']], '26px') ?>
                  <div class="small overflow-hidden">
                    <span class="fw-semibold"><?= htmlspecialchars($a['user_name']) ?></span>
                    <span class="text-muted"> <?= htmlspecialchars($a['description']) ?></span>
                    <?php if ($a['project_name']): ?>
                    <span class="text-primary"> in <?= htmlspecialchars($a['project_name']) ?></span>
                    <?php endif; ?>
                    <div class="text-muted" style="font-size:.68rem;"><?= time_ago($a['created_at']) ?></div>
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
