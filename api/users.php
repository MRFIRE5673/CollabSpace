<?php
// ─── Users API ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$db     = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $role = $_GET['role'] ?? '';
        $q    = trim($_GET['q'] ?? '');
        $where = '1=1'; $params = [];
        if ($role) { $where .= ' AND role=?'; $params[] = $role; }
        if ($q) { $where .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $stmt = $db->prepare("SELECT id, name, email, role, status, avatar, is_active FROM users WHERE is_active=1 AND $where ORDER BY name");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'get':
        $target = (int)($_GET['id'] ?? $uid);
        $stmt = $db->prepare("SELECT id, name, email, role, status, avatar, bio, phone, created_at, last_seen FROM users WHERE id=?");
        $stmt->execute([$target]);
        $u = $stmt->fetch();
        if (!$u) { echo json_encode(['error' => 'Not found']); exit; }
        $u['task_count']    = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$u['id']}")->fetchColumn();
        $u['project_count'] = (int)$db->query("SELECT COUNT(*) FROM project_members WHERE user_id={$u['id']}")->fetchColumn();
        echo json_encode($u);
        break;

    case 'update':
        // Users can only update their own profile; admins can update anyone
        $target = (int)($_POST['user_id'] ?? $uid);
        if ($target != $uid && !is_admin()) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
        $allowed = ['name','bio','phone'];
        if (is_admin()) $allowed[] = 'role';
        $fields = []; $params = [];
        foreach ($allowed as $f) {
            if (isset($_POST[$f])) { $fields[] = "$f=?"; $params[] = $_POST[$f]; }
        }
        if (empty($fields)) { echo json_encode(['success' => false]); exit; }
        $params[] = $target;
        $db->prepare("UPDATE users SET " . implode(',',$fields) . " WHERE id=?")->execute($params);
        // Update session if own profile
        if ($target == $uid && isset($_POST['name'])) $_SESSION['user_name'] = $_POST['name'];
        echo json_encode(['success' => true]);
        break;

    case 'search':
        // Quick user search for @mentions / assignments
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) { echo json_encode([]); exit; }
        $stmt = $db->prepare("SELECT id, name, role, avatar FROM users WHERE is_active=1 AND name LIKE ? LIMIT 10");
        $stmt->execute(["%$q%"]);
        echo json_encode($stmt->fetchAll());
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
