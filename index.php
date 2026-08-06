<?php
// ─── CollabSpace Landing Page ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth.php';

$is_logged = is_logged_in();
$user = $is_logged ? current_user() : null;
$dashboard_url = $is_logged ? get_role_redirect($user['role'] ?? 'member') : 'login.php';

// Try loading live stats from database
$total_projects  = 12;
$completed_tasks = 148;
$total_members   = 24;
$active_online   = 8;

try {
    $db = getDB();
    if ($db) {
        $total_projects  = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $completed_tasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();
        $total_members   = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
        $active_online   = (int)$db->query("SELECT COUNT(*) FROM users WHERE status='online' AND is_active=1")->fetchColumn();
    }
} catch (Exception $e) {
    // Graceful fallback to default display stats if DB setup pending
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CollabSpace | Real-Time Collaboration Workspace</title>
  <meta name="description" content="Where Teams Connect, Collaborate & Deliver in Real-Time." />

  <!-- Theme Init (No flash) -->
  <script>
    (() => {
      const k = 'lte-theme';
      let s = null; try { s = localStorage.getItem(k); } catch {}
      const dark = globalThis.matchMedia('(prefers-color-scheme: dark)').matches;
      const r = (s === 'dark' || s === 'light') ? s : (dark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', r);
      document.documentElement.style.colorScheme = r;
    })();
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

  <!-- Bootstrap Icons & Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">

  <!-- Custom Design System -->
  <link rel="stylesheet" href="css/custom.css" />

  <style>
    .landing-nav {
      background: var(--cs-surface);
      border-bottom: 1px solid var(--cs-border);
      backdrop-filter: blur(16px);
      position: sticky;
      top: 0;
      z-index: 1040;
    }
    .hero-section {
      position: relative;
      padding: 90px 0 70px;
      overflow: hidden;
    }
    .hero-glow-1 {
      position: absolute;
      top: -100px;
      left: 50%;
      transform: translateX(-50%);
      width: 600px;
      height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(92,73,224,0.22) 0%, rgba(139,92,246,0.05) 50%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 16px;
      border-radius: 99px;
      background: rgba(92,73,224,0.1);
      border: 1px solid rgba(92,73,224,0.25);
      color: var(--cs-primary);
      font-size: 0.82rem;
      font-weight: 600;
      margin-bottom: 24px;
    }
    .hero-headline {
      font-family: 'Outfit', sans-serif;
      font-size: 3.2rem;
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: -0.02em;
      margin-bottom: 20px;
    }
    @media (max-width: 768px) {
      .hero-headline { font-size: 2.2rem; }
    }
    .hero-headline span {
      background: linear-gradient(135deg, var(--cs-primary), var(--cs-secondary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-subtext {
      font-size: 1.15rem;
      color: var(--cs-text-muted);
      max-width: 680px;
      margin: 0 auto 36px;
      line-height: 1.6;
    }
    .feature-card {
      background: var(--cs-surface);
      border: 1px solid var(--cs-border);
      border-radius: var(--cs-radius-lg);
      padding: 32px 24px;
      transition: all var(--cs-t) var(--cs-ease);
      height: 100%;
    }
    .feature-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--cs-shadow);
      border-color: rgba(92,73,224,0.3);
    }
    .feature-icon {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 20px;
    }
    .preview-window {
      background: var(--cs-surface-2);
      border: 1px solid var(--cs-border);
      border-radius: var(--cs-radius-xl);
      box-shadow: var(--cs-shadow-lg);
      overflow: hidden;
      margin-top: 50px;
    }
    .preview-header {
      background: var(--cs-surface);
      border-bottom: 1px solid var(--cs-border);
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
    .dot-red { background: #ff5f56; }
    .dot-yellow { background: #ffbd2e; }
    .dot-green { background: #27c93f; }

    .role-card {
      background: var(--cs-surface);
      border: 1px solid var(--cs-border);
      border-radius: var(--cs-radius-lg);
      padding: 24px;
      text-align: center;
      transition: transform var(--cs-t) var(--cs-ease);
    }
    .role-card:hover {
      transform: translateY(-4px);
    }
    .cta-banner {
      background: linear-gradient(135deg, var(--cs-primary-dark) 0%, var(--cs-primary) 50%, var(--cs-secondary) 100%);
      border-radius: var(--cs-radius-xl);
      padding: 60px 40px;
      color: #fff;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
  </style>
</head>
<body>

  <!-- ─── NAVBAR ─────────────────────────────────────────── -->
  <nav class="landing-nav py-3">
    <div class="container d-flex align-items-center justify-content-between">
      <!-- Brand -->
      <a href="index.php" class="text-decoration-none d-flex align-items-center gap-3">
        <div class="sidebar-brand-logo" style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--cs-primary),var(--cs-secondary));display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(92,73,224,.4);">
          <i class="bi bi-lightning-charge-fill text-white fs-5"></i>
        </div>
        <div class="lh-1">
          <div class="brand-text fw-bold fs-5 text-body">CollabSpace</div>
          <div class="text-muted x-small text-uppercase tracking-wider" style="font-size:.62rem;letter-spacing:.08em;">Real-Time Workspace</div>
        </div>
      </a>

      <!-- Navigation Links -->
      <div class="d-none d-md-flex align-items-center gap-4">
        <a href="#features" class="text-decoration-none text-body fw-medium small">Features</a>
        <a href="#demo-preview" class="text-decoration-none text-body fw-medium small">Live Demo</a>
        <a href="#roles" class="text-decoration-none text-body fw-medium small">Roles</a>
        <a href="#stats" class="text-decoration-none text-body fw-medium small">Workspace Stats</a>
      </div>

      <!-- Action Area -->
      <div class="d-flex align-items-center gap-2">
        <!-- Theme Toggle Button -->
        <button id="theme-toggle" class="theme-toggle-pill border-0 me-2" title="Toggle theme" type="button">
          <span class="toggle-icon" id="theme-icon-wrap">
            <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
          </span>
          <span class="theme-toggle-label d-none d-sm-inline" id="theme-label">Dark</span>
        </button>

        <?php if ($is_logged): ?>
          <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn btn-primary px-3 py-2 fw-semibold">
            <i class="bi bi-speedometer2 me-1"></i> My Dashboard
          </a>
          <a href="logout.php" class="btn btn-outline-danger px-3 py-2 fw-semibold ms-1">Logout</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline-primary px-3 py-2 fw-semibold me-1">Log In</a>
          <a href="register.php" class="btn btn-primary px-3 py-2 fw-semibold">Get Started Free</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- ─── HERO SECTION ───────────────────────────────────── -->
  <section class="hero-section text-center">
    <div class="hero-glow-1"></div>
    <div class="container position-relative" style="z-index: 1;">
      <div class="hero-badge">
        <span class="online-dot"></span> Real-Time Collaboration Workspace v2.0
      </div>
      <h1 class="hero-headline">
        Where Teams Connect, <span>Collaborate</span> & Deliver Faster.
      </h1>
      <p class="hero-subtext">
        Streamline projects with interactive Kanban boards, instant team chat, secure file sharing, and role-based permission controls — all synchronized in real time.
      </p>

      <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
        <?php if ($is_logged): ?>
          <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn btn-primary btn-lg px-4 py-3 fs-6">
            <i class="bi bi-speedometer2 me-2"></i> Enter Workspace Dashboard
          </a>
        <?php else: ?>
          <a href="register.php" class="btn btn-primary btn-lg px-4 py-3 fs-6">
            <i class="bi bi-rocket-takeoff-fill me-2"></i> Create Free Account
          </a>
          <a href="login.php" class="btn btn-outline-primary btn-lg px-4 py-3 fs-6">
            <i class="bi bi-box-arrow-in-right me-2"></i> Log In to Existing Account
          </a>
        <?php endif; ?>
      </div>

      <!-- Live Interactive App Preview Box -->
      <div class="preview-window text-start" id="demo-preview">
        <div class="preview-header">
          <span class="dot dot-red"></span>
          <span class="dot dot-yellow"></span>
          <span class="dot dot-green"></span>
          <span class="small text-muted ms-2 fw-semibold" style="font-size:.78rem;">CollabSpace — Active Workspace Board</span>
          <span class="ms-auto presence-strip" style="font-size:.68rem;">
            <span class="online-dot"></span> Live Sync Active
          </span>
        </div>
        <div class="p-4" style="background: var(--cs-bg);">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <div class="p-3 rounded-3 text-white" style="background: linear-gradient(135deg, #4338ca, #7c3aed);">
                <div class="small opacity-75">Active Projects</div>
                <div class="fs-3 fw-bold" style="font-family:'Outfit';"><?= $total_projects ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="p-3 rounded-3 text-white" style="background: linear-gradient(135deg, #059669, #10b981);">
                <div class="small opacity-75">Completed Tasks</div>
                <div class="fs-3 fw-bold" style="font-family:'Outfit';"><?= $completed_tasks ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="p-3 rounded-3 text-white" style="background: linear-gradient(135deg, #0891b2, #06b6d4);">
                <div class="small opacity-75">Team Members</div>
                <div class="fs-3 fw-bold" style="font-family:'Outfit';"><?= $total_members ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="p-3 rounded-3 text-white" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                <div class="small opacity-75">Online Now</div>
                <div class="fs-3 fw-bold" style="font-family:'Outfit';"><?= $active_online ?></div>
              </div>
            </div>
          </div>

          <!-- Mock Kanban Preview Columns -->
          <div class="kanban-wrapper" style="min-height: 220px;">
            <div class="kanban-col" data-status="todo">
              <div class="kanban-col-header"><i class="bi bi-circle"></i> To Do (2)</div>
              <div class="kanban-cards">
                <div class="task-card">
                  <div class="priority-bar high"></div>
                  <div class="task-title">Design Modern UI Specs</div>
                  <div class="task-meta">Frontend · High Priority</div>
                </div>
              </div>
            </div>
            <div class="kanban-col" data-status="in_progress">
              <div class="kanban-col-header"><i class="bi bi-play-circle-fill"></i> In Progress (3)</div>
              <div class="kanban-cards">
                <div class="task-card">
                  <div class="priority-bar critical"></div>
                  <div class="task-title">API Authentication Endpoint</div>
                  <div class="task-meta">Backend · Critical</div>
                </div>
              </div>
            </div>
            <div class="kanban-col" data-status="done">
              <div class="kanban-col-header"><i class="bi bi-check-circle-fill"></i> Completed</div>
              <div class="kanban-cards">
                <div class="task-card">
                  <div class="priority-bar low"></div>
                  <div class="task-title">Database Schema Migration</div>
                  <div class="task-meta">Database · Completed</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ─── STATS STRIP ────────────────────────────────────── -->
  <section class="py-5 border-top border-bottom" id="stats" style="background: var(--cs-surface-2);">
    <div class="container">
      <div class="row text-center g-4">
        <div class="col-6 col-md-3">
          <div class="fs-1 fw-bold text-primary" style="font-family:'Outfit';"><?= $total_projects ?>+</div>
          <div class="text-muted small fw-semibold">Workspace Projects</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fs-1 fw-bold text-success" style="font-family:'Outfit';"><?= $completed_tasks ?>+</div>
          <div class="text-muted small fw-semibold">Tasks Completed</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fs-1 fw-bold text-info" style="font-family:'Outfit';"><?= $total_members ?></div>
          <div class="text-muted small fw-semibold">Team Members</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fs-1 fw-bold text-warning" style="font-family:'Outfit';">100%</div>
          <div class="text-muted small fw-semibold">Real-Time Sync</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── FEATURES GRID ──────────────────────────────────── -->
  <section class="py-5" id="features">
    <div class="container py-4">
      <div class="text-center max-w-700 mx-auto mb-5">
        <div class="section-label justify-content-center">Everything You Need</div>
        <h2 class="fw-bold fs-2" style="font-family:'Outfit';">Built for Modern Team Productivity</h2>
        <p class="text-muted">Explore powerful tools designed to manage projects, assign tasks, chat live, and share files seamlessly.</p>
      </div>

      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon bg-primary bg-opacity-10 text-primary">
              <i class="bi bi-kanban-fill"></i>
            </div>
            <h5 class="fw-bold" style="font-family:'Outfit';">Interactive Kanban Board</h5>
            <p class="text-muted small mb-0">Drag and drop tasks across columns, set priority badges, define due dates, and track project status in real time.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon bg-success bg-opacity-10 text-success">
              <i class="bi bi-chat-dots-fill"></i>
            </div>
            <h5 class="fw-bold" style="font-family:'Outfit';">Team Chat & Messaging</h5>
            <p class="text-muted small mb-0">Direct and channel-based chat built right into your workspace. Communicate instantly without leaving your board.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon bg-info bg-opacity-10 text-info">
              <i class="bi bi-folder2-open"></i>
            </div>
            <h5 class="fw-bold" style="font-family:'Outfit';">Cloud File Sharing</h5>
            <p class="text-muted small mb-0">Upload documents, assets, and project files with automatic size, type validation, and member permissions.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon bg-warning bg-opacity-10 text-warning">
              <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h5 class="fw-bold" style="font-family:'Outfit';">Role-Based Access Control</h5>
            <p class="text-muted small mb-0">Distinct access tiers for Admins, Managers, Members, and Viewers so everyone has the exact permissions they need.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon bg-danger bg-opacity-10 text-danger">
              <i class="bi bi-calendar3"></i>
            </div>
            <h5 class="fw-bold" style="font-family:'Outfit';">Calendar & Milestones</h5>
            <p class="text-muted small mb-0">Never miss a deadline with integrated team calendars, project due dates, and event scheduling.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon bg-purple bg-opacity-10 text-purple" style="color:var(--cs-secondary);">
              <i class="bi bi-activity"></i>
            </div>
            <h5 class="fw-bold" style="font-family:'Outfit';">Live Activity Feed</h5>
            <p class="text-muted small mb-0">Complete audit trails and activity logs tracking task updates, project creations, and member contributions.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── ROLE DEMO ACCESS SECTION ───────────────────────── -->
  <section class="py-5" id="roles" style="background: var(--cs-surface-2);">
    <div class="container py-4">
      <div class="text-center mb-5">
        <div class="section-label justify-content-center">Role-Based Dashboards</div>
        <h2 class="fw-bold fs-2" style="font-family:'Outfit';">Designed for Every Role in Your Team</h2>
        <p class="text-muted">Test out CollabSpace with pre-configured role access accounts.</p>
      </div>

      <div class="row g-4">
        <div class="col-6 col-md-3">
          <div class="role-card">
            <div class="rounded-circle bg-danger bg-opacity-15 text-danger mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-shield-fill-check fs-5"></i>
            </div>
            <h6 class="fw-bold" style="font-family:'Outfit';">Admin</h6>
            <p class="text-muted x-small mb-3">Full workspace control, user management & system logs.</p>
            <a href="login.php" class="btn btn-sm btn-outline-danger w-100">Login as Admin</a>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="role-card">
            <div class="rounded-circle bg-success bg-opacity-15 text-success mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-diagram-3-fill fs-5"></i>
            </div>
            <h6 class="fw-bold" style="font-family:'Outfit';">Manager</h6>
            <p class="text-muted x-small mb-3">Create projects, assign tasks, manage workload.</p>
            <a href="login.php" class="btn btn-sm btn-outline-success w-100">Login as Manager</a>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="role-card">
            <div class="rounded-circle bg-primary bg-opacity-15 text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-person-fill fs-5"></i>
            </div>
            <h6 class="fw-bold" style="font-family:'Outfit';">Member</h6>
            <p class="text-muted x-small mb-3">Personal task board, chat, file uploads & updates.</p>
            <a href="login.php" class="btn btn-sm btn-outline-primary w-100">Login as Member</a>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="role-card">
            <div class="rounded-circle bg-secondary bg-opacity-15 text-secondary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-eye-fill fs-5"></i>
            </div>
            <h6 class="fw-bold" style="font-family:'Outfit';">Viewer</h6>
            <p class="text-muted x-small mb-3">Read-only overview portal for stakeholders.</p>
            <a href="login.php" class="btn btn-sm btn-outline-secondary w-100">Login as Viewer</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── CTA BANNER ─────────────────────────────────────── -->
  <section class="py-5">
    <div class="container">
      <div class="cta-banner">
        <h2 class="fw-bold fs-2 mb-3" style="font-family:'Outfit';">Ready to Supercharge Your Team's Productivity?</h2>
        <p class="opacity-90 max-w-600 mx-auto mb-4">Join CollabSpace today and start organizing your projects, tasks, and team communication in one unified workspace.</p>
        <div class="d-flex justify-content-center gap-3">
          <?php if ($is_logged): ?>
            <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn btn-light btn-lg px-4 fw-semibold text-primary">Go to Dashboard →</a>
          <?php else: ?>
            <a href="register.php" class="btn btn-light btn-lg px-4 fw-semibold text-primary">Get Started Free</a>
            <a href="login.php" class="btn btn-outline-light btn-lg px-4 fw-semibold">Sign In</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── FOOTER ─────────────────────────────────────────── -->
  <footer class="py-4 border-top" style="background: var(--cs-surface);">
    <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-2">
        <div class="sidebar-brand-logo" style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--cs-primary),var(--cs-secondary));display:flex;align-items:center;justify-content:center;">
          <i class="bi bi-lightning-charge-fill text-white x-small"></i>
        </div>
        <span class="fw-bold small" style="font-family:'Outfit';">CollabSpace &copy; <?= date('Y') ?></span>
      </div>

      <div class="d-flex gap-4 small text-muted">
        <a href="login.php" class="text-decoration-none text-muted">Log In</a>
        <a href="register.php" class="text-decoration-none text-muted">Sign Up</a>
        <a href="dashboard.php" class="text-decoration-none text-muted">Dashboard</a>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    (function () {
      const btn   = document.getElementById('theme-toggle');
      const icon  = document.getElementById('theme-icon');
      const label = document.getElementById('theme-label');
      const html  = document.documentElement;
      const KEY   = 'lte-theme';

      function applyTheme(t) {
        html.setAttribute('data-bs-theme', t);
        html.style.colorScheme = t;
        try { localStorage.setItem(KEY, t); } catch (_) {}

        if (icon) icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        if (label) label.textContent = t === 'dark' ? 'Light' : 'Dark';
      }

      const current = html.getAttribute('data-bs-theme') || 'light';
      applyTheme(current);

      if (btn) {
        btn.addEventListener('click', () => {
          const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
          applyTheme(next);
        });
      }
    })();
  </script>
</body>
</html>
