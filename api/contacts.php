<?php
// ============================================================
// Multi-Tenant Contacts API Endpoint
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
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

switch ($action) {
    case 'search':
        $query = trim($_GET['q'] ?? '');
        if (!$query) { echo json_encode([]); exit; }

        $stmt = $db->prepare("
            SELECT id, name, email, avatar, status
            FROM users
            WHERE company_id = ? AND id != ? AND is_active = 1 AND (name LIKE ? OR email LIKE ?)
            ORDER BY name ASC LIMIT 10
        ");
        $stmt->execute([$cid, $uid, "%$query%", "%$query%"]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'add':
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $query      = trim($_POST['query'] ?? ($_GET['query'] ?? ''));

        if (!$contact_id && $query) {
            $stmt = $db->prepare("SELECT id, name, email, avatar, status FROM users WHERE company_id = ? AND id != ? AND is_active = 1 AND (email = ? OR name = ? OR name LIKE ?) LIMIT 1");
            $stmt->execute([$cid, $uid, $query, $query, "%$query%"]);
            $found = $stmt->fetch();
            if ($found) {
                $contact_id = $found['id'];
            }
        }

        if (!$contact_id || $contact_id == $uid) {
            echo json_encode(['success' => false, 'message' => 'User not found in your company.']);
            exit;
        }

        try {
            $db->prepare("INSERT INTO user_contacts (company_id, user_id, contact_id, status) VALUES (?,?,?,'accepted'), (?,?,?,'accepted')")
               ->execute([$cid, $uid, $contact_id, $cid, $contact_id, $uid]);
        } catch (Exception $e) { }

        $stmt = $db->prepare("SELECT id, name, email, avatar, status FROM users WHERE id = ? AND company_id = ?");
        $stmt->execute([$contact_id, $cid]);
        $friend = $stmt->fetch();

        echo json_encode(['success' => true, 'contact' => $friend]);
        break;

    case 'list':
    default:
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.avatar, u.status
            FROM user_contacts uc
            JOIN users u ON u.id = uc.contact_id
            WHERE uc.company_id = ? AND uc.user_id = ? AND u.is_active = 1
            ORDER BY u.status DESC, u.name ASC
        ");
        $stmt->execute([$cid, $uid]);
        echo json_encode($stmt->fetchAll());
        break;
}
