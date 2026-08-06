<?php
// ============================================================
// Database Setup & Seed Script
// Run: php config/setup.php
// Or visit: http://localhost/your-path/config/setup.php
// ============================================================
require_once __DIR__ . '/database.php';

function setupDatabase(): void {
    $db = getDB();

    $tables = [];

    // Users Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin','manager','member','viewer') NOT NULL DEFAULT 'member',
        `avatar` VARCHAR(255) DEFAULT NULL,
        `bio` TEXT DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `status` ENUM('online','offline','away') DEFAULT 'offline',
        `last_seen` DATETIME DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Workspaces Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `workspaces` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `color` VARCHAR(20) DEFAULT '#4f46e5',
        `created_by` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Workspace Members
    $tables[] = "CREATE TABLE IF NOT EXISTS `workspace_members` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `workspace_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `role` ENUM('owner','admin','member') DEFAULT 'member',
        `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_member` (`workspace_id`, `user_id`),
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Projects Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `projects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `workspace_id` INT DEFAULT NULL,
        `name` VARCHAR(200) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `status` ENUM('planning','active','on_hold','completed','cancelled') DEFAULT 'planning',
        `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
        `start_date` DATE DEFAULT NULL,
        `due_date` DATE DEFAULT NULL,
        `progress` TINYINT DEFAULT 0,
        `manager_id` INT NOT NULL,
        `created_by` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Project Members
    $tables[] = "CREATE TABLE IF NOT EXISTS `project_members` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_proj_member` (`project_id`, `user_id`),
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Tasks Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `assigned_to` INT DEFAULT NULL,
        `created_by` INT NOT NULL,
        `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
        `status` ENUM('todo','in_progress','in_review','done') DEFAULT 'todo',
        `due_date` DATE DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `position` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Chat Messages Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `chats` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `room_type` ENUM('project','direct','group') DEFAULT 'project',
        `sender_id` INT NOT NULL,
        `receiver_id` INT DEFAULT NULL,
        `message` TEXT DEFAULT NULL,
        `file_path` VARCHAR(500) DEFAULT NULL,
        `file_name` VARCHAR(255) DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Files Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `uploaded_by` INT NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_size` BIGINT DEFAULT 0,
        `file_type` VARCHAR(100) DEFAULT NULL,
        `mime_type` VARCHAR(100) DEFAULT NULL,
        `downloads` INT DEFAULT 0,
        `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Notifications Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT DEFAULT NULL,
        `type` ENUM('task','project','chat','file','system') DEFAULT 'system',
        `link` VARCHAR(500) DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Activity Logs Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `user_id` INT NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `entity_type` VARCHAR(50) DEFAULT NULL,
        `entity_id` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // User Contacts / Friends Table
    $tables[] = "CREATE TABLE IF NOT EXISTS `user_contacts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `contact_id` INT NOT NULL,
        `status` ENUM('accepted','pending') DEFAULT 'accepted',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_contact` (`user_id`, `contact_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`contact_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    foreach ($tables as $sql) {
        $db->exec($sql);
    }

    echo "✅ Tables created successfully.\n";
    seedData($db);
}

function seedData(PDO $db): void {
    // Check if already seeded
    $existing = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($existing > 0) {
        return;
    }

    // Seed default Admin user
    $users = [
        ['Admin User', 'admin@workspace.com', password_hash('password123', PASSWORD_DEFAULT), 'admin']
    ];
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
    foreach ($users as $u) $stmt->execute($u);
    echo "✅ Clean database initialized.\n";
}

// Standalone execution
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'setup.php') {
    try {
        setupDatabase();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
