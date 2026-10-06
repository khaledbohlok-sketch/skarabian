# SK Arabians — website and Management System

The new **skarabian.com**: a horse-first public website (English / Arabic) and the **SK Arabians Management System** staff portal, with **SK Arabian Studio** (official documents) built in.

- PHP 8.2+, MySQL 8 / MariaDB 10.6+, Apache (GoDaddy cPanel shared hosting). No Node, Composer or Docker needed on the server.
- One database; every horse, embryo, employee, client, supplier, bill and document exists once and is linked everywhere.
- Strict roles with server-side permission checks, 2-factor login, an encrypted and tamper-evident activity log.

## Documentation

| Document | For |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | Installing on GoDaddy cPanel: upload, database, config, first Owner, cron, backups, go-live |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Bringing the data across from the old portal (preview, report, import) |
| [docs/USER_GUIDE_EN.md](docs/USER_GUIDE_EN.md) | Short guide per role (English) |
| [docs/USER_GUIDE_AR.md](docs/USER_GUIDE_AR.md) | دليل الاستخدام المختصر لكل دور (عربي) |
| [docs/SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md) | Every security requirement and where it is implemented |

## Folders

```
app/            application code (no file here is reachable from the web)
  Controllers/  Site (public website, installer) and Portal (management system)
  Core/         framework: router, database, sessions, auth, CSRF, audit log, encryption
  Resources/    one class per record type (fields, lists, filters, permissions) used by the generic CRUD screens
  Services/     business rules: finance, payroll, horses, Studio documents, backups, cron, old-data import
  Views/        templates (site/, portal/, studio/, layouts/)
config/         config.sample.php → copy to config.php (never committed)
cron/run.php    the one scheduled job (every 15 minutes)
database/migrations/   versioned SQL schema, applied once each
lang/           en.php and ar.php — every label in both languages
migration/      optional mapping for the old-data import
public/         the ONLY web-visible folder (document root): index.php, assets, .htaccess
storage/        uploads, backups, logs, sessions, cache (outside the web root, denied by .htaccess)
tests/          unit tests, translation check, sample old database for import tests
tools/          command-line helpers: migrate, create_owner, keygen, import_old, seed_demo
```

## Local development

```bash
cp config/config.sample.php config/config.php     # set db, app.key (php tools/keygen.php), env=local, debug=true
php tools/migrate.php                             # create tables + reference data
php tools/seed_demo.php --yes                     # optional demo data (prints the demo logins)
php -S 127.0.0.1:8080 -t public tools/dev-router.php   # then open http://127.0.0.1:8080/en and /portal
```

## Tests

```bash
php tests/unit.php          # money, payroll, horse rules, passwords, 2FA, encryption, import name matching
php tests/lang_check.php    # every label used in the code exists in English and Arabic
php cron/run.php --list     # scheduled tasks and their last run
```

The importer can be tried against `tests/fixtures/old_system_sample.sql`, an example old database that reproduces the
problems listed in the specification (see docs/MIGRATION.md).
