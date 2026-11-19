# RYCOOPWEB RC03 — Frontend/Backend Content Parity, Mock Data Removal & Document Recovery

Updated: 2026-11-19. **TEST FIRST — DO NOT DEPLOY TO PRODUCTION YET.**
Baseline production: `rc02-loan-empty-hotfix-20261119T111713Z` (commit `5c4412f`).
This branch is a partial implementation, not an all-clear for publication.

## Acceptance policy
- Every user-visible *business-controlled* string, image, menu item, CTA label/URL, footer item, contact detail, widget caption, and published document metadata must have a server-backed admin CRUD field, review and publish path, permission checks, validation, audit history and a meaningful empty state.
- UI-only strings (buttons like Cancel, accessibility labels, auth errors, system validation messages) should be localizable through a versioned i18n/message catalog, not directly editable by untrusted CMS editors. Security-critical messages must not be changed to mislead users.
- Preview is separate from publication. Publishing must be authenticated and audited; privileged actions remain MFA-gated. Public routes show approved/published data only.
- Never synthesize members, finances, loan rates, receipts, document records, requests or complaints from bundled historical fixtures or browser storage.
- Do not delete members, dividends, receipt records, news, files, logs or historical audits to satisfy mock-data cleanup.
- An HTTP 200 SPA shell alone is **not** a rendering or CRUD acceptance test.

## Grounded gap inventory (not yet exhaustive)

| Frontend surface | Existing source of truth | Remaining problem | Action |
| --- | --- | --- | --- |
| Home sections visibility | `/api/public/layout`, Admin HomepageLayoutController | Section titles, hero default copy, descriptions, quick-link labels and CTA copy are hardcoded in `src/pages/HomePage.jsx` | Design keyed `site_content_blocks` or extend versioned published CMS; connect all fields and preview |
| Home loan widget | PublicContentContext published loan catalog | Empty data previously crashed; fixed in this branch | Browser-test empty/published/malformed payload |
| Home news | Public news API + CMS | Mix of static heading/CTA copy and API data | Add editable section copy; preserve live news data |
| Header navigation | SiteNavigationCopyController for a subset of strings | Many mega-menu labels and descriptions hardcoded in `Navbar.jsx` | Full navigation tree CRUD (label, target, order, visibility, publication) |
| Footer / contact | PublicContentContext + static JSX | Text, links and service descriptions hardcoded in `Footer.jsx` | CMS footer groups, contact cards, link validation |
| Public cooperative facts | PublicContentAdminPage and published revision catalog | Need field-by-field coverage and preview of all public values | Verify published-versus-draft behavior |
| Admin legacy dashboard | `AdminDashboardPage.jsx` | Browser-local hero/popup/complaints and seeded announcements/products | Disable local-only writes; migrate to server CRUD before re-enabling |
| Member dashboard | Member API for financial summary, browser-local complaint/loan queues | Browser-local entries falsely appeared persistent | Current RC03 blocks fake submission; real backed workflow must be separately designed |
| e-Receipt verification | No verified API on old page | Fake positive result and sample signature | Current RC03 fails closed; build verified receipt API with token policy and signature validation before enablement |
| Documents public list | `/api/public/documents` | 81 listed previously; download link used blocked legacy URL | Added `/api/public/documents/{id}/download`, pending integration test |
| Documents admin | `/api/admin/documents` (AdminCrudService) | Need test upload/update/deactivate, file validation, broken paths, storage consistency | Run UAT against TEST, preserve backup and history |
| Download storage | shared-production/uploads | Historic path formats and availability not yet reconciled | Execute read-only document file audit on VPS |
| Privacy policy | `PrivacyPolicyPage.jsx` | Some fixed text; contact info imported from legacy mockData | Migrate to published legal document revision with restricted editor/publisher role |
| Global dynamic sections | DynamicLayoutController | Custom sections already server-backed; ensure nested CRUD and publishing works | Verify create/edit/delete/reorder, scheduled publish and displayed parity |

## Download restoration runbook

1. Run `sudo bash scripts/audit-prod-documents-readonly.sh` in the checked-out RC03 workspace (requires database CLI privileges and `www-data`).
2. Check `counts.tsv` versus number of IDs in `published-paths.tsv` and the published API. No production database writes are made.
3. Review `file-results.tsv` securely (filenames/paths may be confidential). For `MISSING`, `UNSAFE_PATH`, `UNREADABLE` do not bulk delete or blindly copy. Establish each legacy file's provenance and actual approved visibility.
4. Search previous releases and backups read-only for missing file hashes/paths, create a restore manifest with source, destination, size, SHA256, permissions and approver. Back up destination before restoring.
5. Restore to canonical Shared Storage with path traversal and symlink checks, safe file MIME policy, dedicated audit log and least-privilege permissions. Do not expose `/storage/` broadly in Nginx.
6. Only after TEST integration: route `/api/public/documents/{id}/download` to PHP, check valid public document => file with proper Content-Type, unavailable/disabled/private => 404, no sensitive file disclosure. Confirm direct public URL returns 200 for real files, bytes match source.
7. Production release must atomically deploy both new router and controller with frontend download link. Confirm at least 3 approved documents end-to-end and monitoring for 404; avoid exhausting bandwidth or inflating download counters with automated full-catalog GET.

## RC03 verification gates
- `bash -n scripts/audit-prod-documents-readonly.sh`
- `php -l config/routes.php`; `php -l app/Controllers/Public/DocumentController.php`
- `npm ci && npm run build`; run existing tests/harness/lint as available.
- Browser E2E: homepage with empty/partial published API, news, documents search/download, navigation, footer, mobile.
- Admin UAT with MFA for every mutable section: create -> preview -> save -> publish -> verify public -> unpublish -> verify hidden, across role boundaries.
- Member login, PDPA acknowledgement and MFA protections must not regress.
- Confirm zero fabricated financial values, complaints, receipts and published loan rates in SPA; inspect chunk contents, not just chunk filenames.
- Audit backups and rollback paths before any Production promotion.

## Engineering caution
The RC03 branch currently contains incomplete cleanup patches (including deliberately unavailable legacy browser-only operations). It is **not** approved for deploy. Preserve working production release while adding real service-backed workflows and tests. A complete frontend-text CRUD requirement needs an explicit catalog schema, role/permissions, migration and full route-to-field parity review.
