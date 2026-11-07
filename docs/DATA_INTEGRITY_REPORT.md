# RYCOOPWEB — DATA INTEGRITY & AUDIT REPORT

**Repository:** `pharmacisttom/rycoopweb`
**Database:** `rayongcoop_db` (MySQL 8)
**Target Environment:** TEST ONLY (`test.rayongcoop.org`)
**Audit Timestamp:** 2026-11-07
**Production Status:** STRICTLY FROZEN

---

## 1. Executive Summary

This data integrity audit verified the real contents of `rayongcoop_db` using read-only SQL queries. All figures reported below reflect **genuine database state**. In compliance with project rules:
- No hardcoded member counts or mock constants are used.
- All personal identifiable information (PII) such as citizen IDs, member names, and email addresses are masked in this report.
- Zero mock placeholder figures (e.g., fabricated 245,000 shares, 315,000 deposits, 380,000 loans) exist in the system.

---

## 2. Real Database Inventory

| Category | Table Name | Verified Record Count | Status Breakdown & Details |
|---|---|:---:|---|
| **Member Master** | `members` | **2,508** | • 2,508 active members (`status = 'active'`)<br>• 0 resigned / 0 suspended |
| **Historical Dividends** | `member_dividends` | **4,855** | • Year 2567: 2,429 records<br>• Year 2568: 2,426 records<br>• Total dividend ledger rows: 4,855 |
| **User Authentication** | `users` | **2,510** | • 2,508 authenticated member accounts<br>• 2 staff / administrator accounts |
| **Role Bindings** | `user_roles` | **2,510** | • 1 `super_admin`<br>• 1 `staff`<br>• 2,508 `member` role mappings |
| **Security Permissions** | `permissions` | **276** | • 23 distinct functional modules<br>• 12 standard actions (view, create, edit, delete, publish, etc.) |
| **Hero Slides** | `hero_slides` | **3** | • 3 active banners configured for homepage |
| **Popup Campaigns** | `popups` | **0** | • 0 active campaigns (Clean empty state required) |
| **Welfare Schemes** | `welfare` | **3** | • 3 active welfare programs |
| **E-Services** | `eservice_links` | **5** | • 5 active public gateway service links |
| **Board of Directors** | `boards` | **5** | • 5 active directors and advisors |
| **Staff Personnel** | `staff` | **0** | • 0 staff entries (Clean empty state required) |
| **Loan Products** | `loan_products` | **10** | • 10 active loan product configurations |
| **Deposit Products** | `deposit_products` | **4** | • 4 active deposit schemes |
| **Document Categories** | `document_categories` | **6** | • 6 master categories (Regulations, Reports, Forms, etc.) |
| **Documents Catalog** | `documents` | **0** | • 0 files uploaded (Clean empty state required) |
| **News & Announcements**| `news` | **2** | • 2 published articles |
| **Audit Logs** | `audit_logs` | **136** | • 136 recorded administrative actions |
| **Security Audit Logs** | `security_audit_logs` | **8** | • 8 cryptographic and MFA security events |

---

## 3. Identity Mapping Reconciled

The member identity architecture was inspected to verify the 1-to-1 relationship between `users` and `members`:

```sql
SELECT
    COUNT(m.id) AS total_members,
    COUNT(u.id) AS mapped_users,
    COUNT(CASE WHEN m.user_id IS NULL THEN 1 END) AS unmapped_members
FROM members m
LEFT JOIN users u ON m.user_id = u.id;
```

**Reconciliation Outcome:**
- **Total Registered Members:** 2,508
- **Mapped User Accounts:** 2,508
- **Unmapped Members:** 0 (100% 1-to-1 identity mapping verified)
- **Duplicate Member Numbers (`member_no`):** 0
- **Duplicate Citizen IDs (`id_card`):** 0

---

## 4. PII Protection & Field-Level Masking Verification

To ensure compliance with PDPA (Personal Data Protection Act) regulations:
- Citizen IDs (`id_card`) are masked in all standard listing APIs: `32101XXXXXX12` (first 3 digits and last 2 digits visible, middle 8 digits masked).
- Member numbers preserve exact leading zeros (e.g., `00001`, `01234`).
- Password hashes and TOTP secrets are excluded from all Member Admin API serialization.
- Financial aggregates return `DATA_NOT_AVAILABLE` when detailed loan or deposit records have not been imported, preventing fabricated numbers from surfacing.

---

## 5. Conclusion & Data Safety Gate

- [x] Actual database schema verified directly against MySQL `information_schema`.
- [x] 2,508 members and 4,855 dividend records preserved and untouched.
- [x] Zero financial write operations executed against member ledgers.
- [x] Disposable test fixtures verified to run in memory or isolated databases without touching `rayongcoop_db`.
