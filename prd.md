# CollabSpace Product Requirements Document

## 1. Product Scope

CollabSpace is a web-based multi-company business collaboration suite.

The product supports multiple independent companies (tenants), each with its own users, workspaces, projects, tasks, conversations, files/documents, calendars, notifications, contacts, and audit history.

The system must enforce strict company data isolation. A user must never be able to view, search, modify, infer, enumerate, or access another company's data unless they are the global Super Admin.

## 2. Core Platform

- PHP 8.x / PHP-FPM
- PostgreSQL
- PDO + pdo_pgsql
- Nginx
- Docker / Docker Compose
- HTML5
- Vanilla JavaScript ES6+
- AdminLTE 4
- Bootstrap 5.3.x
- Bootstrap Icons
- Inter + Outfit
- OverlayScrollbars
- Dragula
- Chart.js
- Native PHP sessions
- Lightweight JSON REST-style PHP endpoints
- Private server-side file storage

Do not introduce unrelated application domains or developer tooling.

## 3. Tenant / Company Model

Top-level hierarchy:

Super Admin
  -> Companies
      -> Company Admins
          -> Subroles / Managers / Team Leads / Members / Viewers
              -> Workspaces
                  -> Projects
                      -> Tasks / Chats / Files / Events

Required entities:

- companies
- users
- company_members
- login_approval_requests
- role_definitions / subroles
- workspaces
- workspace_members
- projects
- project_members
- tasks
- chats
- files
- notifications
- activity_logs
- user_contacts

Every tenant-owned record must carry a company/tenant identifier directly or through an immutable ownership chain.

## 4. Global Super Admin

There must be a distinct `super_admin` role.

Super Admin has global administrative access across the entire platform, including:

- create, edit, suspend, archive companies
- create, edit, suspend, restore users
- assign and remove Company Admins
- create/configure roles and subroles
- view global system health and audit information
- manage company-level configuration
- investigate security events
- manage platform-wide settings

Only Super Admin can:

- create a Company Admin
- promote a user to Company Admin
- remove Company Admin privileges
- assign the Super Admin role
- modify global role definitions
- cross company boundaries

Never allow a Company Admin to grant themselves or another user administrative privileges.

Super Admin accounts must be protected with the strongest available authentication controls.

## 5. Company Admin

Each company may have multiple Company Admins.

Company Admin permissions are strictly limited to their own company.

They can manage, subject to permissions:

- company users
- company memberships
- login approvals
- workspaces
- projects
- teams
- tasks
- company files/documents
- company events
- notifications
- company contacts
- company audit records

They cannot:

- access another company's records
- create/promote another Company Admin
- create a Super Admin
- modify global security settings
- bypass tenant isolation
- download protected platform files
- grant permissions they themselves do not possess

## 6. Subroles

Implement granular subroles instead of relying only on the old four-role model.

Suggested baseline roles:

- Super Admin
- Company Admin
- Manager
- Project Manager
- Team Lead
- Member
- Viewer

The authorization system should be permission-based so additional company roles can be added later without rewriting authorization logic.

Permissions should cover at minimum:

- company.view
- company.manage
- users.view
- users.manage
- users.approve_login
- roles.view
- roles.manage
- workspace.view
- workspace.manage
- project.view
- project.manage
- task.view
- task.create
- task.edit
- task.assign
- task.delete
- chat.view
- chat.send
- files.view
- files.preview
- files.upload
- files.delete
- files.download
- calendar.view
- calendar.manage
- reports.view
- audit.view

`files.download` must not be granted in the normal product UI. The product must operate as a preview-only document/file system unless a future explicit business requirement authorizes controlled export.

## 7. Login Approval Workflow

For every normal company user login:

1. User submits credentials.
2. Credentials are validated.
3. If credentials are valid but the login has not been approved:
   - create a `login_approval_request`
   - do not create an authenticated application session
   - show a clear "Waiting for company administrator approval" state
