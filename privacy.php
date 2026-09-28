<?php
// ─── Privacy Policy ──────────────────────────────────────────
$page_title = 'Privacy Policy';
require_once __DIR__ . '/includes/auth.php';
$isLoggedIn = is_logged_in();

if ($isLoggedIn) {
    include __DIR__ . '/includes/header.php';
    include __DIR__ . '/includes/sidebar.php';
    include __DIR__ . '/includes/navbar.php';
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="light">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Privacy Policy | CollabSpace</title>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg border-bottom bg-body">
      <div class="container">
        <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" href="index.php">
          <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);" class="d-flex align-items-center justify-content-center">
            <i class="bi bi-lightning-charge-fill text-white fs-6"></i>
          </div>
          <span style="font-family:'Outfit';">CollabSpace</span>
        </a>
        <div class="ms-auto d-flex gap-2">
          <a href="login.php" class="btn btn-outline-primary btn-sm">Sign In</a>
          <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
        </div>
      </div>
    </nav>
    <?php
}
?>

<div class="container py-5" style="max-width:860px;">
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header border-bottom py-4 px-4 px-md-5" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;">
      <h1 class="fw-bold mb-1 h3" style="font-family:'Outfit';">Privacy Policy</h1>
      <p class="mb-0 opacity-85 small">Last Updated: September 19, 2026</p>
    </div>
    <div class="card-body p-4 p-md-5 lh-lg">

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">1. Executive Overview & Data Scope</h2>
        <p>CollabSpace ("we", "our", or "platform") operates a secure, multi-tenant enterprise collaboration suite. We are committed to preserving the privacy and security of your corporate and personal data. This Privacy Policy details how data is gathered, processed, and protected within the CollabSpace software environment.</p>
        <p><strong>Core Infrastructure:</strong> All data is strictly compartmentalized by multi-tenant company boundaries (<code>company_id</code>). We do not perform cross-tenant data sharing, third-party user tracking, or telemetry monetization.</p>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">2. Information We Collect</h2>
        <p>We process only the essential data required to provide collaborative workspace functionality:</p>
        <ul>
          <li><strong>Account & Credentials:</strong> Full name, professional email address, salted password hashes (Bcrypt/Argon2), and optional avatar images.</li>
          <li><strong>Company & Tenant Profile:</strong> Company name, tenant domain identifier, user role assignments, and login approval statuses.</li>
          <li><strong>Workspace Content:</strong> Workspace details, project specifications, Kanban task boards, real-time team chat messages, comments, and event calendars.</li>
          <li><strong>Uploaded Files & Documents:</strong> Uploaded attachments are stored in private tenant directories outside the web server root (<code>private_storage/&lt;company_id&gt;/</code>) and served exclusively via secure inline streaming previews.</li>
        </ul>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">3. How We Use Data</h2>
        <p>Your information is used solely for the following operational purposes:</p>
        <ul>
          <li>Authenticating users and validating company membership during login.</li>
          <li>Enforcing role-based access controls (RBAC) and Login Approval workflows.</li>
          <li>Facilitating real-time chat, Kanban task updates, and file preview rendering.</li>
          <li>Maintaining audit trails (<code>activity_logs</code>) for company security oversight.</li>
        </ul>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">4. Third-Party Services & Trackers</h2>
        <div class="alert alert-info border-0 rounded-3 small">
          <i class="bi bi-shield-check-fill me-2 fs-6"></i>
          <strong>Zero External Trackers:</strong> CollabSpace does NOT integrate third-party advertising networks, user tracking pixels, analytics scripts (such as Google Analytics or Facebook Pixel), or data brokers.
        </div>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">5. Data Retention & Tenant Security</h2>
        <p>Data is stored in isolated relational database tables indexed by <code>company_id</code>. Tenant files are protected against public HTTP directory traversal. Company Administrators and Super Administrators can deactivate users or purge project assets as needed.</p>
      </section>

      <section class="mb-0">
        <h2 class="h5 fw-bold text-primary mb-3">6. Contact & Support</h2>
        <p class="mb-0">For questions or concerns regarding our privacy practices, contact your Company Administrator or reach out to <a href="mailto:privacy@collabspace.com">privacy@collabspace.com</a>.</p>
      </section>

    </div>
  </div>
</div>

<?php
if ($isLoggedIn) {
    include __DIR__ . '/includes/footer.php';
} else {
    ?>
    <footer class="py-4 text-center text-muted small border-top bg-body">
      <div class="container">
        &copy; <?= date('Y') ?> CollabSpace Suite. All rights reserved. · <a href="terms.php" class="text-decoration-none text-muted me-2">Terms</a> · <a href="cookies.php" class="text-decoration-none text-muted">Cookies</a>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
