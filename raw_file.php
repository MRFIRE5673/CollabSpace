<?php
// ============================================================
// Raw File Streamer — High Performance Public Streamer
// Enables Microsoft Office, Google Docs, & Browser Viewers
// Also serves chat-uploaded files and direct uploads
// ============================================================
require_once __DIR__ . '/config/database.php';

// Turn off output buffering quirks
if (ob_get_level()) ob_end_clean();
ob_start();

$fid      = (int)($_GET['id'] ?? 0);
$fname    = basename(trim($_GET['file'] ?? ''));

$db = getDB();
$file_rec = null;

// 1. Query files table by ID
if ($fid) {
    $stmt = $db->prepare("SELECT id, file_name, original_name, file_path FROM files WHERE id = ?");
    $stmt->execute([$fid]);
    $file_rec = $stmt->fetch();
}

// 2. Query chats table by ID
if (!$file_rec && $fid) {
    $cstmt = $db->prepare("SELECT id, file_path AS file_name, file_name AS original_name FROM chats WHERE id = ?");
    $cstmt->execute([$fid]);
    $chat_rec = $cstmt->fetch();
    if ($chat_rec) {
        $file_rec = $chat_rec;
    }
}

// 3. Query files table by filename
if (!$file_rec && $fname) {
    $stmt = $db->prepare("SELECT id, file_name, original_name, file_path FROM files WHERE file_name = ? OR original_name = ? OR file_path = ?");
    $stmt->execute([$fname, $fname, $fname]);
    $file_rec = $stmt->fetch();
}

// 4. Query chats table by filename
if (!$file_rec && $fname) {
    $cstmt = $db->prepare("SELECT id, file_path AS file_name, file_name AS original_name FROM chats WHERE file_path = ? OR file_name = ?");
    $cstmt->execute([$fname, $fname]);
    $chat_rec = $cstmt->fetch();
    if ($chat_rec) {
        $file_rec = $chat_rec;
    }
}

// 5. Fallback: Direct disk match
if (!$file_rec && $fname) {
    $file_rec = [
        'id' => 0,
        'file_name' => $fname,
        'original_name' => $fname
    ];
}

if (!$file_rec) {
    http_response_code(404);
    echo 'File record not found.';
    exit;
}

// Search physical disk location
$file_path = null;
$possible_names = array_unique(array_filter([
    $file_rec['file_name'] ?? null,
    $file_rec['file_path'] ?? null,
    $file_rec['original_name'] ?? null,
    $fname ?? null
]));

foreach ($possible_names as $pname) {
    $target = UPLOAD_DIR . basename($pname);
    if (file_exists($target)) {
        $file_path = $target;
        break;
    }
}

if (!$file_path || !file_exists($file_path)) {
    http_response_code(404);
    echo 'File binary missing.';
    exit;
}

$ext = strtolower(pathinfo($file_rec['original_name'] ?? $file_rec['file_name'], PATHINFO_EXTENSION));

$mime_map = [
    'pdf'        => 'application/pdf',
    'jpg'        => 'image/jpeg',
    'jpeg'       => 'image/jpeg',
    'png'        => 'image/png',
    'gif'        => 'image/gif',
    'webp'       => 'image/webp',
    'svg'        => 'image/svg+xml',
    'bmp'        => 'image/bmp',
    'ico'        => 'image/x-icon',
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

// Clear output buffer completely so no extra bytes corrupt binary images
ob_clean();

header("Content-Type: $mime");
header('Content-Disposition: inline; filename="' . rawurlencode($file_rec['original_name'] ?? $file_rec['file_name']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');
readfile($file_path);
exit;
