# RC22 — Icon system, Impeccable audit and Playwright acceptance

## Production baseline and scope
- Baseline: RC21 Modern Glass Admin, tested release; development branch isolates changes.
- Public: keep existing Lucide navigation and content icons, introduce **self-hosted Simple Icons** only for actual service brands (Google, Facebook, LINE).
- Admin: keep route-based Lucide set from RC20; no permission, sidebar route, role or session logic changes.
- No structural shell patch; the previously canceled overlapping-header layout patch is **not** included.

## Design decisions (Impeccable-inspired audit)
- Visual hierarchy: 18 px branded marks with text, 16–22 px Lucide task symbols; no unlabeled icon-only action.
- Clear semantics: Lucide for generic actions/navigation; Simple Icons reserved for verified product identities.
- Accessible interaction: text label persists, focus visible, touch target min 44 px where actionable; icon itself decorative.
- Performance/privacy: brand SVG included locally in Vite asset graph; no third-party icon CDN, runtime injection or new npm dependency.
- Brand identity: retain official cooperative logo; no vendor mark replaces the cooperative mark.
- Responsive: browser coverage at 390 px and 1280 px; horizontal overflow is an error.
- Motion: respect prefers-reduced-motion and avoid animation-based critical affordances.
- Dark themes: ensure white brand marks in dark footer and visible, not color-only, active states.
- No false visual states: mock fixture contains synthetic menu entries only and does not simulate a successful staff login.

## Test matrix
| Test | Purpose |
|---|---|
| Source contract | Confirms self-hosted Simple Icons, existing Lucide admin mappings, focus and labels |
| RC22 Playwright Chrome | Real SVG load, correct brand icons, Lucide menu, published links, mobile/desktop overflow, JS errors |
| Existing Admin browser suite | Sidebar, RBAC UI, critical actions, modals and security gates |
| Existing security/php/media QA | Backend unchanged |
| Production build | No unresolved imports, valid bundled assets |

## Release controls
1. CI green on pinned commit and all mandatory checks.
2. Diff only UI, SVG assets, documentation and tests.
3. Immutable candidate build and HTTP smoke in staging or separate release.
4. No credential automation against real production; run authorized MFA UAT manually.
5. Confirm Browser visual acceptance before promoting or declaring feature complete.
