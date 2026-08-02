<?php
// ─── Events / Presence API ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$db     = getDB();
$action = $_GET['action'] ?? 'ping';

switch ($action) {
    case 'ping':
        // Update user status to online and last_seen
        $db->prepare("UPDATE users SET status='online', last_seen=NOW() WHERE id=?")->execute([$uid]);
        echo json_encode(['success' => true]);
        break;

    case 'online_users':
        // Users seen in last 2 minutes are "online"
        $stmt = $db->query("
            SELECT id, name, status,
                   CASE WHEN last_seen >= NOW() - INTERVAL 2 MINUTE THEN 'online' ELSE 'offline' END AS computed_status
            FROM users
            WHERE is_active=1
            ORDER BY name
        ");
        $users = $stmt->fetchAll();
        // Update stale online statuses
        $db->query("UPDATE users SET status='offline' WHERE status='online' AND last_seen < NOW() - INTERVAL 2 MINUTE AND id != $uid");
        echo json_encode(array_map(function($u) {
            return [
                'id'     => $u['id'],
                'name'   => $u['name'],
                'status' => $u['computed_status'],
            ];
        }, $users));
        break;

    case 'mark_offline':
        $db->prepare("UPDATE users SET status='offline' WHERE id=?")->execute([$uid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
