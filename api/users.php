<?php
// ============================================================
// Multi-Tenant Users API Endpoint
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$cid    = active_company_id();
$db     = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $role = $_GET['role'] ?? '';
        $q    = trim($_GET['q'] ?? '');
        $where = 'company_id = ? AND is_active = 1';
        $params = [$cid];
        if ($role) { $where .= ' AND role = ?'; $params[] = $role; }
        if ($q) { $where .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

        $stmt = $db->prepare("SELECT id, name, email, role, status, avatar, is_active FROM users WHERE $where ORDER BY name");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'get':
        $target = (int)($_GET['id'] ?? $uid);
        $stmt = $db->prepare("SELECT id, name, email, role, status, avatar, bio, phone, created_at, last_seen FROM users WHERE id = ? AND company_id = ?");
        $stmt->execute([$target, $cid]);
        $u = $stmt->fetch();
        if (!$u) { echo json_encode(['error' => 'User not found or access denied']); exit; }

        $t_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND company_id = ?");
        $t_stmt->execute([$u['id'], $cid]);
        $u['task_count'] = (int)$t_stmt->fetchColumn();

        $p_stmt = $db->prepare("SELECT COUNT(*) FROM project_members WHERE user_id = ? AND company_id = ?");
        $p_stmt->execute([$u['id'], $cid]);
        $u['project_count'] = (int)$p_stmt->fetchColumn();

        echo json_encode($u);
        break;

    case 'update':
        $target = (int)($_POST['user_id'] ?? $uid);
        if ($target != $uid && !is_admin()) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }

        $allowed = ['name','bio','phone'];
        if (is_admin()) $allowed[] = 'role';

        $fields = []; $params = [];
        foreach ($allowed as $f) {
            if (isset($_POST[$f])) {
                $val = $_POST[$f];
                // ABSOLUTE RULE: Company Admin CANNOT promote anyone to company_admin or super_admin
                if ($f === 'role' && $val === 'company_admin' && !is_super_admin()) {
                    continue; // Ignore forbidden privilege elevation
                }
                $fields[] = "$f = ?"; $params[] = $val;
            }
        }
        if (empty($fields)) { echo json_encode(['success' => false, 'message' => 'No valid fields to update']); exit; }

        $params[] = $target;
        $params[] = $cid;

        $db->prepare("UPDATE users SET " . implode(',',$fields) . " WHERE id = ? AND company_id = ?")->execute($params);
        if ($target == $uid && isset($_POST['name'])) $_SESSION['user_name'] = $_POST['name'];
        echo json_encode(['success' => true]);
        break;

    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) { echo json_encode([]); exit; }
        $stmt = $db->prepare("SELECT id, name, role, avatar FROM users WHERE company_id = ? AND is_active = 1 AND name LIKE ? LIMIT 10");
        $stmt->execute([$cid, "%$q%"]);
        echo json_encode($stmt->fetchAll());
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
