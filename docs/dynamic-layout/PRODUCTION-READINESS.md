# Production readiness: NO-GO

No production deployment is authorized. Local/isolated evidence is not a substitute for TEST acceptance.

- [x] Separate feature branch and scoped source changes; original homepage blocks preserved.
- [x] Versioned API, hierarchy/CRUD, draft/publication separation, rollback and gallery.
- [x] MFA/RBAC/CSRF/XSS/upload and transaction tests executed locally.
- [x] Local responsive browser test with real PHP/session/TOTP/multipart requests and persisted fixture records.
- [x] Additive migration/idempotency and two-publisher locking verified on isolated MariaDB 10.4.32.
- [ ] Dedicated CI MySQL 8 migration/concurrency results verified (see final QA update).
- [ ] Approved rycoop-test SSH profile/key and trusted independently verified host fingerprint installed.
- [ ] Read-only remote preflight and deployed-source reconciliation complete.
- [ ] TEST backup verified and migration explicitly approved/applied to rycoopweb_test.
- [ ] TEST-only private storage/uploads/proxy/FPM configuration verified.
- [ ] All real persisted TEST acceptance items signed off, including live roles/TOTP, gallery details and regressions.
- [ ] TEST rollback rehearsed and evidence reviewed.
- [ ] Separate validated production release procedure and authorization.
