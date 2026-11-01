# RYCOOPWEB 3.0 — Member login and PDPA UAT (TEST only)

**Status:** Review-only code in feature branch. Do not deploy to production, apply DDL, enable member flags, or activate imported real member accounts without a separate approved release change.

## Intended member flow
1. **First-time registration / identity verification**: Imported user account remains `inactive`. A staff member must verify identity using an approved out-of-band channel and document the verification method/evidence (not full ID/OTP in logs). Account activation, password setup and reset require a separate approved implementation with one-time, expiring invitations, controlled delivery, retry limits and revocation. Never issue initial passwords derived from member numbers or national IDs.
2. **Login**: The current backend requires valid credentials, `users.status=active`, `member` role, member linkage, `FEATURE_MEMBER_LOGIN=true`. Keep TEST login disabled until a synthetic-only or fully isolated UAT release and verified enrollment have been reviewed. Staff roles remain separate, with the existing MFA policy.
3. **Privacy notice acknowledgement**: After authenticated member login, the UI fetches `GET /api/member/privacy`, displays link to actual `/privacy/policy` version `2026-09-30` (currently displayed policy publication date), and requires an explicit unticked checkbox to acknowledge reading it. Server stores append-only event with user ID, exact version, event type, choice, timestamp, hashes of connection metadata. The acknowledgement **is not a legal-basis consent** for service-essential processing.
4. **Optional consent**: Separate, unticked, purpose-specific checkbox for optional cooperative updates; allow explicit opt-out and withdrawal via authenticated CSRF POST. Optional consent cannot be forced as a precondition of viewing dividends.
5. **Individual records**: Backend gates protected member API via `MemberMiddleware` and the `/api/member/dividends` handler; dividends use member ID derived from the verified session, never from a request-supplied member number. Re-acknowledge when the approved notice version changes.
6. **Logout/password changes**: Existing member page exposes logout and password-change actions; verify session expiration, device logout and CSRF for change password in QA.

## Privacy governance before approval
- Review Privacy Notice content under PDPA Section 23: actual controller and DPO contacts, processing purposes and legal bases, disclosure categories, retention, and member rights. **Do not equate clicking acknowledgement with legally valid consent.**
- Separately approve whether optional cooperative updates truly require consent, and establish how withdrawal propagates to mailing services. This patch records the choice; **it does not send messages or synchronize a marketing platform**.
- Review legal text, publication version and retention policy with the cooperative DPO/legal team. Do not represent the static draft notice as approved automatically.
- Do not activate all 2,508 imported member users or open public member login in this PR.

## Manual TEST database change — separately approved
DDL: `database/manual/20261101_member_privacy_events_test.sql`.
Back up `rycoopweb_test`, restore-test, inspect schema/constraints, use least privilege, create the ledger **only on TEST**, rerun migrations disabled. No SQL is executed by GitHub PR or assistant.

## UAT checklist
- With `FEATURE_MEMBER_LOGIN=false`, 2,508 imported `inactive` member accounts remain unable to log in.
- Against isolated synthetic member only: after explicit authorized activation and feature flags, validate wrong credentials, inactive account, locked out/throttling, and wrong role are rejected.
- Before notice acknowledgement: `GET /api/member/dividends` must return 403 with `PRIVACY_NOTICE_REQUIRED`; no personal financial data should be in response.
- Read current notice, tick acknowledgement, submit with CSRF; verify append-only ledger and individual dividend visibility for only the authenticated synthetic member.
- Simulate notice version change: old acknowledgement no longer satisfies current-version gate.
- Optional opt-in unchecked by default; decline and withdrawal allowed; neither hides service-essential financial data.
- Deny direct URL/API access to another member's records, use CSRF and session-cookie controls, and verify user cannot see someone else's member ID.
- Ensure no imported member accounts switch from `inactive`, audit any UAT data mutations, validate backup/rollback, and leave production unchanged.

## Outstanding blockers
A secure first-use enrollment mechanism and verified delivery channel (OTP/invitation) are **not implemented by this patch**. Need separate approval and integration testing before granting any real member access. Any email/SMS/LINE delivery requires verified destination, one-time token and rate limits, and an auditable member identity match. This work must not infer identity from a national ID alone.
