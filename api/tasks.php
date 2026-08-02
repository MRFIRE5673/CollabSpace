<?php
// ─── Tasks API Endpoint ─────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$user   = current_user();
$uid    = $user['id'];
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
        // Only allow if user is assigned, project manager, or admin
        $task = $db->prepare("SELECT t.*, p.manager_id FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.id=?");
        $task->execute([$task_id]);
        $task = $task->fetch();
        if (!$task) { echo json_encode(['success'=>false,'message'=>'Task not found']); exit; }

        $completed_at = ($status === 'done') ? date('Y-m-d H:i:s') : null;
        $db->prepare("UPDATE tasks SET status=?, completed_at=? WHERE id=?")->execute([$status, $completed_at, $task_id]);

        // Log activity
        log_activity($task['project_id'], $uid, 'task_status_updated',
            "{$user['name']} moved task '{$task['title']}' to " . ucfirst(str_replace('_', ' ', $status)),
            'task', $task_id);

        // Notify assignee if different from mover
        if ($task['assigned_to'] && $task['assigned_to'] != $uid) {
            send_notification($task['assigned_to'], 'Task Updated',
                "Task '{$task['title']}' moved to " . ucfirst(str_replace('_',' ',$status)),
                'task', "tasks.php?project_id={$task['project_id']}");
        }

        // Update project progress
        updateProjectProgress($task['project_id'], $db);

        echo json_encode(['success' => true]);
        break;

    case 'create':
        if (!is_manager()) { echo json_encode(['success'=>false,'message'=>'Permission denied']); exit; }
        $project_id  = (int)($_POST['project_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $priority    = $_POST['priority'] ?? 'medium';
        $status      = $_POST['status'] ?? 'todo';
        $due_date    = $_POST['due_date'] ?? null;

        if (!$title || !$project_id) { echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit; }

        $stmt = $db->prepare("INSERT INTO tasks (project_id, title, description, assigned_to, created_by, priority, status, due_date) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$project_id, $title, $description, $assigned_to, $uid, $priority, $status, $due_date ?: null]);
        $task_id = $db->lastInsertId();

        log_activity($project_id, $uid, 'task_created', "{$user['name']} created task: $title", 'task', $task_id);
        if ($assigned_to) {
            send_notification($assigned_to, 'New Task Assigned', "You have been assigned: $title", 'task', "tasks.php?project_id=$project_id");
        }

        // Return rendered task card HTML
        $task = $db->prepare("SELECT t.*, u.name AS assignee_name FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.id=?")->execute([$task_id]);
        echo json_encode(['success' => true, 'task_id' => $task_id]);
        break;

    case 'delete':
        if (!is_manager()) { echo json_encode(['success'=>false,'message'=>'Permission denied']); exit; }
        $task_id = (int)($_POST['task_id'] ?? 0);
        if (!$task_id) { echo json_encode(['success'=>false]); exit; }
        $task = $db->prepare("SELECT * FROM tasks WHERE id=?")->execute([$task_id]);
        $db->prepare("DELETE FROM tasks WHERE id=?")->execute([$task_id]);
        echo json_encode(['success' => true]);
        break;

    case 'list':
        $project_id = (int)($_GET['project_id'] ?? 0);
        $query = "SELECT t.*, u.name AS assignee_name, u.avatar AS assignee_avatar
                  FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to
                  WHERE 1=1";
        $params = [];
        if ($project_id) { $query .= " AND t.project_id=?"; $params[] = $project_id; }
        if (!is_manager()) { $query .= " AND (t.assigned_to=? OR t.created_by=?)"; $params[] = $uid; $params[] = $uid; }
        $query .= " ORDER BY t.priority DESC, t.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;
}

function updateProjectProgress(int $project_id, PDO $db): void {
    $total = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id=?")->execute([$project_id]) ? 
             (int)$db->query("SELECT COUNT(*) FROM tasks WHERE project_id=$project_id")->fetchColumn() : 0;
    $done  = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE project_id=$project_id AND status='done'")->fetchColumn();
    $progress = $total > 0 ? round(($done / $total) * 100) : 0;
    $db->prepare("UPDATE projects SET progress=? WHERE id=?")->execute([$progress, $project_id]);
}
