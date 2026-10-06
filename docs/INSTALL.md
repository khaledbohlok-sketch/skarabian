# Installation guide — GoDaddy cPanel

This guide installs the new system first on a **password-protected staging address** (`new.skarabian.com`), then moves
it to `skarabian.com` after the Owner signs off. Nothing here needs SSH; where a command is shown, the cPanel
**Terminal** works too, but there is always a no-terminal way.

Time needed: about one hour, plus the old-data review.

---

## 1. Check the hosting

In cPanel:

1. **MultiPHP Manager** → set the domain (and later the subdomain) to **PHP 8.2** or newer.
2. **Select PHP Version → Extensions** (or *MultiPHP INI Editor*): make sure these are on:
   `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `gd` (with WebP), `fileinfo`, `zip`, `curl`, `iconv`. `ftp` only if you use FTP backups.
3. **MultiPHP INI Editor** → `upload_max_filesize = 20M`, `post_max_size = 25M`, `memory_limit = 256M`, `max_execution_time = 120`.
4. **SSL/TLS Status** → run AutoSSL so `skarabian.com` and `new.skarabian.com` have certificates. The system forces HTTPS.

## 2. Create the database

cPanel → **MySQL® Databases**:

1. *Create New Database*: e.g. `cpuser_ska`.
2. *Add New User*: e.g. `cpuser_ska`, with a long generated password. Keep it.
3. *Add User To Database* → tick **ALL PRIVILEGES**.
4. Optional, recommended: create a second, empty database `cpuser_ska_restore` and add the same user to it.
   The nightly backup is restored into it to prove the backup really works.

## 3. Upload the files

The application must live **outside** `public_html`; only the contents of `public/` are visible on the web.

1. On your computer, make a zip of the project folder (everything in this repository).
2. cPanel → **File Manager** → open your home folder (`/home/cpuser`), **Upload** the zip, then **Extract** it so you get
   `/home/cpuser/skarabian/app`, `/home/cpuser/skarabian/public`, …
3. Folder permissions: `storage/` and everything in it must be writable by PHP (755 for folders is normal on cPanel).

**Staging (`new.skarabian.com`)**: cPanel → **Domains** (or *Subdomains*) → create `new.skarabian.com` with document root
`/home/cpuser/skarabian/public`. That is all; nothing else to copy.

**Live (`skarabian.com`)** (do this only at go-live, step 11): the main domain's document root is `public_html`. Move the
old site out of it, then copy the *contents* of `skarabian/public/` (including the hidden `.htaccess`) into `public_html`.
`index.php` finds the application in `/home/cpuser/skarabian` automatically.

## 4. Configure

1. In File Manager, copy `skarabian/config/config.sample.php` to `skarabian/config/config.php` and edit it:

| Setting | Value |
|---|---|
| `app.url` | `https://new.skarabian.com` for staging, later `https://skarabian.com` |
| `app.env` | `staging` (later `production`) — `debug` stays `false` |
| `app.key` | leave as is for now — the installer gives you one (step 5) |
| `app.staging_user` / `staging_password` | any user/password: the whole staging site asks for it. Set both to `null` at go-live |
| `db.*` | the database name, user and password from step 2 |
| `mail.*` | GoDaddy email: host `smtpout.secureserver.net`, port `465`, `ssl`, the mailbox and its password (e.g. `no-reply@skarabian.com`) |
| `backup.restore_test_db` | `cpuser_ska_restore` if you created it |
| `old_db` | the old portal's database, for the import (docs/MIGRATION.md) |

2. Save. `config.php` is protected: it is outside the web root and never committed.

## 5. Run the installer (creates the tables and the Owner)

1. Open `https://new.skarabian.com/install` (enter the staging password if asked).
2. If it shows **"config.php has no app key yet"**, copy the line it gives you into `config.php` (`'key' => '…'`), save,
   and **keep a copy of that key somewhere safe** (password manager). It encrypts ID, passport and bank numbers and signs
   the activity log: if it is lost, that data cannot be read. Reload the page.
3. Paste the app key, enter the Owner's name, username, email and a strong password, press **Install**.
4. Log in at `/portal/login`. You are asked to set up **2-factor authentication** with an authenticator app
   (Google Authenticator, Microsoft Authenticator, 1Password…). Do it now.

The installer page disappears (404) once the Owner exists.

*With Terminal instead:* `php tools/keygen.php`, put the key in config.php, then `php tools/migrate.php` and
`php tools/create_owner.php "Full Name" username email@example.com`.

## 6. Scheduled tasks (cron) — required

cPanel → **Cron Jobs** → *Add New Cron Job*:

- Common settings: **Once Per Fifteen Minutes** (`*/15 * * * *` in the minute field: `*/15`, others `*`)
- Command:

```
/usr/local/bin/php /home/cpuser/skarabian/cron/run.php >/dev/null 2>&1
```

