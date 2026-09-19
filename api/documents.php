<?php
// ============================================================
// Multi-Tenant Real-Time Document API (Private Storage)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$cid    = active_company_id();
session_write_close();
$db     = getDB();
$action = $_GET['action'] ?? 'fetch';

switch ($action) {
    case 'fetch':
        $fid   = (int)($_GET['id'] ?? 0);
        $fname = trim($_GET['file'] ?? '');

        $file_rec = null;
        if ($fid) {
            $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND company_id = ? LIMIT 1");
            $stmt->execute([$fid, $cid]);
            $file_rec = $stmt->fetch();
        } elseif ($fname) {
            $stmt = $db->prepare("SELECT * FROM files WHERE file_name = ? AND company_id = ? LIMIT 1");
            $stmt->execute([$fname, $cid]);
            $file_rec = $stmt->fetch();
        }

        if (!$file_rec) {
            echo json_encode(['success' => false, 'message' => 'File not found or access denied.']);
            exit;
        }

        $tenant_dir = PRIVATE_STORAGE_DIR . $cid . '/';
        $filePath = $tenant_dir . $file_rec['file_name'];
        if (!file_exists($filePath)) {
            $filePath = PRIVATE_STORAGE_DIR . $file_rec['file_name'];
        }

        $content  = file_exists($filePath) ? file_get_contents($filePath) : '';
        $mtime    = file_exists($filePath) ? filemtime($filePath) : time();

        echo json_encode([
            'success'       => true,
            'file_id'       => $file_rec['id'],
            'file_name'     => $file_rec['file_name'],
            'original_name' => $file_rec['original_name'],
            'content'       => $content,
            'last_modified' => $mtime
        ]);
        break;

    case 'save':
        $fname   = rawurldecode(trim($_POST['file'] ?? ''));
        $fname   = basename($fname);
        $fid     = (int)($_POST['file_id'] ?? 0);
        $content = $_POST['content'] ?? '';

        if (!$fname && $fid) {
            $stmt = $db->prepare("SELECT file_name FROM files WHERE id = ? AND company_id = ? LIMIT 1");
            $stmt->execute([$fid, $cid]);
            $fname = $stmt->fetchColumn() ?: '';
        }

        if (!$fname) {
            echo json_encode(['success' => false, 'message' => 'Invalid file name.']);
            exit;
        }

        $tenant_dir = PRIVATE_STORAGE_DIR . $cid . '/';
        if (!is_dir($tenant_dir)) @mkdir($tenant_dir, 0750, true);

        $filePath = $tenant_dir . $fname;
        $bytes = file_put_contents($filePath, $content);

        if ($bytes === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to write file.']);
            exit;
        }

        $mtime = filemtime($filePath);
        $db->prepare("UPDATE files SET file_size = ? WHERE file_name = ? AND company_id = ?")->execute([$bytes, $fname, $cid]);
        log_activity($cid, null, $uid, 'doc_edited', "Edited document: $fname");

        echo json_encode(['success' => true, 'bytes' => $bytes, 'last_modified' => $mtime]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
