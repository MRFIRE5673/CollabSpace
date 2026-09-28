# CollabSpace Engineering Rules

## Scope

This repository is CollabSpace, a web-based multi-company business collaboration suite.

Do not add unrelated product domains.

## Stack

- PHP 8.x / PHP-FPM
- PostgreSQL
- PDO / pdo_pgsql
- Nginx
- Docker / Docker Compose
- HTML5
- Vanilla JS ES6+
- AdminLTE 4
- Bootstrap 5.3.x
- Bootstrap Icons
- Inter + Outfit
- OverlayScrollbars
- Dragula
- Chart.js
- Native PHP sessions

Do not switch databases without an explicit requirement.

## Tenant Rules

- Company is the mandatory tenant boundary.
- Never trust client-supplied `company_id`.
- Never trust client-supplied role/permission.
- Never fetch a tenant-owned record without authorization.
- Never allow cross-company search or enumeration.
- Scope list, detail, create, update, delete, upload, preview, chat, event, notification, and audit operations to the active company.
- Test both positive and negative authorization paths.

## Super Admin Rules

There is exactly one global privilege level above Company Admin: `super_admin`.

Only Super Admin may:

- create Company Admins
- promote users to Company Admin
- revoke Company Admin
- assign Super Admin
- modify global roles
- cross company boundaries

A Company Admin cannot elevate themselves.

## Login Approval Rules

- Normal company login requires valid credentials plus company-admin approval.
- Do not establish the normal application session before approval.
- Company Admin can approve/reject login requests for their own company only.
- A user from Company A must never see or approve Company B requests.
- Record approval/rejection in audit logs.
- Super Admin can access the platform without company-admin approval.
- Avoid revealing whether arbitrary usernames/emails exist.

## File Rules

The application is preview-only for protected files.

- Remove download buttons.
- Remove download links.
- Remove download attributes.
- Do not expose raw storage paths.
- Do not expose public upload directories.
- Do not create download endpoints.
- Do not use `Content-Disposition: attachment`.
- Store protected files outside the web root.
- Authorize every preview request.
- Validate every upload.
- Generate non-guessable storage identifiers.
- Log file access.
- Never promise impossible screenshot/OS-level copy prevention.

## Security Rules

- Prepared SQL only.
- Password hashing only; never plaintext passwords.
- CSRF protection.
- Secure cookies.
- Session regeneration.
- Rate limiting where appropriate.
- Input validation.
- Output escaping.
- Safe file handling.
- No secrets in repository.
- Generic production errors.
- Audit security-sensitive actions.

## API Rules

Every endpoint must authenticate and authorize where required.

Authorization order:

auth -> tenant -> resource -> permission -> action.

Never:

request -> SQL -> authorization.

## PostgreSQL Rules

- PostgreSQL only.
- Foreign keys.
- Appropriate indexes.
- Transactions for multi-step mutations.
- `timestamptz`.
- Migrations must be repeatable/idempotent where practical.
- No MySQL-specific syntax.
- Consider RLS for tenant-sensitive tables as defense in depth.

## UI Rules

- No horizontal-scroll hacks hiding broken layouts.
- No placeholder text in production.
- No fake testimonials.
- No unsupported claims.
- No dead buttons.
- No unused navigation.
- Clear labels.
- Clear success/error states.
- Accessible forms.
- Visible keyboard focus.
- Sufficient contrast.
- Meaningful alt text.
- Responsive layouts.
- Unique page titles.
- Meta descriptions for public pages.
- Working favicon.
- Working logo.
- Working footer links.
- Custom 404.

## Privacy Rules

Collect only necessary information.

Audit cookies, analytics, embeds, forms, uploads, logs, and external services.

Do not load optional tracking before required consent.

Do not claim legal compliance unless the implementation has actually been audited.

## Documentation Rules

Update:

- prd.md
- architecture.md
- rules.md
- phases.md
- memory.md
- legal-pages.md
- compliance-audit.md
- agent-compliance-addendum.md

Documentation must describe the implementation actually present in the repository.
