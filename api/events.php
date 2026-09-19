<?php
// ============================================================
// Multi-Tenant Events & Presence API Endpoint
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$cid    = active_company_id();
$db     = getDB();
$action = $_GET['action'] ?? 'ping';

switch ($action) {
    case 'ping':
        $db->prepare("UPDATE users SET status='online', last_seen=NOW() WHERE id=? AND company_id=?")->execute([$uid, $cid]);
        echo json_encode(['success' => true]);
        break;

    case 'online_users':
        $stmt = $db->prepare("
            SELECT id, name, status, last_seen
            FROM users
            WHERE company_id=? AND is_active=1
            ORDER BY name
        ");
        $stmt->execute([$cid]);
        $users = $stmt->fetchAll();

        echo json_encode(array_map(function($u) {
            return [
                'id'     => $u['id'],
                'name'   => $u['name'],
                'status' => $u['status'],
            ];
        }, $users));
        break;

    case 'mark_offline':
        $db->prepare("UPDATE users SET status='offline' WHERE id=? AND company_id=?")->execute([$uid, $cid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
