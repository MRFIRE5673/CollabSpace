<?php
// ============================================================
// Database Configuration — Environment-aware Multi-Tenant
// Supports: PostgreSQL (pdo_pgsql) & MySQL (pdo_mysql)
// Works on Railway, PlanetScale, Cloud Run, Docker & Local Dev
// ============================================================

function sanitize_env(?string $val): ?string {
    if (!$val) return null;
    $trimmed = trim($val);
    if (str_contains($trimmed, '(') || str_contains($trimmed, 'copy from') || str_contains($trimmed, 'your_')) {
        return null;
    }
    return $trimmed;
}

// Environment connection auto-detection
$db_driver = strtolower(sanitize_env(getenv('DB_DRIVER')) ?: 'mysql');
$pg_url = sanitize_env(getenv('DATABASE_URL')) ?: sanitize_env(getenv('POSTGRES_URL'));
$mysql_url = sanitize_env(getenv('MYSQL_URL')) ?: sanitize_env(getenv('MYSQLURL'));

if ($pg_url || $db_driver === 'pgsql') {
    $db_driver = 'pgsql';
    if ($pg_url) {
        $db_parts = parse_url($pg_url);
        $env_host = $db_parts['host'] ?? 'localhost';
        $env_port = (string)($db_parts['port'] ?? 5432);
        $env_user = $db_parts['user'] ?? 'postgres';
        $env_pass = urldecode($db_parts['pass'] ?? '');
        $env_name = ltrim($db_parts['path'] ?? 'collab_workspace', '/');
    } else {
        $env_host = sanitize_env(getenv('DB_HOST')) ?: 'localhost';
        $env_port = sanitize_env(getenv('DB_PORT')) ?: '5432';
        $env_name = sanitize_env(getenv('DB_NAME')) ?: 'collab_workspace';
        $env_user = sanitize_env(getenv('DB_USER')) ?: 'postgres';
        $env_pass = sanitize_env(getenv('DB_PASS')) ?: 'postgres';
    }
} elseif ($mysql_url) {
    $db_driver = 'mysql';
    $db_parts = parse_url($mysql_url);
    $env_host = $db_parts['host'] ?? 'localhost';
    $env_port = (string)($db_parts['port'] ?? 3306);
    $env_user = $db_parts['user'] ?? 'root';
    $env_pass = urldecode($db_parts['pass'] ?? '');
    $env_name = ltrim($db_parts['path'] ?? 'collab_workspace', '/');
} else {
    $db_driver = 'mysql';
    $env_host = sanitize_env(getenv('DB_HOST')) ?: (sanitize_env(getenv('MYSQLHOST')) ?: 'localhost');
    $env_port = sanitize_env(getenv('DB_PORT')) ?: (sanitize_env(getenv('MYSQLPORT')) ?: '3306');
    $env_name = sanitize_env(getenv('DB_NAME')) ?: (sanitize_env(getenv('MYSQLDATABASE')) ?: 'collab_workspace');
    $env_user = sanitize_env(getenv('DB_USER')) ?: (sanitize_env(getenv('MYSQLUSER')) ?: 'root');
    $env_pass = sanitize_env(getenv('DB_PASS')) ?: (sanitize_env(getenv('MYSQLPASSWORD')) ?: '5673');
}

define('DB_DRIVER',  $db_driver);
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

// ── File Storage Paths (Private, outside web root) ────────────
define('PRIVATE_STORAGE_DIR', __DIR__ . '/../private_storage/');
define('MAX_UPLOAD_SIZE',     20 * 1024 * 1024);  // 20 MB
define('ALLOWED_EXTENSIONS',  ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','mp4','mp3']);

// Legacy UPLOAD_DIR point to private storage for safety
define('UPLOAD_DIR', PRIVATE_STORAGE_DIR);

// Ensure private storage directory exists
if (!is_dir(PRIVATE_STORAGE_DIR)) {
    @mkdir(PRIVATE_STORAGE_DIR, 0750, true);
    @file_put_contents(PRIVATE_STORAGE_DIR . '.htaccess', "Deny from all\n");
}

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

function getDB(): PDO {
    global $pdo;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'sqlite') {
        $db_file = __DIR__ . '/../database.sqlite';
        $dsn = 'sqlite:' . $db_file;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        $pdo = new PDO($dsn, null, null, $options);
        return $pdo;
    }

    if (DB_DRIVER === 'pgsql') {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (APP_ENV === 'local') {
            // SQLite fallback for standalone local dev environment if MySQL/PgSQL server is offline
            try {
                $db_file = __DIR__ . '/../database.sqlite';
                $dsn_sqlite = 'sqlite:' . $db_file;
                $pdo = new PDO($dsn_sqlite, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                return $pdo;
            } catch (PDOException $e_sqlite) {
                render_db_exception("Database connection failed: " . $e->getMessage());
            }
        }
        render_db_exception("Database connection failed: " . $e->getMessage());
    }

    return $pdo;
}

