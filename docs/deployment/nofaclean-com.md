# Nofa Clean Deployment Runbook

Target production URL: `https://nofaclean.com`

Last local check: the domain resolves to `88.222.210.73`, HTTPS returns `200`, and the page is currently a Hostinger default page served by LiteSpeed/PHP. The target host is Hostinger Cloud, managed through hPanel. Treat this as a live hosting account: do not overwrite the existing `nofaclean.com` document root until the owner approves the cutover window.

## Deployment Shape

- Production domain: `https://nofaclean.com`
- Runtime: PHP 8.3 is already visible on the current host and is compatible with Laravel 12.
- Web server: Hostinger Cloud LiteSpeed is currently serving the domain through hPanel.
- Database: MySQL/MariaDB.
- Laravel public entrypoint: the web server must point to the `public/ document root`; source folders such as `app`, `config`, `database`, `storage`, and `.env` must not be web accessible.
- Queue/scheduler operations: use hPanel Cron Jobs rather than VPS Supervisor.

## Files Added For Deployment

- `.env.nofaclean.production.example` for `https://nofaclean.com`.
- `docs/deployment/nofaclean-com.md` for production UAT, cutover, operations, and rollback.

Copy `.env.nofaclean.production.example` to `.env` on the server only. Never commit the real `.env`.

## Preflight Checklist

- Confirm the exact Hostinger Cloud website root path in hPanel.
- Confirm whether `nofaclean.com` currently has any real public content behind the Hostinger default page.
- Create the production database:
  - `nofaclean_production`
- Create a least-privilege database user for production.
- Create mailboxes or SMTP credentials:
  - `no-reply@nofaclean.com`
- Create a browser-restricted Google Maps key:
  - Production referrer: `https://nofaclean.com/*`
- Confirm SSL is active before login testing.
- Confirm backups are available before production cutover.
- Confirm SSH access or hPanel Terminal is enabled for Composer, Artisan, `npm ci`, and `npm run build`. If Node.js is unavailable on the Hostinger Cloud account, build assets locally or in CI and upload the generated `public/build` directory with the release.

## Production UAT on `https://nofaclean.com`

Because UAT will happen directly on `https://nofaclean.com`, protect the live domain during UAT before inviting testers. Use one of these options:

- hPanel password protection for the website or `public_html`, if available.
- A temporary Laravel maintenance/limited-access window while the team tests.
- A temporary `noindex` launch mode until business approval is finished.

Do not share customer-facing links publicly until UAT is approved.

1. Upload or pull the project into a folder outside public web access, for example:
   - `/home/<user>/domains/nofaclean.com/apps/production/current`
2. Configure the production web root to:
   - `/home/<user>/domains/nofaclean.com/apps/production/current/public`
3. If hPanel does not allow the domain document root to be set directly to Laravel's `public` folder, keep the Laravel app outside `public_html`, place only the contents of Laravel `public/` inside the web root, and update that web root `index.php` to reference the app folder:

```php
require __DIR__.'/../apps/production/current/vendor/autoload.php';
$app = require_once __DIR__.'/../apps/production/current/bootstrap/app.php';
```

The exact `../apps/...` path must match the Hostinger Cloud folder layout.

4. Copy `.env.nofaclean.production.example` to `.env` and fill real production secrets.
5. Install dependencies and build assets:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

6. Prepare Laravel:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

7. Verify production UAT:

```bash
php artisan about
php artisan test
```

Then manually test:

- `https://nofaclean.com`
- `https://nofaclean.com/login`
- Admin login redirects to `/app/dashboard`.
- Customer login redirects to `/app/customer`.
- Worker login redirects to `/app/worker/today`.
- Customer can submit a booking request.
- Admin can see and approve the request.
- Uploaded visit/payment proof files resolve under `/storage/...`.
- Google Maps picker loads only when the Maps key is configured.

## Public Launch Cutover

Do this only after production UAT on `https://nofaclean.com` is accepted.

1. Announce a cutover window.
2. Take backups:

```bash
mysqldump -u <db_user> -p nofaclean_production > nofaclean_production_before_cutover.sql
tar -czf nofaclean_storage_before_cutover.tar.gz storage/app/public
```

3. Put the app in maintenance mode if production already has users:

```bash
php artisan down --render="errors::503"
```

4. Upload or pull the approved release into the production app folder, for example:
   - `/home/<user>/domains/nofaclean.com/apps/production/current`
