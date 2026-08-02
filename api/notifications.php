<?php
// ─── Notifications API ────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$db     = getDB();
$action = $_GET['action'] ?? 'count';

switch ($action) {
    case 'count':
        $count = count_unread_notifications($uid);
        echo json_encode(['count' => $count]);
        break;

    case 'list':
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$uid]);
        $notifs = $stmt->fetchAll();
        foreach ($notifs as &$n) $n['time_ago'] = time_ago($n['created_at']);
        echo json_encode($notifs);
        break;

    case 'read':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $uid]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'read_all':
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
