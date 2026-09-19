<?php
// ============================================================
// Database Setup & Seed Script — Multi-Tenant Architecture
// Run: php config/setup.php
// Or visit: http://localhost/your-path/config/setup.php
// ============================================================
require_once __DIR__ . '/database.php';

function setupDatabase(): void {
    $db = getDB();
    $driver = DB_DRIVER;

    // Helper for driver-specific AUTO INCREMENT / TIMESTAMP
    $pk = ($driver === 'pgsql') ? "SERIAL PRIMARY KEY" : "INT AUTO_INCREMENT PRIMARY KEY";
    $dt = ($driver === 'pgsql') ? "TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP" : "DATETIME DEFAULT CURRENT_TIMESTAMP";

    // 1. Companies Table
    $db->exec("CREATE TABLE IF NOT EXISTS companies (
        id $pk,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        status VARCHAR(30) DEFAULT 'active',
        created_at $dt
    );");

    // 2. Users Table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        company_id INT DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'member',
        is_super_admin INT DEFAULT 0,
        avatar VARCHAR(255) DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'offline',
        last_seen $dt,
        is_active INT DEFAULT 1,
        created_at $dt
    );");

    // 3. Company Members
    $db->exec("CREATE TABLE IF NOT EXISTS company_members (
        id $pk,
        company_id INT NOT NULL,
        user_id INT NOT NULL,
        role VARCHAR(50) DEFAULT 'member',
        joined_at $dt
    );");

    // 4. Login Approval Requests
    $db->exec("CREATE TABLE IF NOT EXISTS login_approval_requests (
        id $pk,
        user_id INT NOT NULL,
        company_id INT NOT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        approved_by INT DEFAULT NULL,
        requested_at $dt,
        decided_at $dt
    );");

    // 5. Workspaces Table
    $db->exec("CREATE TABLE IF NOT EXISTS workspaces (
        id $pk,
        company_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        description TEXT DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#4f46e5',
        created_by INT NOT NULL,
        created_at $dt
    );");

    // 6. Workspace Members
    $db->exec("CREATE TABLE IF NOT EXISTS workspace_members (
        id $pk,
        company_id INT NOT NULL,
        workspace_id INT NOT NULL,
        user_id INT NOT NULL,
        role VARCHAR(50) DEFAULT 'member',
        joined_at $dt
    );");

    // 7. Projects Table
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id $pk,
        company_id INT NOT NULL,
        workspace_id INT DEFAULT NULL,
        name VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'planning',
        priority VARCHAR(30) DEFAULT 'medium',
        start_date DATE DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        progress INT DEFAULT 0,
        manager_id INT NOT NULL,
        created_by INT NOT NULL,
        created_at $dt
    );");

    // 8. Project Members
    $db->exec("CREATE TABLE IF NOT EXISTS project_members (
        id $pk,
        company_id INT NOT NULL,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at $dt
    );");

    // 9. Tasks Table
    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id $pk,
        company_id INT NOT NULL,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        assigned_to INT DEFAULT NULL,
        created_by INT NOT NULL,
        priority VARCHAR(30) DEFAULT 'medium',
        status VARCHAR(50) DEFAULT 'todo',
        due_date DATE DEFAULT NULL,
        completed_at $dt,
        position INT DEFAULT 0,
        created_at $dt
    );");

    // 10. Chat Messages Table
    $db->exec("CREATE TABLE IF NOT EXISTS chats (
        id $pk,
        company_id INT NOT NULL,
        project_id INT DEFAULT NULL,
        room_type VARCHAR(30) DEFAULT 'project',
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,
        message TEXT DEFAULT NULL,
        file_path VARCHAR(500) DEFAULT NULL,
        file_name VARCHAR(255) DEFAULT NULL,
        is_read INT DEFAULT 0,
        created_at $dt
    );");

    // 11. Files Table
    $db->exec("CREATE TABLE IF NOT EXISTS files (
        id $pk,
        company_id INT NOT NULL,
        project_id INT DEFAULT NULL,
        uploaded_by INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT DEFAULT 0,
        file_type VARCHAR(100) DEFAULT NULL,
        mime_type VARCHAR(100) DEFAULT NULL,
        uploaded_at $dt
    );");

    // 12. Notifications Table
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id $pk,
        company_id INT NOT NULL,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT DEFAULT NULL,
        type VARCHAR(50) DEFAULT 'system',
        link VARCHAR(500) DEFAULT NULL,
        is_read INT DEFAULT 0,
        created_at $dt
    );");

    // 13. Activity Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id $pk,
        company_id INT NOT NULL,
        project_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        created_at $dt
    );");

    // 14. User Contacts Table
    $db->exec("CREATE TABLE IF NOT EXISTS user_contacts (
        id $pk,
        company_id INT NOT NULL,
        user_id INT NOT NULL,
        contact_id INT NOT NULL,
        status VARCHAR(30) DEFAULT 'accepted',
        created_at $dt
    );");

    echo "✅ Multi-tenant schema initialized successfully.\n";
    seedData($db);
}

function seedData(PDO $db): void {
    // Seed default company if none exist
    $has_company = $db->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    if ($has_company == 0) {
        $db->exec("INSERT INTO companies (id, name, slug, status) VALUES (1, 'Acme Global Corp', 'acme-corp', 'active')");
    }

    // Seed default users if none exist
    $has_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($has_users == 0) {
        $hash = password_hash('12345678', PASSWORD_DEFAULT);

        // 1. Super Admin (is_super_admin = 1)
        $db->prepare("INSERT INTO users (company_id, name, email, password, role, is_super_admin, is_active, status) VALUES (1, 'Super Admin', 'superadmin@collabspace.com', ?, 'company_admin', 1, 1, 'online')")
           ->execute([$hash]);
        $super_id = $db->lastInsertId();

        // 2. Company Admin (company_id = 1)
        $db->prepare("INSERT INTO users (company_id, name, email, password, role, is_super_admin, is_active, status) VALUES (1, 'Admin User', 'admin@admin.com', ?, 'company_admin', 0, 1, 'online')")
           ->execute([$hash]);
        $admin_id = $db->lastInsertId();

        // 3. Member User
        $db->prepare("INSERT INTO users (company_id, name, email, password, role, is_super_admin, is_active, status) VALUES (1, 'Team Member', 'member@admin.com', ?, 'member', 0, 1, 'online')")
           ->execute([$hash]);
        $member_id = $db->lastInsertId();

        // Add to company_members
        $db->exec("INSERT INTO company_members (company_id, user_id, role) VALUES (1, 1, 'company_admin'), (1, 2, 'company_admin'), (1, 3, 'member')");

        // Pre-approve seeded accounts in login_approval_requests
        $db->exec("INSERT INTO login_approval_requests (user_id, company_id, status, approved_by) VALUES 
            (1, 1, 'approved', 1),
            (2, 1, 'approved', 1),
            (3, 1, 'approved', 2)");

        echo "✅ Seeded default company, Super Admin (superadmin@collabspace.com), Company Admin (admin@admin.com), and Member user.\n";
    }
}

// Standalone execution
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'setup.php') {
    try {
        setupDatabase();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

