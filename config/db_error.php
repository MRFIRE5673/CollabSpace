<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Database Setup Required | CollabSpace</title>
  
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
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 60%, #9333ea 100%);
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
      color: #7c3aed;
      word-break: break-all;
    }
    [data-bs-theme="dark"] .code-box { background: rgba(0,0,0,.3); color: #a78bfa; }
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
      <i class="bi bi-lightning-charge-fill"></i>
    </div>
    <h1 class="h3 fw-bold mb-1" style="font-family:'Outfit';">Supabase Database Required</h1>
    <p class="mb-0 opacity-90 small">CollabSpace is ready on Vercel and waiting for your Supabase database connection.</p>
  </div>

  <div class="error-body">
    <div class="mb-4">
      <div class="small fw-semibold mb-2 text-uppercase tracking-wider text-muted" style="font-size:.7rem;letter-spacing:.08em;">Diagnostic Status</div>
      <div class="code-box">
        <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($db_error_message ?? 'Waiting for DATABASE_URL environment variable in Vercel.') ?>
        <div style="font-family:monospace;font-size:0.72rem;opacity:0.8;margin-top:8px;border-top:1px dashed rgba(124,58,237,.3);padding-top:6px;">
          Detected in Vercel: Host = <b><?= htmlspecialchars(DB_HOST) ?></b> &bull; Port = <b><?= htmlspecialchars(DB_PORT) ?></b> &bull; User = <b><?= htmlspecialchars(DB_USER) ?></b>
        </div>
      </div>
      <div class="alert alert-warning small py-2 px-3 mt-3 mb-0" style="border-radius:10px;font-size:0.78rem;">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Crucial:</strong> After updating Environment Variables in Vercel Settings, you <b>must</b> go to <b>Deployments &rarr; Redeploy</b>, or Vercel will continue using old settings.
      </div>
    </div>

    <div class="mb-4">
      <div class="small fw-semibold mb-3 text-uppercase tracking-wider text-muted" style="font-size:.7rem;letter-spacing:.08em;">Connect Supabase to Vercel in 2 Clicks</div>
      
      <div class="d-flex align-items-start gap-3 mb-3">
        <div class="step-badge">1</div>
        <div class="small">
          <strong>Copy Connection String from Supabase</strong><br>
          In <a href="https://supabase.com/dashboard" target="_blank" class="text-primary text-decoration-none fw-semibold">Supabase Dashboard</a> â <strong>Project Settings â Database</strong> â copy the <strong>URI Connection String</strong>:
          <pre class="bg-body-secondary p-2 rounded mt-1 mb-0" style="font-size:.72rem;">postgres://postgres:[YOUR-PASSWORD]@db.xxx.supabase.co:5432/postgres</pre>
        </div>
      </div>

      <div class="d-flex align-items-start gap-3 mb-3">
        <div class="step-badge">2</div>
        <div class="small">
          <strong>Add Variable in Vercel</strong><br>
          In Vercel â <strong>Settings â Environment Variables</strong> â add:
          <pre class="bg-body-secondary p-2 rounded mt-1 mb-0" style="font-size:.72rem;">DATABASE_URL = postgres://postgres:[YOUR-PASSWORD]@db.xxx.supabase.co:5432/postgres</pre>
        </div>
      </div>

      <div class="d-flex align-items-start gap-3">
        <div class="step-badge">3</div>
        <div class="small">
          <strong>Click Save & Refresh</strong><br>
          Once saved, Vercel automatically connects and CollabSpace initializes your workspace database!
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 justify-content-end border-top pt-3" style="border-color:var(--cs-border) !important;">
      <button onclick="location.reload()" class="btn btn-primary px-4 fw-semibold">
        <i class="bi bi-arrow-clockwise me-1"></i> Refresh & Connect
      </button>
    </div>
  </div>
</div>

</body>
</html>
