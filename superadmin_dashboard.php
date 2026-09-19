<?php
// ============================================================
// Super Admin Global Control Center
// ONLY accessible to Super Admin users.
// ============================================================
$page_title = 'Global Governance (Super Admin)';
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!is_super_admin()) {
    redirect('dashboard.php');
}

$user = current_user();
$db = getDB();

// Global Stats
$total_companies = (int)$db->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$active_companies = (int)$db->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn();
$total_users = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_company_admins = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='company_admin'")->fetchColumn();
$pending_approvals = (int)$db->query("SELECT COUNT(*) FROM login_approval_requests WHERE status='pending'")->fetchColumn();

// Fetch all companies
$companies = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users WHERE company_id = c.id) AS user_count,
           (SELECT COUNT(*) FROM projects WHERE company_id = c.id) AS project_count
    FROM companies c
    ORDER BY c.created_at DESC
")->fetchAll();

// Fetch all users with company details
$users = $db->query("
    SELECT u.*, c.name AS company_name
    FROM users u
    LEFT JOIN companies c ON c.id = u.company_id
    ORDER BY u.created_at DESC LIMIT 50
")->fetchAll();

// Global audit trail
$global_audits = $db->query("
    SELECT a.*, u.name AS user_name, c.name AS company_name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN companies c ON c.id = a.company_id
    ORDER BY a.created_at DESC LIMIT 15
")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
include __DIR__ . '/includes/sidebar.php';
?>

<main class="app-main p-3 p-md-4">
  <!-- Title Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom flex-wrap gap-2">
    <div>
      <span class="badge bg-danger text-uppercase letter-spacing-1 mb-1">Global System Security</span>
      <h2 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;">Super Admin Governance Dashboard</h2>
      <p class="text-muted small mb-0">Platform-wide multi-tenant company administration, global role delegation & security oversight.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-primary rounded-pill px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createCompanyModal">
        <i class="bi bi-building-add fs-5"></i>
        <span>Create New Tenant/Company</span>
      </button>
    </div>
  </div>

  <!-- Global Security KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-gradient" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0.02));">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Total Tenants</div>
            <div class="fs-3 fw-bold text-primary mt-1"><?= $total_companies ?></div>
            <div class="small text-success mt-1"><i class="bi bi-check-circle me-1"></i><?= $active_companies ?> Active</div>
          </div>
          <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-building fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-gradient" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(16, 185, 129, 0.02));">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Global Users</div>
            <div class="fs-3 fw-bold text-success mt-1"><?= $total_users ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-shield-lock me-1"></i><?= $total_company_admins ?> Company Admins</div>
          </div>
          <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success">
            <i class="bi bi-people-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-gradient" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(245, 158, 11, 0.02));">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Login Approvals Queue</div>
            <div class="fs-3 fw-bold text-warning mt-1"><?= $pending_approvals ?></div>
            <div class="small text-warning mt-1"><i class="bi bi-clock-history me-1"></i>Pending Authorization</div>
          </div>
          <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning">
            <i class="bi bi-shield-check fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-gradient" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(239, 68, 68, 0.02));">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Security Clearance</div>
            <div class="fs-3 fw-bold text-danger mt-1">Super Admin</div>
            <div class="small text-muted mt-1"><i class="bi bi-key-fill me-1"></i>Full Global Authority</div>
          </div>
          <div class="rounded-circle p-3 bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-shield-lock-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Companies / Tenants Table -->
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-3 px-4 d-flex align-items-center justify-content-between">
      <h5 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Managed Companies (Tenants)</h5>
      <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill"><?= count($companies) ?> Companies</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="bg-body-tertiary border-bottom">
            <tr>
              <th class="ps-4">Company Name</th>
              <th>Slug / Identifier</th>
              <th>Status</th>
              <th>Users</th>
              <th>Projects</th>
              <th>Created Date</th>
              <th class="pe-4 text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($companies as $c): ?>
            <tr>
              <td class="ps-4">
                <div class="fw-bold"><?= htmlspecialchars($c['name']) ?></div>
                <div class="small text-muted">ID: #<?= $c['id'] ?></div>
              </td>
              <td><code><?= htmlspecialchars($c['slug']) ?></code></td>
              <td>
                <span class="badge bg-<?= $c['status']==='active' ? 'success' : ($c['status']==='suspended'?'danger':'secondary') ?> px-2 py-1">
                  <?= ucfirst($c['status']) ?>
                </span>
              </td>
              <td><span class="fw-semibold"><?= $c['user_count'] ?></span> members</td>
              <td><span class="fw-semibold"><?= $c['project_count'] ?></span> projects</td>
              <td><span class="small text-muted"><?= date('M j, Y', strtotime($c['created_at'])) ?></span></td>
              <td class="pe-4 text-end">
                <button class="btn btn-sm btn-outline-primary me-1" onclick="assignCompanyAdmin(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                  <i class="bi bi-person-badge me-1"></i>Assign Company Admin
                </button>
                <button class="btn btn-sm btn-outline-warning" onclick="toggleCompanyStatus(<?= $c['id'] ?>, '<?= $c['status']==='active'?'suspended':'active' ?>')">
                  <?= $c['status']==='active' ? 'Suspend' : 'Activate' ?>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Global Users Management -->
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-3 px-4 d-flex align-items-center justify-content-between">
      <h5 class="fw-bold mb-0"><i class="bi bi-people me-2 text-success"></i>Global Users & Privileges</h5>
      <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><?= count($users) ?> Loaded Users</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="bg-body-tertiary border-bottom">
            <tr>
              <th class="ps-4">User</th>
              <th>Assigned Company</th>
              <th>Role</th>
              <th>Global Status</th>
              <th>Last Active</th>
              <th class="pe-4 text-end">Super Admin Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td class="ps-4">
                <div class="d-flex align-items-center gap-2">
                  <?= get_avatar_html($u, '36px') ?>
                  <div>
                    <div class="fw-bold"><?= htmlspecialchars($u['name']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-body-secondary text-body border px-2 py-1">
                  <i class="bi bi-building me-1"></i><?= htmlspecialchars($u['company_name'] ?? 'Unassigned') ?>
                </span>
              </td>
              <td>
                <?php if ($u['is_super_admin']): ?>
                  <span class="badge bg-danger text-white"><i class="bi bi-shield-lock-fill me-1"></i>Super Admin</span>
                <?php elseif ($u['role'] === 'company_admin'): ?>
                  <span class="badge bg-primary"><i class="bi bi-shield-fill me-1"></i>Company Admin</span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?= ucfirst($u['role']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-<?= $u['is_active'] ? 'success' : 'danger' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                </span>
              </td>
              <td><span class="small text-muted"><?= $u['last_seen'] ? time_ago($u['last_seen']) : 'Never' ?></span></td>
              <td class="pe-4 text-end">
                <?php if (!$u['is_super_admin']): ?>
                  <?php if ($u['role'] !== 'company_admin'): ?>
                    <button class="btn btn-sm btn-primary me-1" onclick="promoteToCompanyAdmin(<?= $u['id'] ?>, <?= $u['company_id'] ?>)">
                      <i class="bi bi-shield-plus me-1"></i>Promote to Company Admin
                    </button>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="revokeCompanyAdmin(<?= $u['id'] ?>)">
                      Revoke Company Admin
                    </button>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1">Super Admin Account</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Global Security Audit Trail -->
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent border-0 py-3 px-4">
      <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-danger"></i>Platform Security Audit Log</h5>
    </div>
    <div class="card-body p-4">
      <div class="timeline">
        <?php foreach ($global_audits as $audit): ?>
        <div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom">
          <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-shield-check"></i>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex align-items-center justify-content-between">
              <span class="fw-bold text-dark"><?= htmlspecialchars($audit['user_name']) ?></span>
              <span class="small text-muted"><?= time_ago($audit['created_at']) ?></span>
            </div>
            <div class="small text-body mt-1"><?= htmlspecialchars($audit['description']) ?></div>
            <div class="small text-muted mt-1">Company: <strong><?= htmlspecialchars($audit['company_name'] ?? 'System') ?></strong> • Action: <code><?= htmlspecialchars($audit['action']) ?></code></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<!-- Modal: Create Company -->
<div class="modal fade" id="createCompanyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold"><i class="bi bi-building-add text-primary me-2"></i>Create New Tenant / Company</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="createCompanyForm" onsubmit="submitCreateCompany(event)">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Company Name</label>
            <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Acme Enterprise Solutions" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Company Slug / Identifier</label>
            <input type="text" name="slug" class="form-control rounded-3" placeholder="e.g. acme-corp (auto-generated if empty)">
          </div>
        </div>
        <div class="modal-footer border-top py-3 px-4">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4">Create Company</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function submitCreateCompany(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'create_company');
  fetch('api/superadmin.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message, 'danger');
      }
    });
}

function promoteToCompanyAdmin(userId, companyId) {
  if (!confirm('Are you sure you want to promote this user to Company Admin? Only Super Admin can perform this action.')) return;
  const formData = new FormData();
  formData.append('action', 'promote_company_admin');
  formData.append('user_id', userId);
  formData.append('company_id', companyId);

  fetch('api/superadmin.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message, 'danger');
      }
    });
}

function revokeCompanyAdmin(userId) {
  if (!confirm('Are you sure you want to revoke Company Admin privileges from this user?')) return;
  const formData = new FormData();
  formData.append('action', 'revoke_company_admin');
  formData.append('user_id', userId);

  fetch('api/superadmin.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message, 'danger');
      }
    });
}

function toggleCompanyStatus(companyId, newStatus) {
  const formData = new FormData();
  formData.append('action', 'update_company_status');
  formData.append('company_id', companyId);
  formData.append('status', newStatus);

  fetch('api/superadmin.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message, 'danger');
      }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
