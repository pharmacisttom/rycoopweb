# RYCOOPWEB — RC03 DATABASE MIGRATION & DATA INTEGRITY PLAN

**Repository:** `pharmacisttom/rycoopweb`  
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Target Database:** `rycoopweb_test`  
**Production Status:** STRICTLY FROZEN  
**Baseline Commit:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`  

---

## 1. VPS Baseline vs Development Database Investigation

### 1.1 The Authoritative VPS Baseline
On the active TEST VPS server (`https://test.rayongcoop.org` / `rycoopweb_test`), the verified database record counts are:

```sql
SELECT COUNT(*) FROM members;                         -- Returns: 2,508
SELECT COUNT(*) FROM member_dividends;                -- Returns: 4,855
SELECT COUNT(*) FROM users;                           -- Returns: 2,509
SELECT COUNT(DISTINCT user_id) FROM members;          -- Returns: 2,508
```

### 1.2 Account Mapping on VPS
- **Member Users (2,508 records):** User IDs 2 through 2,509 map 1-to-1 with the 2,508 member accounts.
- **Super Administrator (1 record):** User ID 1 (`username: admin`, `role: super_admin`).
- **Mathematical Formula:** `2,508 Members + 1 Super Admin (ID 1) = 2,509 Users`.

### 1.3 Development Environment Discrepancy
- In local development databases, `users` may report `2,510` due to a dedicated test fixture (User ID 2510 `memberadmin`, `role: member_admin`) seeded during dual-control segregation of duties tests.
- **Strict Invariant Rule:** **DO NOT CREATE, DELETE, OR MODIFY ANY USER OR MEMBER ACCOUNTS TO RECONCILE COUNTS.** The VPS baseline of 2,509 users is authoritative and must remain strictly untouched on the TEST server.

---

## 2. Core Immutability Invariants

The following tables and record counts are **STRICTLY IMMUTABLE** and cannot be altered, dropped, or modified by any migration or script:

| Table Name | Authoritative VPS Count | Integrity Rule |
|:---|:---:|:---|
| `member_dividends` | **4,855** | Annual dividend payouts; zero updates or deletions permitted |
| `members` | **2,508** | Core cooperative membership ledger |
| `users` | **2,509** | Authentication credentials & cryptographic nonces |
| `user_roles` | **2,509** | RBAC permission bindings |

---

## 3. Forward Migration Specification: Migration 030

### Migration File: `database/migrations/030_create_board_term_management_tables.sql`
- **Schema Additions (Additive Only):**
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

## 4. Rollback Isolation Architecture & Governance

In compliance with the RC02 migration safety gate, all rollback scripts reside strictly in `database/rollbacks/`:
- `database/rollbacks/029_rollback_popup_campaign_manager_v2_fields.sql`
- `database/rollbacks/030_rollback_board_term_management_tables.sql`

The forward migration runner (`MigrationRunner.php`) autodiscoveres files strictly from `database/migrations/`, completely preventing unintended destructive execution during CI or deployment.

> [!WARNING]
> **CODE ROLLBACK NEVER DROPS TABLES:**
> In the event of a code rollback on the TEST server, the code symlinks are reverted to the baseline release (`ORIG_BACKEND` & `ORIG_STATIC`). The additive Migration 030 tables and any committee term data **remain untouched** in the database.
>
> Dropping tables via `database/rollbacks/030_rollback_board_term_management_tables.sql` is strictly an exceptional manual disaster recovery procedure.

**Accurate Reverse Foreign-Key Table Drop Order:**
1. `DROP TABLE IF EXISTS board_term_group_members;`
2. `DROP TABLE IF EXISTS board_term_groups;`
3. `DROP TABLE IF EXISTS board_term_members;`
4. `DROP TABLE IF EXISTS board_terms;` *(Note: Stale `board_term_terms` reference removed).*

---

## 5. Single-File Migration Deployment Checklist for TEST

1. **Pre-Flight Validation & Dry Run:**
   ```bash
   cd /var/www/rycoopweb/releases/${TARGET_SHA}

   # Load environment securely from shared-test/.env
   set -a
   [ -f /var/www/rycoopweb/shared-test/.env ] && . /var/www/rycoopweb/shared-test/.env
   set +a

   # Run automated regression tests
   php tests/MigrationRunnerTest.php

   # Execute pre-flight dry-run targeting single file 030
   php bin/console db:migrate --dry-run --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql
   ```

2. **Execute Forward Single-File Migration:**
   ```bash
   php bin/console db:migrate --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql
   ```

3. **Verify Table Creation:**
   Verify `board_terms`, `board_term_members`, `board_term_groups`, `board_term_group_members` exist in `rycoopweb_test` (total tables = 38).

4. **Term Governance & Seeding Rule:**
   - Migration 030 is schema-only.
   - Do **NOT** automatically seed or publish term 38 or term 39 during migration.
   - Committee Term 38 must remain publicly visible to website visitors at all times until an authorized replacement term is explicitly published.
   - Committee Term 39 must remain an unapproved draft isolated within the administrative workflow.

5. **Verify Core Financial & Member Invariants:**
   Confirm `member_dividends = 4,855`, `members = 2,508`, `users = 2,509`.
