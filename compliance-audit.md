# CollabSpace Compliance and Security Audit Requirements

## Audit Principle

Do not mark a requirement compliant merely because documentation exists.

Each requirement must be classified:

- Implemented
- Audited
- Not found
- Not applicable
- Not verified
- Blocked by missing business information

## Multi-Tenant Security

Verify:

- company_id ownership
- server-side tenant resolution
- API tenant scoping
- page-level tenant scoping
- search scoping
- chat scoping
- file scoping
- notification scoping
- contact scoping
- audit scoping
- role scoping

Attempt cross-company IDOR and direct URL/API access.

## Privilege Escalation

Verify:

- only Super Admin can create Company Admin
- Company Admin cannot create Company Admin
- Company Admin cannot become Super Admin
- normal users cannot change their own role
- permission changes are audited
- role APIs enforce server-side authorization

## Login Approval

Verify:

- valid credentials alone do not authenticate normal users
- request enters pending state
- correct company admins can approve
- other-company admins cannot approve
- rejection blocks access
- expiry blocks access
- repeated attempts do not bypass approval
- approval is audited
- Super Admin exception works

## File Protection

Verify:

- no download buttons
- no download links
- no `download` attributes
- no public upload directory
- no direct storage path
- no unauthenticated file endpoint
- no cross-company file preview
- no attachment disposition
- guessed IDs do not expose files
- MIME/path manipulation does not expose originals

Remember: browser/OS screenshots and external capture cannot be absolutely prevented.

## Privacy / Data Minimization

Inventory:

- account fields
- company fields
- login metadata
- logs
- contacts
- uploaded content
- analytics
- cookies
- third-party integrations

Remove unnecessary collection.

## Analytics / Tracking

Inspect actual code for:

- Google Analytics
- Google Tag Manager
- Meta Pixel
- advertising scripts
- session replay
- heatmaps
- telemetry
- external fonts/CDNs
- embedded media

Document actual technologies.

## Third-Party Embeds

Inventory actual:

- maps
- videos
- social embeds
- external widgets
- payment providers
- support widgets
- analytics providers

Do not load unnecessary third-party content.

## Accessibility

Target WCAG 2.2 AA.

Check:

- keyboard navigation
- focus
- contrast
- labels
- error states
- alt text
- headings
- semantic buttons/links
- responsive layout
- no horizontal overflow

## Content / Copyright

Verify:

- image licenses
- icon licenses
- fonts
- third-party assets
- stock media
- logos
- user-uploaded content terms

Remove unsupported claims and fake reviews.

## Business Details

Do not invent:

- legal business name
- registered address
- phone
- email
- GST/tax information
- refund promises

Missing real business information is a release blocker where required.

## Release Classification

The final report must clearly separate:

1. implemented
2. tested
3. not found
4. not verified
5. blocked
6. business-owner decisions required
