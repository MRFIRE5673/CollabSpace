<?php
// ─── Login Page ──────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth.php';

// Already logged in → redirect to dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $result   = attempt_login($email, $password);
    if ($result['success']) {
        redirect($result['redirect']);
    } else {
        $error = $result['message'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login | CollabSpace</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in to CollabSpace – Real-Time Collaboration Workspace">
  <script>
    (() => {
      const s = localStorage.getItem('lte-theme');
      const dark = globalThis.matchMedia('(prefers-color-scheme: dark)').matches;
      const r = (s === 'dark' || s === 'light') ? s : (dark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', r);
    })();
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="css/adminlte.css">
  <link rel="stylesheet" href="css/custom.css">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bs-body-bg); }
    .auth-wrap { width: 100%; max-width: 460px; padding: 24px 16px; }
    .auth-card { border-radius: 20px !important; border: 1px solid rgba(79,70,229,.12) !important; box-shadow: 0 20px 60px rgba(79,70,229,.15) !important; }
    .auth-brand { display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 28px; }
    .auth-logo { width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg,#4f46e5,#7c3aed); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; }
    .auth-brand-name { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg,#4f46e5,#7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .divider { display: flex; align-items: center; gap: 12px; color: var(--bs-secondary-color); font-size: .8rem; margin: 18px 0; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(0,0,0,.1); }
    [data-bs-theme="dark"] .divider::before, [data-bs-theme="dark"] .divider::after { background: rgba(255,255,255,.1); }
    .demo-accounts { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; margin-top: 6px; }
    .demo-btn { font-size: .7rem; padding: 8px 4px; border-radius: 8px !important; text-align: center; cursor: pointer; transition: transform .15s, box-shadow .15s; }
    .demo-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
    .demo-btn.active { transform: scale(.95); }
    .role-badge { display: inline-block; font-size:.6rem; padding:1px 5px; border-radius:4px; margin-top:2px; }
    @keyframes shake { 0%,100%{transform:none} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)} }
    .shake { animation: shake .4s ease; }
  </style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-brand">
    <div class="auth-logo"><i class="bi bi-lightning-charge-fill"></i></div>
    <span class="auth-brand-name">CollabSpace</span>
  </div>

  <div class="card auth-card">
    <div class="card-body p-4">
      <h1 class="h5 fw-bold mb-1">Welcome back 👋</h1>
      <p class="text-muted small mb-4">Sign in to your workspace account</p>

      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 shake" role="alert" id="login-error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="login-form" novalidate>
        <div class="mb-3">
          <label for="email" class="form-label small fw-semibold">Email Address</label>
          <div class="input-group">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-envelope-fill text-muted"></i></span>
            <input type="email" id="email" name="email" class="form-control border-0 bg-body-secondary" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <label for="password" class="form-label small fw-semibold">Password</label>
            <a href="#" class="small text-primary text-decoration-none">Forgot password?</a>
          </div>
          <div class="input-group">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-lock-fill text-muted"></i></span>
            <input type="password" id="password" name="password" class="form-control border-0 bg-body-secondary" placeholder="••••••••" required>
            <button type="button" class="btn btn-sm btn-body-secondary border-0" id="toggle-pw" tabindex="-1"><i class="bi bi-eye-slash" id="pw-icon"></i></button>
          </div>
        </div>
        <div class="mb-4 form-check">
          <input type="checkbox" class="form-check-input" id="remember">
          <label class="form-check-label small" for="remember">Keep me signed in</label>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2" id="login-btn">
          <span id="btn-text"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
          <span id="btn-loading" class="d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in...</span>
        </button>
      </form>

      <div class="divider">Quick access — click a role to sign in</div>

      <div class="demo-accounts">
        <button type="button" class="demo-btn btn btn-outline-danger" onclick="fillDemo('admin@workspace.com','password123','demo-admin')" id="demo-admin">
          <i class="bi bi-shield-fill-check d-block fs-5 mb-1"></i>
          Admin
          <div class="role-badge bg-danger text-white">Full Access</div>
        </button>
        <button type="button" class="demo-btn btn btn-outline-success" onclick="fillDemo('pm@workspace.com','password123','demo-manager')" id="demo-manager">
          <i class="bi bi-diagram-3-fill d-block fs-5 mb-1"></i>
          Manager
          <div class="role-badge bg-success text-white">Projects</div>
        </button>
        <button type="button" class="demo-btn btn btn-outline-primary" onclick="fillDemo('member@workspace.com','password123','demo-member')" id="demo-member">
          <i class="bi bi-person-fill d-block fs-5 mb-1"></i>
          Member
          <div class="role-badge bg-primary text-white">Tasks</div>
        </button>
        <button type="button" class="demo-btn btn btn-outline-secondary" onclick="fillDemo('viewer@workspace.com','password123','demo-viewer')" id="demo-viewer">
          <i class="bi bi-eye-fill d-block fs-5 mb-1"></i>
          Viewer
          <div class="role-badge bg-secondary text-white">Read Only</div>
        </button>
      </div>

    </div>
  </div>

  <p class="text-center text-muted small mt-3">
    Don't have an account? <a href="register.php" class="text-primary text-decoration-none fw-semibold">Create one</a>
  </p>
  <p class="text-center text-muted" style="font-size:.65rem;line-height:1.8">
    <strong>Demo password for all accounts:</strong> <code>password123</code><br>
    <span class="text-danger">admin@workspace.com</span> → Admin Dashboard &nbsp;|
    <span class="text-success">pm@workspace.com</span> → Manager View<br>
    <span class="text-primary">member@workspace.com</span> → Member Board &nbsp;|
    <span class="text-secondary">viewer@workspace.com</span> → Viewer Portal
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
function fillDemo(email, pass, btnId) {
  document.getElementById('email').value = email;
  document.getElementById('password').value = pass;
  // Highlight the active button
  document.querySelectorAll('.demo-btn').forEach(b => b.classList.remove('active'));
  if (btnId) document.getElementById(btnId).classList.add('active');
  // Show loading on submit button
  document.getElementById('btn-text').classList.add('d-none');
  document.getElementById('btn-loading').classList.remove('d-none');
  document.getElementById('login-btn').disabled = true;
  document.getElementById('login-form').submit();
}
document.getElementById('toggle-pw').addEventListener('click', function() {
  const pw = document.getElementById('password');
  const icon = document.getElementById('pw-icon');
  if (pw.type === 'password') { pw.type = 'text'; icon.className = 'bi bi-eye'; }
  else { pw.type = 'password'; icon.className = 'bi bi-eye-slash'; }
});
document.getElementById('login-form').addEventListener('submit', function() {
  document.getElementById('btn-text').classList.add('d-none');
  document.getElementById('btn-loading').classList.remove('d-none');
  document.getElementById('login-btn').disabled = true;
});
</script>
</body>
</html>
