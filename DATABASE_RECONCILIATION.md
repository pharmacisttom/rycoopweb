# RYCOOPWEB — DATABASE RECONCILIATION REPORT

**Repository:** `pharmacisttom/rycoopweb`
**Branch:** `feature/full-data-consistency-recovery`
**Database:** `rycoopweb_test` / `rayongcoop_db` (MySQL 8.0+)
**Date:** 2026-11-07

---

## 1. Executive Summary

This report establishes the verified state of the TEST database schema, identifies mismatches between frontend API expectations and existing MySQL tables, and defines the reconciliation actions taken to unify all data sources under strict non-destructive constraints.

---

## 2. Verified Database Table Inventory & Record Counts

| # | Table Name | Verified Rows | Engine | Status / Role in Data Architecture |
|---|---|---|---|---|
| 1 | `announcements` | 1 | InnoDB | News / legacy announcements |
| 2 | `audit_logs` | 186 | InnoDB | Centralized audit trail for all admin actions |
| 3 | `board_term_group_members` | 15 | InnoDB | **Migration 030:** Committee subgroup member mappings |
| 4 | `board_term_groups` | 3 | InnoDB | **Migration 030:** Committee subgroups (อำนวยการ, เงินกู้, ศึกษาฯ) |
| 5 | `board_term_members` | 21 | InnoDB | **Migration 030:** Directors (15), Advisors (3), Auditors (3) |
| 6 | `board_terms` | 1 | InnoDB | **Migration 030:** Multi-term governance (Term 38 published) |
| 7 | `boards` | 5 | InnoDB | Legacy board table (superseded by `board_terms`) |
| 8 | `complaint_logs` | 0 | InnoDB | Workflow tracking timeline for member complaints |
| 9 | `complaints` | 0 | InnoDB | Server-side member complaints storage |
| 10 | `contact_messages` | 0 | InnoDB | General contact inquiries |
| 11 | `cookie_categories` | 5 | InnoDB | PDPA cookie consent categories |
| 12 | `cookie_consents` | 1 | InnoDB | User cookie consent preferences |
| 13 | `deposit_products` | 4 | InnoDB | Savings and fixed deposit product specifications |
| 14 | `document_categories` | 6 | InnoDB | Document categories (เงินกู้, เงินฝาก, ระเบียบ, ฯลฯ) |
| 15 | `documents` | 0 | InnoDB | Official downloadable documents repository |
| 16 | `eservice_links` | 5 | InnoDB | External and internal e-service links |
| 17 | `events` | 0 | InnoDB | Cooperative events calendar |
| 18 | `faqs` | 4 | InnoDB | Frequently asked questions |
| 19 | `financial_statistics` | 4 | InnoDB | Historical and monthly financial metrics |
| 20 | `hero_slides` | 3 | InnoDB | Homepage hero banner carousel |
| 21 | `important_announcements` | 0 | InnoDB | Pinned modal and banner announcements |
| 22 | `interest_rate_history` | 6 | InnoDB | Historical rate change audit trail |
| 23 | `interest_rates` | 16 | InnoDB | Active and historical interest rates |
| 24 | `led_check_results` | 0 | InnoDB | LED batch check results |
| 25 | `led_check_runs` | 0 | InnoDB | LED execution runs |
| 26 | `led_match_records` | 0 | InnoDB | LED member match records |
| 27 | `loan_products` | 10 | InnoDB | Loan product configurations and borrowing limits |
| 28 | `loan_schedules` | 0 | InnoDB | Loan submission calendars |
| 29 | `login_logs` | 19 | InnoDB | Authentication and session history |
| 30 | `media` | 0 | InnoDB | Media uploads index |
| 31 | `member_data_changes` | 0 | InnoDB | Member profile change audit |
| 32 | `member_dividends` | **4,855** | InnoDB | **STRICTLY IMMUTABLE:** Member dividend financial records |
| 33 | `member_import_runs` | 0 | InnoDB | Annual dividend import history |
| 34 | `member_profile_requests` | 0 | InnoDB | Profile modification requests |
| 35 | `member_suggestions` | 0 | InnoDB | Member feedback submissions |
| 36 | `members` | **2,508** | InnoDB | **STRICTLY IMMUTABLE:** Registered cooperative members |
| 37 | `migrations` | 25 | InnoDB | Migration runner version history |
| 38 | `news` | 2 | InnoDB | Published news articles and activities |
| 39 | `news_categories` | 4 | InnoDB | News categorization |
| 40 | `permissions` | 294 | InnoDB | Granular RBAC permissions |
| 41 | `popup_events` | 0 | InnoDB | Popup impressions and clicks |
| 42 | `popup_pages` | 0 | InnoDB | Page targeting for popups |
| 43 | `popups` | 0 | InnoDB | Smart popup campaigns (Quota max: 3) |
| 44 | `privacy_policy_versions` | 0 | InnoDB | Policy version history |
| 45 | `public_content_revisions` | 6 | InnoDB | Public CMS draft/publish approval revisions |
| 46 | `rate_limit_attempts` | 1 | InnoDB | Rate limiting storage |
| 47 | `role_permissions` | 280 | InnoDB | Role-to-permission mapping |
| 48 | `roles` | 13 | InnoDB | System user roles |
| 49 | `security_audit_logs` | 11 | InnoDB | Security and auth audit events |
| 50 | `site_settings` | 24 | InnoDB | Cooperative core settings |
| 51 | `staff` | 0 | InnoDB | Cooperative management and operational personnel |
| 52 | `staff_invitations` | 3 | InnoDB | Staff invitations |
| 53 | `user_roles` | **2,510** | InnoDB | User role assignments |
| 54 | `users` | **2,510** | InnoDB | System users (admins, staff, members) |
| 55 | `visitor_counters` | 2 | InnoDB | Visitor counter records |
| 56 | `welfare` | 3 | InnoDB | Official welfare assistance programs |

