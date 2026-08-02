<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();

function nav_link(string $href, string $icon, string $label, string $current): string {
    $active = (basename($current) === basename($href)) ? 'active' : '';
    return "<li class=\"nav-item\"><a href=\"{$href}\" class=\"nav-link {$active}\"><i class=\"nav-icon bi {$icon}\"></i><p>{$label}</p></a></li>";
}
?>
<!-- ─── Sidebar ─────────────────────────────────────────── -->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <div class="sidebar-brand d-flex align-items-center gap-2 px-3 py-3">
    <div class="brand-logo">
      <div class="rounded-2 d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:linear-gradient(135deg,#4f46e5,#7c3aed);">
        <i class="bi bi-lightning-charge-fill text-white"></i>
      </div>
    </div>
    <span class="brand-text fw-bold fs-5 text-white">CollabSpace</span>
  </div>

  <div class="sidebar-wrapper">
    <nav class="mt-2 sidebar-nav">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">

        <!-- Main — visible to ALL roles -->
        <li class="nav-header text-uppercase" style="font-size:.65rem;letter-spacing:.1em;padding: 8px 16px;color:rgba(255,255,255,.45);">Main</li>

        <?php
        // Each role lands on their own home page
        $home = match($user['role'] ?? 'member') {
            'admin'   => 'dashboard.php',
            'manager' => 'manager_dashboard.php',
            'member'  => 'member_dashboard.php',
            'viewer'  => 'viewer_dashboard.php',
            default   => 'dashboard.php',
        };
        echo nav_link($home, 'bi-speedometer2', 'Dashboard', $current_page);
        ?>

        <?php if (is_member()): // member, manager, admin ?>
        <!-- Workspace -->
        <li class="nav-header text-uppercase" style="font-size:.65rem;letter-spacing:.1em;padding: 8px 16px;color:rgba(255,255,255,.45);">Workspace</li>

        <?= nav_link('workspaces.php', 'bi-grid-1x2-fill', 'Workspaces', $current_page) ?>
        <?= nav_link('projects.php',   'bi-kanban-fill',   'Projects',   $current_page) ?>
        <?= nav_link('tasks.php',      'bi-check2-square', 'Task Board', $current_page) ?>
        <?php endif; ?>

        <?php if (!is_member() && is_viewer()): // pure viewer only ?>
        <!-- Overview -->
        <li class="nav-header text-uppercase" style="font-size:.65rem;letter-spacing:.1em;padding: 8px 16px;color:rgba(255,255,255,.45);">Overview</li>
        <?= nav_link('viewer_dashboard.php', 'bi-eye-fill', 'Viewer Portal', $current_page) ?>
        <?php endif; ?>

        <?php if (is_member()): ?>
        <!-- Communication -->
        <li class="nav-header text-uppercase" style="font-size:.65rem;letter-spacing:.1em;padding: 8px 16px;color:rgba(255,255,255,.45);">Communication</li>

        <li class="nav-item">
          <a href="chat.php" class="nav-link <?= $current_page === 'chat.php' ? 'active' : '' ?>">
            <i class="nav-icon bi bi-chat-dots-fill"></i>
            <p>Team Chat <span class="badge badge-sm bg-success ms-auto" id="sidebar-chat-badge" style="display:none;"></span></p>
          </a>
        </li>
        <?= nav_link('files.php', 'bi-folder2-open', 'File Sharing', $current_page) ?>

        <!-- Planning -->
        <li class="nav-header text-uppercase" style="font-size:.65rem;letter-spacing:.1em;padding: 8px 16px;color:rgba(255,255,255,.45);">Planning</li>

        <?= nav_link('calendar.php',  'bi-calendar3', 'Calendar',      $current_page) ?>
        <?= nav_link('activity.php',  'bi-activity',  'Activity Feed', $current_page) ?>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <!-- Admin only -->
        <li class="nav-header text-uppercase" style="font-size:.65rem;letter-spacing:.1em;padding: 8px 16px;color:rgba(255,255,255,.45);">Administration</li>
        <?= nav_link('users.php', 'bi-people-fill', 'User Management', $current_page) ?>
        <?php endif; ?>

      </ul>
    </nav>
  </div>

  <!-- User Status Footer -->
  <div class="sidebar-footer px-3 py-2 border-top border-secondary">
    <div class="d-flex align-items-center gap-2">
      <?= get_avatar_html($user, '32px') ?>
      <div class="overflow-hidden">
        <div class="small fw-semibold text-white text-truncate"><?= htmlspecialchars($user['name']) ?></div>
        <div class="d-flex align-items-center gap-1">
          <span class="online-dot" style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
          <?php
          $roleBadgeColors = ['admin'=>'danger','manager'=>'success','member'=>'primary','viewer'=>'secondary'];
          $roleColor = $roleBadgeColors[$user['role']] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $roleColor ?>" style="font-size:.55rem;padding:2px 5px;"><?= ucfirst(htmlspecialchars($user['role'])) ?></span>
        </div>
      </div>
      <a href="profile.php" class="ms-auto text-secondary"><i class="bi bi-gear-fill"></i></a>
    </div>
  </div>
</aside>
<!-- ─── /Sidebar ────────────────────────────────────────── -->
