<?php
// ─── Projects API ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user = current_user();
$uid  = $user['id'];
session_write_close();
$db     = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    case 'create':
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'planning';
        $priority    = $_POST['priority'] ?? 'medium';
        $start_date  = $_POST['start_date'] ?: null;
        $due_date    = $_POST['due_date'] ?: null;
        $manager_id  = (int)($_POST['manager_id'] ?? $uid);
        $ws_id       = (int)($_POST['workspace_id'] ?? 0) ?: null;

        if (!$name) { echo json_encode(['success' => false, 'message' => 'Project name required.']); exit; }

        $stmt = $db->prepare("INSERT INTO projects (workspace_id, name, description, status, priority, start_date, due_date, manager_id, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$ws_id, $name, $description, $status, $priority, $start_date, $due_date, $manager_id, $uid]);
        $pid = $db->lastInsertId();

        $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?,?)")->execute([$pid, $uid]);
        if ($manager_id != $uid) {
            $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?,?)")->execute([$pid, $manager_id]);
        }

        log_activity($pid, $uid, 'project_created', "Created project: $name", 'project', $pid);

        // Fetch back with manager name
        $proj = $db->prepare("SELECT p.*, u.name AS manager_name FROM projects p JOIN users u ON u.id=p.manager_id WHERE p.id=?");
        $proj->execute([$pid]);
        $project = $proj->fetch();

        echo json_encode(['success' => true, 'project' => $project]);
        break;

    case 'delete':
        $pid = (int)($_POST['project_id'] ?? 0);
        if (!$pid || !is_admin()) { echo json_encode(['success' => false, 'message' => 'Not authorized']); exit; }
        $db->prepare("DELETE FROM projects WHERE id=?")->execute([$pid]);
        echo json_encode(['success' => true]);
        break;

    case 'update_status':
        $pid    = (int)($_POST['project_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['planning','active','on_hold','completed','cancelled'];
        if (!$pid || !in_array($status, $allowed)) { echo json_encode(['success' => false]); exit; }
        $db->prepare("UPDATE projects SET status=? WHERE id=?")->execute([$status, $pid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
