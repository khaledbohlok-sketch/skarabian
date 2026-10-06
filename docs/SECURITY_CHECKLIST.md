# Security checklist (specification section 5)

Each requirement, how it is implemented and where. ✅ = implemented and tested; ⚙️ = implemented, needs a setting or a
step at installation; 📋 = a procedure for people (the software supports it).

## Login and accounts

| Requirement | Status | How / where |
|---|---|---|
| Each person has their own account; no shared logins | 📋 ✅ | One account per person (Users). Every action is logged with the user, so shared logins would be visible. Sessions list shows each device. |
| Strong passwords (10+, mixed) | ✅ | `Passwords::validate`: minimum 10 characters, upper + lower case, digit and symbol, must not contain the name/username. |
| bcrypt hashing | ✅ | `password_hash(PASSWORD_BCRYPT)` in `Passwords::hash`. Old passwords are never imported. |
| Forced change on first login | ✅ | `must_change_password` on every new / reset / imported account; the portal allows nothing else until it is changed. |
| 2FA (authenticator app or email code) | ✅ | TOTP (RFC 6238, with replay protection) or a 6-digit email code. |
| 2FA required for Owner, GM, Accountant, HR | ✅ | `require_2fa` on those roles (editable by the Owner per role); users of those roles must set it up before using the portal. |
| 5 failed attempts → locked 15 minutes, Owner alerted | ✅ | `AuthService::attempt`; numbers in `config.php → security`. Owner gets an in-app + email alert. |
| CAPTCHA after 3 failed attempts | ✅ | Per username and per IP address (`Captcha`, image generated server-side). |
| Session timeout 30 minutes | ✅ | `security.session_timeout` = 1800 s, checked on every request; idle sessions also closed by the hourly cron. |
| Log out of all devices; sessions list; Owner can end any session | ✅ | My account → Sessions; Administration → Active sessions; Users → Log out everywhere; Security → Log out everyone. |
| Owner alerted on login from a new device or country | ✅ ⚙️ | Known devices per user; alert by email. Country needs `security.geo_lookup`: `header` (Cloudflare `CF-IPCountry`) or `ipapi`. |
| Restrict login to Qatar / business hours per role | ✅ ⚙️ | Role settings: allowed country code, business hours only (hours in Security settings). If the country cannot be determined the login is not blocked. |
| Deactivate an employee's account instantly | ✅ | Employee profile → *Deactivate login* (also automatic when the employee is set to Left); all their sessions end at once. |

## Application security

| Requirement | Status | How / where |
|---|---|---|
| Prepared statements everywhere | ✅ | All queries go through `App\Core\DB` with bound parameters; table/column names are checked with `DB::assertIdent`. |
| XSS protection | ✅ | Every output escaped with `e()`; Content-Security-Policy restricts scripts to the site itself. |
| CSRF on every form and API call | ✅ | Token checked in `Kernel` for every POST (forms and fetch calls); rejected attempts are logged. |
| Every endpoint checks login and permission on the server | ✅ | `Auth::requirePerm` / resource `can*()` in every controller; record-level limits (groom's assigned horses, own records within 24 h, own payslips only). Denied attempts get "Access denied" and are logged. |
| Secure cookies (HttpOnly, Secure, SameSite) | ✅ | `Session::start`: HttpOnly, Secure on HTTPS, SameSite=Lax, strict mode, ID regenerated at login. |
| HTTPS only with HSTS | ✅ ⚙️ | `.htaccess` redirects to HTTPS; HSTS header (`security.hsts`). Needs the SSL certificate (AutoSSL). |
| Security headers | ✅ | CSP, X-Frame-Options SAMEORIGIN, X-Content-Type-Options nosniff, Referrer-Policy, Permissions-Policy (`Response::securityHeaders`). |
| Uploads: allowed types, size limit, renamed, outside public folder, authorised access only | ✅ | `FileStore`: type checked from the file content (images, PDF, MP4 for horse videos), size limit, random names, images re-encoded to WebP (removes hidden content), stored in `storage/uploads`, streamed by `FilesController` after a permission check. |
| Sensitive fields encrypted | ✅ ⚙️ | QID, passport, bank name/IBAN, account numbers: libsodium secretbox with `app.key`. **Keep a safe copy of the key.** |
| Sensitive fields hidden without "view sensitive"; views logged | ✅ | Salaries, allowances, bank and ID numbers, purchase prices, per-horse profit/loss, balances. Each view is logged (`view_sensitive`). |
| No error details shown to users | ✅ | `ErrorHandler`: users see a short message with a reference; details in `storage/logs` (outside the web root). `debug` must stay `false`. |
| Old portal files and APIs removed | 📋 ✅ | Go-live step 11 in INSTALL.md; `.htaccess` answers 410 Gone for old portal/API URLs. |

## Monitoring and recovery

| Requirement | Status | How / where |
|---|---|---|
| Complete activity log (logins, logouts, failed logins, sensitive views, create/edit/delete, approve, print, export) with old/new values, user, time, IP, device | ✅ | `Audit::log`, called by the CRUD engine, services, auth, Studio (print/download/share) and exports. |
| Tamper-proof, cannot be edited or deleted | ✅ ⚙️ | No code path updates or deletes it; every entry is chained with an HMAC of the previous one (Activity log → *Check log integrity*, also in the weekly report). Optional database triggers (`009_activity_log_guard.optional.sql`) block changes at database level where the hosting allows triggers. |
| Only the Owner sees the full log | ✅ | Others with Activity access (Technical Support) see login and security events only. |
| Daily automatic backups (database + files), kept 30 days | ✅ ⚙️ | Cron task `backup` at 02:00; needs the cron job (INSTALL.md step 6). |
| Off-server copy | ⚙️ | Google Drive (Shared drive + service account) or FTP/FTPS (INSTALL.md step 7). Until set, the Backups page warns. |
| Restore test | ✅ ⚙️ | Every night the newest dump is restored into `backup.restore_test_db` and row counts are compared; without a test database, the dump is read end-to-end and checked for every table. The Owner is alerted if it fails. |
| Weekly security email to the Owner | ✅ | Sundays 08:00: failed logins, locked accounts, new users and roles, permission and account changes, large exports, blocked access attempts, log-chain status. |

## Before go-live — tick each

- [ ] `config.php`: `debug` false, `env` production, staging password removed, real `app.url`.
- [ ] `app.key` stored in the Owner's password manager (and not anywhere public).
- [ ] SSL certificate active for skarabian.com (padlock, no warnings).
- [ ] Cron job running (Settings → Scheduled tasks shows recent runs).
- [ ] Backup off-server copy configured and a restore test passed (Backups → Test the latest backup).
- [ ] Mail sending works (daily summary received).
- [ ] Every user: own account, new password, 2FA where required; old accounts reviewed; Technical Support has no finance/HR access.
- [ ] Old portal files moved out of `public_html`; old database user removed; `old_db` removed from config.
- [ ] `php tests/unit.php` and `php tests/lang_check.php` pass on the server.
