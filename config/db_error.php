<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Database Connection Issue | CollabSpace</title>
  
  <script>
    (() => {
      const k = 'lte-theme';
      let s = null; try { s = localStorage.getItem(k); } catch {}
      const dark = globalThis.matchMedia('(prefers-color-scheme: dark)').matches;
      const r = (s === 'dark' || s === 'light') ? s : (dark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', r);
    })();
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap Icons & Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">

  <style>
    :root {
      --cs-bg: #f4f3ff;
      --cs-surface: #ffffff;
      --cs-border: rgba(92,73,224,.12);
      --cs-text: #1e1b4b;
      --cs-muted: #6b7280;
    }
    [data-bs-theme="dark"] {
      --cs-bg: #0d0b1e;
      --cs-surface: #13102a;
      --cs-border: rgba(255,255,255,.08);
      --cs-text: #f0eeff;
      --cs-muted: #9ca3af;
    }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--cs-bg);
      color: var(--cs-text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }
    .error-card {
      background: var(--cs-surface);
      border: 1px solid var(--cs-border);
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(92,73,224,.15);
      max-width: 640px;
      width: 100%;
      overflow: hidden;
    }
    .error-header {
      background: linear-gradient(135deg, #dc2626 0%, #ef4444 60%, #f97316 100%);
      color: #fff;
      padding: 32px;
      position: relative;
    }
    .error-icon-box {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      background: rgba(255,255,255,.2);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      margin-bottom: 16px;
    }
    .error-body { padding: 32px; }
    .code-box {
      background: rgba(0,0,0,.06);
      border: 1px solid var(--cs-border);
      border-radius: 12px;
      padding: 14px 16px;
      font-family: monospace;
      font-size: 0.8rem;
      color: #ef4444;
      word-break: break-all;
    }
    [data-bs-theme="dark"] .code-box { background: rgba(0,0,0,.3); }
    .step-badge {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #4f46e5;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.72rem;
      font-weight: 700;
      flex-shrink: 0;
    }
  </style>
</head>
<body>

<div class="error-card">
  <div class="error-header">
    <div class="error-icon-box">
      <i class="bi bi-database-fill-exclamation"></i>
    </div>
    <h1 class="h3 fw-bold mb-1" style="font-family:'Outfit';">Database Connection Issue</h1>
    <p class="mb-0 opacity-90 small">CollabSpace could not connect to the database server.</p>
  </div>

  <div class="error-body">
    <div class="mb-4">
      <div class="small fw-semibold mb-2 text-uppercase tracking-wider text-muted" style="font-size:.7rem;letter-spacing:.08em;">Diagnostic Error Details</div>
      <div class="code-box">
        <i class="bi bi-bug-fill me-2"></i><?= htmlspecialchars($db_error_message ?? 'Database connection failed.') ?>
      </div>
    </div>

    <div class="mb-4">
      <div class="small fw-semibold mb-3 text-uppercase tracking-wider text-muted" style="font-size:.7rem;letter-spacing:.08em;">How to Fix on Railway</div>
      
      <div class="d-flex align-items-start gap-3 mb-3">
        <div class="step-badge">1</div>
        <div class="small">
          <strong>Check Environment Variables</strong><br>
          In Railway → App Service → <strong>Variables</strong> tab → click <strong>RAW Editor</strong> and set:
          <pre class="bg-body-secondary p-2 rounded mt-1 mb-0" style="font-size:.75rem;">DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASS=${{MySQL.MYSQLPASSWORD}}</pre>
        </div>
      </div>

      <div class="d-flex align-items-start gap-3 mb-3">
        <div class="step-badge">2</div>
        <div class="small">
          <strong>Verify MySQL Service</strong><br>
          Ensure the MySQL database plugin service in your Railway project is <span class="badge bg-success">Online</span>.
        </div>
      </div>

      <div class="d-flex align-items-start gap-3">
        <div class="step-badge">3</div>
        <div class="small">
          <strong>Run Database Setup Script</strong><br>
          In Railway App Service → <strong>Console</strong> tab, run: <code>php config/setup.php</code>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 justify-content-end border-top pt-3" style="border-color:var(--cs-border) !important;">
      <a href="index.php" class="btn btn-outline-secondary px-4">Home</a>
      <button onclick="location.reload()" class="btn btn-primary px-4">
        <i class="bi bi-arrow-clockwise me-1"></i> Retry Connection
      </button>
    </div>
  </div>
</div>

</body>
</html>
