<?php
// ============================================================
// Contacts & Friends API Endpoint (High Speed)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user   = current_user();
$uid    = $user['id'];
session_write_close();
$db     = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

switch ($action) {
    case 'search':
        $query = trim($_GET['q'] ?? '');
        if (!$query) {
            echo json_encode([]);
            exit;
        }
        $stmt = $db->prepare("
            SELECT id, name, email, avatar, status
            FROM users
            WHERE id != ? AND is_active = 1 AND (name LIKE ? OR email LIKE ?)
            ORDER BY name ASC LIMIT 10
        ");
        $stmt->execute([$uid, "%$query%", "%$query%"]);
        $users = $stmt->fetchAll();
        echo json_encode($users);
        break;

    case 'add':
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $query      = trim($_POST['query'] ?? ($_GET['query'] ?? ''));

        if (!$contact_id && $query) {
            // Search exact match or substring
            $stmt = $db->prepare("SELECT id, name, email, avatar, status FROM users WHERE id != ? AND is_active = 1 AND (email = ? OR name = ? OR name LIKE ?) LIMIT 1");
            $stmt->execute([$uid, $query, $query, "%$query%"]);
            $found = $stmt->fetch();
            if ($found) {
                $contact_id = $found['id'];
            }
        }

        if (!$contact_id || $contact_id == $uid) {
            echo json_encode(['success' => false, 'message' => 'User not found or invalid.']);
            exit;
        }

        // Add mutual contact link
        try {
            $db->prepare("INSERT IGNORE INTO user_contacts (user_id, contact_id, status) VALUES (?,?,'accepted'), (?,?,'accepted')")
               ->execute([$uid, $contact_id, $contact_id, $uid]);
        } catch (Exception $e) {
            // Ignore duplicate
        }

        // Fetch friend details
        $stmt = $db->prepare("SELECT id, name, email, avatar, status FROM users WHERE id = ?");
        $stmt->execute([$contact_id]);
        $friend = $stmt->fetch();

        echo json_encode(['success' => true, 'contact' => $friend]);
        break;

    case 'list':
    default:
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.avatar, u.status
            FROM user_contacts uc
            JOIN users u ON u.id = uc.contact_id
            WHERE uc.user_id = ? AND u.is_active = 1
            ORDER BY u.status DESC, u.name ASC
        ");
        $stmt->execute([$uid]);
        $contacts = $stmt->fetchAll();
        echo json_encode($contacts);
        break;
}
