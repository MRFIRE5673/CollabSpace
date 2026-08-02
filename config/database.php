<?php
// ============================================================
// Database Configuration - Real-Time Collaboration Workspace
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'collab_workspace');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'mp4', 'mp3']);

$pdo = null;

function getDB(): PDO {
    global $pdo;
    if ($pdo !== null) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // If DB doesn't exist, try connecting without dbname to create it
        try {
            $dsn_no_db = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $tmp = new PDO($dsn_no_db, DB_USER, DB_PASS, $options);
            $tmp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e2) {
            die(json_encode(['error' => 'Database connection failed: ' . $e2->getMessage()]));
        }
    }

    return $pdo;
}

// Ensure uploads directory exists and is protected
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    file_put_contents(UPLOAD_DIR . '.htaccess', "Options -Indexes\nAddType application/octet-stream .php .php3 .phtml\n");
}
