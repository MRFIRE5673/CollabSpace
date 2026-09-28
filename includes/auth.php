<?php
// ============================================================
// Multi-Tenant Auth & Access Control (RBAC) Engine
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) ob_start();
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// ─── Redirect helpers ─────────────────────────────────────
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// ─── Auth Guards & User Context ───────────────────────────
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
        'id'             => (int)$_SESSION['user_id'],
        'company_id'     => (int)($_SESSION['company_id'] ?? 1),
        'name'           => $_SESSION['user_name'] ?? 'User',
        'email'          => $_SESSION['user_email'] ?? '',
        'role'           => $_SESSION['user_role'] ?? 'member',
        'is_super_admin' => (int)($_SESSION['is_super_admin'] ?? 0),
        'avatar'         => $_SESSION['user_avatar'] ?? null,
    ];
}

function is_super_admin(): bool {
    return !empty($_SESSION['is_super_admin']);
}

function active_company_id(): int {
    $u = current_user();
    return $u ? $u['company_id'] : 1;
}

function get_active_company_name(): string {
    $cid = active_company_id();
    if (!empty($_SESSION['company_name'])) {
        return $_SESSION['company_name'];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT name FROM companies WHERE id=?");
        $stmt->execute([$cid]);
        $name = $stmt->fetchColumn();
        if ($name) {
            $_SESSION['company_name'] = $name;
            return $name;
        }
    } catch (\Throwable $e) {}
    return 'Acme Global Corp';
}

// Role checks
function has_role(string ...$roles): bool {
    if (is_super_admin()) return true;
    $userRole = $_SESSION['user_role'] ?? 'member';
    return in_array($userRole, $roles, true);
}

function is_admin(): bool    { return is_super_admin() || has_role('company_admin'); }
function is_manager(): bool  { return is_super_admin() || has_role('company_admin', 'manager', 'project_manager'); }
function is_member(): bool   { return is_super_admin() || has_role('company_admin', 'manager', 'project_manager', 'team_lead', 'member'); }
function is_viewer(): bool   { return is_super_admin() || has_role('company_admin', 'manager', 'project_manager', 'team_lead', 'member', 'viewer'); }

// Central Authorization Guard
function authorize(?array $user, int $target_company_id, ?string $permission = null): bool {
    if (!$user) return false;
    // Super admin has global cross-company clearance
    if (!empty($user['is_super_admin'])) return true;
    // Enforce Tenant Boundary: User's company must match target company
    if ((int)$user['company_id'] !== (int)$target_company_id) return false;
    
    // Role-permission verification
    if ($permission !== null) {
        if ($user['role'] === 'company_admin') return true;
        // Granular permissions check map
        $role_permissions = [
            'manager'         => ['project.view','project.manage','task.view','task.create','task.edit','task.assign','chat.view','chat.send','files.view','files.preview','files.upload','calendar.view','calendar.manage'],
            'project_manager' => ['project.view','project.manage','task.view','task.create','task.edit','task.assign','chat.view','chat.send','files.view','files.preview','files.upload','calendar.view'],
            'team_lead'       => ['project.view','task.view','task.create','task.edit','task.assign','chat.view','chat.send','files.view','files.preview','files.upload','calendar.view'],
            'member'          => ['project.view','task.view','task.create','task.edit','chat.view','chat.send','files.view','files.preview','files.upload','calendar.view'],
            'viewer'          => ['project.view','task.view','chat.view','files.view','files.preview','calendar.view'],
        ];
        $allowed = $role_permissions[$user['role']] ?? [];
        return in_array($permission, $allowed, true);
    }
    return true;
}

// Role-Based Redirect Helper
function get_role_redirect(string $role, bool $is_super_admin = false): string {
    if ($is_super_admin) return 'superadmin_dashboard.php';
    return match($role) {
        'company_admin' => 'dashboard.php',
        'manager', 'project_manager' => 'manager_dashboard.php',
        'member', 'team_lead' => 'member_dashboard.php',
        'viewer' => 'viewer_dashboard.php',
        default => 'dashboard.php',
    };
}

