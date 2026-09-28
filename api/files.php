<?php
// ============================================================
// Multi-Tenant Private File Storage & Preview API (No Downloads)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$user   = current_user();
$uid    = $user['id'];
$cid    = active_company_id();
$db     = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'preview':
        // PREVIEW ONLY ENGINE — Strictly streams inline from private storage outside web root
        $fid  = (int)($_GET['id'] ?? 0);
        $file_name = $_GET['file'] ?? '';

        if ($fid) {
            $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND company_id = ? LIMIT 1");
            $stmt->execute([$fid, $cid]);
            $f = $stmt->fetch();
        } elseif ($file_name) {
            $stmt = $db->prepare("SELECT * FROM files WHERE (file_name = ? OR file_path = ?) AND company_id = ? LIMIT 1");
            $stmt->execute([$file_name, $file_name, $cid]);
            $f = $stmt->fetch();
        } else {
            $f = null;
        }

        // If file record not found in database or belongs to another company -> 404
        if (!$f) {
            http_response_code(404);
            echo "File not found or access denied.";
            exit;
        }

        // Resolve absolute private file storage path
        $company_storage_dir = PRIVATE_STORAGE_DIR . $cid . '/';
        $path = $company_storage_dir . $f['file_name'];
        if (!file_exists($path)) {
            $path = PRIVATE_STORAGE_DIR . $f['file_name'];
        }

        if (!file_exists($path)) {
            http_response_code(404);
            echo "Physical file not found.";
            exit;
        }

        // Security check: Prevent path traversal outside PRIVATE_STORAGE_DIR
        $real_path = realpath($path);
        $real_storage = realpath(PRIVATE_STORAGE_DIR);
        if ($real_path === false || strpos($real_path, $real_storage) !== 0) {
            http_response_code(403);
            echo "Forbidden file path.";
            exit;
        }

        // Clear output buffers
        if (ob_get_level()) ob_end_clean();

        $mime = $f['mime_type'] ?? mime_content_type($path) ?: 'application/octet-stream';
        
        // STREAM AS INLINE PREVIEW ONLY (Never attachment!)
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . rawurlencode($f['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');

        readfile($path);
        exit;

    case 'upload':
        header('Content-Type: application/json');
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
            exit;
        }

        $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
        $file       = $_FILES['file'];
        $orig_name  = basename($file['name']);
        $ext        = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed.']);
            exit;
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds maximum limit of 20MB.']);
            exit;
        }

        // Store inside tenant-isolated private directory
        $tenant_dir = PRIVATE_STORAGE_DIR . $cid . '/';
        if (!is_dir($tenant_dir)) {
            @mkdir($tenant_dir, 0750, true);
        }

        $fname = 'file_' . uniqid() . '_' . time() . '.' . $ext;
        $target_file_path = $tenant_dir . $fname;

        if (!move_uploaded_file($file['tmp_name'], $target_file_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file to private storage.']);
            exit;
        }

        $mime = $file['type'] ?? 'application/octet-stream';
        $stmt = $db->prepare("INSERT INTO files (company_id, project_id, uploaded_by, original_name, file_name, file_path, file_size, file_type, mime_type) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$cid, $project_id, $uid, $orig_name, $fname, $fname, $file['size'], $ext, $mime]);
        $fid = $db->lastInsertId();

        log_activity($cid, $project_id, $uid, 'file_uploaded', "Uploaded private document: $orig_name", 'file', $fid);

        echo json_encode([
            'success'   => true,
            'file_id'   => $fid,
            'file_name' => $orig_name,
            'preview_url' => "api/files.php?action=preview&id=$fid"
        ]);
        break;

    case 'delete':
        header('Content-Type: application/json');
        $fid = (int)($_POST['file_id'] ?? 0);
        if (!$fid) { echo json_encode(['success' => false]); exit; }

        $f = $db->prepare("SELECT * FROM files WHERE id = ? AND company_id = ? LIMIT 1");
        $f->execute([$fid, $cid]);
        $file_rec = $f->fetch();

        if (!$file_rec) { echo json_encode(['success' => false, 'message' => 'File not found or access denied']); exit; }
        if (!is_admin() && $file_rec['uploaded_by'] != $uid) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }

        $tenant_file = PRIVATE_STORAGE_DIR . $cid . '/' . $file_rec['file_name'];
        if (file_exists($tenant_file)) @unlink($tenant_file);

        $db->prepare("DELETE FROM files WHERE id = ? AND company_id = ?")->execute([$fid, $cid]);
        log_activity($cid, $file_rec['project_id'], $uid, 'file_deleted', "Deleted file: {$file_rec['original_name']}");
        echo json_encode(['success' => true]);
        break;

    case 'list':
        header('Content-Type: application/json');
        $project_id = (int)($_GET['project_id'] ?? 0);
        $query = "SELECT f.*, u.name AS uploader_name FROM files f JOIN users u ON u.id = f.uploaded_by WHERE f.company_id = ?";
        $params = [$cid];
        if ($project_id) { $query .= " AND f.project_id = ?"; $params[] = $project_id; }
        $query .= " ORDER BY f.uploaded_at DESC LIMIT 50";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $files = $stmt->fetchAll();

        foreach ($files as &$f) {
            $f['time_ago']    = time_ago($f['uploaded_at']);
            $f['size_fmt']    = $f['file_size'] > 1048576 ? round($f['file_size']/1048576,1).'MB' : round($f['file_size']/1024,1).'KB';
            $f['preview_url'] = "api/files.php?action=preview&id={$f['id']}";
        }
        echo json_encode($files);
        break;

    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unknown action']);
}
