<?php
// ============================================================
// Auth & Role-Based Access Control (RBAC) Helper
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// ─── Redirect helpers ─────────────────────────────────────
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// ─── Auth Guards ──────────────────────────────────────────
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'name'   => $_SESSION['user_name'] ?? 'User',
        'email'  => $_SESSION['user_email'] ?? '',
        'role'   => $_SESSION['user_role'] ?? 'member',
        'avatar' => $_SESSION['user_avatar'] ?? null,
    ];
}

function has_role(string ...$roles): bool {
    $userRole = $_SESSION['user_role'] ?? 'member';
    return in_array($userRole, $roles, true);
}

function is_admin(): bool    { return has_role('admin'); }
function is_manager(): bool  { return has_role('admin', 'manager'); }
function is_member(): bool   { return has_role('admin', 'manager', 'member'); }

// ─── Login / Logout ───────────────────────────────────────
function attempt_login(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    // Update status and last seen
    $db->prepare("UPDATE users SET status='online', last_seen=NOW() WHERE id=?")->execute([$user['id']]);

    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];

    return ['success' => true, 'role' => $user['role']];
}

function attempt_register(string $name, string $email, string $password, string $role = 'member'): array {
    $db = getDB();
    $exists = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $exists->execute([$email]);
    if ($exists->fetch()) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    // Only admin can create admin/manager accounts
    $allowed = ['member'];
    if (is_admin()) $allowed = ['admin', 'manager', 'member'];
    if (!in_array($role, $allowed)) $role = 'member';

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
    $stmt->execute([$name, $email, $hash, $role]);
    $uid = $db->lastInsertId();

    log_activity(null, $uid, 'user_registered', "New user registered: $name", 'user');
    return ['success' => true, 'id' => $uid];
}

function do_logout(): void {
    if (!empty($_SESSION['user_id'])) {
        $db = getDB();
        $db->prepare("UPDATE users SET status='offline', last_seen=NOW() WHERE id=?")->execute([$_SESSION['user_id']]);
    }
    session_destroy();
    redirect('login.php');
}

// ─── Activity Logging ────────────────────────────────────
function log_activity(?int $project_id, int $user_id, string $action, string $description, ?string $entity_type = null, ?int $entity_id = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (project_id, user_id, action, description, entity_type, entity_id) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$project_id, $user_id, $action, $description, $entity_type, $entity_id]);
    } catch (Exception $e) {
        // silent fail
    }
}

// ─── Notification Helpers ────────────────────────────────
function send_notification(int $user_id, string $title, string $message, string $type = 'system', string $link = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)");
        $stmt->execute([$user_id, $title, $message, $type, $link]);
    } catch (Exception $e) { }
}

function get_unread_notification_count(int $user_id): int {
    $db = getDB();
    return (int)$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$user_id]) ? 
           $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$user_id]) ? 0 : 0 : 0;
}

function count_unread_notifications(int $user_id): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

// ─── Avatar Helper ───────────────────────────────────────
function get_avatar_html(array $user, string $size = '32px'): string {
    if (!empty($user['avatar'])) {
        return '<img src="uploads/' . htmlspecialchars($user['avatar']) . '" alt="' . htmlspecialchars($user['name']) . '" class="rounded-circle" style="width:' . $size . ';height:' . $size . ';object-fit:cover;">';
    }
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($user['name']))));
    $initials = substr($initials, 0, 2);
    $colors = ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626'];
    $color = $colors[crc32($user['name']) % count($colors)];
    $fs = (int)$size * 0.4 . 'px';
    return "<div class=\"rounded-circle d-flex align-items-center justify-content-center text-white fw-bold\" style=\"width:{$size};height:{$size};background:{$color};font-size:{$fs};flex-shrink:0;\">{$initials}</div>";
}

// ─── Priority Badge ─────────────────────────────────────
function priority_badge(string $priority): string {
    $map = ['low'=>'success','medium'=>'info','high'=>'warning','critical'=>'danger'];
    $cls = $map[$priority] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . ucfirst($priority) . "</span>";
}

// ─── Status Badge ───────────────────────────────────────
function status_badge(string $status): string {
    $labels = ['todo'=>'To Do','in_progress'=>'In Progress','in_review'=>'In Review','done'=>'Done',
               'planning'=>'Planning','active'=>'Active','on_hold'=>'On Hold','completed'=>'Completed','cancelled'=>'Cancelled'];
    $classes = ['todo'=>'secondary','in_progress'=>'primary','in_review'=>'warning','done'=>'success',
                'planning'=>'info','active'=>'primary','on_hold'=>'warning','completed'=>'success','cancelled'=>'danger'];
    $label = $labels[$status] ?? ucfirst($status);
    $cls = $classes[$status] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">{$label}</span>";
}

// ─── Time Ago ───────────────────────────────────────────
function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff/60) . 'm ago';
    if ($diff < 86400)      return floor($diff/3600) . 'h ago';
    if ($diff < 2592000)    return floor($diff/86400) . 'd ago';
    return date('M j, Y', $time);
}
