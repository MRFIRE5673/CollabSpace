<?php
// ─── User Management (Admin Only) ────────────────────────────
$page_title = 'User Management';
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { redirect('dashboard.php'); }
$db = getDB();
$user = current_user();
$uid  = $user['id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pw    = $_POST['password'] ?? 'password123';
        $role  = $_POST['role'] ?? 'member';
        if ($name && $email) {
            $result = attempt_register($name, $email, $pw, $role);
            if (!$result['success']) {
                $error = $result['message'];
            }
        }
    }

    if ($act === 'toggle_active') {
        $tid = (int)($_POST['target_id'] ?? 0);
        if ($tid && $tid != $uid) {
            $cur = $db->prepare("SELECT is_active FROM users WHERE id=?"); $cur->execute([$tid]);
            $cur = (int)$cur->fetchColumn();
            $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$cur?0:1, $tid]);
        }
    }

    if ($act === 'change_role') {
        $tid  = (int)($_POST['target_id'] ?? 0);
        $role = $_POST['new_role'] ?? 'member';
        if ($tid && $tid != $uid && in_array($role,['admin','manager','member'])) {
            $db->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $tid]);
        }
    }

    if ($act === 'delete') {
        $tid = (int)($_POST['target_id'] ?? 0);
        if ($tid && $tid != $uid) {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$tid]);
        }
    }

    header('Location: users.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$filter_role = $_GET['role'] ?? '';

$where  = '1=1';
$params = [];
if ($search)      { $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter_role) { $where .= ' AND u.role=?'; $params[] = $filter_role; }

$stmt = $db->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to=u.id) AS task_count,
           (SELECT COUNT(*) FROM project_members WHERE user_id=u.id) AS project_count
    FROM users u
    WHERE $where
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$role_colors = ['admin'=>'danger','manager'=>'primary','member'=>'success'];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>


  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-people-fill me-2 text-primary"></i>User Management</h2>
        <p class="text-muted small mb-0"><?= count($users) ?> user<?= count($users)!=1?'s':'' ?></p>
      </div>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal" id="create-user-btn">
        <i class="bi bi-person-plus me-1"></i>Add User
      </button>
    </div>
  </div>

  <div class="app-content">

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <?php
        $admin_count   = $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
        $manager_count = $db->query("SELECT COUNT(*) FROM users WHERE role='manager' AND is_active=1")->fetchColumn();
        $member_count  = $db->query("SELECT COUNT(*) FROM users WHERE role='member' AND is_active=1")->fetchColumn();
        $inactive_count = $db->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetchColumn();
      ?>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
          <div class="card-body py-3"><div class="stat-number"><?= $admin_count ?></div><div class="small opacity-85">Admins</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
          <div class="card-body py-3"><div class="stat-number"><?= $manager_count ?></div><div class="small opacity-85">Managers</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#059669,#047857);">
          <div class="card-body py-3"><div class="stat-number"><?= $member_count ?></div><div class="small opacity-85">Members</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#6b7280,#4b5563);">
          <div class="card-body py-3"><div class="stat-number"><?= $inactive_count ?></div><div class="small opacity-85">Inactive</div></div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="users-filter-form">
          <div class="input-group input-group-sm" style="max-width:250px;">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" class="form-control border-0 bg-body-secondary" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>" id="users-search-input">
          </div>
          <select name="role" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:160px;" id="users-role-filter">
            <option value="">All Roles</option>
            <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
            <option value="manager" <?= $filter_role==='manager'?'selected':'' ?>>Manager</option>
            <option value="member" <?= $filter_role==='member'?'selected':'' ?>>Member</option>
          </select>
          <button type="submit" class="btn btn-sm btn-primary" id="users-filter-btn">Filter</button>
          <?php if ($search || $filter_role): ?>
          <a href="users.php" class="btn btn-sm btn-outline-secondary" id="users-clear-filter">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Users Table -->
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0" id="users-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Projects</th>
                <th>Tasks</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr id="user-row-<?= $u['id'] ?>">
                <td>
                  <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:38px;height:38px;font-size:.75rem;background:#4f46e5;">
                      <?= strtoupper(substr($u['name'],0,2)) ?>
                    </div>
                    <div>
                      <div class="fw-semibold small"><?= htmlspecialchars($u['name']) ?> <?= $u['id']==$uid?'<span class="badge bg-secondary" style="font-size:.6rem;">You</span>':'' ?></div>
                      <div class="x-small text-muted"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge bg-<?= $role_colors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span>
                </td>
                <td><span class="small"><?= $u['project_count'] ?></span></td>
                <td><span class="small"><?= $u['task_count'] ?></span></td>
                <td>
                  <span class="d-flex align-items-center gap-1">
                    <span class="online-indicator <?= $u['status']==='online'?'':'offline-indicator' ?>"></span>
                    <span class="x-small <?= $u['is_active']?'text-success':'text-muted' ?>"><?= $u['is_active']?($u['status']==='online'?'Online':'Active'):'Inactive' ?></span>
                  </span>
                </td>
                <td><span class="x-small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></span></td>
                <td>
                  <?php if ($u['id'] != $uid): ?>
                  <div class="d-flex gap-1">
                    <!-- Role change -->
                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" id="role-btn-<?= $u['id'] ?>" style="font-size:.72rem;padding:2px 8px;">
                        Role
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <?php foreach (['admin','manager','member'] as $r): ?>
                        <li>
                          <form method="POST">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="new_role" value="<?= $r ?>">
                            <button type="submit" class="dropdown-item small <?= $u['role']===$r?'active':'' ?>" id="role-<?= $r ?>-<?= $u['id'] ?>"><?= ucfirst($r) ?></button>
                          </form>
                        </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                    <!-- Toggle active -->
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active']?'warning':'success' ?>" title="<?= $u['is_active']?'Deactivate':'Activate' ?>" id="toggle-active-<?= $u['id'] ?>" style="font-size:.72rem;padding:2px 6px;">
                        <i class="bi bi-<?= $u['is_active']?'person-dash':'person-check' ?>"></i>
                      </button>
                    </form>
                    <!-- Delete -->
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" id="del-user-<?= $u['id'] ?>" style="font-size:.72rem;padding:2px 6px;">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                  <?php else: ?>
                  <a href="profile.php" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;padding:2px 8px;" id="edit-own-profile">Edit Profile</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</main>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="create-user-form">
        <input type="hidden" name="action" value="create">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold" id="createUserModalLabel"><i class="bi bi-person-plus me-2 text-primary"></i>Add New User</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (!empty($error)): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required id="new-user-name">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required id="new-user-email">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Default: password123" id="new-user-pw">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Role</label>
            <select name="role" class="form-select" id="new-user-role">
              <option value="member">Member</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="create-user-submit"><i class="bi bi-person-plus me-1"></i>Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
