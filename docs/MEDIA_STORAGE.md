# Media storage

The application uses two distinct media locations:

- Versioned/static files live under `public/assets` and are addressed as `/assets/...`.
- Runtime uploads live under `storage/uploads` and are addressed as `/storage/uploads/...` through the Nginx alias.
- Backups live under `storage/backups` and must never be served publicly.

Database values may be an external HTTP(S) URL, an absolute public path, or an upload-relative path such as `news/file.webp`. PHP responses must use `media_url()`/`resolve_media_url()` and React code must use `resolveMediaUrl()`. Code must not concatenate `/storage/uploads/` directly.

Filesystem access must use `storage_upload_path()`. It rejects traversal and absolute/server paths. New writes must never target `public/uploads` or `public/storage/uploads`; those paths are supported only as temporary read-only legacy fallbacks where documented.

## Production deployment

1. Create `storage/uploads` and `storage/backups`, owned by the PHP-FPM user and not executable.
2. Install `docs/nginx-rayongcoop.tomvisolution.tech.conf`, then run `nginx -t` and reload Nginx.
3. Run `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
4. Run database migrations and `php bin/check-seed-assets.php`.
5. Verify `/assets/news/sample_news_1.jpg`, `/assets/news/sample_news_2.jpg`, and a known `/storage/uploads/...` file return 200, while uploaded `.php`, `.phtml`, `.phar`, and `/storage/backups/` are denied.

Do not bulk-rewrite production database paths without a reviewed backup and migration plan.
