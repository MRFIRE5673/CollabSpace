<?php
// ============================================================
// Database Configuration — Environment-aware
// Supports: Railway, PlanetScale, Cloud Run, Docker & Local Dev
// ============================================================

// Helper to filter out copy-paste instructions or placeholder strings
function sanitize_env(?string $val): ?string {
    if (!$val) return null;
    $trimmed = trim($val);
    if (str_contains($trimmed, '(') || str_contains($trimmed, 'copy from') || str_contains($trimmed, 'your_')) {
        return null; // Invalid placeholder value
    }
    return $trimmed;
}

// Support Railway MYSQL_URL format if available
$mysql_url = sanitize_env(getenv('MYSQL_URL')) ?: sanitize_env(getenv('MYSQLURL'));

if ($mysql_url) {
    $db_parts = parse_url($mysql_url);
    $env_host = $db_parts['host'] ?? 'localhost';
    $env_port = (string)($db_parts['port'] ?? 3306);
    $env_user = $db_parts['user'] ?? 'root';
    $env_pass = $db_parts['pass'] ?? '';
    $env_name = ltrim($db_parts['path'] ?? 'collab_workspace', '/');
} else {
    $env_host = sanitize_env(getenv('DB_HOST')) ?: (sanitize_env(getenv('MYSQLHOST')) ?: 'localhost');
    $env_port = sanitize_env(getenv('DB_PORT')) ?: (sanitize_env(getenv('MYSQLPORT')) ?: '3306');
    $env_name = sanitize_env(getenv('DB_NAME')) ?: (sanitize_env(getenv('MYSQLDATABASE')) ?: 'collab_workspace');
    $env_user = sanitize_env(getenv('DB_USER')) ?: (sanitize_env(getenv('MYSQLUSER')) ?: 'root');
    $env_pass = sanitize_env(getenv('DB_PASS')) ?: (sanitize_env(getenv('MYSQLPASSWORD')) ?: '5673');
}

define('DB_HOST',    $env_host);
define('DB_PORT',    $env_port);
define('DB_NAME',    $env_name);
define('DB_USER',    $env_user);
define('DB_PASS',    $env_pass);
define('DB_CHARSET', 'utf8mb4');

// ── App config ───────────────────────────────────────────────
define('APP_URL',        getenv('APP_URL')       ?: 'http://localhost');
define('APP_ENV',        getenv('APP_ENV')       ?: 'local');
define('SESSION_SECRET', getenv('SESSION_SECRET')?: 'collabspace-dev-secret-123');

// ── Uploads ──────────────────────────────────────────────────
define('UPLOAD_DIR',         __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE',    20 * 1024 * 1024);  // 20 MB
define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','mp4','mp3']);

// ── PDO singleton ─────────────────────────────────────────────
$pdo = null;

function render_db_exception(string $error_msg): void {
    if (php_sapi_name() === 'cli') {
        throw new PDOException($error_msg);
    }
    http_response_code(503);
    $db_error_message = $error_msg;
    include __DIR__ . '/db_error.php';
    exit;
}

function ensure_tables_exist(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$check) {
            require_once __DIR__ . '/setup.php';
            if (function_exists('setupDatabase')) {
                setupDatabase();
            }
        }
        // Ensure admin@admin.com account exists with password 12345678
        $admin_exists = $pdo->query("SELECT id FROM users WHERE email='admin@admin.com'")->fetch();
        if (!$admin_exists) {
            $pass = password_hash('12345678', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')")
                ->execute(['Admin User', 'admin@admin.com', $pass]);
        }
    } catch (Exception $e) {
        // Ignore check errors
    }
}

function getDB(): PDO {
    global $pdo;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        ensure_tables_exist($pdo);
    } catch (PDOException $e) {
        // Attempt to create DB if it doesn't exist (local dev only)
        if (APP_ENV === 'local') {
            try {
                $dsn_no_db = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                $tmp = new PDO($dsn_no_db, DB_USER, DB_PASS, $options);
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                ensure_tables_exist($pdo);
                return $pdo;
            } catch (PDOException $e2) {
                render_db_exception("Database connection failed: " . $e2->getMessage());
            }
        }
        render_db_exception("Database connection failed: " . $e->getMessage());
    }

    return $pdo;
}

// ── Ensure uploads directory exists ──────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
    @file_put_contents(UPLOAD_DIR . '.htaccess', "Options -Indexes\nAddType application/octet-stream .php .php3 .phtml\n");
}
