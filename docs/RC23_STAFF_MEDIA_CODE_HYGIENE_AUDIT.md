# RC23 — Code Hygiene / Loop & Section Integrity Review

## Verified defects and patches
| Severity | Area | Observation | Resolution |
| --- | --- | --- | --- |
| High | staff editor | Staff image was URL-only; file-upload UI existed exclusively in the boards module | Staff directory, dedicated upload and preview with retained draft/publish |
| High | permissions | Person editor permission `people.update` did not grant media upload; generic endpoint enforced `news.create` | Separate `/api/admin/media/people` with Auth, people.update, CSRF; only staff/board folders |
| High | section switching | Inaccessible `?section=staff` silently showed first available section | Explicit access warning, no fallback |
| High | async upload | Selected person or section could change during upload | Disable switching and apply update only when section, index and person ID match |
| Medium | deep-link navigation | Back/forward could leave query string and dirty editor out of sync | Restore edited section while dirty; clear old editor for denied section |
| Medium | test maintenance | Board contract was hardcoded to legacy `uploadBoardPhoto` name | Updated contract for shared uploader while retaining board UI assertions |

## Direct first-party portrait upload UX (user acceptance)
- Staff and board editors display a first-party JPEG/PNG/WebP upload control directly in the selected person card.
- The external editable image URL field is removed from people editing forms.
- Upload is retained as a Media Library record under an internal `staff` / `board` folder, converted to WebP by the existing image-processing backend.
- A person preview appears immediately after upload, but changes to public pages still require draft save and authorized publication.
- There is an explicit Remove from Draft control; removing a portrait from a draft is not an uncontrolled file deletion.
- There is no dependency on pasting links from external sites or navigating away to upload from another module.
- Image selection still requires choosing a local image (or device camera, where the browser supports it); the server cannot create a portrait without a source image.

## Remaining tech debt (not modified in RC23)
1. `PublicContentAdminPage.jsx` remains a large multi-module component. Extracting tab-specific editors (staff, boards, products, rates, history) would reduce state coupling. Do this in a separate reviewed refactor, **not** while fixing production data integrity.
2. Legacy CMS page uses many inline callbacks and a shared `selected` index. A row ID is safer long term. This RC uses both ID and index to block wrong attachments.
3. The public content APIs expose multiple sections through one revision workflow. Keep the revision service authoritative; avoid separate per-page DB writers.
4. No real user-photo upload, MFA or production publication was run in CI. Synthetic Playwright and PHP fixtures do not prove actual storage permissions, session authorization, or live media delivery.
5. Running `reload()` after publish and a separate refresh of revision history are legitimate distinct operations, not evidence of a runaway API loop.

## Release and acceptance gates
- Build and all GitHub CI jobs green at a pinned commit, including staff Playwright QA.
- Back up DB and media manifest and verify active Production release before any patch.
- Authenticated UAT with a people.update role: select correct person, upload, draft, preview, publish and verify public page (with second publisher where configured).
- Negative tests: account missing people.update returns 403; invalid image >10MB rejected; after section navigation no stale data is displayed; no update to `boards`.
- Audit that stored media URL is accessible to public page only after authorized publication.
- Preserve all previous RC21 frontend, RC19 mega menu, RBAC and existing production staff data.

## Recommended next cleanup stage
- Add a central media picker for staff and board photos with authorization-aware previews.
- Split `PublicContentAdminPage.jsx` into section-specific editor components and a revision table subcomponent only with comprehensive browser coverage.
- Add Playwright network-request counting to ensure exactly one read per section change (excluding intentional development StrictMode double effects).
- Introduce stable `row.id` selection rather than index and verify reordering/duplicates.
