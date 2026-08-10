<div align="center">

# ?? CollabSpace

**A real-time team collaboration platform built with PHP, MySQL & JavaScript**

[![Live Demo](https://img.shields.io/badge/Live%20Demo-Railway-blueviolet?style=for-the-badge&logo=railway)](https://collabspace-production-430b.up.railway.app)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![Docker](https://img.shields.io/badge/Docker-Containerized-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://docker.com)

*One workspace for projects, tasks, files, and real-time team communication*

[**Live Demo**](https://collabspace-production-430b.up.railway.app) | [Features](#-features) | [Setup](#-getting-started) | [Architecture](#-architecture)

</div>

---

## Features

### Team & Access Management
- **Role-Based Access Control (RBAC)** — 4 roles: Admin, Manager, Member, Viewer
- bcrypt-hashed authentication, profile management, online presence

### Project & Task Management
- Projects with member assignments
- **Kanban task board** — drag tasks across To Do / In Progress / Done
- Task priorities, due dates, assignee tracking

### Real-Time Communication
- **Live team chat** with file attachments
- **Cross-device document sync** — edits appear on all connected devices within 800ms
- Real-time notifications

### File Management
- Upload any file type (images, PDFs, Office docs, videos)
- **In-browser file viewer** — images, PDFs, videos without downloading
- **Interactive editors** for Word (.docx), Excel (.xlsx), PowerPoint (.pptx)
- Live collaborative editing with auto-save

### Dashboards & Analytics
- Role-specific dashboards (Admin / Manager / Member / Viewer)
- **Calendar view** for deadlines
- Full audit log with timestamp trail

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 with PDO |
| Frontend | Bootstrap 5.3, Vanilla JavaScript |
| Icons | Bootstrap Icons |
| File Parsing | Mammoth.js (Word), SheetJS (Excel) |
| Hosting | Railway |
| Container | Docker + Apache |
| CI/CD | GitHub -> Railway auto-deploy |

---

## Architecture

```
Browser (HTML + JS)
       |
       v  HTTP Requests (GET/POST)
  PHP Application
  |- Pages (.php)
  |- API (/api/*.php)  <-- return JSON
       |
       v
  MySQL Database
```

**Real-Time Sync:**
```
User types -> JS debounce (200ms) -> POST /api/documents.php (save)
Other devices -> poll every 800ms -> GET /api/documents.php -> update editor
```

**CI/CD Pipeline:**
```
git push -> GitHub -> Railway webhook -> Docker build -> Live deploy (~60s)
```

---

## Database Schema

```
users           -> id, name, email, password, role, avatar, status
projects        -> id, name, description, status, created_by
project_members -> project_id, user_id
tasks           -> id, title, project_id, assigned_to, status, due_date
files           -> id, file_name, original_name, project_id, uploaded_by
chats           -> id, project_id, sender_id, message, file_path
notifications   -> id, user_id, type, message, is_read
activity_logs   -> id, user_id, action, details, created_at
```

---

## Getting Started

### Prerequisites
- PHP 8.0+, MySQL 8.0+, Apache with mod_rewrite (or XAMPP)

### Local Setup

```bash
git clone https://github.com/MRFIRE5673/CollabSpace.git
cd CollabSpace
cp .env.example .env
# Edit .env with your DB credentials
# Visit http://localhost/CollabSpace/config/setup.php to init DB
```

**Default Admin Login**
```
Email:    admin@admin.com
Password: 12345678
```

### Docker

```bash
docker-compose up --build
# Visit http://localhost:8080
```

---

## Deploying to Railway

1. Fork this repo
2. Create project on [Railway](https://railway.app) and connect GitHub
3. Add MySQL plugin
4. Set environment variables: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `APP_URL`
5. Railway auto-detects Dockerfile and deploys

---

## Security

- Passwords hashed with `password_hash()` (bcrypt)
- SQL Injection prevented via PDO prepared statements
- Session-based auth with server-side storage
- Role-based middleware on every protected page and API route
- File validation: extension whitelist + 20MB size limit
- Credentials via environment variables only

---

## Project Structure

```
CollabSpace/
+-- api/                 # AJAX endpoints (return JSON)
¦   +-- documents.php    # Real-time document sync
¦   +-- files.php        # File upload/delete/rename
¦   +-- tasks.php        # Task CRUD + Kanban
¦   +-- chat.php         # Chat send/fetch
+-- config/
¦   +-- database.php     # PDO singleton + env config
¦   +-- setup.php        # DB schema auto-setup
+-- includes/
¦   +-- auth.php         # RBAC + session helpers
¦   +-- sidebar.php      # Navigation
¦   +-- header.php       # HTML head
+-- css/custom.css       # Design system
+-- uploads/             # User files (gitignored)
+-- dashboard.php        # Admin dashboard
+-- projects.php         # Projects list
+-- tasks.php            # Task board
+-- files.php            # File manager
+-- chat.php             # Team chat
+-- view_file.php        # File viewer + live editor
+-- users.php            # User management
+-- calendar.php         # Calendar view
+-- Dockerfile           # Container config
+-- docker-compose.yml   # Local Docker setup
```

---

## License

MIT License

---

<div align="center">

Built as a real-world collaboration platform

[Star this repo](https://github.com/MRFIRE5673/CollabSpace) if you found it useful!

</div>