(If GoDaddy shows a different PHP path for 8.2, e.g. `/opt/cpanel/ea-php82/root/usr/bin/php`, use that.)

This one job runs everything when it is due:

| Task | When |
|---|---|
| Overdue bills, clean-up of expired access and idle sessions | every hour |
| Backup (database + files), off-server copy, restore test | daily 02:00 |
| Exchange rates (floating currencies only) | daily 05:00 |
| Horse categories from age | daily 05:10 |
| Reminders: documents, vet/farrier due, foalings, low stock, expiring medicines, budgets | daily 06:00 |
| Owner's daily summary (email / WhatsApp) | daily, time set in Settings (default 07:00) |
| Weekly security report to the Owner | Sundays 08:00 |

Check it works: **Administration → Settings → Scheduled tasks** shows the last run of each task. A warning appears there
if nothing ran for 2 hours.

## 7. Backups and the off-server copy

Backups run every night and are kept 30 days in `skarabian/storage/backups`. The Owner can also run one, download it
and test it in **Administration → Backups**.

Keep a copy **outside** the server — choose one:

**Google Drive (needs a Google Workspace Shared Drive):**
1. In Google Cloud Console create a project → enable *Google Drive API* → *Service accounts* → create one → *Keys* →
   *Add key* → JSON. Download the file.
2. Upload the JSON file to `/home/cpuser/skarabian/storage/` (outside the web root; `.json` is also blocked by .htaccess).
3. In Google Drive create a **Shared drive** "SK Backups", add the service account's email as *Content manager*,
   create a folder in it and copy the folder ID from its URL.
4. In config.php: `backup.gdrive_service_account_json` = the full path of the JSON file, `backup.gdrive_folder_id` = the ID.

(Service accounts cannot store files in a personal "My Drive"; that is why a Shared drive is needed.)

**FTP / FTPS** to any other server or NAS: fill `backup.ftp` (host, user, pass, dir, ssl).

Run **Backups → Back up now**: the message shows the off-server result. Old copies there are removed after 30 days too.

## 8. Optional: WhatsApp alerts

The daily summary can also go to the Owner's WhatsApp through the **WhatsApp Cloud API** (Meta for Developers →
WhatsApp → API setup). Put the permanent token and phone number ID in `config.php → whatsapp`, then in
**Settings** enter the Owner's WhatsApp number and tick *Daily summary by WhatsApp*. Without it, everything still works
by email and in-app notifications.

## 9. Fill in the basics

As Owner, in this order:

1. **Settings**: approval limit (default QAR 5,000), company names, C.R. number, daily summary.
2. **Website → Contact details**, **Homepage & texts**.
3. **Finance → Accounts**: cash, bank and petty cash with opening balances.
4. **Currencies & rates**: check the rates (QAR base).
5. **Roles & permissions**: review the default roles; **Users**: create one account per person (no shared logins).
6. Import the old data — see **docs/MIGRATION.md**.

## 10. Test on staging

Let each role log in on `new.skarabian.com` and do their daily work for a few days (the user guides list what each role
does). The Owner checks the migration report line by line and the reports against the old figures.

## 11. Go-live

1. Final backup of the old site and its database (cPanel → Backup).
2. Run the old-data import one last time on the live database (or re-install and import), as in docs/MIGRATION.md.
3. In `config.php`: `app.url = https://skarabian.com`, `app.env = production`, `staging_user`/`staging_password` = `null`.
4. Move the old site's files out of `public_html` (keep them in a private folder for 30 days, then delete them),
   copy the contents of `skarabian/public/` into `public_html`.
   The new `.htaccess` answers *410 Gone* for the old portal and API URLs.
5. Remove `old_db` from config.php, and remove the old portal's database user (or its access) in cPanel.
6. Every user logs in and sets a new password and 2FA; the Owner reviews **Users** and deactivates anything not needed.
7. Google Search Console: submit `https://skarabian.com/sitemap.xml`.
8. Check **Administration → Settings → Scheduled tasks** the next morning and that the daily summary email arrived.

## Troubleshooting

| Problem | Fix |
|---|---|
| White page / "Something went wrong" | Read `skarabian/storage/logs/app-YYYY-MM.log`. Errors are never shown to visitors. |
| Login says the form expired | Session cookies need HTTPS; open the site with `https://`. |
| Emails not arriving | Settings → *Connections* shows the mail driver; check `mail.*`. With driver `log`, emails are written to `storage/logs/mail.log` instead of sent. |
| Uploaded photos fail | PHP `gd` with WebP, `fileinfo`, and the upload size limits in step 1. |
| Cron "not running" warning | Check the PHP path in the cron command; run it once from Terminal to see the output: `php cron/run.php --list`. |
| Activity log tamper protection | `database/migrations/009_activity_log_guard.optional.sql` adds database triggers if the hosting allows TRIGGER; otherwise it is skipped and the hash chain still detects changes (Activity log → Verify). |
