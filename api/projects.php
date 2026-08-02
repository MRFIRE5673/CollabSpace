<?php
// ─── Projects API ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
$db     = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'add_member':
        if (!is_manager()) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
        $project_id = (int)($_POST['project_id'] ?? 0);
        $target_uid = (int)($_POST['user_id'] ?? 0);
        if (!$project_id || !$target_uid) { echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit; }
        $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?,?)")->execute([$project_id, $target_uid]);
        log_activity($project_id, $uid, 'member_added', "{$user['name']} added a new member to the project", 'user', $target_uid);
        // Get target user name
        $tn = $db->prepare("SELECT name FROM users WHERE id=?"); $tn->execute([$target_uid]); $tn = $tn->fetchColumn();
        send_notification($target_uid, 'Added to Project', "You were added to a project by {$user['name']}", 'project', "project_details.php?id=$project_id");
        echo json_encode(['success' => true]);
        break;

    case 'remove_member':
        if (!is_manager()) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
        $project_id = (int)($_POST['project_id'] ?? 0);
        $target_uid = (int)($_POST['user_id'] ?? 0);
        if (!$project_id || !$target_uid) { echo json_encode(['success' => false]); exit; }
        $db->prepare("DELETE FROM project_members WHERE project_id=? AND user_id=?")->execute([$project_id, $target_uid]);
        echo json_encode(['success' => true]);
        break;

    case 'update':
        if (!is_manager()) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
        $project_id = (int)($_POST['project_id'] ?? 0);
        if (!$project_id) { echo json_encode(['success' => false]); exit; }
        $fields = [];
        $params = [];
        $allowed = ['name','description','status','priority','start_date','due_date','progress'];
        foreach ($allowed as $f) {
            if (isset($_POST[$f])) { $fields[] = "$f=?"; $params[] = $_POST[$f]; }
        }
        if (empty($fields)) { echo json_encode(['success' => false, 'message' => 'Nothing to update']); exit; }
        $params[] = $project_id;
        $db->prepare("UPDATE projects SET " . implode(',',$fields) . " WHERE id=?")->execute($params);
        log_activity($project_id, $uid, 'project_updated', "{$user['name']} updated the project", 'project', $project_id);
        echo json_encode(['success' => true]);
        break;

    case 'list':
        $ws_id = (int)($_GET['workspace_id'] ?? 0);
        $q = trim($_GET['q'] ?? '');
        $where = '1=1'; $params = [];
        if ($ws_id) { $where .= ' AND workspace_id=?'; $params[] = $ws_id; }
        if ($q) { $where .= ' AND name LIKE ?'; $params[] = "%$q%"; }
        $stmt = $db->prepare("SELECT p.*, u.name AS manager_name, (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count FROM projects p JOIN users u ON u.id=p.manager_id WHERE $where ORDER BY p.created_at DESC");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'get':
        $project_id = (int)($_GET['id'] ?? 0);
        if (!$project_id) { echo json_encode(['error' => 'Invalid ID']); exit; }
        $stmt = $db->prepare("SELECT p.*, u.name AS manager_name FROM projects p JOIN users u ON u.id=p.manager_id WHERE p.id=?");
        $stmt->execute([$project_id]);
        $p = $stmt->fetch();
        if (!$p) { echo json_encode(['error' => 'Not found']); exit; }
        echo json_encode($p);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
