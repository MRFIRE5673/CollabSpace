<?php
// ============================================================
// Database Configuration Â Environment-aware Multi-Tenant
// Primary Database: PostgreSQL (pdo_pgsql - Supabase, Neon, Railway, Render, Local Postgres)
// Auto-detects Supabase, Neon, Railway, Render & Local Dev
// ============================================================

function sanitize_env(?string $val): ?string {
    if (!$val) return null;
    $trimmed = trim($val);
    if (str_contains($trimmed, "(") || str_contains($trimmed, "copy from") || str_contains($trimmed, "your_")) {
        return null;
    }
    return $trimmed;
}

// Environment connection auto-detection (PostgreSQL)
$db_driver = strtolower(sanitize_env(getenv("DB_DRIVER")) ?: "pgsql");
$pg_url    = sanitize_env(getenv("DATABASE_URL"))
          ?: sanitize_env(getenv("POSTGRES_URL"))
          ?: sanitize_env(getenv("SUPABASE_DB_URL"))
          ?: sanitize_env(getenv("SUPABASE_DATABASE_URL"));

if ($pg_url) {
    if (preg_match('~^postgres(?:ql)?://(?:([^:]+)(?::([^@]*))?@)?([^:/]+)(?::(\d+))?(?:/([^?#]*))?~i', $pg_url, $m)) {
        $env_user = !empty($m[1]) ? $m[1] : "postgres";
        $env_pass = isset($m[2]) ? urldecode($m[2]) : "";
        $env_host = !empty($m[3]) ? $m[3] : "localhost";
        $env_port = !empty($m[4]) ? $m[4] : "5432";
        $env_name = !empty($m[5]) ? $m[5] : "postgres";
    } else {
        $db_parts = parse_url($pg_url);
        $env_host = $db_parts["host"] ?? "localhost";
        $env_port = (string)($db_parts["port"] ?? 5432);
        $env_user = $db_parts["user"] ?? "postgres";
        $env_pass = urldecode($db_parts["pass"] ?? "");
        $env_name = ltrim($db_parts["path"] ?? "postgres", "/");
    }
} else {
    $env_host = sanitize_env(getenv("DB_HOST")) ?: (sanitize_env(getenv("SUPABASE_DB_HOST")) ?: "localhost");
    $env_port = sanitize_env(getenv("DB_PORT")) ?: "5432";
    $env_name = sanitize_env(getenv("DB_NAME")) ?: "postgres";
    $env_user = sanitize_env(getenv("DB_USER")) ?: "postgres";
    $env_pass = sanitize_env(getenv("DB_PASS")) ?: "postgres";
}

// Auto-detect and route direct Supabase IPv6 hosts (db.xxx.supabase.co) to IPv4 Pooler
$supabase_ref = null;
if (preg_match('/^db\.([a-z0-9]+)\.supabase\.co$/i', $env_host, $sm)) {
    $supabase_ref = $sm[1];
    $env_host = "aws-0-ap-northeast-2.pooler.supabase.com";
    if (!str_contains($env_user, ".")) {
        $env_user = "postgres." . $supabase_ref;
    }
}

define("DB_DRIVER",  $db_driver);
define("DB_HOST",    $env_host);
define("DB_PORT",    $env_port);
define("DB_NAME",    $env_name);
define("DB_USER",    $env_user);
define("DB_PASS",    $env_pass);
define("DB_CHARSET", "utf8");

define("APP_URL",        getenv("APP_URL")       ?: "http://localhost");
define("APP_ENV",        getenv("APP_ENV")       ?: "local");
define("SESSION_SECRET", getenv("SESSION_SECRET")?: "collabspace-dev-secret-123");

define("PRIVATE_STORAGE_DIR", __DIR__ . "/../private_storage/");
define("MAX_UPLOAD_SIZE",     20 * 1024 * 1024);
define("ALLOWED_EXTENSIONS",  ["jpg","jpeg","png","gif","pdf","doc","docx","xls","xlsx","ppt","pptx","txt","zip","rar","mp4","mp3"]);

