<?php
$user      = current_user();
$uid       = $user['id'];
$db        = getDB();
$notif_count = count_unread_notifications($uid);

// Fetch recent notifications
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();
?>
<!-- ─── Top Navbar ──────────────────────────────────────── -->
<nav class="app-header navbar navbar-expand bg-body shadow-sm border-bottom">
  <div class="container-fluid">
    <!-- Sidebar toggle -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
          <i class="bi bi-list fs-4"></i>
        </a>
      </li>
      <!-- Brand -->
      <li class="nav-item d-none d-sm-inline-block">
        <a href="dashboard.php" class="nav-link fw-bold text-primary">
          <i class="bi bi-lightning-charge-fill me-1"></i>CollabSpace
        </a>
      </li>
    </ul>

    <!-- Right nav -->
    <ul class="navbar-nav ms-auto">

      <!-- Search -->
      <li class="nav-item d-none d-md-block">
        <form class="d-flex" action="projects.php" method="GET">
          <div class="input-group input-group-sm" style="min-width:220px;">
            <input type="text" name="q" class="form-control form-control-sm border-0 bg-body-secondary rounded-start-pill" placeholder="Search projects…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <button class="btn btn-sm btn-body-secondary rounded-end-pill border-0" type="submit">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </form>
      </li>

      <!-- Dark mode toggle -->
      <li class="nav-item">
        <button id="theme-toggle" class="btn btn-sm nav-link border-0 px-2" title="Toggle dark mode">
          <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
        </button>
      </li>

      <!-- Notifications -->
      <li class="nav-item dropdown">
        <a class="nav-link position-relative px-2" href="#" role="button" data-bs-toggle="dropdown">
          <i class="bi bi-bell-fill fs-5"></i>
          <?php if ($notif_count > 0): ?>
          <span class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger notif-badge" id="notif-count"><?= min($notif_count, 99) ?></span>
          <?php else: ?>
          <span class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger notif-badge d-none" id="notif-count">0</span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-lg shadow border-0" style="min-width:340px;">
          <div class="d-flex align-items-center px-3 py-2 border-bottom">
            <span class="fw-semibold">Notifications</span>
            <a href="#" class="ms-auto small text-primary text-decoration-none mark-all-read">Mark all read</a>
          </div>
          <div class="notif-list" style="max-height:320px;overflow-y:auto;">
            <?php if (empty($notifs)): ?>
            <div class="text-center py-4 text-muted small"><i class="bi bi-check-circle-fill fs-4 d-block mb-2"></i>All caught up!</div>
            <?php else: ?>
            <?php foreach ($notifs as $n): ?>
            <?php
              $icons = ['task'=>'bi-check2-square','project'=>'bi-kanban','chat'=>'bi-chat-dots','file'=>'bi-file-earmark','system'=>'bi-gear'];
              $colors = ['task'=>'primary','project'=>'success','chat'=>'info','file'=>'warning','system'=>'secondary'];
              $ic = $icons[$n['type']] ?? 'bi-bell';
              $cl = $colors[$n['type']] ?? 'secondary';
            ?>
            <a class="dropdown-item px-3 py-2 <?= $n['is_read'] ? '' : 'bg-primary bg-opacity-10' ?> notif-item border-bottom" href="<?= htmlspecialchars($n['link'] ?? '#') ?>" data-id="<?= $n['id'] ?>">
              <div class="d-flex align-items-start gap-2">
                <div class="rounded-circle bg-<?= $cl ?> bg-opacity-15 p-2 text-<?= $cl ?> flex-shrink-0" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                  <i class="bi <?= $ic ?> small"></i>
                </div>
                <div class="overflow-hidden">
                  <div class="fw-semibold small lh-1 mb-1"><?= htmlspecialchars($n['title']) ?></div>
                  <div class="small text-muted text-truncate"><?= htmlspecialchars($n['message']) ?></div>
                  <div class="x-small text-muted mt-1"><?= time_ago($n['created_at']) ?></div>
                </div>
                <?php if (!$n['is_read']): ?><span class="ms-auto mt-1 flex-shrink-0"><i class="bi bi-circle-fill text-primary" style="font-size:8px;"></i></span><?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="px-3 py-2 text-center">
            <a href="activity.php" class="small text-primary text-decoration-none">View all activity</a>
          </div>
        </div>
      </li>

      <!-- User Profile Dropdown -->
      <li class="nav-item dropdown">
        <a class="nav-link d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
          <?= get_avatar_html($user, '32px') ?>
          <div class="d-none d-md-block lh-1">
            <div class="small fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
            <div class="x-small text-muted"><?= ucfirst($user['role']) ?></div>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow border-0">
          <div class="px-3 py-2 border-bottom">
            <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($user['email']) ?></div>
          </div>
          <a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a>
          <a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
          <?php if (is_admin()): ?>
          <a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i>Manage Users</a>
          <?php endif; ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
      </li>

    </ul>
  </div>
</nav>
<!-- ─── /Top Navbar ─────────────────────────────────────── -->
