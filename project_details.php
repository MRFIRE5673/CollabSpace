<?php
// ─── Project Details Page ────────────────────────────────────
$page_title = 'Project Details';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$project_id = (int)($_GET['id'] ?? 0);
if (!$project_id) { header('Location: projects.php'); exit; }

// Fetch project
$proj = $db->prepare("SELECT p.*, u.name AS manager_name FROM projects p JOIN users u ON u.id=p.manager_id WHERE p.id=?");
$proj->execute([$project_id]);
$proj = $proj->fetch();
if (!$proj) { header('Location: projects.php'); exit; }

$page_title = htmlspecialchars($proj['name']);

// Fetch tasks grouped by status
$tasks = $db->prepare("
    SELECT t.*, u.name AS assignee_name, u.avatar AS assignee_avatar
    FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to
    WHERE t.project_id=?
    ORDER BY t.position ASC, t.created_at DESC
");
$tasks->execute([$project_id]);
$tasks = $tasks->fetchAll();

$kanban_cols = ['todo'=>[],'in_progress'=>[],'in_review'=>[],'done'=>[]];
foreach ($tasks as $t) {
    $kanban_cols[$t['status']][] = $t;
}

// Project members
$members = $db->prepare("
    SELECT u.id, u.name, u.role, u.avatar, u.status
    FROM project_members pm JOIN users u ON u.id=pm.user_id
    WHERE pm.project_id=?
    ORDER BY u.name
");
$members->execute([$project_id]);
$members = $members->fetchAll();

// Files
$files = $db->prepare("
    SELECT f.*, u.name AS uploader_name
    FROM files f JOIN users u ON u.id=f.uploaded_by
    WHERE f.project_id=?
    ORDER BY f.uploaded_at DESC LIMIT 20
");
$files->execute([$project_id]);
$files = $files->fetchAll();

// Recent activity
$activity = $db->prepare("
    SELECT a.*, u.name AS user_name
    FROM activity_logs a JOIN users u ON u.id=a.user_id
    WHERE a.project_id=?
    ORDER BY a.created_at DESC LIMIT 15
");
$activity->execute([$project_id]);
$activity = $activity->fetchAll();

// All users for task assignment
$all_users = $db->query("SELECT id, name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();

// Active tab
$active_tab = $_GET['tab'] ?? 'board';

// File icons
$file_icons = [
    'pdf'=>['bi-file-earmark-pdf-fill','danger'],
    'doc'=>['bi-file-earmark-word-fill','primary'],'docx'=>['bi-file-earmark-word-fill','primary'],
    'xls'=>['bi-file-earmark-excel-fill','success'],'xlsx'=>['bi-file-earmark-excel-fill','success'],
    'ppt'=>['bi-file-earmark-ppt-fill','warning'],'pptx'=>['bi-file-earmark-ppt-fill','warning'],
    'jpg'=>['bi-file-earmark-image-fill','info'],'jpeg'=>['bi-file-earmark-image-fill','info'],
    'png'=>['bi-file-earmark-image-fill','info'],'gif'=>['bi-file-earmark-image-fill','info'],
    'zip'=>['bi-file-earmark-zip-fill','secondary'],'rar'=>['bi-file-earmark-zip-fill','secondary'],
    'txt'=>['bi-file-earmark-text-fill','secondary'],
];

// Progress stats
$total_t = count($tasks);
$done_t  = count($kanban_cols['done']);
$prog    = $total_t > 0 ? round(($done_t/$total_t)*100) : $proj['progress'];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>


  <div class="app-content">

    <!-- Page Hero -->
    <div class="page-hero hero-project mb-4">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <a href="projects.php" class="btn btn-sm btn-outline-light rounded-pill px-3" id="back-to-projects">
              <i class="bi bi-arrow-left me-1"></i>Projects
            </a>
            <?= status_badge($proj['status']) ?>
            <?= priority_badge($proj['priority']) ?>
          </div>
          <h1 class="h4 fw-bold mb-1"><?= htmlspecialchars($proj['name']) ?></h1>
          <p class="mb-0 opacity-85 small"><?= htmlspecialchars($proj['description'] ?? '') ?></p>
        </div>
        <div class="text-end">
          <div class="fs-2 fw-bold"><?= $prog ?>%</div>
          <div class="small opacity-85">Complete</div>
          <div class="progress mt-2" style="height:6px;min-width:120px;">
            <div class="progress-bar bg-white" style="width:<?= $prog ?>%;"></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-2">
        <div class="col-auto">
          <div class="small opacity-75"><i class="bi bi-person-fill me-1"></i>Manager: <strong class="opacity-100"><?= htmlspecialchars($proj['manager_name']) ?></strong></div>
        </div>
        <?php if ($proj['start_date']): ?>
        <div class="col-auto">
          <div class="small opacity-75"><i class="bi bi-calendar-check me-1"></i>Start: <strong class="opacity-100"><?= date('M j, Y', strtotime($proj['start_date'])) ?></strong></div>
        </div>
        <?php endif; ?>
        <?php if ($proj['due_date']): ?>
        <div class="col-auto">
          <div class="small opacity-75"><i class="bi bi-calendar-x me-1"></i>Due: <strong class="opacity-100"><?= date('M j, Y', strtotime($proj['due_date'])) ?></strong></div>
        </div>
        <?php endif; ?>
        <div class="col-auto">
          <div class="small opacity-75"><i class="bi bi-people me-1"></i><strong class="opacity-100"><?= count($members) ?></strong> members</div>
        </div>
        <div class="col-auto">
          <div class="small opacity-75"><i class="bi bi-check2-square me-1"></i><strong class="opacity-100"><?= $done_t ?>/<?= $total_t ?></strong> tasks</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="project-tabs">
      <li class="nav-item"><a class="nav-link <?= $active_tab==='board'?'active':'' ?>" href="?id=<?= $project_id ?>&tab=board" id="tab-board"><i class="bi bi-kanban me-1"></i>Task Board</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='chat'?'active':'' ?>" href="?id=<?= $project_id ?>&tab=chat" id="tab-chat"><i class="bi bi-chat-dots me-1"></i>Chat</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='files'?'active':'' ?>" href="?id=<?= $project_id ?>&tab=files" id="tab-files"><i class="bi bi-folder2-open me-1"></i>Files</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='members'?'active':'' ?>" href="?id=<?= $project_id ?>&tab=members" id="tab-members"><i class="bi bi-people me-1"></i>Members</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='analytics'?'active':'' ?>" href="?id=<?= $project_id ?>&tab=analytics" id="tab-analytics"><i class="bi bi-graph-up-arrow me-1"></i>Analytics</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='activity'?'active':'' ?>" href="?id=<?= $project_id ?>&tab=activity" id="tab-activity"><i class="bi bi-activity me-1"></i>Activity</a></li>
    </ul>

    <!-- ─── BOARD TAB ─────────────────────────────────────── -->
    <?php if ($active_tab === 'board'): ?>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h6 fw-bold mb-0"><i class="bi bi-kanban me-2 text-primary"></i>Kanban Board</h2>
      <button class="btn btn-primary btn-sm" onclick="openCreateTaskModal('todo')" id="add-task-btn">
        <i class="bi bi-plus-lg me-1"></i>Add Task
      </button>
    </div>
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
        <div class="kanban-cards" id="kanban-<?= $status ?>">
          <?php foreach ($kanban_cols[$status] as $t): ?>
          <div class="task-card" data-task-id="<?= $t['id'] ?>">
            <div class="priority-bar <?= $t['priority'] ?>"></div>
            <div class="ps-2">
              <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
              <?php if ($t['description']): ?>
              <p class="task-meta mb-1"><?= htmlspecialchars(substr($t['description'],0,60)) ?><?= strlen($t['description'])>60?'…':'' ?></p>
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
                  <button class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="deleteTask(<?= $t['id'] ?>)" title="Delete" id="del-task-<?= $t['id'] ?>"><i class="bi bi-x-circle-fill"></i></button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="kanban-add-btn btn btn-link text-muted w-100 py-2 border-top" style="border-radius:0 0 16px 16px;font-size:.8rem;" id="kanban-add-<?= $status ?>" onclick="openCreateTaskModal('<?= $status ?>')">
          <i class="bi bi-plus-lg me-1"></i>Add Task
        </button>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ─── CHAT TAB ──────────────────────────────────────── -->
    <?php elseif ($active_tab === 'chat'): ?>
      <div class="card border-0 shadow-sm" style="height:calc(100vh - 280px);min-height:480px;border-radius:18px;overflow:hidden;background:var(--cs-surface);">
        <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex align-items-center gap-2">
          <i class="bi bi-chat-dots-fill text-primary fs-5"></i>
          <span class="fw-bold" style="font-family:'Outfit';">Project Chat</span>
          <span class="badge bg-success bg-opacity-15 text-success ms-1">Live</span>
        </div>
        <div class="card-body p-0 d-flex flex-column" style="overflow:hidden;">
          <div class="chat-messages flex-grow-1 p-3 overflow-y-auto" id="chat-messages-box"
               data-project-id="<?= $project_id ?>"
               data-room-type="project"
               data-last-id="<?php
                 $last = $db->prepare("SELECT MAX(id) FROM chats WHERE project_id=? AND room_type='project'");
                 $last->execute([$project_id]);
                 echo (int)$last->fetchColumn();
               ?>">
            <?php
              $chat_msgs = $db->prepare("
                  SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar,
                         (c.sender_id = ?) AS is_mine
                  FROM chats c JOIN users u ON u.id=c.sender_id
                  WHERE c.project_id=? AND c.room_type='project'
                  ORDER BY c.created_at ASC LIMIT 100
              ");
              $chat_msgs->execute([$uid, $project_id]);
              $chat_msgs = $chat_msgs->fetchAll();
            ?>
            <?php if (empty($chat_msgs)): ?>
            <div class="text-center py-5 text-muted my-auto" id="chat-empty-state">
              <i class="bi bi-chat-dots fs-1 d-block mb-2 opacity-25"></i>
              <p class="small mb-0">No messages in this chat yet. Start the conversation!</p>
            </div>
            <?php endif; ?>
            <?php foreach ($chat_msgs as $m): ?>
            <?php $isMine = (bool)$m['is_mine']; ?>
            <div class="chat-msg <?= $isMine ? 'mine' : '' ?>" id="msg-<?= $m['id'] ?>">
              <div class="chat-avatar"><?= strtoupper(substr($m['sender_name'],0,2)) ?></div>
              <div class="chat-bubble">
                <div class="chat-meta">
                  <span class="fw-semibold me-2"><?= htmlspecialchars($m['sender_name']) ?></span>
                  <span><?= time_ago($m['created_at']) ?></span>
                </div>
                <?php if ($m['message']): ?>
                <div class="chat-text"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                <?php endif; ?>
                <?php if ($m['file_path']): ?>
                <?php
                  $isMine = ($m['sender_id'] == $uid);
                  $ext = strtolower(pathinfo($m['file_name'] ?? $m['file_path'], PATHINFO_EXTENSION));
                  $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','svg']);
                ?>
                <?php if ($isImg): ?>
                <div class="mt-2 mb-1">
                  <a href="view_file.php?file=<?= urlencode($m['file_path']) ?>">
                    <img src="raw_file.php?chat=1&file=<?= urlencode($m['file_path']) ?>" class="img-fluid rounded-3 shadow-sm" style="max-height:200px;max-width:100%;object-fit:cover;" onerror="this.style.display='none';">
                  </a>
                </div>
                <?php endif; ?>
                <div class="chat-attachment d-flex align-items-center gap-2 mt-1 p-2 rounded-3" style="<?= $isMine ? 'background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.25);' : 'background:var(--cs-bg);border:1px solid var(--cs-border);' ?>">
                  <a href="raw_file.php?chat=1&file=<?= urlencode($m['file_path']) ?>" download="<?= htmlspecialchars($m['file_name'] ?? $m['file_path']) ?>" class="btn btn-sm <?= $isMine ? 'btn-light text-primary fw-semibold' : 'btn-primary' ?> py-1 px-2" style="font-size:.78rem;">
                    <i class="bi bi-download me-1"></i><?= htmlspecialchars($m['file_name'] ?? 'Download') ?>
                  </a>
                  <a href="view_file.php?file=<?= urlencode($m['file_path']) ?>" class="btn btn-sm <?= $isMine ? 'btn-outline-light' : 'btn-outline-secondary' ?> py-1 px-2" style="font-size:.78rem;">
                    <i class="bi bi-eye me-1"></i>View
                  </a>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="chat-input-bar p-3 border-top bg-body-tertiary" style="flex-shrink:0;">
            <form id="chat-form" onsubmit="return sendChatMessage(this)" enctype="multipart/form-data">
              <input type="hidden" name="project_id" value="<?= $project_id ?>">
              <input type="hidden" name="room_type" value="project">
              <div id="chat-file-preview" class="mb-2"></div>
              <div class="d-flex align-items-center gap-2">
                <label class="btn btn-sm btn-outline-secondary mb-0 p-2 flex-shrink-0" title="Attach file">
                  <i class="bi bi-paperclip fs-6"></i>
                  <input type="file" name="chat_file" id="chat-file-input" class="d-none">
                </label>
                <input type="text" name="message" id="chat-input"
                       class="form-control form-control-sm border-0 bg-body-secondary"
                       placeholder="Type a message… (Press Enter to send)" autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm px-3 flex-shrink-0" id="chat-send-btn">
                  <i class="bi bi-send-fill me-1"></i> Send
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

    <!-- ─── FILES TAB ─────────────────────────────────────── -->
    <?php elseif ($active_tab === 'files'): ?>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h6 fw-bold mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i>Project Files</h2>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadFileModal" id="upload-file-btn">
        <i class="bi bi-cloud-upload me-1"></i>Upload File
      </button>
    </div>
    <?php if (empty($files)): ?>
    <div class="text-center py-5">
      <i class="bi bi-folder2-open fs-1 d-block mb-2 opacity-25"></i>
      <p class="text-muted">No files uploaded yet.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
      <?php foreach ($files as $f): ?>
      <?php
        $ext = strtolower(pathinfo($f['file_name'],PATHINFO_EXTENSION));
        [$ficon,$fcol] = $file_icons[$ext] ?? ['bi-file-earmark-fill','secondary'];
        $fsize = $f['file_size'] > 1048576 ? round($f['file_size']/1048576,1).'MB' : round($f['file_size']/1024,1).'KB';
      ?>
      <div class="col-sm-6 col-md-4 col-lg-3" id="file-card-<?= $f['id'] ?>">
        <div class="card file-card text-center h-100 position-relative">
          <div class="card-body py-4">
            <div class="file-icon-wrap bg-<?= $fcol ?> bg-opacity-10 text-<?= $fcol ?> mx-auto">
              <i class="bi <?= $ficon ?>"></i>
            </div>
            <div class="fw-semibold small text-truncate mb-1" title="<?= htmlspecialchars($f['original_name']) ?>"><?= htmlspecialchars($f['original_name']) ?></div>
            <div class="x-small text-muted mb-1"><?= $fsize ?> · <?= time_ago($f['uploaded_at']) ?></div>
            <div class="x-small text-muted mb-3">by <?= htmlspecialchars($f['uploader_name']) ?></div>
            <div class="d-flex align-items-center justify-content-center gap-2">
              <a href="raw_file.php?file=<?= htmlspecialchars($f['file_path']) ?>" download="<?= htmlspecialchars($f['original_name']) ?>" class="btn btn-sm btn-outline-primary py-1 px-3" id="dl-file-<?= $f['id'] ?>">
                <i class="bi bi-download me-1"></i>Download
              </a>
              <?php if (is_admin() || $f['uploaded_by'] == $uid): ?>
              <button class="btn btn-sm btn-outline-danger py-1 px-2" onclick="deleteProjectFile(<?= $f['id'] ?>)" title="Delete File">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ─── MEMBERS TAB ───────────────────────────────────── -->
    <?php elseif ($active_tab === 'members'): ?>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h6 fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Project Members</h2>
      <?php if (is_manager()): ?>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal" id="add-member-btn">
        <i class="bi bi-person-plus me-1"></i>Add Member
      </button>
      <?php endif; ?>
    </div>
    <div class="row g-3">
      <?php foreach ($members as $m): ?>
      <div class="col-sm-6 col-md-4">
        <div class="card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:48px;height:48px;background:#4f46e5;">
              <?= strtoupper(substr($m['name'],0,2)) ?>
            </div>
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($m['name']) ?></div>
              <div class="small text-muted"><?= ucfirst($m['role']) ?></div>
              <div class="d-flex align-items-center gap-1 mt-1">
                <span class="online-indicator <?= $m['status']==='online'?'':'offline-indicator' ?>"></span>
                <span class="x-small text-<?= $m['status']==='online'?'success':'muted' ?>"><?= $m['status']==='online'?'Online':'Offline' ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ─── ANALYTICS TAB ────────────────────────────────── -->
    <?php elseif ($active_tab === 'analytics'): ?>
    <?php
      $st_counts = ['todo'=>0, 'in_progress'=>0, 'in_review'=>0, 'done'=>0];
      $pr_counts = ['low'=>0, 'medium'=>0, 'high'=>0, 'urgent'=>0];
      $overdue_cnt = 0;
      foreach ($all_tasks as $t) {
        if (isset($st_counts[$t['status']])) $st_counts[$t['status']]++;
        if (isset($pr_counts[$t['priority']])) $pr_counts[$t['priority']]++;
        if ($t['status'] !== 'done' && $t['due_date'] && strtotime($t['due_date']) < time()) $overdue_cnt++;
      }
      $health_status = ($prog >= 75) ? 'Healthy' : (($overdue_cnt > 2) ? 'Needs Attention' : 'On Track');
      $health_color  = ($prog >= 75) ? 'success' : (($overdue_cnt > 2) ? 'danger' : 'info');
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4 text-center" style="border-radius:18px;background:var(--cs-surface);">
          <div class="x-small text-muted mb-1 text-uppercase fw-bold">Project Health Index</div>
          <div class="display-6 fw-bold text-<?= $health_color ?> my-2"><?= $health_status ?></div>
          <span class="badge bg-<?= $health_color ?> bg-opacity-15 text-<?= $health_color ?> mx-auto py-2 px-3 rounded-pill" style="font-size:.78rem;">
            <?= $prog ?>% Completed · <?= $overdue_cnt ?> Overdue
          </span>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4 text-center" style="border-radius:18px;background:var(--cs-surface);">
          <div class="x-small text-muted mb-1 text-uppercase fw-bold">Task Completion Ratio</div>
          <div class="display-6 fw-bold text-primary my-2"><?= $done_t ?> / <?= $total_t ?></div>
          <span class="small text-muted">Tasks Marked Finished</span>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4 text-center" style="border-radius:18px;background:var(--cs-surface);">
          <div class="x-small text-muted mb-1 text-uppercase fw-bold">Active Members</div>
          <div class="display-6 fw-bold text-info my-2"><?= count($members) ?></div>
          <span class="small text-muted">Team Collaborators</span>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:18px;background:var(--cs-surface);">
          <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Task Status Breakdown</h6>
          <div style="height: 260px;" class="position-relative">
            <canvas id="statusDoughnutChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:18px;background:var(--cs-surface);">
          <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-fill text-success me-2"></i>Task Priority Distribution</h6>
          <div style="height: 260px;" class="position-relative">
            <canvas id="priorityBarChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Status Chart
        const ctxStatus = document.getElementById('statusDoughnutChart')?.getContext('2d');
        if (ctxStatus) {
          new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
              labels: ['To Do', 'In Progress', 'In Review', 'Done'],
              datasets: [{
                data: [<?= $st_counts['todo'] ?>, <?= $st_counts['in_progress'] ?>, <?= $st_counts['in_review'] ?>, <?= $st_counts['done'] ?>],
                backgroundColor: ['#64748b', '#3b82f6', '#06b6d4', '#10b981'],
                borderWidth: 0
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { position: 'bottom' } }
            }
          });
        }

        // Priority Chart
        const ctxPriority = document.getElementById('priorityBarChart')?.getContext('2d');
        if (ctxPriority) {
          new Chart(ctxPriority, {
            type: 'bar',
            data: {
              labels: ['Low', 'Medium', 'High', 'Urgent'],
              datasets: [{
                label: 'Tasks',
                data: [<?= $pr_counts['low'] ?>, <?= $pr_counts['medium'] ?>, <?= $pr_counts['high'] ?>, <?= $pr_counts['urgent'] ?>],
                backgroundColor: ['#94a3b8', '#3b82f6', '#f59e0b', '#ef4444'],
                borderRadius: 6
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
          });
        }
      });
    </script>

    <!-- ─── ACTIVITY TAB ──────────────────────────────────── -->
    <?php elseif ($active_tab === 'activity'): ?>
    <h2 class="h6 fw-bold mb-3"><i class="bi bi-activity me-2 text-primary"></i>Project Activity</h2>
    <?php
      $action_icons = ['task_created'=>['bi-plus-circle-fill','primary'],'task_completed'=>['bi-check-circle-fill','success'],'task_status_updated'=>['bi-arrow-repeat','info'],'project_created'=>['bi-kanban-fill','success'],'file_uploaded'=>['bi-file-earmark-arrow-up-fill','warning'],'member_added'=>['bi-person-plus-fill','primary']];
    ?>
    <?php if (empty($activity)): ?>
    <div class="text-center py-5 text-muted small">No activity yet.</div>
    <?php else: ?>
    <?php foreach ($activity as $a): ?>
    <?php [$icon, $color] = $action_icons[$a['action']] ?? ['bi-bell','secondary']; ?>
    <div class="activity-item">
      <div class="activity-icon bg-<?= $color ?> bg-opacity-15 text-<?= $color ?>">
        <i class="bi <?= $icon ?>"></i>
      </div>
      <div class="activity-line">
        <div class="activity-text"><strong><?= htmlspecialchars($a['user_name']) ?></strong> <?= htmlspecialchars($a['description']) ?></div>
        <div class="activity-time"><?= time_ago($a['created_at']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</main>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="create-task-form" onsubmit="submitCreateTask(event)">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold" id="createTaskModalLabel"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Task</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="project_id" value="<?= $project_id ?>">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Task Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Design homepage mockup" required id="modal-task-title">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2" id="modal-task-desc"></textarea>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Assign To</label>
              <select name="assigned_to" class="form-select" id="modal-task-assignee">
                <option value="">Unassigned</option>
                <?php foreach ($members as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
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
          <button type="submit" class="btn btn-primary" id="create-task-submit"><i class="bi bi-plus-lg me-1"></i>Add Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="api/files.php?action=upload" method="POST" enctype="multipart/form-data" id="upload-file-form">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload File</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="upload-zone" onclick="document.getElementById('file-input').click()" id="upload-drop-zone">
            <i class="bi bi-cloud-upload-fill d-block mb-2"></i>
            <div class="fw-semibold mb-1">Click to upload or drag & drop</div>
            <div class="small text-muted">PDF, Word, Excel, Images, ZIP (max 20MB)</div>
          </div>
          <input type="file" name="file" id="file-input" class="d-none" onchange="showFilename(this)">
          <div id="file-selected" class="mt-2 small text-muted"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="upload-file-submit"><i class="bi bi-upload me-1"></i>Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="api/projects.php?action=add_member" method="POST" id="add-member-form">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Add Member</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label small fw-semibold">Select User</label>
          <select name="user_id" class="form-select" id="add-member-select">
            <?php
              $member_ids = array_column($members,'id');
              foreach ($all_users as $u):
                if (!in_array($u['id'],$member_ids)):
            ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endif; endforeach; ?>
          </select>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="add-member-submit"><i class="bi bi-person-plus me-1"></i>Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
function showFilename(input) {
  document.getElementById('file-selected').textContent = input.files[0]?.name || '';
}

// Drag & drop upload zone
const dz = document.getElementById('upload-drop-zone');
if (dz) {
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f) { const dt = new DataTransfer(); dt.items.add(f); document.getElementById('file-input').files = dt.files; showFilename(document.getElementById('file-input')); }
  });
}

// File attach preview with clear button
const chatFileInput = document.getElementById('chat-file-input');
if (chatFileInput) {
  chatFileInput.addEventListener('change', function() {
    const preview = document.getElementById('chat-file-preview');
    if (this.files && this.files[0]) {
      preview.innerHTML = `
        <span class="badge bg-primary text-white p-2 d-inline-flex align-items-center gap-2" style="font-size:.78rem;border-radius:8px;">
          <i class="bi bi-paperclip"></i>
          <span>${escapeHtml(this.files[0].name)}</span>
          <button type="button" class="btn-close btn-close-white ms-1" style="font-size:.65rem;" onclick="clearChatFile()"></button>
        </span>`;
    } else {
      preview.innerHTML = '';
    }
  });
}

function clearChatFile() {
  const fi = document.getElementById('chat-file-input');
  if (fi) fi.value = '';
  const prev = document.getElementById('chat-file-preview');
  if (prev) prev.innerHTML = '';
}

function escapeHtml(str) {
  return String(str || '').replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

// Submit create task via AJAX
function submitCreateTask(e) {
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
        showToast(res.message || 'Failed to create task.', 'danger');
      }
    }).catch(() => showToast('Network error.', 'danger'));
}

// Upload file form submit
const uploadForm = document.getElementById('upload-file-form');
if (uploadForm) {
  uploadForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch(this.action, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) { showToast('File uploaded!', 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Upload failed.', 'danger');
      }).catch(() => showToast('Network error.', 'danger'));
  });
}

// Single-click AJAX File Delete
function deleteProjectFile(id) {
  if (!confirm('Are you sure you want to delete this file?')) return;
  const fd = new FormData();
  fd.append('file_id', id);
  fetch('api/files.php?action=delete', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const card = document.getElementById('file-card-' + id);
        if (card) card.remove();
        showToast('File deleted.', 'success');
      } else {
        showToast(res.message || 'Failed to delete file.', 'danger');
      }
    }).catch(() => showToast('Network error.', 'danger'));
}

// Add member submit
const addMemberForm = document.getElementById('add-member-form');
if (addMemberForm) {
  addMemberForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch(this.action, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) { showToast('Member added!', 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Failed.', 'danger');
      }).catch(() => showToast('Network error.', 'danger'));
  });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
