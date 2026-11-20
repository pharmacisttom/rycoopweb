# RC15 Homepage Highlight & Layout — workstream 2

This branch is **independent** of RC14 board management.

## Implemented in source
- `HomeCompositionService` published/draft snapshots in existing `site_settings`, version conflict check, strict validation of 0–6 slides, 5–6 required when enabling highlights for publication, audit log and transactional publish.
- API: `GET /api/public/home-composition`, protected admin state/draft/publish with CSRF + Super Admin + fresh MFA.
- Admin `/app/admin/layout`: RC15 composition editor with section ordering controls, show/hide flags, 3 themes, autoplay interval, slide fields, preview, draft and publish.
- Home page: fetches **published only** composition, adds Highlight Slider after Hero, moves rate/news cards beneath the slider. Slider pauses on hover/focus, supports manual controls and reduced-motion preferences.
- Existing layout/dynamic-layout editors are retained and not replaced.

## Limitations / not yet accepted
- The public homepage now maps published `order` onto semantic DOM order for all 8 built-in sections. Browser QA is still required to verify real layout, focus order and responsiveness.
- Editor supports safe predefined backgrounds and section spacing layers (plus global theme). Arbitrary image overlays, free text positioning and fully draggable nested layers are NOT YET implemented; do not describe it as a complete pixel-based page builder.
- No generated example 'achievements' are published; real highlight content must be supplied/reviewed by authorized cooperative staff.
- No production deployment; TEST compatibility and browser UAT are mandatory.

## Acceptance before production
1. PHP lint service/controller/routes/test; React build with `npm ci` postinstall; run `php tests/HomeCompositionRc15ValidationTest.php` and `php tests/HomeCompositionRc15ContractTest.php`.
2. Test the validation rules in disposable MySQL (reject malformed link, too few slides, duplicates, invalid order; optimistic locking).
3. Verify public DOM ordering of all 8 sections via Browser QA after moving sections in published configuration.
4. Authorized TEST MFA save draft, publication, audit record and reload.
5. Check mobile, desktop, reduce-motion, focus navigation, link whitelist, visual contrast and responsive behavior.
6. Verify old layout manager and published content remain intact.
7. Production promotion from actual live baseline with no DB migration without explicit verification.
