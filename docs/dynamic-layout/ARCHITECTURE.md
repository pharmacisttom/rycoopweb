# Dynamic Website Layout & Activity Gallery

Baseline: `43263743647e8f79db67eb452539d15529db61d1`. Implementation branch: `codex/dynamic-layout-gallery`.

The repository has no existing `/admin/layout` manager or layout tables. This implementation adds the manager at `/app/admin/layout` (the existing Vite basename is `/app/`). It preserves all eight actual homepage blocks, including conditional statistics, byte-for-byte inside named existing-feature slots. The supplied prompt's six-section assumption does not match this checkout. Existing News Gallery remains independent; it has a news-specific twenty-image cap and is not repurposed or overwritten.

## Ownership and persistence

Migration 032 adds six InnoDB tables, without modifying existing CMS/member/financial data:

| Table | Purpose |
| --- | --- |
| dynamic_layout_state | Singleton draft JSON, monotonically increasing version and the one current publication pointer |
| dynamic_layout_revisions | Append-only published section/category snapshot; source version, actor, timestamp and rollback origin |
| dynamic_gallery_albums | Paginated draft albums; unique slug, metadata JSON, version and soft archive |
| dynamic_gallery_images | Random immutable image IDs with editable draft alt/caption/order and soft archive |
| dynamic_gallery_publications | Immutable per-publication album/image metadata, indexed by revision, category and slug |
| dynamic_gallery_published_images | Indexed authorization membership for a file in a published revision |

No existing table is guessed or reused for album storage. Migration also registers `layout.view`, `layout.update`, `layout.publish` in the existing permissions catalog, with no automatic grants to existing roles. Super administrators retain their established access. Custom roles can receive these permissions through Access Management; update/publish require view. The existing PR officer permission ceiling is unchanged.

Every mutation first authenticates a live MFA-complete session, verifies permission, then locks singleton row 1 using `SELECT ... FOR UPDATE`. The client must supply the current global `version`; edits, image metadata, upload, ordering and publication increment it. This provides a single coherent draft boundary across independently paginated albums. A stale request returns 409. All data and required audit entries commit together; upload failures additionally remove files created by that failed request.

Publication requires an unconsumed fresh server-side TOTP step-up with exact scope `layout.publish`. Login-time MFA and a grant for another module do not suffice. It inserts immutable section/category and album/image snapshots, builds published-image membership, moves the one publication pointer, increments version and writes required audit in one transaction. Parallel writers serialize on the singleton. Rollback uses scope `layout.rollback`, copies a prior publication into a new revision and moves the pointer; it never replaces the working draft or deletes history. A stale or failed operation may consume its single-use security grant and require fresh TOTP again.

## Section schema and rendering

Sections have server-generated ID, validated parent ID, globally unique section slug, title, type, visibility/archive flags, separate `home` and `navigation` placement, typed config and desktop/tablet/mobile display/column settings. Supported types are hero, news, gallery, banner, cards, statistics, quick links, rich text and existing feature. There is no executable template, SQL, JavaScript, arbitrary CSS or filesystem path field. Trees reject missing parents, cycles, duplicate IDs/slugs and depth beyond eight; at most 500 nodes protect editor/public rendering. Existing-feature references cannot be duplicated while active.

`HomePage` keeps its original hooks/data fetching/widgets. `DynamicHome` composes those unchanged blocks when no publication exists or the layout API is unavailable. Once published, the shared `DynamicSections` renderer uses only the published snapshot, respects ancestor visibility, hierarchy, order and homepage placement, and inserts existing slots where configured. Additional navigation links are independent and additive; the original navbar/footer and public routes remain intact. `/app/sections/:slug`, `/app/gallery`, and `/app/gallery/:slug` provide public content and gallery routes. Gallery/section pages manage title, description and canonical URL while retaining the existing SPA architecture.

The editor uses the same renderer for selected-section previews. Existing-feature previews show a descriptive reference; the authenticated `/app/admin/layout/preview` renders the complete persisted draft through the original HomePage, including its real existing widgets. Unsaved section changes appear in the local editor preview; full-page preview and gallery data reflect the saved draft.

## Gallery and files

Albums have no application-level count cap and paginate twenty per page. Published list responses return only the selected cover/first image plus image count; detail responses return the immutable image list. Nested category filtering includes descendants. Album and image removal soft-archives draft data; old published snapshots and files remain for rollback. History lists the latest thirty publications; all publications remain in storage.

Multipart batches accept 1–12 JPEG/PNG/WebP files, at most 5 MiB each and 20 MiB total. Actual `is_uploaded_file`, byte size, finfo signature, dimensions, pixel count and GD decoding are checked. Pixel re-encoding strips EXIF/GPS and trailing payloads; randomized 128-bit IDs produce full (1920 maximum) and thumbnail (480 maximum) WebP images. The private files live under `storage/private/layout-gallery`, outside the web root, with no original extension/path accepted from the browser. Draft file routes require layout.view; public file routes require membership in an immutable published snapshot. Removing a draft image never deletes historical bytes. A separate future retention/erasure policy must account for these intentionally preserved publications.

## API contracts

Root: `/api/admin/v1/layout`. Responses use `{success:true,data:...}` and standard failure `{success:false,code,message}`. The draft response contains `{state:{sections,categories},version,published_revision_id,history}`. Mutation responses contain the new global version and item/result; clients reload draft after writes. Session actors are derived server-side; client actor/user IDs are ignored. CSRF applies to every mutation.

| Method | Relative path | Operation |
| --- | --- | --- |
| GET | /sections, /draft, /preview | Full current draft workspace; no writes |
| POST | /sections | Create section |
| GET/PATCH/DELETE | /sections/{id} | Read, edit, delete childless section |
| POST | /sections/{id}/subsections | Create child with server-validated parent |
| PATCH | /sections/reorder | Complete unique ID ordering |
| POST | /draft | Save all existing section edits/order as one draft |
| POST/PATCH/DELETE | /categories, /categories/{id} | Create/edit/delete nested category; referenced parents cannot be removed |
| GET/POST | /gallery/albums | Page draft albums / create album |
| GET/PATCH/DELETE | /gallery/albums/{id} | Read/edit/soft-archive album |
| POST | /gallery/albums/{id}/images | Multipart `images[]` batch plus version |
| PATCH | /gallery/albums/{id}/images/order | Complete unique album image ordering |
| PATCH/DELETE | /gallery/albums/{id}/images/{image} | Edit alt/caption / remove from draft |
| GET | /images/{image}/{full\|thumb} | Authenticated private file |
| POST | /publish | Publish saved aggregate with scoped fresh MFA |
| POST | /rollback/{revision} | Create a new publication from history |

Public read-only root `/api/public/v1/layout`: GET root (nullable publication), `/albums?page=N&category=ID`, `/albums/{slug}`, `/images/{revision}/{image}/{full|thumb}`. No initializer, scheduler, publication or content writes run on these reads. Empty/unavailable layout preserves the original homepage fallback. Unsupported/malformed data returns 422, unauthorized 401/403, CSRF 419, stale version 409 and missing resource 404.

The shared Router gains PATCH registration. Only Request's body parsing is adopted for JSON PATCH/DELETE and form bodies; unrelated existing client-IP/password edits are excluded from this feature commit.
