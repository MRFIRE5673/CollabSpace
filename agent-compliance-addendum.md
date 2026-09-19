# Antigravity Agent Addendum — Security-Critical CollabSpace Rules

## Read First

Before modifying code, inspect the actual repository and read:

- memory.md
- rules.md
- architecture.md
- prd.md
- phases.md
- legal-pages.md
- compliance-audit.md
- this file

Do not assume the old schema or role model is correct.

## Hard Requirements

### 1. Multi-Company

Implement true multi-tenancy.

Do not simulate isolation only in the UI.

Every server-side data operation must enforce company scope.

### 2. Super Admin

Create a protected global `super_admin`.

Only Super Admin can create/promote Company Admins.

Never let a Company Admin grant themselves or another user Company Admin/Super Admin.

### 3. Login Approval

Normal company users require approval from an administrator of their own company before receiving an authenticated application session.

Never bypass this through:

- direct API calls
- old login endpoints
- remembered sessions
- alternate pages
- AJAX endpoints
- password reset flows
- mobile/responsive variants
- debug routes

Super Admin is the explicit exception.

### 4. Roles

Implement permission-based roles/subroles.

Baseline:

- Super Admin
- Company Admin
- Manager
- Project Manager
- Team Lead
- Member
- Viewer

Do not hard-code authorization into only one page.

### 5. File Downloads

The product must not provide normal file downloads.

Remove all:

- download buttons
- download links
- download attributes
- download APIs
- public file URLs
- attachment endpoints
- raw storage URLs

Protect private files outside the web root.

Use authorization-checked preview endpoints.

Do not claim that screenshots or OS-level capture are technically impossible.

### 6. Do Not Cheat

Do not:

- hide tenant bugs with UI changes
- remove buttons instead of fixing their underlying API
- rely on JavaScript for authorization
- trust URL/query/body company IDs
- trust client-side role values
- use security through obscurity
- expose database errors
- invent business information
- invent compliance certifications
- claim "100% secure"
- claim "impossible to download" when the browser displays the content

## Implementation Workflow

1. Audit repository.
2. Map existing schema.
3. Map authentication.
4. Map every role check.
5. Map every API endpoint.
6. Map every file endpoint.
7. Map every route.
8. Map every company/workspace/project relationship.
9. Design migration.
10. Implement central authorization.
11. Implement tenant scoping.
12. Implement login approval.
13. Implement Super Admin boundary.
14. Implement subroles.
15. Remove downloads.
16. Harden preview access.
17. Run security tests.
18. Run UI/link/button/accessibility audit.
19. Update all Markdown documentation.
20. Produce a final audit report.

## Required Final Report

Return:

### Implemented
Concrete features completed.

### Security Tests
Tenant-isolation tests, privilege-escalation tests, login-approval tests, and file-protection tests.

### UI Tests
Links, buttons, responsive layout, accessibility, favicon, titles, metadata, footer, 404.

### Not Found
Issues searched for but not present.

### Not Verified
Things requiring deployment/business-owner verification.

### Remaining Blockers
Anything that prevents production release.

Never declare legal compliance or perfect security without evidence.
