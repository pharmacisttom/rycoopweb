# Role-Based Access Control

The backend is the authorization authority. `super_admin` has full access;
other staff receive permissions through `user_roles`, `role_permissions`, and
`permissions`. Permissions use module/action pairs such as `news.publish`,
`users.view`, and `audit_logs.export`.

UI visibility is convenience only. Every protected backend operation must
enforce authentication and the applicable role or permission. Auditor roles
are read-only.
