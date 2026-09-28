<?php
// ============================================================
// Super Admin API Endpoint — Global System Governance
// ONLY Super Admin can access this API.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

if (!is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only Super Admin can perform global administrative actions.']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$user = current_user();

switch ($action) {
    case 'create_company':
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Company name is required']);
            exit;
        }
        if (!$slug) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        }
        try {
            $stmt = $db->prepare("INSERT INTO companies (name, slug, status) VALUES (?,?, 'active')");
            $stmt->execute([$name, $slug]);
            $cid = $db->lastInsertId();

            log_activity(1, null, $user['id'], 'company_created', "Super Admin created new company: $name", 'company', $cid);
            echo json_encode(['success' => true, 'message' => 'Company created successfully', 'company_id' => $cid]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to create company: ' . $e->getMessage()]);
        }
        break;

    case 'update_company_status':
        $cid = (int)($_POST['company_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'suspended', 'archived'])) $status = 'active';

        $db->prepare("UPDATE companies SET status = ? WHERE id = ?")->execute([$status, $cid]);
        log_activity($cid, null, $user['id'], 'company_status_updated', "Company status set to $status", 'company', $cid);
        echo json_encode(['success' => true, 'message' => "Company status updated to $status"]);
        break;

    case 'promote_company_admin':
        // ABSOLUTE RULE: Only Super Admin can assign Company Admins
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $cid = (int)($_POST['company_id'] ?? 0);

        if (!$target_user_id) {
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        $u_stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $u_stmt->execute([$target_user_id]);
        $target = $u_stmt->fetch();

        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $target_cid = $cid ?: (int)$target['company_id'];
        $db->prepare("UPDATE users SET role = 'company_admin', company_id = ? WHERE id = ?")->execute([$target_cid, $target_user_id]);

        // Upsert company_members record
        $cm_stmt = $db->prepare("SELECT id FROM company_members WHERE company_id = ? AND user_id = ?");
        $cm_stmt->execute([$target_cid, $target_user_id]);
        if ($cm_stmt->fetch()) {
            $db->prepare("UPDATE company_members SET role = 'company_admin' WHERE company_id = ? AND user_id = ?")->execute([$target_cid, $target_user_id]);
        } else {
            $db->prepare("INSERT INTO company_members (company_id, user_id, role) VALUES (?,?, 'company_admin')")->execute([$target_cid, $target_user_id]);
        }

        // Auto approve login if pending
        $db->prepare("UPDATE login_approval_requests SET status = 'approved', approved_by = ? WHERE user_id = ? AND company_id = ?")
           ->execute([$user['id'], $target_user_id, $target_cid]);

        log_activity($target_cid, null, $user['id'], 'company_admin_promoted', "Super Admin assigned Company Admin privileges to {$target['name']}", 'user', $target_user_id);
        echo json_encode(['success' => true, 'message' => "User {$target['name']} promoted to Company Admin successfully."]);
        break;

    case 'revoke_company_admin':
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $db->prepare("UPDATE users SET role = 'member' WHERE id = ? AND is_super_admin = 0")->execute([$target_user_id]);
        echo json_encode(['success' => true, 'message' => "Company Admin privileges revoked."]);
        break;

    case 'assign_super_admin':
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $db->prepare("UPDATE users SET is_super_admin = 1, role = 'company_admin' WHERE id = ?")->execute([$target_user_id]);
        log_activity(1, null, $user['id'], 'super_admin_assigned', "Super Admin assigned to user ID $target_user_id", 'user', $target_user_id);
        echo json_encode(['success' => true, 'message' => "Super Admin role granted."]);
        break;

    case 'toggle_user_status':
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 1);
        $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND is_super_admin = 0")->execute([$active, $target_user_id]);
        echo json_encode(['success' => true, 'message' => "User account status updated."]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
