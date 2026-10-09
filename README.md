# Pro Appointments — `maludb-os-pro-appointments`

Appointment scheduling for anyone who offers professional services — consultants, coaches, therapists, stylists,
tutors and similar single-provider businesses — as an application of the **MaluDB Business OS**. The professional
defines services, availability and time off; clients book, change and cancel appointments online, by SMS or by
talking to an AI voice agent. The kernel signs people in, owns who they are and which businesses they hold, and
turns each of its sites into a business here.

This is ZozoCal-Professional (`maludb-ed/ZozoCal-Professional`, commit 4dde841) **adopted** onto the kernel with the
`os-adopt` route of the `maludb-os-integration` plugin — the application keeps its code, schema and users table and
gains the kernel's sign-on beside them, behind one flag, `OS_ENABLED`. With the flag off it is the standalone
product. The repository starts with a clean history because the source's carried credentials. The whole record —
survey, decisions, what was built, what was proven, what the owner still decides and what the contract still owes —
is **`docs/os-adoption.md`**.

## Installing beside the kernel

The kernel's installer does everything from `maludb-os.json`:

```
sudo php /var/www/bin/app_install.php plan  /srv/apps/pro_appointments --domain <domain>
sudo php /var/www/bin/app_install.php apply /srv/apps/pro_appointments --by <super-admin email> --domain <domain> --scheme https
```

Catalog key `pro_appointments`, served at `appointments.<domain>`, database `<tenant>_pro_appointments`, the sites on
the application's Scopes tab, people granted per site as `admin`, `manager` or `user`. The owner's steps after apply
are in `docs/os-adoption.md` §5.

## Standalone

1. Create a PostgreSQL 17 database and load `docs/sql/pg_schema.sql`, `docs/sql/nav_permissions.sql`,
   `docs/sql/os_adoption.sql` in that order (`docs/sql/README.md`).
2. Copy `config/local.example.php` to `config/local.php` and fill it in (nothing per server is in the code).
3. `composer install` (`vendor/` is not committed).
4. Point Apache's document root at `html/`.

## What is here

- Professional dashboard, services, availability rules, time off, appointments, calendar, client directory, reports
  and settings — `html/partials/professional/`; the public booking flow — `html/pro-booking/`
- Slot engine, booking and notification logic — `helpers/professional-*.php`
- AI voice and SMS scheduling agents — `helpers/professional-voice-api.php`, `html/api/mcp/pro.php`,
  `html/api/retell/pro-webhook.php`, `html/api/sms/*`
- The REST API — `html/api/v1/`; the kernel-only MCP endpoint — `html/api/mcp/kernel.php`
- The OS adapter — `helpers/os.php`, `html/sso.php`, `html/sso/logout.php`, `scripts/os-directory-sync.php`,
  `deploy/`, `maludb-os.json`; the proofs — `tests/os-adoption/`
- Requirements — `requirements.md`; the technology — `tech-stack.md`

The restaurant and affiliate code of the combined ZozoCal codebase is still in the tree, unreachable (every business
is `professional`); removing it is a separate clean-up, as is renaming the `restaurants` tenant table.

## License

See `LICENSE`.
