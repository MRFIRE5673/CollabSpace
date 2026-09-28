<?php
// ============================================================
// Multi-Tenant Private File Preview Streamer (No Downloads)
// Route through secure preview controller
// ============================================================
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo "Unauthenticated file access attempt.";
    exit;
}

$user = current_user();
$cid  = active_company_id();
$fid  = (int)($_GET['id'] ?? 0);
$file_name = trim($_GET['file'] ?? '');

$redirect_url = 'api/files.php?action=preview&' . ($fid ? "id=$fid" : "file=" . urlencode($file_name));
header("Location: $redirect_url");
exit;
