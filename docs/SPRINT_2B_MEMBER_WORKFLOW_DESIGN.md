# RYCOOPWEB — SPRINT 2B: MEMBER MODULE WORKFLOW DESIGN SPECIFICATION
## Read-Only Architectural Specification, Identity Reconciliation & Audit Safeguards
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Scope:** Member Administration, Dividend Processing & Identity Mapping  
**Security Tier:** Protected Core (High Integrity)  

---

### 1. Architectural Guardrails & Non-Destructive Principles

1. **Read-Only First:** All Member Registry views (`/admin/members`, `/admin/members/dashboard`, `/admin/members/:id`) operate in read-only mode by default for general officers.
2. **Strict Identity Decoupling:** Member dividend imports (`.xlsx` / `.csv`) **never** create member accounts, change member passwords, or open the Member Portal.
3. **Pre-Existing Account Requirement:** A member record must already exist in `members` and have a verified identity mapping in `users` before dividend records can be linked.
4. **Zero Mutation During Preflight:** The Excel preview phase (`POST /api/admin/dividends/preview`) operates strictly in memory/staging without committing rows to `member_dividends`.
5. **No Password Overwrites:** Initial passwords, hashed credentials, and TOTP MFA secrets cannot be viewed or altered through member management interfaces.

---

### 2. Operational Workflows & Life Cycle

```
[Annual Dividend File (XLSX/CSV)]
             |
             v
   [Preflight Validation] ---> (Checks Column Headers, Year, Row Counts)
             |
             v
   [Sample Preview & Diff] ---> (20 Sample Rows: New / Update / Unchanged)
             |
             v
   [Officer Confirmation] ---> (Checkbox Acknowledgment + CSRF Token)
             |
             v
   [Database Commit (PDO)] ---> (Updates `member_dividends`, Logs Audit Trail)
```

#### 2.1 Workflow 1: Member Registry Dashboard (`/admin/members/dashboard`)
- **Purpose:** Provide cooperative officers with live, aggregate visibility into membership statistics and historical dividend distribution runs.
- **KPI Metrics:**
  - Total Registered Members (Active: 2,508)
  - Member Accounts with Portal Access vs Unlinked (`no_account`)
  - Historical Fiscal Years with Completed Dividend Runs
- **Safety Rule:** All metrics are queried directly from MySQL 8 (`COUNT(*)` from `members` and `member_dividends`). Zero hardcoded numbers.

#### 2.2 Workflow 2: Member Identity Mapping & Search (`/admin/members`)
- **Purpose:** Allow officers to look up member records by Member Number (ทะเบียนสมาชิก), Full Name, or Citizen ID Card.
- **PDPA Privacy Masking:**
  - Citizen ID card masked: `x-xxxx-xxxxx-xx-x` (only last 4 digits visible).
  - First name masked: first 2 characters visible (`ทอxx`).
  - Surname masked: first 3 characters visible (`วิศิxxx`).
- **Authorization:** Requires `members.view` permission.

#### 2.3 Workflow 3: Excel Import Preflight & Exception Handling (`/admin/dividends/import`)
- **File Constraints:** `.xlsx` or UTF-8 `.csv` only. Maximum file size 5 MB. Legacy `.xls` rejected.
- **Two-Phase Commit:**
  1. **Phase 1 (Preview):** Parsed by `DividendImportController@preview`. Generates a 30-minute signed preview token, computes total net amounts, created counts, updated counts, and unchanged counts. Returns first 20 rows with diff highlighting.
  2. **Phase 2 (Confirm):** Requires submitting the preview token and explicit acknowledgment checkbox. Executed inside a PDO transaction.
- **Exception Reporting:** Any rows with missing member numbers, mismatched citizen IDs, or negative balances are flagged in an error table with row number and reason.

#### 2.4 Workflow 4: New / Existing Member Reconciliation (`/admin/members/health`)
- **Cross-Table Verification:** Automatically detects dividend records referencing member numbers that do not exist in the primary `members` table.
- **Fail-Closed Rule:** Unmapped rows cannot be imported until the cooperative membership department creates the member record through official registration.

#### 2.5 Workflow 5: Audit & Dual-Control Governance (`/admin/approvals`)
- **Maker-Checker Requirement:** Critical operations (e.g. bulk dividend deletions, system-wide member status changes) require two separate authorized officers (Maker creates request, Checker approves).
- **Immutable Log:** Every member view and export is written to `audit_logs` with actor ID, IP address, timestamp, and action scope.

---

### 3. Implementation Phasing & Next Steps
- **Sprint 2 Execution:** Complete read-only UI components in `AdminMembersDashboardPage.jsx`, `AdminMembersPage.jsx`, and `AdminMembersHealthPage.jsx`.
- **Sprint 3 (Future):** Authorized Maker-Checker dual control and enhanced batch exception resolution.
