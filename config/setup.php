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
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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
        echo "ℹ️  Database already has data, skipping seed.\n";
        return;
    }

    // Seed Users — 4 demo roles
    $users = [
        ['Admin User',        'admin@workspace.com',   password_hash('password123', PASSWORD_DEFAULT), 'admin'],
        ['Sarah Johnson',     'pm@workspace.com',      password_hash('password123', PASSWORD_DEFAULT), 'manager'],
        ['Mike Chen',         'member@workspace.com',  password_hash('password123', PASSWORD_DEFAULT), 'member'],
        ['Emily Davis',       'emily@workspace.com',   password_hash('password123', PASSWORD_DEFAULT), 'member'],
        ['James Wilson',      'james@workspace.com',   password_hash('password123', PASSWORD_DEFAULT), 'member'],
        ['Viewer Guest',      'viewer@workspace.com',  password_hash('password123', PASSWORD_DEFAULT), 'viewer'],
    ];
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
    foreach ($users as $u) $stmt->execute($u);
    echo "✅ Users seeded.\n";

    // Seed Workspace
    $db->exec("INSERT INTO workspaces (name, description, color, created_by) VALUES ('TechCorp HQ', 'Main company collaboration workspace', '#4f46e5', 1)");
    $ws_id = $db->lastInsertId();

    // Add members to workspace
    $db->exec("INSERT INTO workspace_members (workspace_id, user_id, role) VALUES ($ws_id, 1, 'owner'), ($ws_id, 2, 'admin'), ($ws_id, 3, 'member'), ($ws_id, 4, 'member'), ($ws_id, 5, 'member')");

    // Seed Projects
    $db->exec("INSERT INTO projects (workspace_id, name, description, status, priority, start_date, due_date, progress, manager_id, created_by) VALUES
        ($ws_id, 'Website Redesign', 'Complete overhaul of company website with modern UI/UX', 'active', 'high', '2026-07-01', '2026-08-31', 65, 2, 1),
        ($ws_id, 'Mobile App Development', 'iOS and Android app for customer portal', 'planning', 'critical', '2026-08-01', '2026-10-31', 15, 2, 1),
        ($ws_id, 'Database Migration', 'Migrate legacy DB to MySQL 8.x cluster', 'active', 'high', '2026-07-15', '2026-08-15', 40, 2, 1),
        ($ws_id, 'Marketing Campaign Q3', 'Digital marketing campaign for Q3 product launch', 'on_hold', 'medium', '2026-07-01', '2026-09-30', 30, 2, 1)
    ");

    // Add project members
    $db->exec("INSERT IGNORE INTO project_members (project_id, user_id) VALUES (1,1),(1,2),(1,3),(1,4),(2,1),(2,2),(2,5),(3,1),(3,2),(3,3),(4,2),(4,4),(4,5)");

    // Seed Tasks
    $db->exec("INSERT INTO tasks (project_id, title, description, assigned_to, created_by, priority, status, due_date) VALUES
        (1, 'Design new homepage mockup', 'Create Figma mockups for homepage redesign', 3, 2, 'high', 'done', '2026-07-20'),
        (1, 'Implement responsive navigation', 'Build mobile-first navbar with Bootstrap 5', 3, 2, 'high', 'done', '2026-07-25'),
        (1, 'Create component library', 'Build reusable UI components', 4, 2, 'medium', 'in_progress', '2026-08-05'),
        (1, 'SEO optimization', 'Optimize meta tags, schema markup, and page speed', 4, 2, 'medium', 'in_review', '2026-08-10'),
        (1, 'Cross-browser testing', 'Test on Chrome, Firefox, Safari, Edge', 3, 2, 'low', 'todo', '2026-08-20'),
        (1, 'Performance audit', 'Lighthouse audit and optimization', 5, 2, 'high', 'in_progress', '2026-08-15'),
        (2, 'Setup React Native project', 'Initialize project with Expo and navigation', 5, 2, 'critical', 'in_progress', '2026-08-10'),
        (2, 'Design app wireframes', 'Low-fidelity wireframes for all screens', 4, 2, 'high', 'todo', '2026-08-05'),
        (3, 'Backup production database', 'Full backup before migration', 3, 2, 'critical', 'done', '2026-07-20'),
        (3, 'Test migration scripts', 'Run scripts on staging environment', 3, 2, 'high', 'in_progress', '2026-08-01')
    ");

    // Seed Chat Messages
    $db->exec("INSERT INTO chats (project_id, room_type, sender_id, receiver_id, message) VALUES
        (1, 'project', 2, NULL, 'Welcome to the Website Redesign project chat! 👋'),
        (1, 'project', 3, NULL, 'Thanks! I have uploaded the initial mockups to the files section.'),
        (1, 'project', 4, NULL, 'Great work on the mockups! When can we review them together?'),
        (1, 'project', 2, NULL, 'Let us schedule a review for tomorrow at 10 AM. I will send a calendar invite.'),
        (1, 'project', 5, NULL, 'I have started working on the performance audit. Should have results by EOD.'),
        (2, 'project', 2, NULL, 'Mobile app kickoff - Let us discuss the tech stack today.'),
        (2, 'project', 5, NULL, 'I prefer React Native for cross-platform support. Expo makes setup easier.')
    ");

    // Seed Notifications
    $db->exec("INSERT INTO notifications (user_id, title, message, type, link) VALUES
        (3, 'New Task Assigned', 'You have been assigned: Cross-browser testing', 'task', 'tasks.php'),
        (4, 'Task Deadline Approaching', 'SEO optimization is due in 3 days', 'task', 'tasks.php'),
        (5, 'New Project Invitation', 'You have been added to Mobile App Development', 'project', 'projects.php'),
        (3, 'File Uploaded', 'New file uploaded to Website Redesign', 'file', 'files.php'),
        (2, 'Project Status Updated', 'Database Migration progress is at 40%', 'project', 'projects.php')
    ");

    // Seed Activity Logs
    $db->exec("INSERT INTO activity_logs (project_id, user_id, action, description, entity_type) VALUES
        (1, 2, 'project_created', 'Created project: Website Redesign', 'project'),
        (1, 3, 'task_completed', 'Completed task: Design new homepage mockup', 'task'),
        (1, 4, 'file_uploaded', 'Uploaded file: homepage_mockup_v2.fig', 'file'),
        (1, 2, 'member_added', 'Added Mike Chen to project', 'user'),
        (2, 2, 'project_created', 'Created project: Mobile App Development', 'project'),
        (3, 3, 'task_completed', 'Completed task: Backup production database', 'task'),
        (NULL, 1, 'user_registered', 'New user registered: Emily Davis', 'user'),
        (1, 5, 'comment_added', 'Left a comment on Performance audit task', 'task')
    ");

    echo "✅ Seed data inserted successfully.\n";
    echo "\n🎉 Setup complete! You can now visit the application.\n";
    echo "\n📋 Demo Login Credentials (password: password123)\n";
    echo "   🔴 Admin   : admin@workspace.com   → Full dashboard (dashboard.php)\n";
    echo "   🟢 Manager : pm@workspace.com      → Manager view  (manager_dashboard.php)\n";
    echo "   🔵 Member  : member@workspace.com  → Member board  (member_dashboard.php)\n";
    echo "   ⚪ Viewer  : viewer@workspace.com  → Viewer portal (viewer_dashboard.php)\n";
}

// Run setup
try {
    setupDatabase();
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
