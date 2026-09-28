<?php
// ─── Cookie Policy ───────────────────────────────────────────
$page_title = 'Cookie Policy';
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
      <title>Cookie Policy | CollabSpace</title>
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
      <h1 class="fw-bold mb-1 h3" style="font-family:'Outfit';">Cookie Policy</h1>
      <p class="mb-0 opacity-85 small">Last Updated: September 19, 2026</p>
    </div>
    <div class="card-body p-4 p-md-5 lh-lg">

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">1. What Are Cookies?</h2>
        <p>Cookies are small text data files stored in your web browser when visiting web applications. CollabSpace uses cookies exclusively for essential authentication and session state preservation.</p>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">2. Essential Cookies We Use</h2>
        <p>CollabSpace relies on only two functional client-side state storage keys:</p>
        <div class="table-responsive">
          <table class="table table-bordered align-middle small">
            <thead class="table-light">
              <tr>
                <th>Cookie / Key</th>
                <th>Type</th>
                <th>Purpose</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>PHPSESSID</code></td>
                <td>HTTP Cookie</td>
                <td>Essential session token identifying your active login state and tenant context.</td>
                <td>Session (Expires on browser close)</td>
              </tr>
              <tr>
                <td><code>lte-theme</code></td>
                <td>Local Storage</td>
                <td>Remembers your visual color theme preference (Light Mode vs Dark Mode).</td>
                <td>Persistent</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-3">3. Non-Essential & Third-Party Cookies</h2>
        <div class="alert alert-success border-0 rounded-3 small">
          <i class="bi bi-shield-check me-2 fs-6"></i>
          <strong>No Tracking or Advertising Cookies:</strong> We do NOT store advertising, marketing, or behavioral profiling cookies.
        </div>
      </section>

      <section class="mb-0">
        <h2 class="h5 fw-bold text-primary mb-3">4. Managing Cookies</h2>
        <p class="mb-0">You can clear cookies via your browser settings at any time. Please note that clearing <code>PHPSESSID</code> will require logging in again to authenticate your active workspace session.</p>
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
        &copy; <?= date('Y') ?> CollabSpace Suite. All rights reserved. · <a href="privacy.php" class="text-decoration-none text-muted me-2">Privacy</a> · <a href="terms.php" class="text-decoration-none text-muted">Terms</a>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