5. Configure the production web root to the Laravel `public/ document root`.
6. If hPanel keeps `public_html` as the fixed web root, place only the contents of Laravel `public/` inside `public_html` and update `public_html/index.php` to point to the production app folder:

```php
require __DIR__.'/../apps/production/current/vendor/autoload.php';
$app = require_once __DIR__.'/../apps/production/current/bootstrap/app.php';
```

Do not copy the whole Laravel project into `public_html`.

7. Copy `.env.nofaclean.production.example` to `.env` and fill real production secrets.
8. Install/build:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

9. Run production Laravel commands:

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan up
```

10. Remove UAT protection and verify:

- `APP_DEBUG=false`
- `https://nofaclean.com` loads the landing page, not the Hostinger default page.
- Login, registration, admin dashboard, customer portal, worker portal, invoice print, contract print, and file uploads work.
- Browser console has no missing build asset errors.
- `storage/logs/laravel.log` has no new production errors.
- Public visitors can access the landing page only after business approval.

## Queue And Scheduler

The app is configured for database queues. Even if most current actions are synchronous, keep a queue worker ready for mail, notifications, and later background jobs.

On Hostinger Cloud, use hPanel Cron Jobs instead of a long-running Supervisor daemon. Create these cron jobs from hPanel: Websites -> Dashboard -> Advanced -> Cron Jobs.

Laravel scheduler cron:

```cron
* * * * * cd /home/<user>/domains/nofaclean.com/apps/production/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Database queue drain cron:

```cron
* * * * * cd /home/<user>/domains/nofaclean.com/apps/production/current && /usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --stop-when-empty >> storage/logs/queue-cron.log 2>&1
```

If the Hostinger Cloud cron UI provides a PHP command type, set the command body to the same `artisan` command and use the full app path. If cron uses a different PHP binary path, confirm it in hPanel Terminal with:

```bash
which php
php -v
```

## Hostinger Cloud Notes

- Use hPanel File Manager, Git/SSH, or hPanel Terminal depending on what is enabled for the Cloud plan.
- Hostinger's Laravel guidance places the Laravel project above `public_html`; keep source files outside the web root.
- If custom document roots are not available, the only files in `public_html` should be the contents of Laravel's `public` directory plus the adjusted `index.php`.
- Use hPanel Cron Jobs for `php artisan schedule:run` and the database queue drain command.
- If `npm ci` is unavailable on the Hostinger Cloud plan, run `npm ci && npm run build` locally/CI and upload `public/build`.

## Storage And Permissions

Run:

```bash
php artisan storage:link
```

Writable paths:

- `storage`
- `bootstrap/cache`

Recommended permissions:

```bash
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

The app stores worker evidence, checklist photos, issue photos, and customer payment proofs on the Laravel `public` disk. Confirm `https://nofaclean.com/storage/...` URLs are accessible only for files intentionally stored as evidence/proofs. If private evidence storage is required later, move these flows to signed URLs.

## Security Checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://nofaclean.com`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_ENCRYPT=true`
- Real `APP_KEY` generated on the server.
- Real database user has access only to the Nofa Clean database.
- Real `.env` is outside public access and never committed.
- No debug/test route is exposed.
- `/vendor`, `/storage`, `.env`, and project source folders are not directly web browsable.
- Google Maps browser key is referrer-restricted.
- SMTP credentials are production-only and stored outside the repository.
- Remove or rotate demo passwords before launch.

## Backups

Daily backup targets:

- MySQL database dump.
- `storage/app/public` uploaded evidence and payment proof files.
- Current `.env` stored securely outside the web root.

Keep:

- 7 daily backups.
- 4 weekly backups.
- 3 monthly backups.

Test restore before public launch:

1. Restore the production database backup into a temporary database.
2. Restore `storage/app/public`.
3. Open a completed visit with photos and a customer invoice/payment proof.

## Rollback

Before every cutover, keep the previous release folder and database backup.

Fast rollback:

1. Point the web root or `current` symlink back to the previous release.
2. Restore the previous `.env` if it changed.
3. If migrations are not backward compatible, restore the database backup.
4. Run:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

5. Re-test login and the dashboard.

## Launch Acceptance

Production can be considered ready for public launch only when:

- Production UAT on `https://nofaclean.com` has passed.
- `https://nofaclean.com` serves the Nofa Clean Laravel landing page.
- Admin, customer, and worker role redirects work.
- Booking request to admin approval works.
- Contract creation, customer acceptance, worker visit completion, supervisor approval, invoice, and payment proof approval work.
- Dashboard and reports reflect the seeded or real business data.
- Backups and rollback have been tested.
