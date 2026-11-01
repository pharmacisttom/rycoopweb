# RYCOOPWEB 3.0 — Legacy Schema Compatibility & Migration Report

**Date:** 2026-10-31
**Environment:** TEST (`rycoopweb_test`)
**Legacy Dump Source:** `rayongcoop_db.sql` (MariaDB 10.4.32)
**Target Database Engine:** MySQL 8.0 (InnoDB, utf8mb4_unicode_ci)
**Feature Branch:** `codex/legacy-member-dividend-migration`

---

## 1. Executive Summary

This audit assesses the physical structure, data integrity, and semantic compatibility between the legacy Tomvis/MariaDB 10.4 database dump (`rayongcoop_db.sql`, 7,016,754 bytes) and the new RYCOOPWEB 3.0 platform.

### Summary of Dataset
- **Total Member Records in Dump:** 2,508
- **Total User Accounts in Dump:** 2,510
- **Total Member Dividends in Dump:** 4,855 records (2,429 for Year 2567; 2,426 for Year 2568)
- **Account Discrepancy Breakdown:**
  - `user_id = 1`: Super Admin (`admin` / `pharmacisttom@gmail.com`) — **EXCLUDED FROM MIGRATION**
  - `user_id = 2510`: Member Admin (`memberadmin` / `memberadmin@member-admin.invalid`) — **EXCLUDED FROM MIGRATION**
  - `user_id = 2..2509`: Exactly 2,508 member accounts matching the 2,508 member records 1-to-1.

---

## 2. Table-by-Table Schema Comparison

### 2.1 Table: `members`
| Column | Legacy MariaDB 10.4 | RYCOOPWEB 3.0 MySQL 8 | Compatibility Assessment |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned NOT NULL AUTO_INCREMENT` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | **MAPPED** (Re-indexed in target to avoid PK collision) |
| `uuid` | `varchar(36) NOT NULL` | `VARCHAR(36) NOT NULL` | **COMPATIBLE** (Preserved or regenerated) |
| `user_id` | `bigint unsigned NULL` | `BIGINT UNSIGNED NULL` | **MAPPED** (Foreign key to newly assigned `users.id`) |
| `member_no` | `varchar(20) NOT NULL` | `VARCHAR(20) NOT NULL` | **COMPATIBLE** (Leading zeros strictly preserved, e.g. `00004`) |
| `id_card` | `varchar(20) NOT NULL` | `VARCHAR(20) NOT NULL` | **COMPATIBLE** (13-digit national ID validated) |
| `prefix` | `varchar(20) NULL` | `VARCHAR(20) NULL` | **COMPATIBLE** |
| `first_name` | `varchar(100) NOT NULL` | `VARCHAR(100) NOT NULL` | **COMPATIBLE** |
| `last_name` | `varchar(100) NOT NULL` | `VARCHAR(100) NOT NULL` | **COMPATIBLE** |
| `department` | `varchar(150) NULL` | `VARCHAR(150) NULL` | **COMPATIBLE** |
| `position` | `varchar(100) NULL` | `VARCHAR(100) NULL` | **COMPATIBLE** |
| `phone` | `varchar(20) NULL` | `VARCHAR(20) NULL` | **COMPATIBLE** |
| `email` | `varchar(190) NULL` | `VARCHAR(190) NULL` | **COMPATIBLE** |
| `address` | `text NULL` | `TEXT NULL` | **COMPATIBLE** |
| `join_date` | `date NULL` | `DATE NULL` | **COMPATIBLE** |
| `avatar` | `varchar(255) NULL` | `VARCHAR(255) NULL` | **COMPATIBLE** |
| `status` | `enum('active','resigned','suspended')` | `ENUM('active','resigned','suspended')` | **COMPATIBLE** |
| `created_at` | `datetime NOT NULL DEFAULT current_timestamp()` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | **COMPATIBLE** |
| `updated_at` | `datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | **COMPATIBLE** |

### 2.2 Table: `users`
| Column | Legacy MariaDB 10.4 | RYCOOPWEB 3.0 MySQL 8 | Compatibility Assessment |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned NOT NULL AUTO_INCREMENT` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | **MAPPED** (Target DB assigns new ID, avoids colliding with Super Admin ID 1) |
| `uuid` | `varchar(36) NOT NULL` | `VARCHAR(36) NOT NULL` | **COMPATIBLE** |
| `name` | `varchar(100) NOT NULL` | `VARCHAR(100) NOT NULL` | **COMPATIBLE** |
| `department` | `varchar(191) NULL` | `VARCHAR(191) NULL` | **COMPATIBLE** |
| `username` | `varchar(50) NOT NULL` | `VARCHAR(50) NOT NULL` | **COMPATIBLE** (Holds 13-digit national ID) |
| `email` | `varchar(100) NOT NULL` | `VARCHAR(100) NOT NULL` | **COMPATIBLE** (Format `member-XXXXX@members.invalid`) |
| `password` | `varchar(255) NOT NULL` | `VARCHAR(255) NOT NULL` | **COMPATIBLE** (Standard bcrypt `$2y$10$`) |
| `status` | `enum('active','inactive','suspended')` | `ENUM('active','inactive','suspended')` | **COMPATIBLE** |
| `session_version` | `int unsigned NOT NULL DEFAULT 1` | `INT UNSIGNED NOT NULL DEFAULT 1` | **COMPATIBLE** |

### 2.3 Table: `member_dividends`
| Column | Legacy MariaDB 10.4 | RYCOOPWEB 3.0 MySQL 8 | Compatibility Assessment |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned NOT NULL AUTO_INCREMENT` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | **MAPPED** |
| `member_id` | `bigint unsigned NOT NULL` | `BIGINT UNSIGNED NOT NULL` | **MAPPED** (References new target `members.id`) |
| `year` | `smallint unsigned NOT NULL` | `SMALLINT UNSIGNED NOT NULL` | **COMPATIBLE** (2567 & 2568) |
| `details_json` | `longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL` | `JSON NOT NULL` | **COMPATIBLE** (Valid JSON verified for all 4,855 records) |
| `imported_at` | `datetime NOT NULL DEFAULT current_timestamp()` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | **COMPATIBLE** |

### 2.4 Table: `member_import_runs`
- Present in RYCOOPWEB 3.0 target schema (`014_member_admin_history.sql`).
- Populated per batch run with batch identifier (e.g. `MIG-LEGACY-20261031-01`).
