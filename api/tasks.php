<?php
// ============================================================
// Multi-Tenant Tasks API Endpoint
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
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'update_status':
        $task_id = (int)($_POST['task_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $allowed = ['todo','in_progress','in_review','done'];
        if (!$task_id || !in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
        }

        // Verify task belongs to user's company
        $task_stmt = $db->prepare("SELECT t.*, p.manager_id FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ? AND t.company_id = ?");
        $task_stmt->execute([$task_id, $cid]);
        $task = $task_stmt->fetch();

        if (!$task) { echo json_encode(['success' => false, 'message' => 'Task not found or access denied']); exit; }

        $completed_at = ($status === 'done') ? date('Y-m-d H:i:s') : null;
        $db->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ? AND company_id = ?")->execute([$status, $completed_at, $task_id, $cid]);

        log_activity($cid, $task['project_id'], $uid, 'task_status_updated',
            "{$user['name']} moved task '{$task['title']}' to " . ucfirst(str_replace('_', ' ', $status)),
            'task', $task_id);

        if ($task['assigned_to'] && $task['assigned_to'] != $uid) {
            send_notification($cid, $task['assigned_to'], 'Task Updated',
                "Task '{$task['title']}' moved to " . ucfirst(str_replace('_',' ',$status)),
                'task', "tasks.php?project_id={$task['project_id']}");
        }

        updateProjectProgress($task['project_id'], $cid, $db);
        echo json_encode(['success' => true]);
        break;

    case 'create':
        $project_id  = (int)($_POST['project_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $priority    = $_POST['priority'] ?? 'medium';
        $status      = $_POST['status'] ?? 'todo';
        $due_date    = $_POST['due_date'] ?? null;

        if (!$title || !$project_id) { echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit; }

        // Verify project belongs to user's company
        $p_check = $db->prepare("SELECT id FROM projects WHERE id = ? AND company_id = ? LIMIT 1");
        $p_check->execute([$project_id, $cid]);
        if (!$p_check->fetch()) { echo json_encode(['success' => false, 'message' => 'Invalid project or access denied']); exit; }

        $stmt = $db->prepare("INSERT INTO tasks (company_id, project_id, title, description, assigned_to, created_by, priority, status, due_date) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$cid, $project_id, $title, $description, $assigned_to, $uid, $priority, $status, $due_date ?: null]);
        $task_id = $db->lastInsertId();

        log_activity($cid, $project_id, $uid, 'task_created', "{$user['name']} created task: $title", 'task', $task_id);
        if ($assigned_to) {
            send_notification($cid, $assigned_to, 'New Task Assigned', "You have been assigned: $title", 'task', "tasks.php?project_id=$project_id");
        }

        updateProjectProgress($project_id, $cid, $db);
        echo json_encode(['success' => true, 'task_id' => $task_id]);
        break;

    case 'delete':
        if (!is_manager()) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
        $task_id = (int)($_POST['task_id'] ?? 0);
        if (!$task_id) { echo json_encode(['success' => false]); exit; }

        $db->prepare("DELETE FROM tasks WHERE id = ? AND company_id = ?")->execute([$task_id, $cid]);
        echo json_encode(['success' => true]);
        break;

    case 'list':
        $project_id = (int)($_GET['project_id'] ?? 0);
        $query = "SELECT t.*, u.name AS assignee_name, u.avatar AS assignee_avatar
                  FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
                  WHERE t.company_id = ?";
        $params = [$cid];
        if ($project_id) { $query .= " AND t.project_id = ?"; $params[] = $project_id; }
        if (!is_manager()) { $query .= " AND (t.assigned_to = ? OR t.created_by = ?)"; $params[] = $uid; $params[] = $uid; }
        $query .= " ORDER BY t.priority DESC, t.created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;
}

function updateProjectProgress(int $project_id, int $company_id, PDO $db): void {
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND company_id = ?");
    $total_stmt->execute([$project_id, $company_id]);
    $total = (int)$total_stmt->fetchColumn();

    $done_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND company_id = ? AND status = 'done'");
    $done_stmt->execute([$project_id, $company_id]);
    $done = (int)$done_stmt->fetchColumn();

    $progress = $total > 0 ? round(($done / $total) * 100) : 0;
    $db->prepare("UPDATE projects SET progress = ? WHERE id = ? AND company_id = ?")->execute([$progress, $project_id, $company_id]);
}