// ─── Login Approval & Authentication Pipeline ─────────────
function attempt_login(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    $cid = (int)($user['company_id'] ?? 1);
    $is_super = (int)($user['is_super_admin'] ?? 0);

    // SUPER ADMIN EXCEPTION: Super Admin authenticates immediately without company approval
    if ($is_super) {
        $db->prepare("UPDATE users SET status='online', last_seen=NOW() WHERE id=?")->execute([$user['id']]);
        $_SESSION['user_id']        = $user['id'];
        $_SESSION['company_id']    = $cid;
        $_SESSION['user_name']      = $user['name'];
        $_SESSION['user_email']     = $user['email'];
        $_SESSION['user_role']      = $user['role'];
        $_SESSION['is_super_admin'] = 1;
        $_SESSION['user_avatar']    = $user['avatar'];

        log_activity($cid, null, $user['id'], 'super_admin_login', 'Super Admin logged in');
        return ['success' => true, 'role' => $user['role'], 'is_super_admin' => 1, 'redirect' => 'superadmin_dashboard.php'];
    }

    // NORMAL USER: Requires Company Admin Login Approval
    $app_stmt = $db->prepare("SELECT * FROM login_approval_requests WHERE user_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1");
    $app_stmt->execute([$user['id'], $cid]);
    $approval = $app_stmt->fetch();

    if (!$approval) {
        // Create pending login request
        $db->prepare("INSERT INTO login_approval_requests (user_id, company_id, status) VALUES (?,?, 'pending')")
           ->execute([$user['id'], $cid]);
        return [
            'success' => false,
            'status'  => 'pending',
            'message' => 'Your login request is waiting for approval from your company administrator.'
        ];
    }

    if ($approval['status'] === 'pending') {
        return [
            'success' => false,
            'status'  => 'pending',
            'message' => 'Your login request is waiting for approval from your company administrator.'
        ];
    }

    if ($approval['status'] === 'rejected') {
        return [
            'success' => false,
            'status'  => 'rejected',
            'message' => 'Your login request was rejected by your company administrator.'
        ];
    }

    // Approved -> Establish session
    $db->prepare("UPDATE users SET status='online', last_seen=NOW() WHERE id=?")->execute([$user['id']]);

    $_SESSION['user_id']        = $user['id'];
    $_SESSION['company_id']    = $cid;
    $_SESSION['user_name']      = $user['name'];
    $_SESSION['user_email']     = $user['email'];
    $_SESSION['user_role']      = $user['role'];
    $_SESSION['is_super_admin'] = 0;
    $_SESSION['user_avatar']    = $user['avatar'];

    log_activity($cid, null, $user['id'], 'user_login', 'User logged in');
    return ['success' => true, 'role' => $user['role'], 'is_super_admin' => 0, 'redirect' => get_role_redirect($user['role'], false)];
}

function attempt_register(string $name, string $email, string $password, string $role = 'member', int $company_id = 1): array {
    $db = getDB();
    $exists = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $exists->execute([trim($email)]);
    if ($exists->fetch()) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    // Only Super Admin can assign company_admin
    if ($role === 'company_admin' && !is_super_admin()) {
        $role = 'member';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (company_id, name, email, password, role, is_super_admin, is_active) VALUES (?,?,?,?,?, 0, 1)");
    $stmt->execute([$company_id, trim($name), trim($email), $hash, $role]);
    $uid = $db->lastInsertId();

    $db->prepare("INSERT INTO company_members (company_id, user_id, role) VALUES (?,?,?)")->execute([$company_id, $uid, $role]);

    // Create pending login approval request for new user
    $db->prepare("INSERT INTO login_approval_requests (user_id, company_id, status) VALUES (?,?, 'pending')")
       ->execute([$uid, $company_id]);

    log_activity($company_id, null, $uid, 'user_registered', "New registration pending approval: $name");
    return ['success' => true, 'id' => $uid, 'message' => 'Registration successful! Your account is pending approval from your company administrator.'];
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
function log_activity(int $company_id, ?int $project_id, int $user_id, string $action, string $description, ?string $entity_type = null, ?int $entity_id = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (company_id, project_id, user_id, action, description, entity_type, entity_id) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$company_id, $project_id, $user_id, $action, $description, $entity_type, $entity_id]);
    } catch (Exception $e) { }
}

// ─── Notification Helpers ────────────────────────────────
function send_notification(int $company_id, int $user_id, string $title, string $message, string $type = 'system', string $link = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (company_id, user_id, title, message, type, link) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$company_id, $user_id, $title, $message, $type, $link]);
    } catch (Exception $e) { }
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
        return '<img src="api/files.php?action=preview&file=' . urlencode($user['avatar']) . '" alt="' . htmlspecialchars($user['name']) . '" class="rounded-circle" style="width:' . $size . ';height:' . $size . ';object-fit:cover;">';
    }
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($user['name']))));
    $initials = substr($initials, 0, 2);
    $colors = ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626'];
    $color = $colors[crc32($user['name']) % count($colors)];
    $fs = (int)$size * 0.4 . 'px';
    return "<div class=\"rounded-circle d-flex align-items-center justify-content-center text-white fw-bold\" style=\"width:{$size};height:{$size};background:{$color};font-size:{$fs};flex-shrink:0;\">{$initials}</div>";
}

// ─── Priority & Status Badges ─────────────────────────────
function priority_badge(string $priority): string {
    $map = ['low'=>'success','medium'=>'info','high'=>'warning','critical'=>'danger'];
    $cls = $map[$priority] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . ucfirst($priority) . "</span>";
}

function status_badge(string $status): string {
    $labels = ['todo'=>'To Do','in_progress'=>'In Progress','in_review'=>'In Review','done'=>'Done',
               'planning'=>'Planning','active'=>'Active','on_hold'=>'On Hold','completed'=>'Completed','cancelled'=>'Cancelled'];
    $classes = ['todo'=>'secondary','in_progress'=>'primary','in_review'=>'warning','done'=>'success',
                'planning'=>'info','active'=>'primary','on_hold'=>'warning','completed'=>'success','cancelled'=>'danger'];
    $label = $labels[$status] ?? ucfirst($status);
    $cls = $classes[$status] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">{$label}</span>";
}

function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff/60) . 'm ago';
    if ($diff < 86400)      return floor($diff/3600) . 'h ago';
    if ($diff < 2592000)    return floor($diff/86400) . 'd ago';
    return date('M j, Y', $time);
}
