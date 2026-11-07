# RYCOOPWEB — RC03 DATABASE MIGRATION & DATA INTEGRITY PLAN

**Repository:** `pharmacisttom/rycoopweb`  
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Target Database:** `rycoopweb_test`  
**Production Status:** STRICTLY FROZEN  
**Baseline Commit:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`  
**Integration Commit:** `3317da8`  

---

## 1. User Discrepancy Investigation: `users = 2,509` vs `users = 2,510`

### 1.1 The Investigation
A thorough investigation was conducted into the discrepancy between historical inventory counts citing `users = 2,509` versus active database query results showing `users = 2,510`:

```sql
SELECT COUNT(*) FROM members;                         -- Returns: 2,508
SELECT COUNT(*) FROM users;                           -- Returns: 2,510
SELECT COUNT(DISTINCT user_id) FROM members;          -- Returns: 2,508
```

### 1.2 Exact Account Inventory
Every member account (2,508 records) maps 1-to-1 with a user record (User IDs 2 through 2,509).

Non-member accounts:
1. **User ID 1:** `username: admin`, `email: pharmacisttom@gmail.com`, `role: super_admin` (Primary Platform Super Administrator).
2. **User ID 2510:** `username: memberadmin`, `email: memberadmin@member-admin.invalid`, `role: member_admin` (Dedicated Member Administration Officer seeded for Dual-Control SoD).

### 1.3 Resolution & Record
- **Mathematical Formula:** `2,508 Members + 1 Super Admin (ID 1) + 1 Member Admin (ID 2510) = 2,510 Users`.
- Historical documentation referencing `2,509` users performed a naive calculation of `2,508 members + 1 super admin`, omitting the dedicated `memberadmin` service identity.
- **Action Taken:** ZERO modifications made to user accounts. All 2,510 user accounts and 2,508 member accounts are preserved with 100% data integrity.

---

## 2. Core Immutability Invariants

The following tables and record counts are **STRICTLY IMMUTABLE** and cannot be altered, dropped, or modified by any migration or script:

| Table Name | Immutable Row Count | Integrity Rule |
|:---|:---:|:---|
| `member_dividends` | **4,855** | Annual dividend payouts; zero updates or deletions permitted |
| `members` | **2,508** | Core cooperative membership ledger |
| `users` | **2,510** | Authentication credentials & cryptographic nonces |
| `user_roles` | **2,510** | RBAC permission bindings |

---

## 3. Forward Migration Specification: Migration 030

### Migration File: `database/migrations/030_create_board_term_management_tables.sql`
- **Schema Additions:**
  1. `board_terms`:
     - `id` (BIGINT UNSIGNED PK)
     - `term_number` (INT UNSIGNED NOT NULL UNIQUE)
     - `term_name` (VARCHAR(100) NOT NULL)
     - `start_date`, `end_date`, `published_at`, `publish_at`
     - `status` (ENUM: `draft`, `scheduled`, `published`, `archived`)
     - `is_current` (TINYINT(1) DEFAULT 0)
     - `lock_version` (INT UNSIGNED DEFAULT 1)
  2. `board_term_members`:
     - `id` (BIGINT UNSIGNED PK)
     - `term_id` (FK -> `board_terms.id` ON DELETE CASCADE)
     - `category` (ENUM: `director`, `advisor`, `auditor`)
     - `name`, `position`, `workplace`, `department`, `image_url`, `is_chairperson`, `sort_order`
  3. `board_term_groups`:
     - `id` (BIGINT UNSIGNED PK)
     - `term_id` (FK -> `board_terms.id` ON DELETE CASCADE)
     - `title`, `description`, `sort_order`
  4. `board_term_group_members`:
     - `id` (BIGINT UNSIGNED PK)
     - `group_id` (FK -> `board_term_groups.id` ON DELETE CASCADE)
     - `member_id` (FK -> `board_term_members.id` ON DELETE CASCADE)
     - `role_in_group`, `sort_order`

- **Safety Classification:**
  - Destructive statements: **0 (Zero)**
  - Financial mutations: **0 (Zero)**
  - Forward-only, repeatable where designed, zero locks on financial tables.

---

## 4. Rollback Isolation Architecture

In compliance with the RC02 migration safety gate, all rollback scripts reside strictly in `database/rollbacks/`:
- `database/rollbacks/029_rollback_popup_campaign_manager_v2_fields.sql`
- `database/rollbacks/030_rollback_board_term_management_tables.sql`

The forward migration runner (`MigrationRunner.php`) autodiscoveres files strictly from `database/migrations/`, completely preventing unintended destructive execution during CI or deployment.

---

## 5. Migration Deployment Checklist for TEST

1. **Pre-Flight Validation (CLI Dry Run & Unit Tests):**
   ```bash
   php tests/MigrationRunnerTest.php
   php bin/console db:migrate --dry-run --db-name=rycoopweb_test
   ```
2. **Execute Forward Migration:**
   ```bash
   php bin/console db:migrate --db-name=rycoopweb_test
   ```
3. **Verify Table Creation:**
   Verify `board_terms`, `board_term_members`, `board_term_groups`, `board_term_group_members` exist in `rycoopweb_test` (total tables = 38).
4. **Term Governance & Seeding Rule:**
   - Migration 030 is schema-only.
   - Do **NOT** automatically seed or publish term 38 or term 39 during migration.
   - Committee Term 38 remains the active published term for public display.
   - Committee Term 39 remains an unapproved draft isolated within the administrative workflow.
5. **Verify Core Financial & Member Counts:**
   Confirm `member_dividends = 4,855`, `members = 2,508`, `users = 2,510`.
