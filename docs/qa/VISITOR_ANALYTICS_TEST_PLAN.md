# RYCOOPWEB 3.0 — Visitor Counter & Web Analytics QA Test Plan

## 1. Automated Test Suite (`tests/VisitorAnalyticsTest.php`)

Execute the automated 20-scenario verification suite via:

```powershell
php tests/VisitorAnalyticsTest.php
```

### Verified Scenarios (20 / 20)

| # | Scenario | Expected Outcome | Status |
| :--- | :--- | :--- | :--- |
| 1 | First visit from a new browser | Increments `today_visitors`, `month_visitors`, `total_visitors`, `online_visitors`, and `today_page_views` to `1`. | PASS |
| 2 | Refreshing the same page repeatedly | Suppressed within 15-second window (`reason = duplicate_suppressed`); counters do not inflate. | PASS |
| 3 | Navigating between public pages | Increments `today_page_views` while keeping `today_visitors` and `today_sessions` at `1`. | PASS |
| 4 | Opening multiple tabs in the same browser | Shares pseudonymous `visitor_id` and `session_id`; does not duplicate unique or online visitor counts. | PASS |
| 5 | Session expiration after inactivity threshold | Activity after >30 minutes rotates the session hash and increments `today_sessions` while keeping `today_visitors` unchanged. | PASS |
| 6 | Visit on a new calendar day | Resets `today_visitors` to `1` on the new `Asia/Bangkok` day while keeping `month_visitors` deduplicated. | PASS |
| 7 | Month boundary transition | Transition from `2026-10-31 23:59:30` to `2026-11-01 00:00:30` resets `month_visitors` while preserving `total_visitors`. | PASS |
| 8 | `Asia/Bangkok` timezone handling | Late-night visits (`23:59:30 +07:00`) are bucketed into the correct Bangkok calendar date. | PASS |
| 9 | Duplicate event submission | Idempotent within 15 seconds; increments `deduplicated_events` observability metric. | PASS |
| 10 | Concurrent event submission | 30 distinct visitor sessions recorded accurately with zero lost updates. | PASS |
| 11 | Bot user-agent exclusion | `Googlebot`, `curl`, `HeadlessChrome`, and `UptimeRobot` rejected (`bot_filtered`) without incrementing public counters. | PASS |
| 12 | Private-route exclusion | `/admin/*`, `/member/*`, `/api/*`, `/login`, `/portal`, `/verify-tax-cert/*`, and MFA routes rejected (`excluded_route`). | PASS |
| 13 | Query-string secret removal | `?token=...&member_no=...&code=...#private` stripped from both `page_url` and `referrer` before DB persistence. | PASS |
| 14 | Rate limiting under burst requests | >60 requests/min from a single visitor returns `rate_limited` (HTTP `429`). | PASS |
| 15 | Public counter API accuracy | Returns true `0` when empty and exact SQL aggregations after traffic; strips admin-only fields on public endpoints. | PASS |
| 16 | Counter widget / API failure state | Database outage returns HTTP `503` with `available: false` and `null` counters (never fabricated fallback values). | PASS |
| 17 | SQL injection resistance | Malicious SQL payloads in `visitor_id`, `session_id`, `page_url`, `page_title`, and `referrer` safely neutralized. | PASS |
| 18 | Unauthorized access to Admin Analytics | `member` and unpermitted `staff` roles receive HTTP `403` on `/api/admin/analytics/visitors`; `super_admin` receives `200`. | PASS |
| 19 | Export permission validation | `POST /api/admin/analytics/visitors/export` enforces `analytics.visitors.export` / `audit.export` and writes an `audit_logs` record. | PASS |
| 20 | Performance under expected traffic | 30 sequential visits + aggregations complete in `< 20 ms` (well within the `< 2500 ms` budget). | PASS |

---

## 2. Frontend & Regression Verification Commands

```powershell
node tests/PublicContentReferenceTest.mjs
node tests/NavigationRedirectTest.mjs
node tests/WorkflowFrontendTest.mjs
php tests/AdminControlCenterTest.php
php tests/SecurityRegressionTest.php
php tests/WorkflowRoutesTest.php
npm run build
```