define("UPLOAD_DIR", PRIVATE_STORAGE_DIR);

if (!is_dir(PRIVATE_STORAGE_DIR)) {
    @mkdir(PRIVATE_STORAGE_DIR, 0750, true);
    @file_put_contents(PRIVATE_STORAGE_DIR . ".htaccess", "Deny from all\n");
}

$pdo = null;

/**
 * PostgreSQL PDO wrapper that provides seamless lastInsertId() support via lastval()
 */
class CollabSpacePDO extends PDO {
    public function lastInsertId(?string $name = null): string|false {
        if ($name !== null && $name !== '') {
            return parent::lastInsertId($name);
        }
        try {
            $stmt = $this->query("SELECT lastval()");
            $val = $stmt ? $stmt->fetchColumn() : false;
            return ($val !== false && $val !== null) ? (string)$val : parent::lastInsertId();
        } catch (Throwable $t) {
            try {
                return parent::lastInsertId();
            } catch (Throwable $t2) {
                return false;
            }
        }
    }
}

function render_db_exception(string $error_msg): void {
    if (php_sapi_name() === "cli") {
        throw new PDOException($error_msg);
    }
    http_response_code(503);
    $db_error_message = $error_msg;
    include __DIR__ . "/db_error.php";
    exit;
}

function getDB(): PDO {
    global $pdo;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === "sqlite") {
        $db_file = __DIR__ . "/../database.sqlite";
        $dsn = "sqlite:" . $db_file;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        $pdo = new CollabSpacePDO($dsn, null, null, $options);
        return $pdo;
    }

    $ssl_env = sanitize_env(getenv("DB_SSLMODE"));
    if ($ssl_env) {
        $sslmode = ";sslmode=" . $ssl_env;
    } else {
        $sslmode = (DB_HOST !== "localhost" && DB_HOST !== "127.0.0.1" && DB_HOST !== "db") ? ";sslmode=require" : "";
    }
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s%s", DB_HOST, DB_PORT, DB_NAME, $sslmode);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_TIMEOUT            => 10,
    ];

    try {
        $pdo = new CollabSpacePDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // If Supabase direct/pooler connection failed, try other AWS regional poolers
        global $supabase_ref;
        if (!empty($supabase_ref)) {
            $candidate_regions = ['ap-northeast-2', 'ap-south-1', 'us-east-1', 'eu-central-1', 'ap-southeast-1', 'us-west-1', 'eu-west-1'];
            foreach ($candidate_regions as $reg) {
                $pool_host = "aws-0-{$reg}.pooler.supabase.com";
                if ($pool_host === DB_HOST) continue;
                $alt_dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;sslmode=require", $pool_host, DB_PORT, DB_NAME);
                try {
                    $pdo = new CollabSpacePDO($alt_dsn, DB_USER, DB_PASS, $options);
                    break;
                } catch (PDOException $pe) {
                    continue;
                }
            }
        }
        if (!$pdo) {
            if (APP_ENV === "local") {
                try {
                    $db_file = __DIR__ . "/../database.sqlite";
                    $dsn_sqlite = "sqlite:" . $db_file;
                    $pdo = new CollabSpacePDO($dsn_sqlite, null, null, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    return $pdo;
                } catch (PDOException $e_sqlite) {}
            }
            render_db_exception("Database connection failed: " . $e->getMessage());
        }
    }

    // Auto-setup database schema and initial seed data if not yet created
    static $schema_checked = false;
    if (!$schema_checked) {
        $schema_checked = true;
        try {
            $pdo->query("SELECT 1 FROM users LIMIT 1");
        } catch (Throwable $t) {
            require_once __DIR__ . '/setup.php';
            if (function_exists('setupDatabase')) {
                ob_start();
                try {
                    setupDatabase();
                } catch (Throwable $setupError) {
                    error_log("CollabSpace DB Setup: " . $setupError->getMessage());
                }
                ob_end_clean();
            }
        }
    }

    return $pdo;
}

