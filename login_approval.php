<?php
// ============================================================
// Company Admin — Login Approval Request Queue
// Company Admins approve/reject login attempts for their tenant.
// ============================================================
$page_title = 'Login Approval Queue';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$cid  = active_company_id();

// Access Guard: Only Company Admin or Super Admin can approve logins
if (!is_admin()) {
    redirect(get_role_redirect($user['role'], is_super_admin()));
}

$db = getDB();

// Handle Approval / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action_type = $_POST['action_type'] ?? ''; // 'approve' or 'reject'

    if ($request_id && in_array($action_type, ['approve', 'reject'])) {
        // TENANT GUARANTEE: Request MUST belong to Company Admin's company!
        $req_stmt = $db->prepare("SELECT r.*, u.name AS user_name, u.email FROM login_approval_requests r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.company_id = ? LIMIT 1");
        $req_stmt->execute([$request_id, $cid]);
        $req = $req_stmt->fetch();

        if ($req) {
            $new_status = ($action_type === 'approve') ? 'approved' : 'rejected';
            $db->prepare("UPDATE login_approval_requests SET status = ?, approved_by = ?, decided_at = NOW() WHERE id = ?")
               ->execute([$new_status, $user['id'], $request_id]);

            log_activity($cid, null, $user['id'], "login_request_$new_status", "Company Admin {$user['name']} $new_status login request for {$req['user_name']} ({$req['email']})", 'user', $req['user_id']);
            $msg = "Login request for " . htmlspecialchars($req['user_name']) . " has been " . $new_status . ".";
            $_SESSION['toast'] = ['type' => 'success', 'msg' => $msg];
        } else {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized or invalid approval request.'];
        }
    }
    redirect('login_approval.php');
}

// Fetch pending approval requests for active company
$pending_stmt = $db->prepare("
    SELECT r.*, u.name AS user_name, u.email, u.role, u.avatar, u.created_at AS registered_at
    FROM login_approval_requests r
    JOIN users u ON u.id = r.user_id
    WHERE r.company_id = ? AND r.status = 'pending'
    ORDER BY r.requested_at DESC
");
$pending_stmt->execute([$cid]);
$pending_requests = $pending_stmt->fetchAll();

// Fetch recent history for active company
$history_stmt = $db->prepare("
    SELECT r.*, u.name AS user_name, u.email, u.role, u.avatar,
           admin.name AS approver_name
    FROM login_approval_requests r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN users admin ON admin.id = r.approved_by
    WHERE r.company_id = ? AND r.status != 'pending'
    ORDER BY r.decided_at DESC LIMIT 20
");
$history_stmt->execute([$cid]);
$history_requests = $history_stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
include __DIR__ . '/includes/sidebar.php';
?>

<main class="app-main p-3 p-md-4">
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom flex-wrap gap-2">
    <div>
      <span class="badge bg-warning text-dark text-uppercase letter-spacing-1 mb-1">Company Security</span>
      <h2 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;">Login Approval Authorization Queue</h2>
      <p class="text-muted small mb-0">Company Administrators must review and approve user login requests before access is granted.</p>
    </div>
    <div>
      <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-6 rounded-pill">
        <i class="bi bi-shield-check me-1"></i><?= count($pending_requests) ?> Pending Requests
      </span>
    </div>
  </div>

  <?php if (isset($_SESSION['toast'])): ?>
    <div class="alert alert-<?= $_SESSION['toast']['type'] ?> alert-dismissible fade show rounded-3 mb-4" role="alert">
      <?= $_SESSION['toast']['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['toast']); ?>
  <?php endif; ?>

  <!-- Pending Approvals Section -->
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-3 px-4 d-flex align-items-center justify-content-between">
      <h5 class="fw-bold mb-0 text-warning"><i class="bi bi-clock-history me-2"></i>Pending Login Requests</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($pending_requests)): ?>
        <div class="text-center py-5">
          <i class="bi bi-shield-check fs-1 text-success d-block mb-2"></i>
          <h5 class="fw-bold text-dark">No Pending Login Requests</h5>
          <p class="text-muted small">All user login attempts for your company have been reviewed.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="bg-body-tertiary border-bottom">
              <tr>
                <th class="ps-4">User Details</th>
                <th>Assigned Role</th>
                <th>Requested Timestamp</th>
                <th class="pe-4 text-end">Company Admin Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending_requests as $req): ?>
              <tr>
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-2">
                    <?= get_avatar_html($req, '38px') ?>
                    <div>
                      <div class="fw-bold text-dark"><?= htmlspecialchars($req['user_name']) ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($req['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge bg-secondary"><?= ucfirst($req['role']) ?></span></td>
                <td><span class="small text-muted"><?= time_ago($req['requested_at']) ?></span></td>
                <td class="pe-4 text-end">
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                    <button type="submit" name="action_type" value="approve" class="btn btn-sm btn-success rounded-pill px-3 me-1">
                      <i class="bi bi-check-circle me-1"></i>Approve Login
                    </button>
                    <button type="submit" name="action_type" value="reject" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Reject login for this user?')">
                      <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Approval History Table -->
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent border-0 py-3 px-4">
      <h5 class="fw-bold mb-0"><i class="bi bi-history me-2 text-primary"></i>Decision Audit History</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="bg-body-tertiary border-bottom">
            <tr>
              <th class="ps-4">User</th>
              <th>Status</th>
              <th>Decided By</th>
              <th>Decision Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history_requests as $h): ?>
            <tr>
              <td class="ps-4">
                <div class="fw-bold"><?= htmlspecialchars($h['user_name']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($h['email']) ?></div>
              </td>
              <td>
                <span class="badge bg-<?= $h['status']==='approved'?'success':'danger' ?>">
                  <?= ucfirst($h['status']) ?>
                </span>
              </td>
              <td><span class="fw-semibold text-body"><?= htmlspecialchars($h['approver_name'] ?? 'System') ?></span></td>
              <td><span class="small text-muted"><?= time_ago($h['decided_at'] ?? $h['requested_at']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
