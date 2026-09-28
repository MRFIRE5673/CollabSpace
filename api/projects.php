<?php
// ============================================================
// Multi-Tenant Projects API Endpoint
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

        $stmt = $db->prepare("INSERT INTO projects (company_id, workspace_id, name, description, status, priority, start_date, due_date, manager_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$cid, $ws_id, $name, $description, $status, $priority, $start_date, $due_date, $manager_id, $uid]);
        $pid = $db->lastInsertId();

        $db->prepare("INSERT INTO project_members (company_id, project_id, user_id) VALUES (?,?,?)")->execute([$cid, $pid, $uid]);
        if ($manager_id != $uid) {
            $db->prepare("INSERT INTO project_members (company_id, project_id, user_id) VALUES (?,?,?)")->execute([$cid, $pid, $manager_id]);
        }

        log_activity($cid, $pid, $uid, 'project_created', "Created project: $name", 'project', $pid);

        $proj = $db->prepare("SELECT p.*, u.name AS manager_name FROM projects p JOIN users u ON u.id = p.manager_id WHERE p.id = ? AND p.company_id = ?");
        $proj->execute([$pid, $cid]);
        $project = $proj->fetch();

        echo json_encode(['success' => true, 'project' => $project]);
        break;

    case 'delete':
        $pid = (int)($_POST['project_id'] ?? 0);
        if (!$pid || !is_admin()) { echo json_encode(['success' => false, 'message' => 'Not authorized']); exit; }
        $db->prepare("DELETE FROM projects WHERE id = ? AND company_id = ?")->execute([$pid, $cid]);
        echo json_encode(['success' => true]);
        break;

    case 'update_status':
        $pid    = (int)($_POST['project_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['planning','active','on_hold','completed','cancelled'];
        if (!$pid || !in_array($status, $allowed)) { echo json_encode(['success' => false]); exit; }
        $db->prepare("UPDATE projects SET status = ? WHERE id = ? AND company_id = ?")->execute([$status, $pid, $cid]);
        echo json_encode(['success' => true]);
        break;

    case 'add_member':
        $pid = (int)($_POST['project_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);

        if (!$pid || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'Please select a user to add.']);
            exit;
        }

        // Verify target user belongs to same company
        $u_check = $db->prepare("SELECT id, name FROM users WHERE id = ? AND company_id = ? LIMIT 1");
        $u_check->execute([$user_id, $cid]);
        $target_user = $u_check->fetch();

        if (!$target_user) {
            echo json_encode(['success' => false, 'message' => 'User does not belong to your company.']);
            exit;
        }

        $db->prepare("INSERT INTO project_members (company_id, project_id, user_id) VALUES (?,?,?)")->execute([$cid, $pid, $user_id]);

        log_activity($cid, $pid, $uid, 'member_added', "Added {$target_user['name']} to project", 'project', $pid);
        echo json_encode(['success' => true]);
        break;

    case 'remove_member':
        $pid = (int)($_POST['project_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);

        if (!$pid || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        $db->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ? AND company_id = ?")->execute([$pid, $user_id, $cid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
