<?php
// ─── User Profile & Settings ──────────────────────────────────
$page_title = 'My Profile';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Fetch full user data
$full_user = $db->prepare("SELECT * FROM users WHERE id=?");
$full_user->execute([$uid]);
$full_user = $full_user->fetch();

$success = '';
$error   = '';

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $bio   = trim($_POST['bio'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!$name) { $error = 'Name is required.'; }
        else {
            $db->prepare("UPDATE users SET name=?, bio=?, phone=? WHERE id=?")->execute([$name, $bio, $phone, $uid]);
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully!';
            // Refresh
            $full_user = $db->prepare("SELECT * FROM users WHERE id=?");
            $full_user->execute([$uid]);
            $full_user = $full_user->fetch();
        }
    }

    if ($act === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $full_user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            $success = 'Password changed successfully!';
        }
    }

    if ($act === 'upload_avatar' && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $fname = 'avatar_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fname)) {
                    // Delete old
                    if ($full_user['avatar']) @unlink(UPLOAD_DIR . $full_user['avatar']);
                    $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$fname, $uid]);
                    $_SESSION['user_avatar'] = $fname;
                    $success = 'Avatar updated!';
                    $full_user['avatar'] = $fname;
                }
            } else { $error = 'Only JPG, PNG, GIF files are allowed.'; }
        }
    }
}

// Stats
$my_tasks    = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid")->fetchColumn();
$done_tasks  = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to=$uid AND status='done'")->fetchColumn();
$my_projects = (int)$db->query("SELECT COUNT(*) FROM project_members WHERE user_id=$uid")->fetchColumn();
$my_files    = (int)$db->query("SELECT COUNT(*) FROM files WHERE uploaded_by=$uid")->fetchColumn();

