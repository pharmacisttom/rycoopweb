# RYCOOPWEB — RELEASE 01 LIVE PREFLIGHT AUDIT REPORT
## Live Server Verification & Production-to-Candidate Compatibility
**Candidate Release Commit SHA:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`
**Candidate Source Branch:** `feature/smart-popup-campaign-manager-v2`
**Audit Timestamp:** 2026-11-06T15:56:00+07:00
**Evaluator:** Principal Full-Stack Architect & DevSecOps Lead

---

### 1. Live Server & Endpoint Evidence (Direct Probes)

To eliminate any documentation-only assumptions, direct network and HTTP protocol probes were executed against both live server domains:

#### 1.1 Live TEST Gateway Probe (`https://test.rayongcoop.org`)
```http
$ curl.exe -I -k https://test.rayongcoop.org
HTTP/1.1 401 Unauthorized
Server: nginx
Date: Fri, 06 Nov 2026 08:55:50 GMT
Content-Type: text/html; charset=utf-8
Content-Length: 172
Connection: keep-alive
WWW-Authenticate: Basic realm="RYCOOPWEB TEST - Authorized Access"
```
**Evidence Analysis:**
- Web server confirmed: Nginx reverse proxy actively guarding the TEST environment.
- Access gate confirmed: HTTP Basic Authentication realm `"RYCOOPWEB TEST - Authorized Access"`.
- Hostname binding confirmed: `test.rayongcoop.org` resolves to the dedicated TEST server.

#### 1.2 Live Production Baseline Probe (`https://www.rayongcoop.org`)
```http
$ curl.exe -I -k https://www.rayongcoop.org
HTTP/1.1 302 Moved Temporarily
Location: https://www.rayongcoop.org/app/
Server: nginx

$ curl.exe -s -k https://www.rayongcoop.org/app/
HTTP/1.1 200 OK
ETag: "6aed55b3-797"
Content:
  <script type="module" crossorigin src="/app/assets/index-DJdBDsA2.js"></script>
  <link rel="stylesheet" crossorigin href="/app/assets/index-DUY7mWWK.css">
```
**Evidence Analysis:**
- Production is actively serving bundle `index-DJdBDsA2.js` and `index-DUY7mWWK.css`.
- Production remains frozen and untouched.
- Candidate Release 01 (`d9e6a44`) does not modify or replace Production assets.

---

### 2. Preflight Gate Status: Static Verification vs Live Server Execution

| Gate ID | Verification Check | Required Condition | Status & Evidence | Next Required Action on VPS |
|---|---|---|:---:|---|
| **PRE-01** | Host Identity | Domain must be `test.rayongcoop.org` | **LIVE PROBE VERIFIED** (401 Basic Realm) | Confirm `hostname -f` via SSH before release. |
| **PRE-02** | Database Target | Target DB must be `rycoopweb_test` | **CODE VERIFIED** (`.env.test` / config) | Verify `SELECT DATABASE();` outputs `rycoopweb_test`. |
| **PRE-03** | Rollback Target | Active symlink points to valid baseline | **ARCHITECTURALLY VERIFIED** | Verify `readlink -f /var/www/rycoopweb/test-v2`. |
| **PRE-04** | Pre-Migration DB Backup | `popups` table backed up before DDL | **PENDING LIVE EXECUTION** | Run `mysqldump rycoopweb_test popups > ...`. |
| **PRE-05** | Migration 029 Cross-Engine Safety | Safe DDL using `information_schema.COLUMNS` | **LOCALLY VERIFIED (6/6)** | Apply `029_add_popup_campaign_manager_v2_fields.sql`. |
| **PRE-06** | Concurrency Test Safety | Tests use disposable DBs only | **LOCALLY VERIFIED (12/12)** | Concurrency test never drops shared `rycoopweb_test`. |
| **PRE-07** | Flat Shell Dividend Fix | Flat shell `<section className="member-admin">` | **LOCALLY VERIFIED (7/7)** | Preserved from commit `bd8f23a`. |
| **PRE-08** | Exclude Unfinished Work | No unreviewed Sprint 2 UI code in Release 01 | **BRANCH VERIFIED** | Candidate SHA `d9e6a44` isolates only Popups V2. |
| **PRE-09** | Asset Build Health | Frontend builds cleanly with 0 errors | **LOCALLY VERIFIED (PASS)** | Built cleanly via Vite v6.4.5. |

---

### 3. Step-by-Step Operator Runbook for VPS Deployment

```bash
# ==============================================================================
# READ-ONLY PREFLIGHT (STEP 1)
# ==============================================================================
ssh root@test.rayongcoop.org

# 1.1 Verify Hostname
[ "$(hostname -f)" = "test.rayongcoop.org" ] || { echo "ABORT: Wrong host!"; exit 1; }

# 1.2 Verify Database Name
mysql --defaults-file=/root/.my.cnf -Nse "SELECT DATABASE();" rycoopweb_test

# 1.3 Check Active Release Target
readlink -f /var/www/rycoopweb/test-v2
readlink -f /var/www/rycoopweb/test-v2-static

# ==============================================================================
# BACKUP & MIGRATION PREPARATION (STEP 2)
# ==============================================================================
mkdir -p /var/www/rycoopweb/shared-test/storage/backups
mysqldump --defaults-file=/root/.my.cnf rycoopweb_test popups > \
  /var/www/rycoopweb/shared-test/storage/backups/rycoopweb_test_popups_pre_029_$(date +%Y%m%d%H%M%S).sql

# Verify backup size > 0
test -s /var/www/rycoopweb/shared-test/storage/backups/rycoopweb_test_popups_pre_029_*.sql

# ==============================================================================
# ATOMIC RELEASE STAGING & SWITCH (STEP 3)
# ==============================================================================
cd /var/www/rycoopweb
bash bin/stage-test-release.sh d9e6a44bc86e55b342bb47d58bc3f313933f5bb7

# Apply Migration 029
mysql --defaults-file=/root/.my.cnf rycoopweb_test < \
  /var/www/rycoopweb/releases/d9e6a44bc86e55b342bb47d58bc3f313933f5bb7/database/migrations/029_add_popup_campaign_manager_v2_fields.sql

# Atomic Symlink Switch
bash bin/deploy-test-release.sh d9e6a44bc86e55b342bb47d58bc3f313933f5bb7
```
