# Deployment

Deploy from the reviewed `Test` branch using the production environment file and least-privilege database credentials. Keep the member portal disabled until its separate security review is complete.

Media layout, permissions, Nginx rules, build commands, and post-deploy checks are documented in [MEDIA_STORAGE.md](MEDIA_STORAGE.md). Backups belong in `storage/backups`, outside the public web root.
