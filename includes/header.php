<?php
// ─── Shared Page Header ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
$page_title = $page_title ?? 'Dashboard';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title><?= htmlspecialchars($page_title) ?> | CollabSpace</title>

  <!-- Theme Init (no flash) -->
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

  <!-- Meta -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="color-scheme" content="light dark" />
  <meta name="description" content="Real-Time Collaboration Workspace - CollabSpace" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

  <!-- OverlayScrollbars -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">

  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">

  <!-- Custom CSS System -->
  <link rel="stylesheet" href="css/custom.css" />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
