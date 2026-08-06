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
  <div class="container-fluid px-3">
    <!-- Sidebar toggle -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link px-2" data-lte-toggle="sidebar" href="#" role="button">
          <i class="bi bi-list fs-4"></i>
        </a>
      </li>
      <!-- Brand (mobile) -->
      <li class="nav-item d-sm-none">
        <a href="dashboard.php" class="nav-link fw-bold text-primary d-flex align-items-center gap-2">
          <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#5c49e0,#8b5cf6);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-lightning-charge-fill text-white" style="font-size:.75rem;"></i>
          </div>
        </a>
      </li>
    </ul>

    <!-- Right nav -->
    <ul class="navbar-nav ms-auto align-items-center gap-1">

      <!-- Search -->
      <li class="nav-item d-none d-md-block me-1">
        <form class="d-flex" action="projects.php" method="GET">
          <div class="input-group" style="min-width:220px;">
            <input type="text" name="q"
                   class="form-control nav-search-input border-0"
                   placeholder="Search projects, tasks…"
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <button class="btn btn-sm border-0 px-2" type="submit"
                    style="background:var(--cs-surface-2);border-radius:0 99px 99px 0 !important;color:var(--cs-text-muted);">
              <i class="bi bi-search" style="font-size:.75rem;"></i>
            </button>
          </div>
        </form>
      </li>

      <!-- Theme Toggle — animated pill -->
      <li class="nav-item">
        <button id="theme-toggle" class="theme-toggle-pill border-0" title="Toggle theme" type="button">
          <span class="toggle-icon" id="theme-icon-wrap">
            <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
          </span>
          <span class="theme-toggle-label" id="theme-label">Dark</span>
        </button>
      </li>

      <!-- Notifications -->
      <li class="nav-item dropdown">
        <a class="nav-link position-relative px-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bi bi-bell-fill fs-5"></i>
          <?php if ($notif_count > 0): ?>
          <span class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger notif-badge" id="notif-count"><?= min($notif_count, 99) ?></span>
          <?php else: ?>
          <span class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger notif-badge d-none" id="notif-count">0</span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="min-width:340px;border-radius:16px !important;overflow:hidden;">
          <div class="d-flex align-items-center px-4 py-3" style="border-bottom:1px solid var(--cs-border);background:var(--cs-surface-2);">
            <div>
              <div class="fw-bold" style="font-family:'Outfit',sans-serif;font-size:.9rem;">Notifications</div>
              <?php if ($notif_count > 0): ?>
              <div class="text-muted" style="font-size:.68rem;"><?= $notif_count ?> unread</div>
              <?php endif; ?>
            </div>
            <a href="#" class="ms-auto btn btn-sm btn-outline-primary mark-all-read" style="font-size:.7rem;">Mark all read</a>
          </div>
          <div class="notif-list" style="max-height:320px;overflow-y:auto;">
            <?php if (empty($notifs)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-check-circle-fill fs-3 d-block mb-2 text-success opacity-50"></i>
              <div style="font-size:.82rem;">All caught up!</div>
            </div>
            <?php else: ?>
            <?php foreach ($notifs as $n): ?>
            <?php
              $icons  = ['task'=>'bi-check2-square','project'=>'bi-kanban','chat'=>'bi-chat-dots','file'=>'bi-file-earmark','system'=>'bi-gear'];
              $colors = ['task'=>'primary','project'=>'success','chat'=>'info','file'=>'warning','system'=>'secondary'];
              $ic = $icons[$n['type']] ?? 'bi-bell';
              $cl = $colors[$n['type']] ?? 'secondary';
            ?>
            <a class="dropdown-item px-4 py-3 <?= $n['is_read'] ? '' : 'bg-primary bg-opacity-10' ?> notif-item"
               href="<?= htmlspecialchars($n['link'] ?? '#') ?>" data-id="<?= $n['id'] ?>"
               style="border-bottom:1px solid var(--cs-border);">
              <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-<?= $cl ?> bg-opacity-15 text-<?= $cl ?> flex-shrink-0"
                     style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:.85rem;">
                  <i class="bi <?= $ic ?>"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                  <div class="fw-semibold" style="font-size:.82rem;line-height:1.2;"><?= htmlspecialchars($n['title']) ?></div>
                  <div class="text-muted text-truncate" style="font-size:.75rem;"><?= htmlspecialchars($n['message']) ?></div>
                  <div class="text-muted mt-1" style="font-size:.66rem;"><?= time_ago($n['created_at']) ?></div>
                </div>
                <?php if (!$n['is_read']): ?><span class="ms-auto mt-1 flex-shrink-0"><i class="bi bi-circle-fill text-primary" style="font-size:7px;"></i></span><?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="px-4 py-2 text-center" style="border-top:1px solid var(--cs-border);background:var(--cs-surface-2);">
            <a href="activity.php" class="small text-primary text-decoration-none fw-semibold">View all activity →</a>
          </div>
        </div>
      </li>

      <!-- User Profile -->
      <li class="nav-item dropdown">
        <a class="nav-link d-flex align-items-center gap-2 ps-2" href="#" role="button" data-bs-toggle="dropdown">
          <?= get_avatar_html($user, '34px') ?>
          <div class="d-none d-md-block lh-1">
            <div style="font-size:.82rem;font-weight:600;"><?= htmlspecialchars($user['name']) ?></div>
            <div class="text-muted" style="font-size:.65rem;"><?= ucfirst($user['role']) ?></div>
          </div>
          <i class="bi bi-chevron-down d-none d-md-block" style="font-size:.65rem;color:var(--cs-text-muted);"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="min-width:220px;border-radius:14px !important;overflow:hidden;">
          <div class="px-4 py-3" style="background:linear-gradient(135deg,#5c49e0,#8b5cf6);">
            <div class="fw-bold text-white" style="font-size:.88rem;"><?= htmlspecialchars($user['name']) ?></div>
            <div class="text-white opacity-75" style="font-size:.72rem;"><?= htmlspecialchars($user['email']) ?></div>
          </div>
          <div class="py-1">
            <a class="dropdown-item py-2 px-4" href="profile.php"><i class="bi bi-person me-2 text-primary"></i>My Profile</a>
            <a class="dropdown-item py-2 px-4" href="dashboard.php"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</a>
            <?php if (is_admin()): ?>
            <a class="dropdown-item py-2 px-4" href="users.php"><i class="bi bi-people me-2 text-success"></i>Manage Users</a>
            <?php endif; ?>
            <div class="dropdown-divider my-1"></div>
            <a class="dropdown-item py-2 px-4 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
          </div>
        </div>
      </li>

    </ul>
  </div>
</nav>
<!-- ─── /Top Navbar ─────────────────────────────────────── -->
