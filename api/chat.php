<?php
// ─── Chat API Endpoint ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user = current_user();
$uid  = $user['id'];
session_write_close();
$db   = getDB();
$action = $_GET['action'] ?? 'fetch';

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
                WHERE c.room_type = 'direct'
                  AND ((c.sender_id = ? AND c.receiver_id = ?) OR (c.sender_id = ? AND c.receiver_id = ?))
                  AND c.id > ?
                ORDER BY c.created_at ASC LIMIT 50
            ");
            $stmt->execute([$uid, $uid, $receiver_id, $receiver_id, $uid, $after_id]);
        } else {
            $stmt = $db->prepare("
                SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar,
                       (c.sender_id = ?) AS is_mine
                FROM chats c
                JOIN users u ON u.id = c.sender_id
                WHERE c.project_id = ? AND c.room_type = 'project' AND c.id > ?
                ORDER BY c.created_at ASC LIMIT 50
            ");
            $stmt->execute([$uid, $project_id, $after_id]);
        }
        $messages = $stmt->fetchAll();
        foreach ($messages as &$m) {
            $m['time_ago'] = time_ago($m['created_at']);
            $m['is_mine']  = (bool)$m['is_mine'];
        }
        echo json_encode($messages);
        break;

    case 'send':
        $project_id  = (int)($_POST['project_id'] ?? 0);
        $room_type   = $_POST['room_type'] ?? 'project';
        $receiver_id = (int)($_POST['receiver_id'] ?? 0) ?: null;
        $message     = trim($_POST['message'] ?? '');
        $file_path   = null; $file_name = null;

        // Handle file upload
        if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
            $orig = basename($_FILES['chat_file']['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (in_array($ext, ALLOWED_EXTENSIONS)) {
                $fname = 'chat_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['chat_file']['tmp_name'], UPLOAD_DIR . $fname)) {
                    $file_path = $fname; $file_name = $orig;
                }
            }
        }

        if (empty($message) && !$file_path) {
            echo json_encode(['success' => false, 'message' => 'Empty message']); exit;
        }

        // Backend Deduplication check: ignore duplicate message from same user if sent within 10 seconds
        if ($message && !$file_path) {
            $dup = $db->prepare("
                SELECT id, message, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS sec_diff
                FROM chats
                WHERE sender_id = ? AND room_type = ?
                ORDER BY id DESC LIMIT 1
            ");
            $dup->execute([$uid, $room_type]);
            $last_chat = $dup->fetch();

            if ($last_chat && $last_chat['message'] === $message && (int)$last_chat['sec_diff'] <= 10) {
                $existing_id = (int)$last_chat['id'];
                $existing = $db->prepare("SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar, 1 AS is_mine FROM chats c JOIN users u ON u.id=c.sender_id WHERE c.id=?");
                $existing->execute([$existing_id]);
                $msg = $existing->fetch();
                $msg['time_ago'] = time_ago($msg['created_at']);
                $msg['is_mine']  = true;
                echo json_encode(['success' => true, 'id' => $existing_id, 'message' => $msg]);
                exit;
            }
        }

        $stmt = $db->prepare("INSERT INTO chats (project_id, room_type, sender_id, receiver_id, message, file_path, file_name) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$project_id ?: null, $room_type, $uid, $receiver_id, $message ?: null, $file_path, $file_name]);
        $cid = $db->lastInsertId();

        // Notify project members
        if ($project_id) {
            $members = $db->prepare("SELECT user_id FROM project_members WHERE project_id=? AND user_id!=?");
            $members->execute([$project_id, $uid]);
            foreach ($members->fetchAll() as $m) {
                send_notification($m['user_id'], 'New Message', "{$user['name']}: " . substr($message, 0, 60), 'chat', "project_details.php?id={$project_id}&tab=chat");
            }
        }

        echo json_encode([
            'success' => true, 'id' => $cid,
            'message' => [
                'id' => $cid, 'message' => $message, 'file_path' => $file_path,
                'file_name' => $file_name, 'sender_name' => $user['name'],
                'sender_avatar' => $user['avatar'], 'is_mine' => true,
                'time_ago' => 'just now'
            ]
        ]);
        break;
}
