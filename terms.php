<?php
// ─── Terms of Service ─────────────────────────────────────────
$page_title = 'Terms of Service';
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
      <title>Terms of Service | CollabSpace</title>
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
      <h1 class="fw-bold mb-1 h3" style="font-family:'Outfit';">Terms of Service</h1>
      <p class="mb-0 opacity-85 small">Effective Date: September 19, 2026</p>
    </div>
    <div class="card-body p-4 p-md-5 lh-lg">

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">1. Acceptance of Terms</h2>
        <p>By registering, authenticating, or accessing the CollabSpace platform, you agree to comply with and be bound by these Terms of Service. If you are registering on behalf of a corporation or entity, you represent that you possess authority to bind that entity to these terms.</p>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">2. Multi-Tenant Architecture & Account Approval</h2>
        <p>CollabSpace is structured as a multi-company platform:</p>
        <ul>
          <li><strong>Super Admin Authority:</strong> Only global Super Administrators can provision new corporate tenants or assign/promote Company Administrators.</li>
          <li><strong>Login Approval Required:</strong> All standard company users must be approved by their designated Company Administrator via the Login Approval Workflow before gaining active session clearance.</li>
          <li><strong>Subrole Governance:</strong> Company Admins govern company members with granular permissions (Manager, Project Manager, Team Lead, Member, Viewer).</li>
        </ul>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">3. Acceptable Use & Asset Policy</h2>
        <p>Users agree not to upload malicious scripts, reverse-engineer API endpoints, or attempt cross-tenant data access. All uploaded document previews are served strictly inline through authenticated streams; software downloads are disabled by platform security policy.</p>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">4. System Availability & Disclaimers</h2>
        <p>The platform is provided "as is" and "as available". While multi-driver database redundancy (PostgreSQL / MySQL) and automatic fallback mechanisms are employed, CollabSpace disclaims liability for unintended service interruptions resulting from underlying server outages.</p>
      </section>

      <section class="mb-0">
        <h2 class="h5 fw-bold text-primary mb-3">5. Governing Law & Modifications</h2>
        <p class="mb-0">These Terms shall be governed in accordance with applicable standard corporate software licensing regulations. We reserve the right to revise these terms as software governance requirements evolve.</p>
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
        &copy; <?= date('Y') ?> CollabSpace Suite. All rights reserved. · <a href="privacy.php" class="text-decoration-none text-muted me-2">Privacy</a> · <a href="cookies.php" class="text-decoration-none text-muted">Cookies</a>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
