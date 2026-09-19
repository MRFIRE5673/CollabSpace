# CollabSpace Project Memory

## Identity

CollabSpace is a web-based multi-company business collaboration suite.

## Stack

PHP 8.x / PHP-FPM, PostgreSQL, PDO/pdo_pgsql, Nginx, Docker/Compose, AdminLTE 4, Bootstrap 5.3.x, Bootstrap Icons, vanilla ES6+, Inter/Outfit, OverlayScrollbars, Dragula, Chart.js, native PHP sessions.

## Tenant Model

The platform supports multiple independent companies.

Company is the primary security boundary.

A company owns its:

- users/memberships
- workspaces
- projects
- tasks
- chats
- files/documents
- events
- notifications
- contacts
- activity/audit records

Cross-company access is forbidden for all normal users.

## Global Role

`super_admin`

Super Admin is the only global role.

Super Admin can manage all companies and users.

Only Super Admin can:

- create Company Admins
- promote users to Company Admin
- revoke Company Admin
- assign Super Admin
- modify global role definitions
- cross company boundaries

## Company Roles

Baseline subroles:

- company_admin
- manager
- project_manager
- team_lead
- member
- viewer

Authorization is permission-based and should support additional subroles.

## Login Approval

Normal company users require:

credentials -> company admin approval -> authenticated session.

The company administrator responsible for the user's company approves or rejects the login request.

No normal authenticated application session is created before required approval.

Super Admin bypasses company approval.

## File Policy

CollabSpace is preview-first and does not provide normal file downloads.

Files must:

- remain outside the public web root
- be accessed through authorization-checked preview endpoints
- have no public raw URLs
- have no download buttons/endpoints
- be protected against cross-company access

The application must not claim impossible prevention of screenshots or OS-level capture.

## Security Priorities

1. Tenant isolation
2. Privilege boundaries
3. Login approval
4. File protection
5. Secure authentication/session handling
6. Auditability
7. Privacy/data minimization
8. Accessibility
9. Reliable UI and API behavior

## Existing Modules

Authentication, companies, workspaces, projects, project members, tasks, Kanban, chats, files/documents, document preview, calendar/events, notifications, activity/audit logs, contacts, dashboards.

## Source of Truth

For implementation decisions use:

1. actual repository/code/schema
2. memory.md
3. rules.md
4. architecture.md
5. prd.md
6. phases.md
7. legal-pages.md
8. compliance-audit.md
9. agent-compliance-addendum.md

When documentation conflicts with actual code, inspect the repository and update documentation to match the intended final architecture rather than inventing behavior.
