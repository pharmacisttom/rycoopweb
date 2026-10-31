# RYCOOPWEB 3.0 — Duplicate & Identity Conflict Audit Report

**Date:** 2026-10-31
**Dataset Analyzed:** `rayongcoop_db.sql`

---

## 1. Conflict Audit Results

| Audit Check | Findings | Status |
| :--- | :--- | :---: |
| Duplicate `member_no` | **0 duplicates found** (All 2,508 member numbers are unique) | **PASS** |
| Duplicate `id_card` | **0 duplicates found** (All 2,508 national ID cards are unique) | **PASS** |
| Malformed `id_card` | **0 malformed cards** (All 2,508 cards match `^\d{13}$`) | **PASS** |
| Leading Zero Preservation | Verified on `00004`, `00006`, `00008`, `00015`, `00017`, `00019`, `00021`, `00022`, `00024` | **PASS** |
| Password Hash Algorithm | **100% bcrypt (`$2y$10$`)** across all 2,508 accounts. Zero MD5, SHA1, plaintext or unrecognized formats. | **PASS** |
| Orphaned Dividends | **0 orphaned dividends** (All 4,855 dividend rows map to a valid member) | **PASS** |
| Orphaned User Links | **0 orphaned links** (All 2,508 members link to their corresponding user) | **PASS** |

---

## 2. Administrative & Staff Account Quarantine

In accordance with Requirement 8, administrative and staff accounts from the legacy dump are strictly quarantined and filtered out before migration:

1. **Legacy User ID 1:**
   - Username: `admin`
   - Email: `pharmacisttom@gmail.com`
   - Role: `super_admin`
   - **Action:** Excluded. Target database Super Admin account is preserved without overwrite.
2. **Legacy User ID 2510:**
   - Username: `memberadmin`
   - Email: `memberadmin@member-admin.invalid`
   - Role: `member_admin`
   - **Action:** Excluded.
3. **Pre-Scan Collision Guard:**
   - The importer pre-scans all existing target users possessing staff or administrative roles (`super_admin`, `manager`, `executive`, `finance`, `loan_officer`, `welfare_officer`, `pr_officer`, `document_officer`, `complaint_officer`, `auditor`, `it_admin`).
   - If any legacy member `username` (13-digit ID) or `email` matches a staff account, migration immediately aborts and rolls back to prevent role demotion or account corruption.
