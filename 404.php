<?php
// ─── 404 Page Not Found ───────────────────────────────────────
http_response_code(404);
$page_title = '404 Page Not Found';
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
      <title>404 Page Not Found | CollabSpace</title>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="bg-body-tertiary">
    <?php
}
?>

<div class="container py-5 text-center my-auto">
  <div class="py-5" style="max-width:540px;margin:0 auto;">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 mb-4" style="width:96px;height:96px;">
      <i class="bi bi-compass text-primary" style="font-size:3rem;"></i>
    </div>
    <h1 class="display-4 fw-bold mb-2" style="font-family:'Outfit';">404</h1>
    <h4 class="fw-bold mb-3">Page Not Found</h4>
    <p class="text-muted mb-4">The page or resource you requested could not be located. It may have been moved, deleted, or you may lack permissions to view it.</p>
    <div class="d-flex justify-content-center gap-3">
      <a href="dashboard.php" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold">
        <i class="bi bi-house-door-fill me-1"></i> Return to Dashboard
      </a>
      <a href="javascript:history.back()" class="btn btn-outline-secondary px-4 py-2 rounded-3">
        <i class="bi bi-arrow-left me-1"></i> Go Back
      </a>
    </div>
  </div>
</div>

<?php
if ($isLoggedIn) {
    include __DIR__ . '/includes/footer.php';
} else {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
