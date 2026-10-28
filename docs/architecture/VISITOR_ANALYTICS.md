# RYCOOPWEB 3.0 — Visitor Counter & Web Analytics Architecture

## 1. Root Cause Audit of the Legacy Implementation

Prior to RYCOOPWEB 3.0, the visitor counter and analytics subsystem exhibited four critical architectural flaws:

1. **Inconsistent / Missing Public Display**:
   - Neither `src/components/layout/Footer.jsx` nor `src/pages/HomePage.jsx` mounted a visitor counter widget.
   - Public visitors could only view statistics if they manually navigated to `/visitor-stats`, which was not linked from the public footer.
2. **Fabricated & Hardcoded Numbers**:
   - `VisitorCounterService::getPublicStats()` added arbitrary offsets (`+ 342` today, `+ 895` yesterday, `+ 14280` month, `+ 98500` year, `max($onlineRaw, 5)`) and returned fake fallback arrays on database exceptions.
   - `VisitorCounterService::getDetailedAnalytics()` and `src/pages/VisitorStatsPage.jsx` (`getFallbackAnalytics()`) generated synthetic curves, hardcoded device percentages (`62.4% / 33.1% / 4.5%`), and fabricated recent visitor rows.
3. **Incomplete Route & Privacy Hygiene**:
   - `src/hooks/useVisitorTracker.js` used `Math.random()` in `sessionStorage` and sent raw `location.pathname + location.search` for every route transition, including `/admin/*`, `/member/*`, `/login`, and URLs with query-string tokens.
4. **Lack of Bot Filtering & Duplicate Suppression**:
   - Automated crawlers, headless browsers, and health checks were recorded as human visits, and rapid page refreshes incremented page views without idempotency protection.

---

## 2. Corrected Architecture & Metric Definitions (Asia/Bangkok `+07:00`)

All metrics are computed strictly from real database records in `website_visits` and `visitor_counters` using `Asia/Bangkok` (`+07:00`) calendar boundaries:

| Metric | Thai Label | Definition |
| :--- | :--- | :--- |
| **Page Views** | จำนวนการเปิดหน้าเว็บ | Total accepted public page-view events after bot filtering, route validation, and 15-second same-page duplicate suppression. |
| **Unique Visitors** | ผู้เข้าชมไม่ซ้ำ | Distinct pseudonymous visitor hashes (`visitorHash`, 16-char HMAC-SHA256 prefix stored in `website_visits.session_id` as `visitorHash:sessionHash`) within the requested period. |
| **Sessions** | เซสชันการใช้งาน | Distinct `visitorHash:sessionHash` pairs separated by a 30-minute (`1800s`) inactivity threshold enforced on both client and server. |
| **Online Visitors** | กำลังใช้งานเว็บไซต์ | Distinct `visitorHash` identifiers with accepted public page-view activity within the previous 5 minutes (`300s`). |
| **Daily Visitors** | ผู้เข้าชมวันนี้ | Distinct `visitorHash` identifiers between `00:00:00` and `23:59:59` `Asia/Bangkok`. |
| **Monthly Visitors** | ผู้เข้าชมเดือนนี้ | Distinct `visitorHash` identifiers from the 1st of the current `Asia/Bangkok` calendar month. |
| **Total Visitors** | ผู้เข้าชมทั้งหมด | Distinct `visitorHash` identifiers across the retained measurement dataset. |

> **Documented Metric Limitation**: Pseudonymous visitor identifiers represent browser/device instances within the retention window and must not be interpreted as guaranteed unique human beings across devices or after browser storage clearing.

---

## 3. Privacy, Security & Bot Filtering Controls

- **Public Route Allowlist & Private Route Exclusion**:
  - `VisitorCounterService::normalizeAllowedPublicPath()` and `isTrackablePublicPath()` in `src/hooks/useVisitorTracker.js` strictly exclude `/admin/*`, `/member/*`, `/api/*`, `/login`, `/portal`, `/dashboard`, `/staff/*`, `/profile`, `/settings`, `/verify-tax-cert/*`, and MFA routes.
- **Query-String Secret Removal**:
  - Query strings (`?token=...`, `?code=...`, `?member_no=...`) and URL fragments (`#...`) are stripped before storage on both `page_url` and `referrer`.
- **PDPA IP Masking**:
  - Raw IP addresses are never used as primary visitor identifiers and are masked via `PdpaService::maskIpAddress()` before persistence.
- **Bot & Synthetic Traffic Filtering**:
  - `VisitorCounterService::isBotUserAgent()` filters crawlers, spiders, headless browsers, `curl`, `wget`, `python-requests`, `Lighthouse`, and uptime monitors, recording their count separately in `visitor_counters` (`bot_filtered_events`) without inflating public counters.
- **Duplicate Suppression & Rate Limiting**:
  - Same-page hits within 15 seconds are suppressed (`duplicate_suppressed`, tracked under `deduplicated_events`).
  - Visitors exceeding 60 events per minute receive HTTP `429` (`rate_limited`, tracked under `rate_limited_events`).
- **True Zero vs. Unavailable State**:
  - Empty tables return `0` with `available: true`.
  - Database failures return HTTP `503` with `available: false` and `null` counters so the UI renders `"ไม่สามารถโหลดข้อมูลได้"` with a Retry action (or marks cached verified data as stale) — never fabricated numbers.

---

## 4. Non-Executed Optional Database Index Proposal

The enhancement operates with 100% compatibility on the existing `021_create_visitor_counter_tables.sql` schema (`website_visits` and `visitor_counters`). No migrations are executed automatically. For high-volume production optimization, the following additive composite index can be reviewed and applied manually by the DBA:

```sql
-- Optional composite index for faster session & path deduplication lookups (Manual DBA Review Only)
ALTER TABLE `website_visits`
  ADD INDEX `idx_session_page_visited` (`session_id`, `page_url`, `visited_at`);
```
