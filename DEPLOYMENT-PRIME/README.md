# LOKA — DEPLOYMENT-PRIME (Hostinger KVM)

Staging target prepared for:

| Item | Value |
|------|--------|
| Domain | `https://lokastage.dictr2.cloud` |
| Server IP | `187.77.150.203` |
| Document root | `/home/lokaloka/htdocs/lokastage.dictr2.cloud` |
| Hosting | Hostinger KVM 2 |

## What’s in this folder

| Path | Purpose |
|------|---------|
| `htdocs/` | **Upload these files** into the document root above |
| `.env.staging.example` | Template for server `.env` (copy → `.env`, fill secrets) |
| `set-permissions.sh` | Run **on the server** after upload |
| `crontab.example` | Email + SMS cron lines (CLI and HTTP options) |
| `README.md` | This guide |

Do **not** upload your local `.env` with XAMPP secrets. Create `.env` on the server from the example.

---

## 1. Upload

1. Zip `DEPLOYMENT-PRIME/htdocs/*` (or SFTP the folder contents).
2. Extract / sync into:

   `/home/lokaloka/htdocs/lokastage.dictr2.cloud/`

   The site root should contain `index.php`, `config/`, `pages/`, `cron/`, etc.  
   **Do not** nest an extra `htdocs/` or `public_html/` folder inside the document root unless Hostinger’s panel expects that (your panel says root = `lokastage.dictr2.cloud`).

3. On the server:

```bash
cd /home/lokaloka/htdocs/lokastage.dictr2.cloud
cp /path/to/DEPLOYMENT-PRIME/.env.staging.example .env
nano .env   # set DB_*, APP_URL, MAIL_*, APP_KEY, etc.
```

4. PHP dependencies (if `vendor/` is missing or incomplete):

```bash
cd /home/lokaloka/htdocs/lokastage.dictr2.cloud
composer install --no-dev --optimize-autoloader
```

5. Frontend build (only if `assets/dist/.vite/manifest.json` is missing):

```bash
npm ci
npm run build
```

---

## 2. Permissions

Copy `set-permissions.sh` to the server (or run from a checkout), then:

```bash
chmod +x set-permissions.sh
./set-permissions.sh /home/lokaloka/htdocs/lokastage.dictr2.cloud
```

Typical Hostinger PHP user is your account user (`lokaloka`). The script sets:

- Directories `755`, files `644`
- Writable: `cache/`, `logs/`, `uploads/` (+ subdirs) → `775` / `664`
- Private: `.env` → `640`
- Blocks web listing on sensitive trees via existing `.htaccess` where present

---

## 3. Database

1. Create DB + user in Hostinger (MySQL).
2. Put credentials in `.env`.
3. Run migrations from the app root:

```bash
cd /home/lokaloka/htdocs/lokastage.dictr2.cloud
php migrate.php
# or individual migrations under migrations/ as needed (incl. 023 SMS, 024 email mode, 028 odometer)
```

---

## 4. Cron jobs (email + SMS)

See `crontab.example` for copy-paste lines.

### Recommended (CLI) — every 2 minutes

```cron
*/2 * * * * /usr/bin/php /home/lokaloka/htdocs/lokastage.dictr2.cloud/cron/process_queue.php >> /home/lokaloka/htdocs/lokastage.dictr2.cloud/logs/cron-email.log 2>&1
*/2 * * * * /usr/bin/php /home/lokaloka/htdocs/lokastage.dictr2.cloud/cron/process_sms_queue.php >> /home/lokaloka/htdocs/lokastage.dictr2.cloud/logs/cron-sms.log 2>&1
```

Confirm PHP path: `which php` or `which php8.2`.

### Alternative (HTTP) — if CLI cron is awkward

1. Log in as **All Father** → System Control → **Email**.
2. Note / rotate **Cron secret**.
3. Schedule:

```cron
*/2 * * * * curl -fsS "https://lokastage.dictr2.cloud/?page=cron&action=email&key=YOUR_CRON_SECRET" >> /home/lokaloka/htdocs/lokastage.dictr2.cloud/logs/cron-email-http.log 2>&1
*/2 * * * * curl -fsS "https://lokastage.dictr2.cloud/?page=cron&action=sms&key=YOUR_CRON_SECRET" >> /home/lokaloka/htdocs/lokastage.dictr2.cloud/logs/cron-sms-http.log 2>&1
```

### App settings after go-live

- **Email delivery mode**: prefer `queued` or `hybrid` on the VPS (All Father → Email) so pages stay fast.
- **SMS**: enable + gateway in All Father → SMS. SMS is **queued on notify** and drained by the SMS cron (does not block request submit).

---

## 5. Quick verify

```bash
curl -I https://lokastage.dictr2.cloud
php /home/lokaloka/htdocs/lokastage.dictr2.cloud/cron/process_queue.php
php /home/lokaloka/htdocs/lokastage.dictr2.cloud/cron/process_sms_queue.php
```

- Login works  
- `.env` not downloadable in browser  
- Email/SMS pending rows clear after cron  

---

## Rebuild this package from the repo

From Windows (repo root `prod-loka-push`):

```powershell
powershell -ExecutionPolicy Bypass -File .\DEPLOYMENT-PRIME\scripts\build-package.ps1
```

That refreshes `DEPLOYMENT-PRIME/htdocs/` from `public_html` (plus `vendor` / `assets/dist` from local live tree when present).
