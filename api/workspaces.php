<?php
// ============================================================
// Multi-Tenant Workspaces API Endpoint
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
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#4f46e5');
        if (!$name) { echo json_encode(['success' => false, 'message' => 'Workspace name is required.']); exit; }

        $stmt = $db->prepare("INSERT INTO workspaces (company_id, name, description, color, created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([$cid, $name, $desc, $color, $uid]);
        $wid = $db->lastInsertId();

        $db->prepare("INSERT INTO workspace_members (company_id, workspace_id, user_id, role) VALUES (?,?,?,'owner')")->execute([$cid, $wid, $uid]);

        $ws = $db->prepare("SELECT w.*, u.name AS creator_name, 1 AS member_count, 0 AS project_count FROM workspaces w JOIN users u ON u.id = w.created_by WHERE w.id = ? AND w.company_id = ?");
        $ws->execute([$wid, $cid]);
        $workspace = $ws->fetch();

        echo json_encode(['success' => true, 'workspace' => $workspace]);
        break;

    case 'delete':
        $wid = (int)($_POST['workspace_id'] ?? 0);
        if (!$wid) { echo json_encode(['success' => false]); exit; }

        $check = $db->prepare("SELECT created_by FROM workspaces WHERE id = ? AND company_id = ?");
        $check->execute([$wid, $cid]);
        $ws = $check->fetch();
        if (!$ws || ($ws['created_by'] != $uid && !is_admin())) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']); exit;
        }

        $db->prepare("DELETE FROM workspaces WHERE id = ? AND company_id = ?")->execute([$wid, $cid]);
        echo json_encode(['success' => true]);
        break;

    case 'invite_member':
        $wid   = (int)($_POST['workspace_id'] ?? 0);
        $query = trim($_POST['query'] ?? '');
        if (!$wid || !$query) { echo json_encode(['success' => false, 'message' => 'Missing data']); exit; }

        $check = $db->prepare("SELECT created_by FROM workspaces WHERE id = ? AND company_id = ?");
        $check->execute([$wid, $cid]);
        $ws = $check->fetch();
        if (!$ws || ($ws['created_by'] != $uid && !is_admin())) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']); exit;
        }

        // Restrict search strictly to current active company users
        $find = $db->prepare("SELECT id, name, email, status FROM users WHERE (email = ? OR name = ?) AND company_id = ? AND is_active = 1 LIMIT 1");
        $find->execute([$query, $query, $cid]);
        $found = $find->fetch();
        if (!$found) { echo json_encode(['success' => false, 'message' => 'User not found in your company.']); exit; }

        $db->prepare("INSERT INTO workspace_members (company_id, workspace_id, user_id, role) VALUES (?,?,?,'member')")->execute([$cid, $wid, $found['id']]);
        echo json_encode(['success' => true, 'user' => $found]);
        break;

    case 'list_members':
        $wid = (int)($_GET['workspace_id'] ?? 0);
        if (!$wid) { echo json_encode([]); exit; }

        $stmt = $db->prepare("SELECT u.id, u.name, u.email, u.status, wm.role FROM workspace_members wm JOIN users u ON u.id = wm.user_id WHERE wm.workspace_id = ? AND wm.company_id = ? ORDER BY wm.role DESC, u.name ASC");
        $stmt->execute([$wid, $cid]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'list_projects':
        $wid = (int)($_GET['workspace_id'] ?? 0);
        if (!$wid) { echo json_encode([]); exit; }

        $stmt = $db->prepare("
            SELECT p.*, u.name AS manager_name,
                   (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND company_id = ?) AS task_count,
                   (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND company_id = ? AND status = 'done') AS done_count
            FROM projects p
            JOIN users u ON u.id = p.manager_id
            WHERE p.workspace_id = ? AND p.company_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$cid, $cid, $wid, $cid]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'remove_member':
        $wid   = (int)($_POST['workspace_id'] ?? 0);
        $mu_id = (int)($_POST['user_id'] ?? 0);
        if (!$wid || !$mu_id) { echo json_encode(['success' => false]); exit; }

        $check = $db->prepare("SELECT created_by FROM workspaces WHERE id = ? AND company_id = ?");
        $check->execute([$wid, $cid]);
        $ws = $check->fetch();
        if (!$ws || ($ws['created_by'] != $uid && !is_admin())) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']); exit;
        }
        if ($mu_id == $ws['created_by']) {
            echo json_encode(['success' => false, 'message' => 'Cannot remove workspace owner']); exit;
        }

        $db->prepare("DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND company_id = ?")->execute([$wid, $mu_id, $cid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
