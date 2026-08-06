<?php
// ============================================================
// Database Configuration — Environment-aware
// Supports: local dev, Railway, PlanetScale, Cloud SQL, etc.
// ============================================================

// ── Read from environment variables (set on your host) ──────
// Falls back to local dev defaults if not set
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'collab_workspace');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '5673');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// ── App config ───────────────────────────────────────────────
define('APP_URL',       getenv('APP_URL')    ?: 'http://localhost');
define('APP_ENV',       getenv('APP_ENV')    ?: 'local');          // local | production
define('SESSION_SECRET',getenv('SESSION_SECRET') ?: 'changeme-dev-secret-123');

// ── Uploads (works locally and on Railway/Cloud Run volumes) ─
define('UPLOAD_DIR',         __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE',    20 * 1024 * 1024);  // 20 MB
define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','mp4','mp3']);

// ── PDO singleton ─────────────────────────────────────────────
$pdo = null;

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
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Attempt to create DB if it doesn't exist (local dev only)
        if (APP_ENV === 'local') {
            try {
                $dsn_no_db = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                $tmp = new PDO($dsn_no_db, DB_USER, DB_PASS, $options);
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e2) {
                die(json_encode(['error' => 'DB connection failed: ' . $e2->getMessage()]));
            }
        } else {
            // In production, show a friendly error
            http_response_code(503);
            die('<h2 style="font-family:sans-serif;text-align:center;padding:2rem;">Service temporarily unavailable. Please try again later.</h2>');
        }
    }

    return $pdo;
}

// ── Ensure uploads directory exists (local) ──────────────────
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
    @file_put_contents(UPLOAD_DIR . '.htaccess', "Options -Indexes\nAddType application/octet-stream .php .php3 .phtml\n");
}