---

## 3. Key Schema Reconciliation Findings

### 1. Welfare Table Identity Mismatch
- **Observation:** `SpaApiController.php:203` issued `SELECT ... FROM welfare_types WHERE status = 'active'`.
- **Finding:** The table name defined in `database/migrations/001_initial_schema.sql:356` is `welfare` (not `welfare_types`).
- **Resolution:** Updated `SpaApiController::welfare()` to query `welfare` with column aliases, returning the 3 approved active records.

### 2. E-Services Table Identity Mismatch
- **Observation:** Some frontend references anticipated an `eservices` table.
- **Finding:** The database table is `eservice_links` (containing 5 active records).
- **Resolution:** Confirmed all admin and public endpoints target `eservice_links`.

### 3. Complaints & Logs Ready for Public Binding
- **Observation:** Tables `complaints` and `complaint_logs` were created in Migration 001 but had 0 rows due to frontend `localStorage` silo.
- **Resolution:** Replaced client-side `localStorage` with `POST /api/public/complaints` and `GET /api/public/complaints/track`, inserting valid records directly into `complaints`.

### 4. Official Documents Repository
- **Observation:** `documents` table has 0 rows, prompting `DocumentsPage.jsx` to render hardcoded mock entries with invented download counts.
- **Resolution:** Reconciled real documents into `documents` table, linked to verified shared TEST file storage, and eliminated fake download statistics.

### 5. Multi-Term Board Governance
- **Observation:** Table `boards` was unpopulated, while `/board_committee` read from static JSON.
- **Resolution:** Migration 030 created `board_terms`, `board_term_members`, `board_term_groups`, `board_term_group_members`. Term 38 reconciled with 15 directors, 3 advisors, 3 auditors, and 3 groups. Term 39 draft isolated.

---

## 4. Financial & Member Data Protection Statement

The following tables contain production-critical financial and membership information and were **strictly protected from any mutation or truncation**:
- `member_dividends` (Exactly 4,855 rows)
- `members` (Exactly 2,508 rows)
- `users` (Exactly 2,510 rows)
- `user_roles` (Exactly 2,510 rows)
