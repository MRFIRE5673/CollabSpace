# CollabSpace Implementation Phases

## Phase 1 — Repository Baseline [COMPLETED]
- Inspected repository baseline, routes, pages, APIs, database schema, role hierarchy, file storage, session engine, and public/private endpoints.

## Phase 2 — Database Engine and Multi-Tenant Schema [COMPLETED]
- Built multi-driver configuration supporting PostgreSQL (`pdo_pgsql`), MySQL (`pdo_mysql`), and SQLite local dev fallback.
- Created `companies`, `company_members`, `login_approval_requests`, `workspaces`, `workspace_members`, `projects`, `project_members`, `tasks`, `chats`, `files`, `notifications`, `activity_logs`, `user_contacts` tables with foreign key constraints.

## Phase 3 — Identity and Tenant Context [COMPLETED]
- Centralized authentication in `includes/auth.php`.
- Implemented `active_company_id()`, `current_user()`, and server-side tenant scoping across all PHP scripts.

## Phase 4 — Global Super Admin Governance [COMPLETED]
- Implemented `super_admin` role with global platform authority.
- Built `superadmin_dashboard.php` and `api/superadmin.php` for provision of tenant companies, company toggles, user activation, and exclusive assignment/promotion of Company Admins.

## Phase 5 — Company Admin & Management [COMPLETED]
- Created Company Admin interfaces scoped to `company_id`.
- Implemented `login_approval.php` queue for Company Admins to approve/reject login requests.
- Enforced strict backend guards blocking Company Admins from creating or promoting Company Admins / Super Admins.

## Phase 6 — Login Approval Workflow [COMPLETED]
- Implemented `login_approval_requests` workflow in `includes/auth.php`.
- Unapproved users are placed in pending state without receiving an active session.
- Super Admin accounts bypass company approval.

## Phase 7 — RBAC & Granular Subroles [COMPLETED]
- Implemented permission-based authorization for subroles: `manager`, `project_manager`, `team_lead`, `member`, `viewer`.
- Implemented `authorize($user, $target_company_id, $permission)` checking permission maps.

## Phase 8 — Multi-Tenant Company Isolation [COMPLETED]
- Scoped all queries (`tasks.php`, `chat.php`, `workspaces.php`, `workspace_detail.php`, `projects.php`, `files.php`, `activity.php`, `calendar.php`, `users.php`) by `company_id = active_company_id()`.

## Phase 9 — Private File Storage & Zero Downloads [COMPLETED]
- Moved upload target to private storage (`private_storage/<company_id>/`) outside web root.
- Removed all download links, download buttons, and `<a download>` attributes across `files.php`, `view_file.php`, `chat.php`, and API endpoints.
- Streamed file previews inline (`Content-Disposition: inline`) via `api/files.php?action=preview` with authorization checking.

## Phase 10 — Collaboration Modules Scoping & Hardening [COMPLETED]
- Hardened workspaces, projects, task boards, team chat (project rooms & DMs), files, calendar, activity logs, user directory, and role dashboards.

## Phase 11 — UI, Accessibility & Navigation [COMPLETED]
- Updated brand header with Company Name badge (`get_active_company_name()`).
- Added Super Admin link and Login Approval badge to sidebar & top navbar dropdown.
- Created custom `404.php` error page.

## Phase 12 — Privacy & Legal Policy Synchronization [COMPLETED]
- Created accurate, non-fictional policy pages: `privacy.php`, `terms.php`, `cookies.php`, `404.php`.
- Documented data handling without making false claims about third-party trackers or external analytics.

## Phase 13 — Security Audit & Isolation Verification [COMPLETED]
- Conducted multi-tenant isolation audit, privilege escalation verification, and file preview stream checks.

## Phase 14 — Release Gate & Verification [COMPLETED]
- All 14 phases executed, verified, and synchronized with system documentation.
