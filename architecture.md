# CollabSpace Architecture

## 1. High-Level Architecture

Browser
-> Nginx
-> PHP-FPM
-> PHP Application
-> PostgreSQL

Private file storage is outside the public web root.

## 2. Multi-Tenant Architecture

The primary security boundary is the Company/Tenant.

Core relationship:

Company
-> Company Members
-> Workspaces
-> Projects
-> Tasks
-> Chats
-> Files
-> Events
-> Notifications
-> Contacts
-> Audit Logs

Every tenant-owned object must be attributable to exactly one company.

For entities that inherit tenancy through another object, the application must still resolve the final company server-side and verify it before access.

For critical tables, prefer an explicit `company_id` where practical to make isolation auditable and queryable.

## 3. Authorization Pipeline

Every authenticated request follows:

1. establish session
2. identify user
3. identify active company
4. verify company membership
5. load effective role/subroles
6. resolve permissions
7. resolve target resource
8. verify target belongs to active company
9. verify permission
10. execute operation
11. audit security-sensitive operations

Never authorize based on client-supplied role, company ID, permission, or ownership claim.

## 4. Role Hierarchy

`super_admin` is global.

Company-level roles:

- company_admin
- manager
- project_manager
- team_lead
- member
- viewer

Role definitions should map to explicit permissions.

Only `super_admin` can grant or revoke `company_admin`.

## 5. Login Approval

Authentication is split into:

Credential Validation
-> Login Approval Request
-> Company Admin Decision
-> Authenticated Session

Credential validity alone does not create a normal application session for company users.

A login request should contain enough information for the Company Admin to make a security decision without unnecessarily collecting excessive personal data.

## 6. PostgreSQL

Use:

- PostgreSQL
- PDO / pdo_pgsql
- prepared statements
- transactions
- foreign keys
- indexes
- `timestamptz`
- migrations

Use PostgreSQL Row-Level Security as defense in depth for tenant-sensitive tables where it can be implemented safely.

Application authorization remains mandatory even when RLS exists.

Do not use MySQL/MariaDB-specific SQL.

## 7. Recommended Tables

### Identity / tenancy

- users
- companies
- company_members
- role_definitions
- role_permissions
- login_approval_requests
- sessions / session metadata if required

### Collaboration

- workspaces
- workspace_members
- projects
- project_members
- tasks
- chats
- chat_members
- files
- events
- notifications
- activity_logs
- user_contacts

### Security

- login_attempts
- authorization_events
- security_events

Avoid storing unnecessary personal or device information.

## 8. File Architecture

Storage:

`/app/private_storage/<company-id>/<safe-generated-file-id>`

Do not expose this path through Nginx.

Preview endpoint:

`GET /api/files/{id}/preview`

Server must:

- authenticate
- resolve active company
- resolve file
- verify company ownership
- verify `files.preview`
- verify file status
- stream an approved preview representation

No:

`GET /uploads/file.pdf`

No public object storage URL.

No download endpoint.

No `Content-Disposition: attachment` for protected content.

Where a browser-native viewer inherently exposes save/copy capabilities, do not falsely claim complete prevention. The security objective is to prevent application-level download/retrieval.

## 9. API Architecture

All JSON APIs must:

- authenticate
- validate input
- authorize
- enforce company scope
- use prepared SQL
- return consistent status codes
- avoid leaking database errors
- log security-sensitive actions

Recommended error behavior:

- 401 unauthenticated
- 403 authenticated but unauthorized
- 404 resource not found or intentionally concealed
- 422 validation error
- 429 rate limited
- 500 generic server error

## 10. Application Layers

- presentation/pages
- HTTP/API
- authentication/authorization services
- business services
- repositories/data access
- storage service
- audit/security service

Authorization should not be scattered through arbitrary page code.

Centralize reusable checks.

## 11. Security-Critical Invariants

1. No cross-company data.
2. No client-controlled authorization.
3. No Company Admin -> Company Admin promotion.
4. No Company Admin -> Super Admin promotion.
5. No normal file download.
6. No public private files.
7. No login session before required approval.
8. No direct database errors to users.
9. No secrets in source control.
10. Every security-sensitive action is auditable.
