<?php
// ─── Registration Page ───────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect('dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $result = attempt_register($name, $email, $password, 'member');
        if ($result['success']) {
            // Auto-login
            $login = attempt_login($email, $password);
            if ($login['success']) redirect('dashboard.php');
            $success = 'Account created! You can now <a href="login.php">sign in</a>.';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register | CollabSpace</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create your CollabSpace account – Real-Time Collaboration Workspace">
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
    .strength-bar { height: 4px; border-radius: 99px; transition: all .3s; }
    .feature-item { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--bs-secondary-color); }
    .feature-item i { color: #059669; font-size: .9rem; }
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
      <h1 class="h5 fw-bold mb-1">Create your account 🚀</h1>
      <p class="text-muted small mb-4">Join CollabSpace and start collaborating in real time</p>

      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert alert-success d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= $success ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="register-form" novalidate>
        <div class="mb-3">
          <label for="name" class="form-label small fw-semibold">Full Name</label>
          <div class="input-group">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-person-fill text-muted"></i></span>
            <input type="text" id="name" name="name" class="form-control border-0 bg-body-secondary" placeholder="John Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label for="email" class="form-label small fw-semibold">Email Address</label>
          <div class="input-group">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-envelope-fill text-muted"></i></span>
            <input type="email" id="email" name="email" class="form-control border-0 bg-body-secondary" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label small fw-semibold">Password</label>
          <div class="input-group">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-lock-fill text-muted"></i></span>
            <input type="password" id="password" name="password" class="form-control border-0 bg-body-secondary" placeholder="Min. 8 characters" required oninput="updateStrength(this.value)">
            <button type="button" class="btn btn-sm btn-body-secondary border-0" onclick="togglePw('password','pw-icon1')" tabindex="-1"><i class="bi bi-eye-slash" id="pw-icon1"></i></button>
          </div>
          <div class="mt-2">
            <div class="strength-bar" id="strength-bar" style="width:0%;background:#dc2626;"></div>
          </div>
          <div class="mt-1 x-small text-muted" id="strength-text"></div>
        </div>
        <div class="mb-4">
          <label for="confirm" class="form-label small fw-semibold">Confirm Password</label>
          <div class="input-group">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-shield-lock-fill text-muted"></i></span>
            <input type="password" id="confirm" name="confirm" class="form-control border-0 bg-body-secondary" placeholder="Repeat password" required>
            <button type="button" class="btn btn-sm btn-body-secondary border-0" onclick="togglePw('confirm','pw-icon2')" tabindex="-1"><i class="bi bi-eye-slash" id="pw-icon2"></i></button>
          </div>
        </div>

        <div class="mb-3 d-flex flex-column gap-2">
          <div class="feature-item"><i class="bi bi-check-circle-fill"></i> Real-time team chat & messaging</div>
          <div class="feature-item"><i class="bi bi-check-circle-fill"></i> Kanban task board & project management</div>
          <div class="feature-item"><i class="bi bi-check-circle-fill"></i> Secure file sharing & collaboration</div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2" id="reg-btn">
          <i class="bi bi-person-plus-fill me-2"></i>Create Account
        </button>
      </form>
    </div>
  </div>

  <p class="text-center text-muted small mt-3">
    Already have an account? <a href="login.php" class="text-primary text-decoration-none fw-semibold">Sign in</a>
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
function togglePw(id, iconId) {
  const el = document.getElementById(id);
  const ic = document.getElementById(iconId);
  if (el.type === 'password') { el.type = 'text'; ic.className = 'bi bi-eye'; }
  else { el.type = 'password'; ic.className = 'bi bi-eye-slash'; }
}
function updateStrength(val) {
  const bar = document.getElementById('strength-bar');
  const txt = document.getElementById('strength-text');
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const configs = [
    { w: '25%', c: '#dc2626', t: 'Weak' },
    { w: '50%', c: '#d97706', t: 'Fair' },
    { w: '75%', c: '#0891b2', t: 'Good' },
    { w: '100%', c: '#059669', t: 'Strong' }
  ];
  if (val.length === 0) { bar.style.width = '0'; txt.textContent = ''; return; }
  const cfg = configs[Math.min(score - 1, 3)] || configs[0];
  bar.style.width = cfg.w; bar.style.background = cfg.c;
  txt.textContent = cfg.t; txt.style.color = cfg.c;
}
</script>
</body>
</html>
