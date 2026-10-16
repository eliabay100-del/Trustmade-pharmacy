# Deployment runbook

Everything needed to take TRUSTMED PHARMACY from the repo to a working
production install, in order. Read it once end to end before starting.

The app is a standard Laravel 13 / PHP 8.4 application with a MySQL database,
file sessions, file cache, and no queue worker or background jobs to run. The
scheduler only does housekeeping (session GC, cache pruning, nightly backups).

---

## 1. Server requirements

- PHP 8.4 with `pdo_mysql`, `gd`, `zip`, `fileinfo`, `openssl`, `mbstring`.
- MySQL 8.x or MariaDB reachable from the app.
- Node 24+ and Composer **on the build machine** (assets are compiled, then the
  server only needs PHP — no Node).
- The MySQL client tools (`mysqldump`) available for backups.

---

## 2. Environment file

Copy the template and fill it in:

```bash
cp .env.production.example .env
```

Must be set correctly:

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | the real URL users will visit |
| `APP_TIMEZONE` | `Africa/Addis_Ababa` (already in the template) |
| `DB_*` | host, port, database, username, password |
| `SESSION_SECURE_COOKIE` | `true` once served over HTTPS |
| `TRUSTED_PROXIES` | proxy IPs, only if behind a reverse proxy |
| `ADMIN_USERNAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` | first administrator; the username is what you sign in with (see step 5) |

`APP_KEY` is **not** set here — it is generated in step 4.

> Business identity (name, logo, colours, VAT rate, receipt footer) is *not* in
> `.env`. It lives in the `settings` table and is edited in the app under
> Business Settings.

---

## 3. Build and install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

---

## 4. Application key — do not reuse the development key

The development `APP_KEY` appears in git history (commit `e56951a`), so it must
not be used in production:

```bash
php artisan key:generate --force
```

This rewrites `APP_KEY` in `.env`. Do it on the server, after copying `.env`.

**Optional hardening:** because the old key is in the repository history, treat
it as public forever. Rewriting published history (to scrub it) is destructive
and is left to the repository owner; if you want it gone, use
`git filter-repo` or the BFG repo cleaner and force-push, coordinating with
everyone who has a clone.

---

## 5. Database

```bash
php artisan migrate --force
php artisan db:seed --class=Database\Seeders\ProductionSeeder --force
```

- `migrate` builds the schema. It also guarantees the core posting accounts the
  accounting code looks up (`1001`, `1002`, `1200`, `3000`, `4000`, `5000`), so
  the app will not crash on the first sale even before seeding.
- `ProductionSeeder` is idempotent and creates **only** what a real deployment
  needs: the Main Branch, the chart of accounts, the default expense categories,
  branding defaults and one administrator. It does **not** insert the demo
  drugs/products.
- If `ADMIN_PASSWORD` is blank, the seeder generates a strong password and
  prints it once. Copy it, then sign in with the administrator **username**
  (`ADMIN_USERNAME`, default `admin`) and change the password immediately
  (see step 9). Staff sign in with a username, not an email address.

Do **not** run the default `php artisan db:seed` in production — it also loads
`CatalogSeeder`, which puts ~15 demo drugs and ~20 demo products into the live
catalog.

---

## 6. Storage and caches

```bash
php artisan storage:link
php artisan optimize
```

`storage:link` is what makes uploaded logos and the default logo visible under
`public/storage`. `optimize` caches config, routes and views — remember that
while these caches exist, edits to `.env`, route files and Blade templates are
ignored until you run `php artisan optimize:clear`.

Set the correct permissions so the web user can write to `storage/` and
`bootstrap/cache/`.

---

## 7. Scheduler (housekeeping + backups)

Add one cron line for the web user:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

This runs:

- `session:gc` daily,
- `cache:prune-stale-tags` hourly, and
- `pmas:backup` nightly at 02:30.

### Backups

`pmas:backup` shells out to `mysqldump` and writes a gzipped SQL dump to
`storage/app/private/backups`, keeping the newest `BACKUP_KEEP` files (default
14). It finds `mysqldump` automatically on Windows; elsewhere put it on `PATH`
or set `MYSQLDUMP_PATH` in `.env`.

```bash
php artisan pmas:backup                 # run now
php artisan pmas:backup --keep=30       # override retention for this run
```

The password is passed through the `MYSQL_PWD` environment variable, never on
the command line. Backups are body-of-the-database dumps (`CREATE TABLE` +
`INSERT`); restore with `mysql` (or `phpMyAdmin`):

```bash
gunzip -c pmas-YYYY-MM-DD_HHMMSS.sql.gz | mysql -u USER -p DATABASE
```

Store a copy **off the machine** as well — a backup on the same disk does not
survive a disk failure. `BACKUP_KEEP` only bounds disk growth, not disaster
recovery.

---

## 8. Serving over HTTPS

The template assumes plain HTTP on the local network
(`SESSION_SECURE_COOKIE=false`). Before exposing the app beyond the LAN,
terminate TLS at a reverse proxy and:

- set `SESSION_SECURE_COOKIE=true`,
- set `TRUSTED_PROXIES` to the proxy's address(es) if the proxy is not on the
  same host, so `X-Forwarded-Proto`/`Host` are honoured and links are `https`,
- re-run `php artisan optimize` (or `optimize:clear`) so the new config is used.

---

## 9. Go-live checklist

- [ ] `.env` filled in, `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] Fresh `APP_KEY` generated on the server (step 4).
- [ ] `migrate --force` and `ProductionSeeder` completed without errors.
- [ ] Logged in as the seeded administrator and **changed its password**.
- [ ] Opening balances entered (Finance → **Opening Balances**) *before* any real
      sales or purchases — cash, wallets/bank, stock at cost, receivables, payables.
- [ ] Confirmed you can record a purchase, make a sale, and take an expense
      without errors (proves the chart of accounts is complete).
- [ ] Business Settings: correct name, logo, VAT rate and receipt footer.
- [ ] `storage:link` done; logo shows on the login page.
- [ ] Scheduler cron installed; `php artisan schedule:list` shows the jobs.
- [ ] `php artisan pmas:backup` produces a file, and you can restore it.
- [ ] Backups copied off the machine.
- [ ] HTTPS enabled and `SESSION_SECURE_COOKIE=true` if reachable beyond the LAN.
- [ ] Health endpoint `GET /up` returns 200.
- [ ] `php artisan optimize` run; `php artisan about` reports production.

---

## 10. Cash handling and opening balances (addressed)

Both gaps found during the schema review are now implemented:

- **Till closing posts its variance.** Closing a till still records the cash
  count, and now also posts the difference between counted and expected cash to
  `5200 Cash Over/Short`: an overage debits `1001 Cash` and credits `5200`; a
  shortage debits `5200` and credits `1001`. A till that matches posts nothing,
  and the ledger cash balance then agrees with the physical drawer.
- **Opening balances are entered at go-live.** Owner/Admin have an **Opening
  Balances** page (Finance menu) to set day-one cash, Telebirr / CBE Birr / bank /
  card, receivables, stock at cost and supplier payables. Owner's capital is
  derived so the entry always balances, and the page posts only the difference
  from the current books — so re-saving the same figures, or correcting a
  typo, changes nothing extra. Do this **once, before recording sales or
  purchases**.