// My recent tasks
$recent_tasks = $db->query("
    SELECT t.*, p.name AS project_name FROM tasks t JOIN projects p ON p.id=t.project_id
    WHERE t.assigned_to=$uid ORDER BY t.created_at DESC LIMIT 6
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>


  <div class="app-content-header py-3 px-4 border-bottom">
    <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h2>
  </div>

  <div class="app-content">

    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-4 alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill"></i><span><?= htmlspecialchars($success) ?></span>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-4 alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill"></i><span><?= htmlspecialchars($error) ?></span>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Profile Card -->
      <div class="col-lg-4">
        <div class="card text-center mb-4">
          <div class="card-body py-4">
            <div class="position-relative d-inline-block mb-3">
              <?php if ($full_user['avatar']): ?>
              <img src="uploads/<?= htmlspecialchars($full_user['avatar']) ?>" class="rounded-circle" style="width:100px;height:100px;object-fit:cover;border:4px solid rgba(79,70,229,.2);" alt="Avatar" id="profile-avatar-img">
              <?php else: ?>
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold mx-auto" style="width:100px;height:100px;font-size:2rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);" id="profile-avatar-initials">
                <?= strtoupper(substr($full_user['name'],0,2)) ?>
              </div>
              <?php endif; ?>
              <!-- Avatar upload overlay -->
              <label class="position-absolute bottom-0 end-0 btn btn-sm btn-primary rounded-circle p-1" for="avatar-input" style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;" title="Change avatar" id="avatar-upload-label">
                <i class="bi bi-camera-fill" style="font-size:.7rem;"></i>
              </label>
            </div>

            <h2 class="h5 fw-bold mb-1"><?= htmlspecialchars($full_user['name']) ?></h2>
            <p class="text-muted small mb-2"><?= htmlspecialchars($full_user['email']) ?></p>
            <span class="badge bg-<?= $full_user['role']==='admin'?'danger':($full_user['role']==='manager'?'primary':'success') ?> mb-3"><?= ucfirst($full_user['role']) ?></span>

            <?php if ($full_user['bio']): ?>
            <p class="small text-muted"><?= nl2br(htmlspecialchars($full_user['bio'])) ?></p>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-center gap-1 mb-3">
              <span class="online-indicator <?= $full_user['status']==='online'?'':'offline-indicator' ?>"></span>
              <span class="small text-<?= $full_user['status']==='online'?'success':'muted' ?>">
                <?= $full_user['status']==='online'?'Online':'Last seen: '.time_ago($full_user['last_seen']??$full_user['created_at']) ?>
              </span>
            </div>

            <div class="row g-2 text-center mt-2">
              <div class="col-3">
                <div class="fw-bold"><?= $my_projects ?></div><div class="x-small text-muted">Projects</div>
              </div>
              <div class="col-3">
                <div class="fw-bold"><?= $my_tasks ?></div><div class="x-small text-muted">Tasks</div>
              </div>
              <div class="col-3">
                <div class="fw-bold"><?= $done_tasks ?></div><div class="x-small text-muted">Done</div>
              </div>
              <div class="col-3">
                <div class="fw-bold"><?= $my_files ?></div><div class="x-small text-muted">Files</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Avatar Upload (hidden form) -->
        <form method="POST" enctype="multipart/form-data" id="avatar-form">
          <input type="hidden" name="action" value="upload_avatar">
          <input type="file" id="avatar-input" name="avatar" class="d-none" accept="image/*" onchange="document.getElementById('avatar-form').submit()">
        </form>

        <!-- My Recent Tasks -->
        <div class="card">
          <div class="card-header bg-transparent py-3">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-check2-square me-2 text-primary"></i>My Recent Tasks</h3>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recent_tasks)): ?>
            <div class="text-center py-3 text-muted small">No tasks assigned.</div>
            <?php else: ?>
            <?php foreach ($recent_tasks as $t): ?>
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
              <div class="priority-bar <?= $t['priority'] ?>" style="position:relative;width:4px;height:30px;border-radius:99px;flex-shrink:0;"></div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold small text-truncate"><?= htmlspecialchars($t['title']) ?></div>
                <div class="x-small text-muted"><?= htmlspecialchars($t['project_name']) ?></div>
              </div>
              <?= status_badge($t['status']) ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Settings -->
      <div class="col-lg-8">
        <ul class="nav nav-tabs mb-4" id="profile-tabs">
          <li class="nav-item"><a class="nav-link active" href="#info" data-bs-toggle="tab" id="tab-profile-info"><i class="bi bi-person me-1"></i>Profile Info</a></li>
          <li class="nav-item"><a class="nav-link" href="#security" data-bs-toggle="tab" id="tab-profile-security"><i class="bi bi-shield-lock me-1"></i>Security</a></li>
        </ul>
        <div class="tab-content">

          <!-- Profile Info Tab -->
          <div class="tab-pane fade show active" id="info">
            <div class="card">
              <div class="card-header bg-transparent py-3">
                <h3 class="h6 fw-bold mb-0">Edit Profile Information</h3>
              </div>
              <div class="card-body">
                <form method="POST" id="profile-info-form">
                  <input type="hidden" name="action" value="update_profile">
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($full_user['name']) ?>" required id="profile-name-input">
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" class="form-control bg-body-secondary" value="<?= htmlspecialchars($full_user['email']) ?>" readonly id="profile-email-display">
                    <div class="form-text">Email cannot be changed. Contact admin for assistance.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Phone Number</label>
                    <div class="input-group">
                      <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-telephone text-muted"></i></span>
                      <input type="tel" name="phone" class="form-control border-0 bg-body-secondary" value="<?= htmlspecialchars($full_user['phone'] ?? '') ?>" placeholder="+1 234 567 8900" id="profile-phone-input">
                    </div>
                  </div>
                  <div class="mb-4">
                    <label class="form-label small fw-semibold">Bio</label>
                    <textarea name="bio" class="form-control" rows="4" placeholder="Tell your team about yourself…" id="profile-bio-input"><?= htmlspecialchars($full_user['bio'] ?? '') ?></textarea>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Role</label>
                    <input type="text" class="form-control bg-body-secondary" value="<?= ucfirst($full_user['role']) ?>" readonly id="profile-role-display">
                    <div class="form-text">Role is assigned by administrators.</div>
                  </div>
                  <button type="submit" class="btn btn-primary" id="save-profile-btn"><i class="bi bi-save me-1"></i>Save Changes</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Security Tab -->
          <div class="tab-pane fade" id="security">
            <div class="card">
              <div class="card-header bg-transparent py-3">
                <h3 class="h6 fw-bold mb-0">Change Password</h3>
              </div>
              <div class="card-body">
                <form method="POST" id="change-password-form">
                  <input type="hidden" name="action" value="change_password">
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Current Password</label>
                    <div class="input-group">
                      <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-lock text-muted"></i></span>
                      <input type="password" name="current_password" class="form-control border-0 bg-body-secondary" required id="current-pw-input">
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">New Password</label>
                    <div class="input-group">
                      <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-lock-fill text-muted"></i></span>
                      <input type="password" name="new_password" class="form-control border-0 bg-body-secondary" required minlength="8" placeholder="Min. 8 characters" id="new-pw-input">
                    </div>
                  </div>
                  <div class="mb-4">
                    <label class="form-label small fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                      <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-shield-lock text-muted"></i></span>
                      <input type="password" name="confirm_password" class="form-control border-0 bg-body-secondary" required id="confirm-pw-input">
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary" id="change-pw-btn"><i class="bi bi-key me-1"></i>Change Password</button>
                </form>

                <hr class="my-4">

                <!-- Session / Account Info -->
                <h6 class="fw-semibold mb-3">Account Information</h6>
                <div class="row g-2">
                  <div class="col-sm-6">
                    <div class="card bg-body-secondary border-0">
                      <div class="card-body py-2">
                        <div class="x-small text-muted">Member Since</div>
                        <div class="small fw-semibold"><?= date('F j, Y', strtotime($full_user['created_at'])) ?></div>
                      </div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="card bg-body-secondary border-0">
                      <div class="card-body py-2">
                        <div class="x-small text-muted">Account Status</div>
                        <div class="small fw-semibold text-success"><?= $full_user['is_active']?'Active':'Inactive' ?></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
