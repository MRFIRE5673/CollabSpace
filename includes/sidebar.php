<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();

function nav_link(string $href, string $icon, string $label, string $current): string {
    $active = (basename($current) === basename($href)) ? 'active' : '';
    return "<li class=\"nav-item\"><a href=\"{$href}\" class=\"nav-link {$active}\"><i class=\"nav-icon bi {$icon}\"></i><p>{$label}</p></a></li>";
}
?>
<!-- ─── Sidebar ─────────────────────────────────────────── -->
<aside class="app-sidebar shadow">

  <!-- Brand -->
  <div class="sidebar-brand d-flex align-items-center gap-3">
    <div class="sidebar-brand-logo">
      <i class="bi bi-lightning-charge-fill text-white" style="font-size:1.1rem;"></i>
    </div>
    <div class="overflow-hidden">
      <div class="brand-text fw-bold">CollabSpace</div>
      <div class="brand-tagline">Real-Time Workspace</div>
    </div>
    <span class="brand-live-dot ms-auto flex-shrink-0" title="Live"></span>
  </div>

  <div class="sidebar-wrapper">
    <nav class="mt-2 sidebar-nav">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">

        <!-- Main -->
        <li class="nav-header">Main</li>

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
        <!-- Workspace -->
        <li class="nav-header">Workspace</li>
        <?= nav_link('workspaces.php', 'bi-grid-1x2-fill',  'Workspaces', $current_page) ?>
        <?= nav_link('projects.php',   'bi-kanban-fill',    'Projects',   $current_page) ?>
        <?= nav_link('tasks.php',      'bi-check2-square',  'Task Board', $current_page) ?>
        <?php endif; ?>

        <?php if (!is_member() && is_viewer()): ?>
        <li class="nav-header">Overview</li>
        <?= nav_link('viewer_dashboard.php', 'bi-eye-fill', 'Viewer Portal', $current_page) ?>
        <?php endif; ?>

        <?php if (is_member()): ?>
        <!-- Collaborate -->
        <li class="nav-header">Collaborate</li>
        <li class="nav-item">
          <a href="chat.php" class="nav-link <?= $current_page === 'chat.php' ? 'active' : '' ?>">
            <i class="nav-icon bi bi-chat-dots-fill"></i>
            <p>Team Chat <span class="badge badge-sm bg-success ms-auto" id="sidebar-chat-badge" style="display:none;font-size:.55rem;"></span></p>
          </a>
        </li>
        <?= nav_link('files.php',    'bi-folder2-open', 'File Sharing', $current_page) ?>

        <!-- Planning -->
        <li class="nav-header">Planning</li>
        <?= nav_link('calendar.php', 'bi-calendar3', 'Calendar',      $current_page) ?>
        <?= nav_link('activity.php', 'bi-activity',  'Activity Feed', $current_page) ?>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <!-- Administration -->
        <li class="nav-header">Administration</li>
        <?= nav_link('users.php', 'bi-people-fill', 'User Management', $current_page) ?>
        <?php endif; ?>

      </ul>
    </nav>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <?= get_avatar_html($user, '34px') ?>
      <div class="overflow-hidden flex-grow-1">
        <div class="small fw-semibold text-white text-truncate" style="font-size:.8rem;"><?= htmlspecialchars($user['name']) ?></div>
        <div class="d-flex align-items-center gap-1 mt-1">
          <span class="online-dot"></span>
          <?php
          $roleBadgeColors = ['admin'=>'danger','manager'=>'success','member'=>'primary','viewer'=>'secondary'];
          $roleColor = $roleBadgeColors[$user['role']] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $roleColor ?>" style="font-size:.52rem;padding:2px 6px;border-radius:4px !important;">
            <?= ucfirst(htmlspecialchars($user['role'])) ?>
          </span>
        </div>
      </div>
      <a href="profile.php" class="text-white opacity-50 hover-opacity-100 flex-shrink-0"
         style="transition:opacity .2s ease;" title="Settings">
        <i class="bi bi-gear-fill" style="font-size:.9rem;"></i>
      </a>
    </div>
  </div>

</aside>
<!-- ─── /Sidebar ────────────────────────────────────────── -->
