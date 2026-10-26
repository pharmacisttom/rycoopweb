# RYCOOPWEB 2.2 — Google Cloud OAuth 2.0 & Mandatory 2FA Setup Guide

## 1. Overview

RYCOOPWEB 2.2 integrates Google Sign-In (OpenID Connect / OAuth 2.0 Authorization Code Flow with PKCE) and Mandatory Multi-Factor Authentication (MFA / TOTP) for all cooperative staff and administrators.

Key Architectural Guarantees:
- **Identity vs. Access**: Google Identity determines *who* the person is (`sub` claim). RYCOOPWEB Role-Based Access Control (RBAC) determines *what* they can access.
- **No Open Registration**: Only staff with a cryptographically secure, valid invitation from a Super Admin can authenticate and activate an account.
- **Mandatory MFA**: All back-office staff (`super_admin`, `loan_officer`, `finance`, `auditor`, etc.) are required to enroll in TOTP MFA (Google Authenticator / compatible app) with 8 single-use recovery codes, regardless of account type.
- **Zero Client-Side Secrets**: Google OAuth Client Secret is stored strictly server-side in `.env`. Tokens are never stored in `localStorage` or exposed to JavaScript.

---

## 2. Google Cloud Project Creation

1. Log into the [Google Cloud Console](https://console.cloud.google.com/) using an authorized administrative Google account.
2. At the top left project dropdown, click **New Project**.
3. **Project Name**: `RYCOOPWEB-Identity` (or `RYCOOPWEB-Test` / `RYCOOPWEB-Prod`).
4. Select your Organization if using Google Workspace, or leave as "No organization" for standalone environments.
5. Click **Create** and ensure the project is active.

---

## 3. Configuring OAuth Consent Screen

Navigate to **APIs & Services** > **OAuth consent screen**:

### A. User Type Selection
- **Internal** (Recommended if your cooperative operates a Google Workspace domain):
  - Accessible only to users within your organization.
  - No Google verification review required.
- **External** (Required if staff or primary administrators use personal Gmail accounts, such as `pharmacisttom@gmail.com`):
  - In *Testing* publishing status: You must add approved Google email addresses under **Test users**.
  - In *Production* publishing status: Anyone can reach the consent screen, but **RYCOOPWEB's server-side invitation engine rejects any Google user not in `staff_invitations` or `user_google_identities`**.

### B. App Information
- **App name**: `สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด (Staff Portal)`
- **User support email**: Select administrator email (e.g. `pharmacisttom@gmail.com` or coop admin).
- **App logo**: Optional. Upload official cooperative crest/logo.
- **Application home page**: `https://test.rayongcoop.org`
- **Application privacy policy link**: `https://test.rayongcoop.org/privacy-policy`
- **Developer contact information**: Provide system administrator email.

### C. Scopes (Minimal Privilege)
Click **Add or Remove Scopes**. Select **ONLY** the 3 standard OpenID Connect scopes:
- `.../auth/userinfo.email` (`email`)
- `.../auth/userinfo.profile` (`profile`)
- `openid`

> [!CAUTION]
> **DO NOT** select or request any sensitive or restricted scopes (such as Gmail, Google Drive, Google Calendar, or Google Workspace Admin APIs). RYCOOPWEB strictly uses Google for identity authentication only.

---

## 4. Creating OAuth 2.0 Client Credentials

Navigate to **APIs & Services** > **Credentials**:

1. Click **+ CREATE CREDENTIALS** > **OAuth client ID**.
2. **Application type**: Select **Web application**.
3. **Name**: `RYCOOPWEB Web Client (Test)` (or `Prod`).
4. **Authorized JavaScript origins**:
   - `https://test.rayongcoop.org`
   - *(For local development)*: `http://localhost`
5. **Authorized redirect URIs**:
   - Exact URI: `https://test.rayongcoop.org/auth/google/callback`
   - *(For local development)*: `http://localhost/auth/google/callback` or `http://localhost:8000/auth/google/callback`

> [!IMPORTANT]
> The redirect URI must match character-by-character with `GOOGLE_OAUTH_REDIRECT_URI` in `.env`. Any trailing slash mismatch or HTTP vs HTTPS mismatch will cause a Google `redirect_uri_mismatch` error.

6. Click **Create**. A modal will display your:
   - **Client ID** (e.g., `1234567890-abcdefg.apps.googleusercontent.com`)
   - **Client Secret** (e.g., `GOCSPX-xxxxxxxxxxxxxxxxxxxx`)
7. Copy both values immediately and store them securely.

---

## 5. Environment Configuration (.env)

Update the server `.env` file (never commit actual secrets to Git):

```ini
# Google OAuth 2.0 Credentials
GOOGLE_OAUTH_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_OAUTH_CLIENT_SECRET=GOCSPX-your-client-secret
GOOGLE_OAUTH_REDIRECT_URI=https://test.rayongcoop.org/auth/google/callback
GOOGLE_AUTH_ENABLED=true

# Security Policies
STAFF_GOOGLE_LOGIN_REQUIRED=true
STAFF_MFA_REQUIRED=true
PRIMARY_SUPERADMIN_EMAIL=pharmacisttom@gmail.com
APP_URL=https://test.rayongcoop.org
SESSION_SECURE=true
```

---

## 6. Primary Super Admin Bootstrap Procedure

The primary Super Admin email `pharmacisttom@gmail.com` is an authorization reference, **not** an authentication bypass.

To bootstrap or link the initial Super Admin account:
1. Ensure `PRIMARY_SUPERADMIN_EMAIL=pharmacisttom@gmail.com` in `.env`.
2. Run the server CLI bootstrap command:
   ```bash
   php bin/console auth:bootstrap-superadmin
   ```
3. Open `https://test.rayongcoop.org/admin/login`.
4. Click **เข้าสู่ระบบด้วยบัญชี Google**.
5. Authenticate with `pharmacisttom@gmail.com`.
6. The backend verifies the Google ID Token, checks the bootstrap flag, binds Google's immutable `sub` claim to the local `super_admin` record, and automatically disables further bootstrap requests.
7. Complete mandatory MFA enrollment (scan local QR code with Google Authenticator, enter OTP code, save 8 recovery codes).
8. Once enrolled, the Super Admin enters the Staff Management console (`/admin/staff`).

---

## 7. TEST vs. Production Separation

For DevSecOps hygiene and isolation:
- Create separate OAuth 2.0 Client IDs for TEST and PRODUCTION.
- **TEST Project**:
  - Redirect URI: `https://test.rayongcoop.org/auth/google/callback`
- **PRODUCTION Project**:
  - Redirect URI: `https://www.rayongcoop.org/auth/google/callback`
- Staging credentials must never have access to production databases or vice-versa.

---

## 8. Multi-Factor Authentication (MFA / 2FA) Policy

### A. Google Workspace / Cloud Identity Accounts
If the cooperative provisions staff accounts under a Google Workspace domain:
1. Google Workspace Admins must enforce **2-Step Verification (2SV)** under *Security > 2-Step Verification* in the Google Admin Console.
2. Set frequency to *Enforce* for the Staff Organizational Unit (OU).

### B. Personal Gmail Accounts & Universal Policy
Because Google's standard OpenID Connect ID token does not guarantee MFA assurance across personal Gmail accounts, **RYCOOPWEB enforces an internal, application-controlled factor (TOTP)**:
- **100% Local QR Generation**: QR codes are rendered as SVG in pure PHP (`app/Services/QrCodeGenerator.php`). **Zero third-party APIs (no `api.qrserver.com`)** to prevent secret leakage.
- **Encrypted Secrets**: TOTP secrets are encrypted at rest using AES-256-GCM.
- **Replay Protection**: Time slices are tracked in `users.two_factor_last_used_slice`. An OTP cannot be submitted twice within the same 30-second window.
- **Single-Use Recovery Codes**: 8 random cryptographically generated recovery codes are hashed (SHA-256) at rest. Used codes are stamped with `used_at` and cannot be reused.
- **Instant Session Invalidation**: `users.session_version` is checked on every authenticated request. Administrators can revoke staff sessions immediately from `/admin/staff`.

---

## 9. Troubleshooting & FAQ

### `redirect_uri_mismatch` (400)
- **Cause**: The redirect URL sent by RYCOOPWEB does not match the Authorized Redirect URI in Google Cloud Console.
- **Fix**: Check `GOOGLE_OAUTH_REDIRECT_URI` in `.env`. Ensure protocol (`https://`), port, and path (`/auth/google/callback`) match Google Cloud Console exactly.

### `access_denied`
- **Cause**: The user clicked "Cancel" on the Google Consent Screen, or the user is not listed under "Test users" in an External OAuth app in Testing status.
- **Fix**: Add the staff Google email to **Test users** in Google Cloud Console, or publish the OAuth consent screen to Production.

### `Account not authorized`
- **Cause**: The Google user is authenticated, but has not received an invitation from the Super Admin.
- **Fix**: The Super Admin must invite the user via `/admin/staff` before they can log in.

### Client Secret Rotation
1. In Google Cloud Console, open Credentials > your OAuth Client.
2. Click **+ Add secret** to generate a new Client Secret.
3. Update `GOOGLE_OAUTH_CLIENT_SECRET` in `.env`.
4. Verify staff can log in using the new secret.
5. In Google Cloud Console, delete the old secret. (Zero downtime achieved).
