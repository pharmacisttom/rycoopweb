# RYCOOPWEB — Media Library Datasheet / API Contract (RC01)

Status: **TEST-FIRST, not approved for Production**. Source baseline: `596511c77edebe1045b97bd6187753df74df6834`. All fields below come from `database/migrations/001_initial_schema.sql`, not from a live Production inspection.

## Database table: `media`

| Field | MySQL type | Null | Purpose |
|---|---|---|---|
| id | BIGINT UNSIGNED PK AUTO_INCREMENT | no | Stable media identifier |
| filename | VARCHAR(255) | no | Randomized storage name |
| original_name | VARCHAR(255) | no | Uploaded original basename |
| mime_type | VARCHAR(100) | no | Validated MIME (new uploads standardized to image/webp) |
| file_size | BIGINT UNSIGNED | no | Encoded output size in bytes |
| path | VARCHAR(255) | no | Folder-relative storage path (do not let client supply arbitrary path) |
| disk | VARCHAR(50), default local | no | Storage backend |
| folder | VARCHAR(50), default general | no | Admin-selected logical grouping |
| alt_text | VARCHAR(255) | yes | Accessibility description |
| caption | VARCHAR(255) | yes | Optional human-readable caption |
| uploaded_by | BIGINT UNSIGNED | yes | Authenticated actor identifier |
| created_at | DATETIME | no | Creation time |
| deleted_at | DATETIME | yes | Soft deletion marker |

Existing index: `idx_media_folder(folder)`. No schema migration is required for RC01. Production column types and existing table indexes **must be verified independently** before deployment. Existing media files are not automatically rewritten or sanitized; the new upload pipeline only handles newly uploaded pictures.

## API: authenticated CMS media library

Base URL: `/api/admin/media`; uses authenticated staff session, CMS `news` permissions and CSRF for all POST requests. No public catalog is exposed.

| Method | Path | Permission | Action |
|---|---|---|---|
| GET | /api/admin/media | news.view | Search/paginate (max 50 per page) |
| GET | /api/admin/media/{id} | news.view | Get one image |
| POST | /api/admin/media | news.create | Upload one file (multipart/form-data) |
| POST | /api/admin/media/{id}/update | news.update | Update alt_text/caption with version |
| POST | /api/admin/media/{id}/delete | news.delete | Soft-delete with version |

Query params: `page`, `per_page`, `search`, `folder`. Return shape: `{success:true,data:[...],pagination:{page,per_page,total,pages}}`. Single-item responses return `data:{...}`. Errors use existing `AdminCrudService::respond` envelope with `code` and `message`.

Upload fields: `file` (required), `folder` (optional, default `general`, lowercase alphanumeric/hyphen/underscore), `alt_text`, `caption`. Max input size 10 MiB, max 16 megapixels, JPEG/PNG/WebP only. Server decodes image and re-encodes at longest edge ≤2200px to WebP to remove embedded EXIF/GPS and standardize display. Requires PHP GD with WebP support and writable image storage.

Metadata edit and delete require latest `version`, and return HTTP 409 on a stale version. All successful mutations require Audit Log persistence; upload removes output file if DB operation fails. Deletion is metadata-only; existing public URL may continue serving the file until storage lifecycle policy reclaims it. **Do not use soft delete as a privacy erasure mechanism.** Do not upload confidential member records or identity documents as public CMS images.

## Frontend integration

- Staff-only page: `/app/admin/media` (React Router with `basename="/app"`)
- API client: `src/services/mediaLibraryApi.js`
- Reusable selection: `<AdminMediaLibraryPage onSelect={(image) => saveImageReference(image.id, image.url)} />`. Integration with a particular CMS editor remains a follow-up task.
- Loading/error/empty states, search, pagination, image cards, upload, metadata edit, soft delete, URL copy; responsive CSS scoped to the module.

## Acceptance and security gates

1. `php -l` controller and route; `npm ci` / production Vite build.
2. Route introspection: GET paths require auth and view permission; mutating routes require authenticated staff, correct permission and CSRF; unauthorized member cannot enumerate images.
3. Real TEST MySQL: seed media fixture, upload real valid JPEG, verify WebP magic/MIME, original EXIF stripped; reject invalid MIME, oversized file, folder traversal, oversized pixel dimensions, stale version, CSRF failure and missing permissions.
4. TEST browser: open `/app/admin/media`, upload, search, edit, select, paginate and soft-delete; check mobile 375px, tablet 768px, desktop 1440px.
5. Confirm immutable frontend bundle, read-only DB schema snapshot and media storage backup.
6. Staged release smoke checks and rollback; no Production deployment before gates 1–5 are evidenced.

**Known gap:** current upload and legacy CMS media may be reachable through public image URLs if chosen for public presentation; add an explicit publication/visibility model and reference protection before using it for internal-only assets.
