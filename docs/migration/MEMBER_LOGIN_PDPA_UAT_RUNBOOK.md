# RYCOOPWEB 3.0 — Member login and PDPA UAT (TEST only)

**Status:** Review-only code in feature branch. Do not deploy to production, apply DDL, enable member flags, or activate imported real member accounts without a separate approved release change.

## Intended member flow
1. **First-time registration / identity verification**: Imported user account remains `inactive`. A staff member must verify identity using an approved out-of-band channel and document the verification method/evidence (not full ID/OTP in logs). Implemented as a gated **in-person verified** Super Admin invitation and self-service first-time password setup using a 256-bit random one-time code (only its hash is stored), 20-minute expiry, revocation of older unused codes, issue limits, POST rate limits and CSRF. No SMS/LINE/Email delivery is implemented; staff must hand the code to the *verified* member only, never via public chats. Never issue initial passwords derived from member numbers or national IDs.
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
DDL: `database/manual/20261101_member_privacy_events_test.sql` and `database/manual/20261101_member_activation_invitations_test.sql`.
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
A **manual in-person first-use invitation** is implemented behind `FEATURE_MEMBER_ACTIVATION=false` by default. Automatic remote identity verification and verified SMS/LINE/Email OTP delivery **are not implemented**. Before any real member rollout, require DPO/legal review, two-person approval/step-up MFA policy for staff and independent security testing. Activation is single-member only and disabled until specifically approved; it must not infer identity from a national ID alone.

## Enabled test sequence (strictly synthetic UAT)
1. DBA-approved DDL for **both** new tables only in an isolated/synthetic TEST fixture, with backup/restore and no production access.
2. Verify the default flags: `FEATURE_MEMBER_ACTIVATION=false`, `FEATURE_MEMBER_LOGIN=false`, `FEATURE_MEMBER_PORTAL=false`.
3. In isolated synthetic UAT, after separate approval, temporarily enable activation flag. Create a *synthetic* test member with active member status, one `member` role, linked `inactive` user and no financial information about real members.
4. Super Admin, after in-person verification, opens member detail and requests one invitation. Only this staff session can view the code once; deliver directly and securely. Public `/member/activate` lets the synthetic member set 12+ character password (letters and numbers).
5. For synthetic-only UAT, temporarily enable `FEATURE_MEMBER_LOGIN=true` in an isolated instance, and verify login, notice acknowledgement, optional consent, dividend access, session logout and password reset. **Do not** enable login on the TEST site containing all 2,508 real imported members without full privacy/security approval.
6. Disable activation/login again and check that the UAT activation token cannot be reused. Do not bulk-activate imported accounts.
