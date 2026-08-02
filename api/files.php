<?php
// ─── Files API ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$db     = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']); exit;
        }

        $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
        $file       = $_FILES['file'];
        $orig_name  = basename($file['name']);
        $ext        = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed.']); exit;
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            echo json_encode(['success' => false, 'message' => 'File too large. Max 20MB.']); exit;
        }

        $fname = 'file_' . uniqid() . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fname)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file.']); exit;
        }

        $mime = $file['type'] ?? 'application/octet-stream';
        $stmt = $db->prepare("INSERT INTO files (project_id, uploaded_by, original_name, file_name, file_path, file_size, file_type, mime_type) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$project_id, $uid, $orig_name, $fname, $fname, $file['size'], $ext, $mime]);
        $fid = $db->lastInsertId();

        log_activity($project_id, $uid, 'file_uploaded', "{$user['name']} uploaded: $orig_name", 'file', $fid);

        // Notify project members
        if ($project_id) {
            $members = $db->prepare("SELECT user_id FROM project_members WHERE project_id=? AND user_id!=?");
            $members->execute([$project_id, $uid]);
            foreach ($members->fetchAll() as $m) {
                send_notification($m['user_id'], 'File Uploaded', "{$user['name']} uploaded: $orig_name", 'file', "files.php?project_id=$project_id");
            }
        }

        echo json_encode(['success' => true, 'file_id' => $fid, 'file_name' => $orig_name]);
        break;

    case 'delete':
        $fid = (int)($_POST['file_id'] ?? 0);
        if (!$fid) { echo json_encode(['success' => false]); exit; }

        $f = $db->prepare("SELECT * FROM files WHERE id=?"); $f->execute([$fid]);
        $f = $f->fetch();
        if (!$f) { echo json_encode(['success' => false, 'message' => 'File not found']); exit; }
        if (!is_admin() && $f['uploaded_by'] != $uid) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }

        @unlink(UPLOAD_DIR . $f['file_name']);
        $db->prepare("DELETE FROM files WHERE id=?")->execute([$fid]);
        echo json_encode(['success' => true]);
        break;

    case 'list':
        $project_id = (int)($_GET['project_id'] ?? 0);
        $query = "SELECT f.*, u.name AS uploader_name FROM files f JOIN users u ON u.id=f.uploaded_by";
        $params = [];
        if ($project_id) { $query .= " WHERE f.project_id=?"; $params[] = $project_id; }
        $query .= " ORDER BY f.uploaded_at DESC LIMIT 50";
        $stmt = $db->prepare($query); $stmt->execute($params);
        $files = $stmt->fetchAll();
        foreach ($files as &$f) {
            $f['time_ago'] = time_ago($f['uploaded_at']);
            $f['size_fmt'] = $f['file_size'] > 1048576 ? round($f['file_size']/1048576,1).'MB' : round($f['file_size']/1024,1).'KB';
        }
        echo json_encode($files);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
