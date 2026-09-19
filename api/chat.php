<?php
// ============================================================
// Multi-Tenant Chat API Endpoint
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

function isProjectMember($db, $userId, $projectId, $companyId) {
    if (!$projectId) return false;
    $stmt = $db->prepare("
        SELECT 1 FROM projects p
        LEFT JOIN project_members pm ON pm.project_id = p.id
        WHERE p.id = ? AND p.company_id = ? AND (p.created_by = ? OR p.manager_id = ? OR pm.user_id = ?)
    ");
    $stmt->execute([$projectId, $companyId, $userId, $userId, $userId]);
    return (bool)$stmt->fetchColumn();
}

switch ($action) {
    case 'fetch':
        $project_id  = (int)($_GET['project_id'] ?? 0);
        $room_type   = $_GET['room_type'] ?? 'project';
        $receiver_id = (int)($_GET['receiver_id'] ?? 0);
        $after_id    = (int)($_GET['after_id'] ?? 0);

        if ($room_type === 'direct' && $receiver_id) {
            $stmt = $db->prepare("
                SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar,
                       (c.sender_id = ?) AS is_mine
                FROM chats c
                JOIN users u ON u.id = c.sender_id
                WHERE c.company_id = ? AND c.room_type = 'direct'
                  AND ((c.sender_id = ? AND c.receiver_id = ?) OR (c.sender_id = ? AND c.receiver_id = ?))
                  AND c.id > ?
                ORDER BY c.created_at ASC LIMIT 50
            ");
            $stmt->execute([$uid, $cid, $uid, $receiver_id, $receiver_id, $uid, $after_id]);
        } else {
            // Verify user is member of project & project belongs to active company
            if ($project_id && !isProjectMember($db, $uid, $project_id, $cid)) {
                echo json_encode([]);
                exit;
            }

            $stmt = $db->prepare("
                SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar,
                       (c.sender_id = ?) AS is_mine
                FROM chats c
                JOIN users u ON u.id = c.sender_id
                WHERE c.company_id = ? AND c.project_id = ? AND c.room_type = 'project' AND c.id > ?
                ORDER BY c.created_at ASC LIMIT 50
            ");
            $stmt->execute([$uid, $cid, $project_id, $after_id]);
        }
        $messages = $stmt->fetchAll();
        foreach ($messages as &$m) {
            $m['time_ago'] = time_ago($m['created_at']);
            $m['is_mine']  = (bool)$m['is_mine'];
            if ($m['file_path']) {
                $m['preview_url'] = "api/files.php?action=preview&file=" . urlencode($m['file_path']);
            }
        }
        echo json_encode($messages);
        break;

    case 'send':
        $project_id  = (int)($_POST['project_id'] ?? 0);
        $room_type   = $_POST['room_type'] ?? 'project';
        $receiver_id = (int)($_POST['receiver_id'] ?? 0) ?: null;
        $message     = trim($_POST['message'] ?? '');
        $file_path   = null; $file_name = null;

        if ($room_type === 'project') {
            if (!$project_id || !isProjectMember($db, $uid, $project_id, $cid)) {
                echo json_encode(['error' => 'Unauthorized project access.']);
                exit;
            }
        }

        // Handle private chat file upload
        if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
            $orig = basename($_FILES['chat_file']['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (in_array($ext, ALLOWED_EXTENSIONS)) {
                $tenant_dir = PRIVATE_STORAGE_DIR . $cid . '/';
                if (!is_dir($tenant_dir)) @mkdir($tenant_dir, 0750, true);
                
                $fname = 'chat_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['chat_file']['tmp_name'], $tenant_dir . $fname)) {
                    $file_path = $fname; $file_name = $orig;
                }
            }
        }

        if (empty($message) && !$file_path) {
            echo json_encode(['success' => false, 'message' => 'Empty message']); exit;
        }

        $stmt = $db->prepare("INSERT INTO chats (company_id, project_id, room_type, sender_id, receiver_id, message, file_path, file_name) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$cid, $project_id ?: null, $room_type, $uid, $receiver_id, $message ?: null, $file_path, $file_name]);
        $chat_id = $db->lastInsertId();

        // Notify project members
        if ($project_id) {
            $members = $db->prepare("SELECT user_id FROM project_members WHERE project_id=? AND company_id=? AND user_id!=?");
            $members->execute([$project_id, $cid, $uid]);
            foreach ($members->fetchAll() as $m) {
                send_notification($cid, $m['user_id'], 'New Message', "{$user['name']}: " . substr($message, 0, 60), 'chat', "project_details.php?id={$project_id}&tab=chat");
            }
        }

        echo json_encode([
            'success' => true, 'id' => $chat_id,
            'message' => [
                'id' => $chat_id, 'message' => $message, 'file_path' => $file_path,
                'file_name' => $file_name, 'sender_name' => $user['name'],
                'sender_avatar' => $user['avatar'], 'is_mine' => true,
                'time_ago' => 'just now',
                'preview_url' => $file_path ? "api/files.php?action=preview&file=" . urlencode($file_path) : null
            ]
        ]);
        break;
}
