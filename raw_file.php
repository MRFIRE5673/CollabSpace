<?php
// ============================================================
// Raw File Streamer — High Performance Public Streamer
// Enables Microsoft Office, Google Docs, & Browser Viewers
// Also serves chat-uploaded files by filename
// ============================================================
require_once __DIR__ . '/config/database.php';

$fid      = (int)($_GET['id'] ?? 0);
$fname    = basename(trim($_GET['file'] ?? ''));
$chatMode = isset($_GET['chat']) && $_GET['chat'] == '1';

$mime_map = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png'  => 'image/png',  'gif'  => 'image/gif',
    'webp' => 'image/webp', 'svg'  => 'image/svg+xml',
    'txt'  => 'text/plain; charset=utf-8',
    'csv'  => 'text/plain; charset=utf-8',
    'json' => 'application/json',
    'mp4'  => 'video/mp4',  'webm' => 'video/webm',
    'mp3'  => 'audio/mpeg', 'wav'  => 'audio/wav',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip'  => 'application/zip',
    'rar'  => 'application/x-rar-compressed',
];

// ── Chat file mode: stream by raw filename from uploads/ ──────
if ($chatMode && $fname) {
    $file_path    = UPLOAD_DIR . $fname;
    $display_name = $fname;
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo 'Chat file not found.';
        exit;
    }
    $ext  = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
    $mime = $mime_map[$ext] ?? 'application/octet-stream';
    header("Content-Type: $mime");
    header('Content-Disposition: inline; filename="' . rawurlencode($display_name) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=3600');
    readfile($file_path);
    exit;
}



$db = getDB();
if ($fid) {
    $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fid]);
    $file_rec = $stmt->fetch();
} elseif ($fname) {
    $stmt = $db->prepare("SELECT * FROM files WHERE file_name = ?");
    $stmt->execute([$fname]);
    $file_rec = $stmt->fetch();
    if (!$file_rec) {
        $cstmt = $db->prepare("SELECT id, file_path AS file_name, file_name AS original_name FROM chats WHERE file_path = ?");
        $cstmt->execute([$fname]);
        $chat_rec = $cstmt->fetch();
        if ($chat_rec) {
            $file_rec = $chat_rec;
        } elseif (file_exists(UPLOAD_DIR . $fname)) {
            $file_rec = ['id' => 0, 'file_name' => $fname, 'original_name' => $fname];
        }
    }
} else {
    $file_rec = null;
}

if (!$file_rec) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$file_path = UPLOAD_DIR . $file_rec['file_name'];
if (!file_exists($file_path)) {
    http_response_code(404);
    echo 'File binary missing.';
    exit;
}

$ext = strtolower(pathinfo($file_rec['original_name'], PATHINFO_EXTENSION));

$mime_map = [
    'pdf'        => 'application/pdf',
    'jpg'        => 'image/jpeg',
    'jpeg'       => 'image/jpeg',
    'png'        => 'image/png',
    'gif'        => 'image/gif',
    'webp'       => 'image/webp',
    'svg'        => 'image/svg+xml',
    'txt'        => 'text/plain; charset=utf-8',
    'csv'        => 'text/plain; charset=utf-8',
    'json'       => 'application/json',
    'html'       => 'text/html',
    'mp4'        => 'video/mp4',
    'webm'       => 'video/webm',
    'mp3'        => 'audio/mpeg',
    'wav'        => 'audio/wav',
    'doc'        => 'application/msword',
    'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'        => 'application/vnd.ms-excel',
    'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'        => 'application/vnd.ms-powerpoint',
    'pptx'       => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

$mime = $mime_map[$ext] ?? 'application/octet-stream';

header("Content-Type: $mime");
header('Content-Disposition: inline; filename="' . rawurlencode($file_rec['original_name']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');
readfile($file_path);
exit;
