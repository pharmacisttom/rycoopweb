# RYCOOP Production News Gallery Media Repair — RC01

**State:** Fix candidate, not production-applied. The current user request explicitly authorizes the repair, but automatic deployment must still verify a source of missing images, the active backend release, and root-level Nginx access.

## Root cause evidence (GitHub Actions)

- Production news API succeeds and returns 2 published news records.
- The public cover and gallery image URLs each returned HTTP 404.
- Active frontend and backend release folders and expected shared gallery folder were absent.
- Wider scan of `/var/www/rycoopweb`, `/var/www/rycoop`, `/var/www/html` found zero `news/gallery` directories.
- This DOES NOT prove original files were deleted. Archives, old backups, mounted volumes or other paths might still have the originals.

## Correct design

- Persistent upload root: `/var/www/rycoopweb/shared/uploads`, **outside immutable production releases**.
- PHP `RYCOOP_UPLOAD_ROOT` chooses persistent upload directory with fallback to legacy `<release>/storage/uploads` so existing TEST/PROD deployments are unaffected unless opted in.
- Release artifact must not include or delete shared uploaded media. Do not copy .env from TEST to PROD.
- Public Nginx serves **only intended image file extensions under the news gallery prefix**, not private activity albums, arbitrary user uploads, `.php`, `.env`, backups, SQL or documents.
- Activity Gallery private files are streamed through its authorization-aware controller. Never alias those private files to a public directory.

## Preflight — only on VPS with an authorized admin

1. Preserve current backend root, frontend root, Nginx config, PHP-FPM pool, DB, media and logs; take verified off-server backup.
2. Identify the actual production backend PHP source path and worker identity:
   ```bash
   sudo nginx -T 2>/dev/null | grep -E 'server_name|root |proxy_pass|fastcgi_pass' | head -n 70
   sudo grep -E '^\s*(user|group|listen)\s*=' /etc/php/8.3/fpm/pool.d/rycoop-prod.conf
   ```
   Confirm that the active backend uses the new PHP code **before** enabling a shared root.
3. Locate missing originals by matching basenames from authenticated/authorized known-good DB records against older releases, server backup disks and archival storage. Do not paste member data, image filenames or backups in public CI logs. If images cannot be recovered, restore from verified off-server backup or request re-upload of the exact files, retaining existing news records and their draft/published metadata.
4. Record evidence for the exact missing files and candidate source directory; do not run a blind `rsync --delete` or re-create empty files in place of missing originals.

## Server-only sequence (root access required, NOT run by CI)

A. Choose the source after verifying it contains the original files. Create persistent directory:

```bash
sudo install -d -m 0750 -o www-data -g www-data /var/www/rycoopweb/shared/uploads/news/gallery
```

Replace `www-data` with the **actual verified PHP-FPM worker user/group** if they differ. The Nginx user must have read/traverse permission.

B. Restore **only verified originals**, without overwriting existing copies. Example after setting an explicitly verified source path:

```bash
SOURCE_DIR=/path/to/verified/recovered/news/gallery
test -d "$SOURCE_DIR" || exit 1
sudo rsync -anv --ignore-existing "$SOURCE_DIR/" /var/www/rycoopweb/shared/uploads/news/gallery/
# After inspecting dry-run output and hashes:
sudo rsync -av --ignore-existing "$SOURCE_DIR/" /var/www/rycoopweb/shared/uploads/news/gallery/
```

For archives use a staging directory, inspect `tar -tzf` before extraction, and prevent symlink/path traversal. Do not extract untrusted archives as root.

C. Add `RYCOOP_UPLOAD_ROOT=/var/www/rycoopweb/shared/uploads` to **active backend's** protected runtime environment (.env is read by the app helper; do not expose it through Nginx); verify `storage_upload_path('news/gallery')` resolves to this folder. Ensure backend source code contains the new opt-in helper. Do not edit unrelated production feature flags.

D. Add to the **www SSL server block**, before general `location / ` route:

```nginx
# Exact public news gallery file extension only. No /storage/uploads/ blanket alias.
location ~ ^/storage/uploads/news/gallery/(?<news_gallery_file>[A-Za-z0-9_.-]+\.(?:webp|png|jpe?g|gif))$ {
    alias /var/www/rycoopweb/shared/uploads/news/gallery/$news_gallery_file;
    add_header X-Content-Type-Options nosniff always;
    expires 1h;
}
```

Review precedence against other regex locations in `nginx -T`; do not use a `^~ /storage/uploads/` prefix ahead of this regex or it would block matching. Do not configure public access to `private-activity-photos`.

E. Verify configuration **before** applying:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Use a real image reference from the news API to check `200`, `image/webp` or matching image type, image dimensions, and correct thumbnail; test an invalid extension `.php` returns non-200. Verify `/app/news`, `/app/admin/content?tab=news`, member PDPA/login and existing API health, then perform authenticated upload + publish test with synthetic content on TEST first.

F. Backout: revert the exact Nginx change and persistent env opt-in after `sudo nginx -t`, reload Nginx and return to prior immutable backend release. Never delete the shared media directory or overwrite source backups during rollback. Image restoration from backup is a separately versioned data operation.

## Gate

**NO-GO** while: original files not found/restored; API returns 404; active backend still writes release-local storage; PHP-FPM worker cannot write; Nginx cannot serve only intended public media; or verified rollback missing.

**GO** only once original photos display in both preview and public news, new upload survives backend/SPA release switch, and tests pass. All original news, images, audit and financial data remain preserved.
