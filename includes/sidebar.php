<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();

function nav_link(string $href, string $icon, string $label, string $current, ?string $badgeText = null, string $badgeClass = 'bg-primary'): string {
    $active = (basename($current) === basename($href)) ? 'active' : '';
    $badgeHtml = $badgeText ? "<span class=\"badge {$badgeClass} ms-auto nav-badge\">{$badgeText}</span>" : '';
    return "
    <li class=\"nav-item\">
      <a href=\"{$href}\" class=\"nav-link {$active}\">
        <span class=\"active-indicator\"></span>
        <div class=\"nav-icon-wrapper\">
          <i class=\"bi {$icon}\"></i>
        </div>
        <span class=\"nav-link-title\">{$label}</span>
        {$badgeHtml}
      </a>
    </li>";
}
?>
<!-- ─── Premium Glassmorphic Sidebar ────────────────────────── -->
<aside class="app-sidebar shadow">

  <!-- Brand Section -->
  <div class="sidebar-brand d-flex align-items-center justify-content-between px-3 py-3">
    <a href="dashboard.php" class="d-flex align-items-center gap-3 text-decoration-none">
      <div class="sidebar-brand-logo">
        <i class="bi bi-lightning-charge-fill text-white fs-5"></i>
      </div>
      <div class="overflow-hidden">
        <div class="brand-text fw-bold">CollabSpace</div>
        <div class="brand-tagline">Pro Workspace</div>
      </div>
    </a>
    <div class="d-flex align-items-center gap-2">
      <span class="brand-live-dot" title="Real-Time System Active"></span>
      <button class="btn btn-sm text-muted p-0 d-lg-none" onclick="document.body.classList.toggle('sidebar-collapsed')" title="Toggle Sidebar">
        <i class="bi bi-x-lg fs-5"></i>
      </button>
    </div>
  </div>

  <!-- Workspace Selector Card -->
  <div class="sidebar-workspace-card mx-3 my-2 p-2 rounded-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2 overflow-hidden">
      <div class="rounded-circle bg-primary bg-opacity-15 text-primary p-2 d-flex align-items-center justify-content-center" style="width:28px;height:28px;">
        <i class="bi bi-building fs-6"></i>
      </div>
      <div class="overflow-hidden">
        <div class="x-small fw-bold text-truncate brand-text" style="font-size:.72rem;">Main Organization</div>
        <div class="text-muted x-small" style="font-size:.64rem;">Enterprise Plan</div>
      </div>
    </div>
    <span class="badge bg-success bg-opacity-15 text-success x-small" style="font-size:.58rem;padding:2px 6px;">Live</span>
  </div>

  <div class="sidebar-wrapper px-2">
    <nav class="sidebar-nav">
      <ul class="nav sidebar-menu flex-column" role="menu">

        <!-- Main Section -->
        <li class="nav-header">Main Overview</li>

        <?php
        $home = match($user['role'] ?? 'member') {
            'admin'   => 'dashboard.php',
            'manager' => 'manager_dashboard.php',
            'member'  => 'member_dashboard.php',
            'viewer'  => 'viewer_dashboard.php',
            default   => 'dashboard.php',
        };
        echo nav_link($home, 'bi-speedometer2', 'Dashboard', $current_page);
        ?>

        <?php if (is_member()): ?>
        <!-- Workspace Section -->
        <li class="nav-header">Workspace & Projects</li>
        <?= nav_link('workspaces.php', 'bi-grid-1x2-fill',  'Workspaces', $current_page) ?>
        <?= nav_link('projects.php',   'bi-kanban-fill',    'Projects',   $current_page) ?>
        <?= nav_link('tasks.php',      'bi-check2-square',  'Task Board', $current_page) ?>
        <?php endif; ?>

        <?php if (!is_member() && is_viewer()): ?>
        <li class="nav-header">Overview</li>
        <?= nav_link('viewer_dashboard.php', 'bi-eye-fill', 'Viewer Portal', $current_page) ?>
        <?php endif; ?>

        <?php if (is_member()): ?>
        <!-- Collaborate Section -->
        <li class="nav-header">Collaborate</li>
        <li class="nav-item">
          <a href="chat.php" class="nav-link <?= $current_page === 'chat.php' ? 'active' : '' ?>">
            <span class="active-indicator"></span>
            <div class="nav-icon-wrapper">
              <i class="bi bi-chat-dots-fill"></i>
            </div>
            <span class="nav-link-title">Team Chat</span>
            <span class="badge bg-success ms-auto nav-badge" id="sidebar-chat-badge" style="display:none;font-size:.6rem;">0</span>
          </a>
        </li>
        <?= nav_link('files.php',    'bi-folder2-open', 'File Storage', $current_page) ?>

        <!-- Planning Section -->
        <li class="nav-header">Planning & Logs</li>
        <?= nav_link('calendar.php', 'bi-calendar3', 'Calendar',      $current_page) ?>
        <?= nav_link('activity.php', 'bi-activity',  'Activity Feed', $current_page) ?>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <!-- Administration Section -->
        <li class="nav-header">Administration</li>
        <?= nav_link('users.php', 'bi-people-fill', 'User Management', $current_page) ?>
        <?php endif; ?>

      </ul>
    </nav>
  </div>

  <!-- Sidebar Footer Profile Card -->
  <div class="sidebar-footer p-2 m-3 rounded-4 shadow-sm">
    <div class="d-flex align-items-center gap-2">
      <div class="position-relative flex-shrink-0">
        <?= get_avatar_html($user, '36px') ?>
        <span class="online-indicator-dot"></span>
      </div>
      <div class="overflow-hidden flex-grow-1">
        <div class="fw-bold text-truncate sidebar-user-name" style="font-size:.82rem;"><?= htmlspecialchars($user['name']) ?></div>
        <div class="d-flex align-items-center gap-1 mt-1">
          <?php
          $roleBadgeColors = ['admin'=>'danger','manager'=>'success','member'=>'primary','viewer'=>'secondary'];
          $roleColor = $roleBadgeColors[$user['role']] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $roleColor ?> bg-opacity-25 text-<?= $roleColor ?> border border-<?= $roleColor ?> border-opacity-25" style="font-size:.56rem;padding:2px 7px;border-radius:6px !important;font-weight:600;">
            <?= ucfirst(htmlspecialchars($user['role'])) ?>
          </span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-1 flex-shrink-0">
        <a href="profile.php" class="btn btn-sm btn-icon-only text-muted rounded-circle p-1" title="Account Settings">
          <i class="bi bi-gear-fill" style="font-size:.85rem;"></i>
        </a>
        <a href="logout.php" class="btn btn-sm btn-icon-only text-danger rounded-circle p-1" title="Logout">
          <i class="bi bi-box-arrow-right" style="font-size:.85rem;"></i>
        </a>
      </div>
    </div>
  </div>

</aside>
<!-- ─── /Sidebar ────────────────────────────────────────── -->
