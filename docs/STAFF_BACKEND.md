# Staff Backend

Current production scope is the public website plus the staff CMS. Staff sign
in at `/admin/login`; the dashboard is `/admin/dashboard`. Administrative
routes require an authenticated active account and an allowed role. Mutations
also require CSRF validation. Member features are not staff dependencies.

Never expose password hashes, sessions, CSRF tokens, full citizen IDs, or
application secrets in the UI, exports, or logs.
