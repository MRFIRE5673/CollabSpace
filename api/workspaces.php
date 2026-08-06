<?php
// ─── Workspaces API ───────────────────────────────────────────
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
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#4f46e5');
        if (!$name) { echo json_encode(['success' => false, 'message' => 'Workspace name is required.']); exit; }

        $stmt = $db->prepare("INSERT INTO workspaces (name, description, color, created_by) VALUES (?,?,?,?)");
        $stmt->execute([$name, $desc, $color, $uid]);
        $wid = $db->lastInsertId();

        // Creator becomes owner
        $db->prepare("INSERT IGNORE INTO workspace_members (workspace_id, user_id, role) VALUES (?,?,'owner')")->execute([$wid, $uid]);

        $ws = $db->prepare("SELECT w.*, u.name AS creator_name, 1 AS member_count, 0 AS project_count FROM workspaces w JOIN users u ON u.id=w.created_by WHERE w.id=?");
        $ws->execute([$wid]);
        $workspace = $ws->fetch();

        echo json_encode(['success' => true, 'workspace' => $workspace]);
        break;

    case 'delete':
        $wid = (int)($_POST['workspace_id'] ?? 0);
        if (!$wid) { echo json_encode(['success' => false]); exit; }

        // Only admin or workspace owner can delete
        $check = $db->prepare("SELECT created_by FROM workspaces WHERE id=?");
        $check->execute([$wid]);
        $ws = $check->fetch();
        if (!$ws || ($ws['created_by'] != $uid && !is_admin())) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']); exit;
        }

        $db->prepare("DELETE FROM workspaces WHERE id=?")->execute([$wid]);
        echo json_encode(['success' => true]);
        break;

    case 'invite_member':
        $wid   = (int)($_POST['workspace_id'] ?? 0);
        $query = trim($_POST['query'] ?? '');
        if (!$wid || !$query) { echo json_encode(['success' => false, 'message' => 'Missing data']); exit; }

        // Check caller is owner or admin
        $check = $db->prepare("SELECT created_by FROM workspaces WHERE id=?");
        $check->execute([$wid]);
        $ws = $check->fetch();
        if (!$ws || ($ws['created_by'] != $uid && !is_admin())) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']); exit;
        }

        $find = $db->prepare("SELECT id, name, email, status FROM users WHERE (email=? OR name=?) AND is_active=1 LIMIT 1");
        $find->execute([$query, $query]);
        $found = $find->fetch();
        if (!$found) { echo json_encode(['success' => false, 'message' => 'User not found.']); exit; }

        $db->prepare("INSERT IGNORE INTO workspace_members (workspace_id, user_id, role) VALUES (?,?,'member')")->execute([$wid, $found['id']]);
        echo json_encode(['success' => true, 'user' => $found]);
        break;

    case 'list_members':
        $wid = (int)($_GET['workspace_id'] ?? 0);
        if (!$wid) { echo json_encode([]); exit; }

        $stmt = $db->prepare("SELECT u.id, u.name, u.email, u.status, wm.role FROM workspace_members wm JOIN users u ON u.id=wm.user_id WHERE wm.workspace_id=? ORDER BY wm.role DESC, u.name ASC");
        $stmt->execute([$wid]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'list_projects':
        $wid = (int)($_GET['workspace_id'] ?? 0);
        if (!$wid) { echo json_encode([]); exit; }

        $stmt = $db->prepare("
            SELECT p.*, u.name AS manager_name,
                   (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS task_count,
                   (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') AS done_count
            FROM projects p
            JOIN users u ON u.id=p.manager_id
            WHERE p.workspace_id=?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$wid]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'remove_member':
        $wid   = (int)($_POST['workspace_id'] ?? 0);
        $mu_id = (int)($_POST['user_id'] ?? 0);
        if (!$wid || !$mu_id) { echo json_encode(['success' => false]); exit; }

        $check = $db->prepare("SELECT created_by FROM workspaces WHERE id=?");
        $check->execute([$wid]);
        $ws = $check->fetch();
        if (!$ws || ($ws['created_by'] != $uid && !is_admin())) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']); exit;
        }
        if ($mu_id == $ws['created_by']) {
            echo json_encode(['success' => false, 'message' => 'Cannot remove workspace owner']); exit;
        }

        $db->prepare("DELETE FROM workspace_members WHERE workspace_id=? AND user_id=?")->execute([$wid, $mu_id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
