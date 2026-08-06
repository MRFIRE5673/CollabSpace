<?php
// ─── Activity Feed ────────────────────────────────────────────
$page_title = 'Activity Feed';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$project_filter = (int)($_GET['project_id'] ?? 0);
$action_filter  = $_GET['action'] ?? '';
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 20;
$offset         = ($page - 1) * $per_page;

$where  = '1=1';
$params = [];
if ($project_filter) { $where .= ' AND a.project_id=?'; $params[] = $project_filter; }
if ($action_filter)  { $where .= ' AND a.action=?';     $params[] = $action_filter; }

$count_stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs a WHERE $where");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

$stmt = $db->prepare("
    SELECT a.*, u.name AS user_name, u.avatar, u.role AS user_role, p.name AS project_name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$activities = $stmt->fetchAll();

$projects_list = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

$action_icons = [
    'task_created'        => ['bi-plus-circle-fill', 'primary'],
    'task_completed'      => ['bi-check-circle-fill', 'success'],
    'task_status_updated' => ['bi-arrow-repeat',     'info'],
    'project_created'     => ['bi-kanban-fill',       'success'],
    'file_uploaded'       => ['bi-file-earmark-arrow-up-fill', 'warning'],
    'member_added'        => ['bi-person-plus-fill',  'primary'],
    'user_registered'     => ['bi-person-check-fill', 'info'],
    'comment_added'       => ['bi-chat-left-fill',    'secondary'],
];

$action_labels = [
    'task_created'        => 'Task Created',
    'task_completed'      => 'Task Completed',
    'task_status_updated' => 'Task Updated',
    'project_created'     => 'Project Created',
    'file_uploaded'       => 'File Uploaded',
    'member_added'        => 'Member Added',
    'user_registered'     => 'User Registered',
    'comment_added'       => 'Comment Added',
];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>


  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-activity me-2 text-primary"></i>Activity Feed</h2>
        <p class="text-muted small mb-0"><?= $total ?> activities</p>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="row g-4">

      <!-- Activity List -->
      <div class="col-lg-8">
        <!-- Filters -->
        <div class="card mb-4">
          <div class="card-body py-3">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="activity-filter-form">
              <select name="project_id" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:200px;" id="activity-project-filter">
                <option value="">All Projects</option>
                <?php foreach ($projects_list as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $project_filter==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="action" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:200px;" id="activity-action-filter">
                <option value="">All Actions</option>
                <?php foreach ($action_labels as $k => $v): ?>
                <option value="<?= $k ?>" <?= $action_filter===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-primary" id="activity-filter-btn">Filter</button>
              <?php if ($project_filter || $action_filter): ?>
              <a href="activity.php" class="btn btn-sm btn-outline-secondary" id="activity-clear-filter">Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <?php if (empty($activities)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-activity fs-1 d-block mb-2 opacity-25"></i>
              <p>No activity found</p>
            </div>
            <?php else: ?>
            <?php foreach ($activities as $a): ?>
            <?php [$icon, $color] = $action_icons[$a['action']] ?? ['bi-bell','secondary']; ?>
            <div class="activity-item" id="activity-<?= $a['id'] ?>">
              <div class="activity-icon bg-<?= $color ?> bg-opacity-15 text-<?= $color ?>">
                <i class="bi <?= $icon ?>"></i>
              </div>
              <div class="activity-line flex-grow-1">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div class="activity-text">
                    <strong><?= htmlspecialchars($a['user_name']) ?></strong>
                    <span class="badge bg-<?= $color ?> bg-opacity-15 text-<?= $color ?> mx-1" style="font-size:.65rem;"><?= $action_labels[$a['action']] ?? $a['action'] ?></span>
                    <?= htmlspecialchars($a['description']) ?>
                  </div>
                  <span class="activity-time flex-shrink-0"><?= time_ago($a['created_at']) ?></span>
                </div>
                <div class="activity-time mt-1">
                  <?php if ($a['project_name']): ?>
                  <a href="project_details.php?id=<?= $a['project_id'] ?>" class="text-primary text-decoration-none"><i class="bi bi-kanban me-1"></i><?= htmlspecialchars($a['project_name']) ?></a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-3" aria-label="Activity pagination">
          <ul class="pagination pagination-sm justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" id="activity-prev-page">&laquo;</a></li>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" id="activity-page-<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" id="activity-next-page">&raquo;</a></li>
            <?php endif; ?>
          </ul>
        </nav>
        <?php endif; ?>
      </div>

      <!-- Stats Sidebar -->
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header bg-transparent py-3">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Activity Summary</h3>
          </div>
          <div class="card-body">
            <?php
              $action_counts = $db->query("SELECT action, COUNT(*) as cnt FROM activity_logs GROUP BY action ORDER BY cnt DESC")->fetchAll();
            ?>
            <?php foreach ($action_counts as $ac): ?>
            <?php [$icon2, $color2] = $action_icons[$ac['action']] ?? ['bi-bell','secondary']; ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi <?= $icon2 ?> text-<?= $color2 ?>" style="width:20px;"></i>
              <div class="flex-grow-1">
                <div class="small"><?= $action_labels[$ac['action']] ?? $ac['action'] ?></div>
                <div class="progress" style="height:4px;">
                  <div class="progress-bar bg-<?= $color2 ?>" style="width:<?= min(100, $ac['cnt'] * 10) ?>%;"></div>
                </div>
              </div>
              <span class="badge bg-<?= $color2 ?> bg-opacity-15 text-<?= $color2 ?>"><?= $ac['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Recent Active Members -->
        <div class="card">
          <div class="card-header bg-transparent py-3">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Most Active Members</h3>
          </div>
          <div class="card-body p-0">
            <?php
              $active_members = $db->query("
                  SELECT u.name, u.role, COUNT(a.id) AS activity_count
                  FROM activity_logs a JOIN users u ON u.id=a.user_id
                  GROUP BY a.user_id ORDER BY activity_count DESC LIMIT 5
              ")->fetchAll();
            ?>
            <?php foreach ($active_members as $i => $m): ?>
            <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
              <div class="fw-bold text-muted" style="width:20px;font-size:.8rem;">#<?= $i+1 ?></div>
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:34px;height:34px;font-size:.72rem;background:#4f46e5;">
                <?= strtoupper(substr($m['name'],0,2)) ?>
              </div>
              <div class="flex-grow-1">
                <div class="fw-semibold small"><?= htmlspecialchars($m['name']) ?></div>
                <div class="x-small text-muted"><?= ucfirst($m['role']) ?></div>
              </div>
              <span class="badge bg-primary bg-opacity-15 text-primary"><?= $m['activity_count'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
