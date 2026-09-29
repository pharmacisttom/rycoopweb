# Audit Logging

Audit authentication, content lifecycle, settings, user/role administration,
uploads, and LED actions. Store actor, role, action, module, entity, request
metadata, and concise old/new values when useful.

Never log passwords or hashes, APP_KEY, DB credentials, cookies, CSRF tokens,
2FA secrets, or unmasked citizen IDs. Protect audit access and exports. Rotate
application, security, audit, cron, and backup logs daily or weekly and retain
them for 30–90 days according to policy.
