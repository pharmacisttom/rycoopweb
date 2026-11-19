# RYCOOPWEB Production Migration Manifest — 2026-11-18
Status: PRE-PRODUCTION / NO GO-LIVE AUTHORIZATION FROM THIS DOCUMENT.

## Verified operator evidence
- Production database `rayongcoop_db`: 71 tables; TEST `rycoopweb_test`: 73 tables.
- Only TEST-only tables by name: `activity_albums`, `activity_photos`; TEST holds 0 albums and 0 photos.
- Selected TEST tables `news` and `board_terms` each have 0 rows; this is **not** proof that production contents are empty. Do not import empty TEST tables into production.
- Schema-only production clone: 71 baseline tables, 73 after additive gallery DDL; InnoDB utf8mb4_unicode_ci and photo->album FK validated. This was not a populated-data restore test.
- In PROD migration history, `031_add_source_member_id_to_board_term_members.sql` is already successful; preserve it unchanged. Gallery migration renamed from conflicting 031 to `032_create_activity_photo_albums.sql` without changing SQL statements.
- Production history also contains previously applied `026`–`030` migrations absent from the current checkout. **Do not replay** them, rename them, or rewrite their history.
- 18 type mismatches TEST/PROD (mostly JSON vs LONGTEXT) and 4 PROD-only columns were observed. Preserve existing PROD schema for this release; don't blanket-align it to TEST.
- Four sampled PROD JSON-in-LONGTEXT fields had zero invalid JSON rows; this is not a full 18-column compatibility audit.
- Production member, dividend, auth, PDPA, financial, audit, board and live-content data remain authoritative.

## Migration 032 acceptance requirements
1. Review final branch SHA and CI; do not bypass npm security advisory release policy.
2. Confirm exact migration SHA-256 equals the reviewed lab SQL; production migration history must have no `032_create_activity_photo_albums.sql` entry.
3. Verify backup of full production data and uploads, encrypted and restorable; perform restoration on an isolated clone with restricted access, not only schema-only clone.
4. Apply only the additive DDL on a disposable *populated* production clone; validate constraints, permissions and gallery API with production-compatible LONGTEXT fields.
5. Confirm production filename conflicts and migration runner allowlist/checksum semantics. The hardened runner requires dedicated non-root credentials and explicit DB selection.
6. Prepare an immutable backend/frontend release; manually verify routing, MFA, CSRF, admin visual integrity, member portal safeguards, public pages and disabled/feature-flagged paths.
7. Inventory `site_settings`, `public_content_revisions`, `board_terms`, `boards`, `media`, `uploads`, `private-activity-photos` and PROD public content. Do not bulk-copy TEST data; migrate only reviewed content with conflict-safe IDs and file manifests.
8. Record maintenance/cutover window, rollback point, post-cutover smoke tests and escalation owner. Schema rollback is separate from code rollback; never assume DROP TABLE is safe after users create content.
9. Keep production untouched until UAT and approvals are explicit.

## Database-only dry-run inventory
- Migration path: `database/migrations/032_create_activity_photo_albums.sql`.
- Tables: `activity_albums`, `activity_photos`.
- Foreign key: `fk_activity_photos_album` (photos.album_id -> albums.id; ON DELETE CASCADE).
- TEST import rows: 0 albums, 0 photos. No data promotion needed for galleries.

## Open issues
- PHP migration history reconciliation for already-applied 026–031 missing from working tree.
- Full populated-data restore drill and permissions review.
- Frontend/backend UAT on the actual TEST release and published content/board authority mapping.
- Production release branch and security advisory resolution.
