<?php
// ─── Real-Time Collaborative Document API ──────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
session_write_close();
$db     = getDB();
$action = $_GET['action'] ?? 'fetch';

switch ($action) {
    case 'fetch':
        $fid   = (int)($_GET['id'] ?? 0);
        $fname = trim($_GET['file'] ?? '');

        $file_rec = null;
        if ($fid) {
            $stmt = $db->prepare("SELECT * FROM files WHERE id=?");
            $stmt->execute([$fid]);
            $file_rec = $stmt->fetch();
        } elseif ($fname) {
            $stmt = $db->prepare("SELECT * FROM files WHERE file_name=?");
            $stmt->execute([$fname]);
            $file_rec = $stmt->fetch();
            if (!$file_rec && file_exists(UPLOAD_DIR . $fname)) {
                $file_rec = ['id' => 0, 'file_name' => $fname, 'original_name' => $fname];
            }
        }

        if (!$file_rec) {
            echo json_encode(['success' => false, 'message' => 'File not found.']);
            exit;
        }

        $filePath = UPLOAD_DIR . $file_rec['file_name'];
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
        $fname   = trim($_POST['file'] ?? '');
        $content = $_POST['content'] ?? '';

        if (!$fname) {
            echo json_encode(['success' => false, 'message' => 'Invalid file name.']);
            exit;
        }

        $filePath = UPLOAD_DIR . $fname;
        // Verify path safety
        if (basename($filePath) !== $fname) {
            echo json_encode(['success' => false, 'message' => 'Invalid file path.']);
            exit;
        }

        // Save content to file
        $bytes = file_put_contents($filePath, $content);
        if ($bytes === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file on server.']);
            exit;
        }

        $mtime = filemtime($filePath);

        // Update database record if file exists in files table
        $db->prepare("UPDATE files SET file_size=?, uploaded_at=NOW() WHERE file_name=?")->execute([$bytes, $fname]);

        // Register user collaboration ping
        try {
            $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'doc_edit', ?) ON DUPLICATE KEY UPDATE created_at=NOW()")->execute([$uid, "Edited $fname"]);
        } catch (Exception $e) {}

        echo json_encode([
            'success'       => true,
            'bytes_saved'   => $bytes,
            'last_modified' => $mtime,
            'editor_name'   => $user['name']
        ]);
        break;

    case 'poll':
        $fname       = trim($_GET['file'] ?? '');
        $clientMtime = (int)($_GET['client_mtime'] ?? 0);

        if (!$fname) {
            echo json_encode(['has_changes' => false]);
            exit;
        }

        $filePath = UPLOAD_DIR . $fname;
        if (!file_exists($filePath)) {
            echo json_encode(['has_changes' => false]);
            exit;
        }

        $serverMtime = filemtime($filePath);
        $hasChanges  = ($serverMtime > $clientMtime);

        $response = [
            'has_changes'   => $hasChanges,
            'last_modified' => $serverMtime
        ];

        if ($hasChanges) {
            $response['content'] = file_get_contents($filePath);
        }

        echo json_encode($response);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