4. Company Admin(s) of the user's company can approve or reject the login request.
5. Approval is logged with approver, timestamp, user, company, request metadata, and decision.
6. Only after approval may the user receive a fully authenticated application session.
7. Rejected requests must not authenticate the user.
8. Expired/revoked approvals must not grant access.

Super Admin is not subject to company-admin approval.

Do not expose whether another company exists through login error messages.

## 8. Company Data Isolation

Tenant isolation is a critical security requirement.

Every request must establish:

- authenticated user
- active company/tenant
- role
- permissions
- resource ownership

Every query for tenant data must be scoped to the authorized company.

Never rely only on a hidden form field, URL parameter, JavaScript variable, or client-supplied `company_id`.

Use server-side authorization on every endpoint.

Use PostgreSQL foreign keys, indexes, transactions, and Row-Level Security where practical as defense in depth.

Cross-company IDs must not be accepted as authorization.

Return generic not-found/forbidden responses where necessary to prevent tenant enumeration.

Log cross-tenant authorization violations.

## 9. File / Document Protection

CollabSpace must not provide normal file downloading.

Required:

- remove Download buttons
- remove download links
- remove export/download controls
- remove `download` attributes
- remove public file URLs
- remove unauthenticated file endpoints
- remove predictable storage URLs
- keep files outside the public web root
- serve previews through an authorization-checked endpoint
- verify company ownership before every preview
- verify resource permission before every preview
- do not expose raw storage paths
- prevent directory listing
- use safe MIME handling
- validate uploads
- generate safe storage names
- enforce upload size/type restrictions
- log file access

Where technically possible, previews should be rendered in a controlled viewer rather than exposing the original file URL.

Important limitation: a web application cannot guarantee that a person cannot capture information displayed on their screen using screenshots, browser developer tools, OS-level capture, or other external means. The requirement is therefore to eliminate application-level file downloads and direct file retrieval while clearly avoiding false "DRM" claims.

## 10. Authentication / Sessions

- Secure password hashing
- Secure session cookies
- HttpOnly
- SameSite
- Secure in HTTPS production
- session regeneration after authentication
- session timeout
- logout invalidation
- CSRF protection
- login throttling / brute-force protection
- audit authentication events
- no sensitive information in URLs
- no passwords in logs

## 11. Existing Modules

Preserve and harden:

- authentication
- companies
- company members
- workspaces
- projects
- project members
- tasks
- Kanban
- group/project/direct chat
- files/documents
- document preview
- calendar/events
- notifications
- activity/audit logs
- contacts/user directory
- dashboards

## 12. UI / UX

- responsive AdminLTE interface
- no horizontal overflow caused by layout bugs
- unique page titles
- meaningful meta descriptions for public pages
- working favicon
- working logo link
- working footer links
- clickable verified phone/email
- custom 404
- useful 403/401/500 handling
- clear success/error messages
- no placeholder copy in production
- no fake reviews
- no unsupported claims
- no unused navigation
- keyboard accessible controls
- visible focus states
- accessible labels
- sufficient color contrast
- useful alt text

## 13. Privacy / Compliance

Collect only data required for operation.

Audit:

- analytics
- cookies
- third-party embeds
- forms
- uploads
- logs
- notifications
- external services

Optional analytics/marketing technologies must not be loaded before any consent required by the applicable implementation.

Keep privacy policy, terms, cookie policy, refund policy, and consent wording synchronized with actual behavior.

Do not claim legal compliance without verifying the actual deployed implementation and applicable jurisdiction.

## 14. Definition of Done

The release is complete only when:

- multi-company isolation is tested
- Super Admin boundaries are tested
- Company Admin boundaries are tested
- only Super Admin can create/promote Company Admins
- login approval works
- rejected logins cannot authenticate
- cross-company URLs/API requests fail safely
- file downloads are removed
- raw file endpoints are inaccessible
- previews remain authorization protected
- every role has explicit permissions
- audit logs cover security-sensitive actions
- links/buttons/forms have been tested
- legal/privacy pages match implementation
- accessibility baseline is tested
- no placeholder production content remains
- documentation is updated
