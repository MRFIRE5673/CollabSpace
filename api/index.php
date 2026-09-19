<?php
// ─── Vercel Serverless PHP Router ──────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/..' . $uri;
if (file_exists($target) && !is_dir($target)) {
    require $target;
    exit;
}

if (file_exists($target . '.php')) {
    require $target . '.php';
    exit;
}

// Fallback to 404
require __DIR__ . '/../404.php';
