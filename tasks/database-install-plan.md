# Database Install & Connection Setup (fresh server)

## Overview

The repository was moved to a new server. PostgreSQL 17, PHP 8.3 (with `pdo_pgsql`),
Apache 2 and Composer are all already installed and running, and Apache's DocumentRoot
is already `/var/www/html`. What is missing is the database itself: the `zozocal` role
and database do not exist, the schema has never been loaded, and `vendor/` is not present.

`config/database.php` already points at `127.0.0.1:5432`, database `zozocal`, user
`zozocal` — so the database is created to match the existing config rather than editing
the config.

`docs/sql/pg_schema.sql` is the authoritative PostgreSQL schema (61 tables, 37 triggers).
The other `docs/sql/*.sql` files are legacy MySQL migration scripts already folded into it,
with one exception: `nav_permissions.sql` also carries the seed rows for the sidenav, and
`pg_schema.sql` contains no seed data at all. `helpers/auth.php:287` hides any nav item not
listed in `nav_permissions`, so without those rows the sidenav renders completely empty.

## Tasks

- [x] 1. Create the `zozocal` PostgreSQL role and database matching `config/database.php`
- [x] 2. Load `docs/sql/pg_schema.sql` into the new database
- [x] 3. Seed `nav_permissions` from the portable INSERTs in `docs/sql/nav_permissions.sql`
- [x] 4. Run `composer install` (`vendor/` is gitignored and absent; `helpers/notifications.php` requires it)
- [x] 5. Confirm Apache DocumentRoot + PHP PostgreSQL driver are correct (expected: no change)
- [x] 6. Fix `html/test-db.php` — it still uses MySQL `SHOW TABLES` and backtick quoting, so the
       repo's own connection-test page errors out on PostgreSQL
- [x] 7. Verify end to end: PDO connect, table count, register a user, log in

## Review

### What was done

1. **Created the database** — `zozocal` role and `zozocal` database in the local PostgreSQL 17
   instance, owned by the `zozocal` role. Credentials match `config/database.php` exactly, so
   no application config was edited.
2. **Loaded the schema** — `docs/sql/pg_schema.sql` applied cleanly: 61 tables, 131 indexes,
   37 triggers, 1 function, zero errors.
3. **Seeded `nav_permissions`** — 83 rows extracted from the INSERT statements in
   `docs/sql/nav_permissions.sql` (19 professional, 30 restaurant, 26 affiliate, 8 always-visible).
   Without these the sidenav renders empty, because `helpers/auth.php:287` shows only nav items
   present in that table. The INSERTs were already portable ANSI SQL and matched the PostgreSQL
   column list; only the surrounding MySQL DDL in that file was skipped.
4. **Installed dependencies** — `composer install` pulled 8 packages (mailersend, guzzle and
   their dependencies) into the gitignored `vendor/`.
5. **Fixed `html/test-db.php`** — it was still MySQL-only (`SHOW TABLES`, backtick-quoted
   identifiers) and errored on PostgreSQL. Now uses `information_schema.tables` and double-quoted
   identifiers, and checks this product's tables (`users`, `restaurants`,
   `professional_appointments`) instead of the leftover `tasks`/`teams`.
6. **Made `logs/` writable by the web server** — group changed to `www-data` with mode 775, so
   the Retell voice-agent log writes in `helpers/retell-auth.php` succeed.

### What needed no change

Apache's DocumentRoot was already `/var/www/html`, `php_module` was loaded, PHP 8.3 already had
`pdo_pgsql`, and `pg_hba.conf` already permitted scram-sha-256 password auth on `127.0.0.1` —
which is exactly how `config/database.php` connects.

### Verification

- PDO connects as the app does: driver `pgsql`, server 17.10, 61 tables visible.
- `/test-db.php` through Apache reports connection success and all tests passing.
- Registered a professional account through the real HTMX flow — the multi-table transaction
  (users, restaurants, user_restaurants, settings, sections, turn_times, operating_hours)
  committed, including `lastInsertId()` on PostgreSQL.
- Logged in with that account and loaded `/app.php`: full professional sidenav rendered, no PHP
  errors.
- Loaded every professional partial (dashboard, calendar, services, clients, availability,
  time-off, reports, settings): all HTTP 200, no PHP errors or SQL exceptions.
- The smoke-test account was then deleted, leaving the database clean (0 users, 0 restaurants)
  with the 83 nav rows intact.

### Notes

- The database is empty of application data by design — `pg_schema.sql` carries no seed rows and
  the 1,640 rows from the original MySQL migration are not in the repository. Create the first
  account at `/register.php`, choosing "professional" as the location type.
- Two features stay inert until credentials are supplied: the Retell voice agent needs
  `RETELL_API_KEY` in the environment (`config/config.php`), and Google sign-in needs the values
  in `config/google-oauth.php`. Neither blocks normal email/password use of the app.
- As the README notes, the database password is committed in `config/database.php`. It is
  unchanged here, but moving it to an environment variable and rotating it is still worth doing.
