# RYCOOPWEB 3.0 — Legacy-to-New Field Mapping Specification

**Target Branch:** `codex/legacy-member-dividend-migration`

---

## 1. Mapping Flow Architecture

```mermaid
flowchart TD
    subgraph S["Staging Source (rayongcoop_db.sql)"]
        LU["Legacy users (2,508 member accounts)"]
        LM["Legacy members (2,508 records)"]
        LD["Legacy member_dividends (4,855 records)"]
    end

    subgraph M["Migration Engine & Mapping Layer"]
        FILTER["Filter Admin/Staff (user 1 & 2510)"]
        COLLISION["Pre-Scan Target Staff Collisions"]
        MAP_U["Map legacy_user_id -> new_user_id"]
        MAP_M["Map legacy_member_id -> new_member_id"]
        VAL["Validate 13-digit ID & Leading-Zero No"]
    end

    subgraph T["Target Database (rycoopweb_test)"]
        TU["users (AUTO_INCREMENT, role: member)"]
        TUR["user_roles (user_id, role_id=12)"]
        TM["members (user_id=new_user_id, member_no='00004')"]
        TD["member_dividends (member_id=new_member_id)"]
        TR["member_import_runs (batch tracking)"]
        TA["audit_logs (audit trial)"]
    end

    LU --> FILTER --> COLLISION --> MAP_U --> TU
    TU --> TUR
    LM --> VAL --> MAP_M --> TM
    MAP_U -.-> TM
    LD --> MAP_M --> TD
    M --> TR
    M --> TA
```

---

## 2. Entity Mapping Tables

### 2.1 Users Mapping
| Source Column (`users`) | Target Column (`users`) | Transformation / Rule |
| :--- | :--- | :--- |
| `id` | - | Re-mapped. Target database allocates new ID; legacy ID stored in mapping memory/run metadata. |
| `uuid` | `uuid` | Preserved or regenerated if format mismatch. |
| `name` | `name` | String trimmed; sanitized. |
| `department` | `department` | Nullable string preserved. |
| `username` | `username` | National 13-digit ID card. Confirmed non-colliding with staff. |
| `email` | `email` | Preserved (`member-XXXXX@members.invalid`). |
| `password` | `password` | Verified bcrypt hash `$2y$10$...`. Never converted to plaintext. |
| `status` | `status` | 'active' (protected behind `FEATURE_MEMBER_PORTAL=false`). |
| `session_version` | `session_version` | Initialized to `1`. |
| - | `created_at` | Timestamp of migration execution. |
| - | `updated_at` | Timestamp of migration execution. |

### 2.2 Members Mapping
| Source Column (`members`) | Target Column (`members`) | Transformation / Rule |
| :--- | :--- | :--- |
| `id` | - | Re-mapped. Target database allocates new ID; legacy ID stored in mapping. |
| `uuid` | `uuid` | Preserved or regenerated. |
| `user_id` | `user_id` | Target newly generated `users.id` resolved from `userMapping[legacy_user_id]`. |
| `member_no` | `member_no` | **Leading zeros strictly preserved** (`str_pad($val, 5, '0', STR_PAD_LEFT)` if numeric string). |
| `id_card` | `id_card` | 13-character string, verified format `/^\d{13}$/D`. |
| `prefix` | `prefix` | Preserved. |
| `first_name` | `first_name` | Preserved. |
| `last_name` | `last_name` | Preserved. |
| `department` | `department` | Preserved. |
| `status` | `status` | 'active' / 'resigned' / 'suspended'. |

### 2.3 Member Dividends Mapping
| Source Column (`member_dividends`) | Target Column (`member_dividends`) | Transformation / Rule |
| :--- | :--- | :--- |
| `id` | - | Auto-incremented in target database. |
| `member_id` | `member_id` | Resolved from `memberMapping[legacy_member_id]`. Never uses legacy raw ID! |
| `year` | `year` | Filtered for authorized years (`2567`, `2568`). |
| `details_json` | `details_json` | Cleaned and validated JSON object adhering to RYCOOPWEB 3.0 schema. |
| `imported_at` | `imported_at` | Preserved or current timestamp. |
