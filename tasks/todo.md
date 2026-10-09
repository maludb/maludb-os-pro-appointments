# Plan: adopt ZozoCal-Professional into the MaluDB Business OS (2026-10-09)

**Status:** in progress — the owner asked for the conversion to begin; decisions proposed in
`docs/os-adoption.md` §2 are taken as recommended and flagged there.

- [x] 0. A clean repository: `maludb/maludb-os-pro-appointments`, secrets out of the code (`config/app.php`), logs out
- [x] 1. Survey and decisions → `docs/os-adoption.md`
- [ ] 2. Security fixes S1, S3–S13 (S2 is already fixed), one commit each, proven by `tests/os-adoption/security-*.php`
- [ ] 3. The adapter: `db/` + `docs/sql/os_adoption.sql`, `helpers/os.php`, `/sso`, `/sso/logout`, the guard,
      the local sign-in closed, the read-only people and business screens, the directory sync, `app_roles`
      on `/api/mcp/kernel.php`, the application switcher in the header, `maludb-os.json`, `/api/v1/health`, `deploy/`
- [ ] 4. Proofs on a scratch copy (`tests/os-adoption/`): standalone unchanged, then every sign-on proof of adapter.md §9
- [ ] 5. `bin/app_install.php plan` from the kernel reads the repository clean
- [ ] 6. The record (`docs/os-adoption.md` §4–5), README, CLAUDE.md here; the kernel's README and CLAUDE.md

## Review
(written when the work is done)

---

# Plan: API and MCP coverage so AI agents can manage the application

**Status:** awaiting your approval — nothing changed yet.

## What exists today

**REST API** (`html/api/v1/`, token auth, JSON) was built for the mobile app. It is mostly read-only:

| Area | Read | Write |
|---|---|---|
| Appointments | yes | create, update, confirm / complete / cancel / no-show |
| Calendar, open slots | yes | — |
| Clients | yes | preferences only — **no create, no general edit** |
| Services | yes | **none** |
| Availability rules | yes | **none** |
| Time off | yes | **none** |
| Business profile / settings | yes | **none** |
| Todos | yes | create, update, delete |
| Reports, message logs (SMS / voice / email) | **none** | — |
| Staff users, phone numbers, voice / text / email agents, integrations | **none** | **none** |

**MCP** — two servers exist, but both are *customer-facing receptionist* tools (list services,
check slots, book / cancel / modify an appointment by confirmation code, plus todos on the SMS
server). There is **no MCP tool that manages the business** — nothing to add a service, set
hours, block time off, edit a client or change settings.

## Problems found that must be fixed first

1. **The REST API rejects every authenticated request.** Login issues a token, but every call
   using it returns 401 "Missing or invalid Authorization header". PHP runs as an Apache module
   here, which does not put a `Bearer` header into `$_SERVER['HTTP_AUTHORIZATION']` — the only
   place `_bootstrap.php` looks. The MCP servers read it the same way.
2. **Both MCP servers are open to the internet.** `mcp/pro.php` and `mcp/sms.php` allow every
   request when no `mcp_api_key` is set, and none is set. The SMS server's todo tools find the
   business from a caller phone number, so anyone who knows a business's number can read and
   change its todo list.
3. **The MCP key is not per business.** It takes the first `mcp_api_key` of *any* business
   (`LIMIT 1`).
4. **Agents have no way to get a credential** other than logging in with a person's email and
   password.

## Approach

- **One source of truth.** New write actions go into the REST API, following the pattern of the
  existing `v1` files. The management MCP server does not repeat that logic — each MCP tool is a
  small definition (name, description, input schema, REST method and path) and one dispatcher
  forwards the call to the REST API with the caller's own token. Adding a REST action then only
  needs a one-entry MCP definition.
- **Same credential for both.** An agent uses one API key for REST and MCP. Keys are ordinary
  `api_tokens` rows tied to a user and a business, so they carry that user's role (admin /
  manager / user) and can only see that business.
- **The existing receptionist MCP servers stay as they are,** apart from the security fixes.

## Tasks

### Phase 1 — Foundations
- [ ] 1.1 Read the `Authorization` header through a fallback to `getallheaders()` in
      `v1/_bootstrap.php`, `mcp/pro.php` and `mcp/sms.php`. Verify all 9 existing read endpoints
      return 200
- [ ] 1.2 **API keys page** in Settings (admin only): create a named key (shown once, stored
      hashed, 1-year expiry), list keys with last-used time, revoke. Adds a `last_used_at` column
      to `api_tokens`
- [ ] 1.3 Close the open MCP servers: refuse requests when no key is configured, and match the
      key to the business it belongs to instead of `LIMIT 1`

### Phase 2 — Fill the REST gaps (professional scheduling)
- [ ] 2.1 `services.php` — create, update, activate / deactivate
- [ ] 2.2 `availability-rules.php` — create, update, delete, activate / deactivate
- [ ] 2.3 `time-off.php` — create, delete
- [ ] 2.4 `clients.php` — create, full update
- [ ] 2.5 `profile.php` — update business profile and booking settings (same fields as the
      Settings screen)
- [ ] 2.6 New `reports.php` — the Reports screen's summary figures for a date range
- [ ] 2.7 New `messages.php` — read the SMS, voice-call and email logs

### Phase 3 — Fill the REST gaps (business administration) *(see question below)*
- [ ] 3.1 `staff.php` — list, invite / add, change role, deactivate
- [ ] 3.2 `phone-numbers.php`, `voice-prompts.php`, `text-agents.php`, `email-agents.php` — list,
      create, update, delete (voice prompts also sync to Retell, as the screen does)
- [ ] 3.3 `notifications.php`, `integrations.php` — read and update

### Phase 4 — Management MCP server
- [ ] 4.1 New `html/api/mcp/manage.php` (JSON-RPC `initialize`, `tools/list`, `tools/call`,
      same protocol as `pro.php`), authenticated with the API key
- [ ] 4.2 One tool per REST action — roughly 45 tools, e.g. `list_services`, `create_service`,
      `update_service`, `set_availability`, `add_time_off`, `create_client`, `book_appointment`,
      `cancel_appointment`, `update_business_settings`, `get_report`, `list_sms_messages`, …
- [ ] 4.3 Role enforcement comes from the REST layer (a `user`-role key cannot change settings)

### Phase 5 — Documentation
- [ ] 5.1 `docs/agent-api.md`: getting a key, REST endpoint reference, MCP connection example
      (Claude Code / Claude Desktop config), tool list
- [ ] 5.2 Update `docs/mobile-api-spec.md` with the new endpoints

### Phase 6 — Verify
- [ ] 6.1 A test script that calls every REST action and every MCP tool against
      Ed's HVAC Services, checks the result in the database, and removes its test data
- [ ] 6.2 Confirm a `user`-role key is refused on admin actions, a key for one business cannot
      touch another, a revoked key is refused, and the receptionist MCP servers refuse
      unauthenticated calls
- [ ] 6.3 `php -l` on every changed file; no new Apache errors
- [ ] 6.4 Log to `docs/activity.md`, commit per phase, push

## Deliberately not in scope
- **Super-admin platform screens and billing.** Billing depends on two tables that don't exist
  yet (see the strip plan below); platform management is one operator's job, not an agent's.
  Easy to add later with the same pattern.
- **Restaurant and affiliate features**, which are due to be removed.

## Review

(to be completed after implementation)

---

# Plan: Show the professional features instead of the restaurant screens

**Status:** complete (2026-09-25).

## What I found

**The professional features were never removed — they are hidden.** Everything you are looking
for is still in `html/partials/professional/` and its tables are all in the database:

| Feature | Screen | Table |
|---|---|---|
| Service options | `services.php`, `service-form.php` | `professional_services` |
| Operating availability | `availability.php`, `availability-form.php` | `professional_availability_rules` |
| Time off | `time-off.php` | `professional_time_off` |
| Appointments, calendar, clients | `calendar.php`, `clients.php`, … | `professional_appointments`, `professional_clients` |
| Business settings | `settings.php` | `professional_profiles` |
| Public booking page | `html/pro-booking/` | (same tables) |

The menu already has items for all of these, and `nav_permissions` grants them — but only when
the current business has `location_type = 'professional'`.

**Why you see Floor Plan and Waitlist instead.** Three things combine:

1. **There is no business in the database.** `restaurants` is empty, so your account has no
   current business at all.
2. **`html/app.php` line 34 falls back to restaurant mode** when there is no current business,
   so the menu shows the restaurant items (Floor Plan, Waitlist, Tables, Hours, Turn Times…).
3. **Signup also defaults to restaurant.** `partials/auth/register.php` and `google-complete.php`
   use `location_type ?? 'restaurant'`, so any new account gets restaurant mode unless the user
   picks otherwise.

**The strip plan below was never carried out.** It is still marked "awaiting approval" and no
files have been removed, which is why the restaurant screens are still here. This fix is
independent of it and much smaller.

## Tasks

- [x] 1. `html/app.php` — change the fallback from `'restaurant'` to `'professional'` (one line)
- [x] 2. `partials/auth/register.php` and `google-complete.php` — default `location_type` to
      `'professional'`; in `html/register.php` make "Professional" the pre-selected option in both
      dropdowns
- [x] 3. Create a professional business for your super-admin account
      (edward.honour@kineticseas.com) and link it as `admin`, so the professional screens have a
      business to work with. Named "Ed's HVAC Services" (slug `eds-hvac-services`).
- [x] 4. Verify: log in, confirm the menu shows Services, Availability, Time Off, Clients,
      Settings and no Floor Plan / Waitlist; load each screen and confirm HTTP 200 with no PHP/SQL
      errors; add one service and one availability rule; open the public `pro-booking` page for
      the business and confirm it offers slots
- [x] 5. Log to `docs/activity.md`, commit and push

## Not in scope

- Deleting the restaurant and affiliate code — that is the larger strip plan below, still
  waiting for your go-ahead. This fix makes the app behave correctly now whether or not that
  plan runs.

## Review

**Code changes (4 files, one line each):**
- `html/app.php` — the fallback mode is now `professional`.
- `partials/auth/register.php`, `google-complete.php` — signup defaults to `professional`.
- `html/register.php` — "Professional" is pre-selected in both Business Type dropdowns.

**Data:** created business id 2 "Ed's HVAC Services" (`location_type = professional`,
`status = active`), linked to edward.honour@kineticseas.com as `admin`, with a
`professional_profiles` row so the public booking page works.

**Bugs found during verification and fixed** — leftover MySQL syntax the PostgreSQL migration
missed:
- `helpers/professional-availability.php` — `DATE_SUB/DATE_ADD … INTERVAL col MINUTE` crashed
  every public booking slot lookup. Now uses `col * INTERVAL '1 minute'`.
- `partials/professional/save-settings.php` — `ON DUPLICATE KEY UPDATE` → `ON CONFLICT … DO UPDATE`.
- `api/retell/pro-webhook.php`, `api/sms/pro-webhook.php` — `CURDATE()` → `CURRENT_DATE`
  (voice and SMS agents' upcoming-appointment lookup).

**Verified over HTTP:**
- Login shows Services, Availability, Time Off, Clients and Settings, and no restaurant items.
- All 12 professional screens return 200 with no PHP or SQL errors.
- Saved a service, Mon–Fri 8am–5pm availability, and Settings.
- Public booking offers 8:00am–3:30pm on a Monday and nothing on Sunday.

**Test data left in place:** the "Furnace Tune-Up" service ($129, 60 min + 30 min buffer) and
the Mon–Fri availability. Edit or delete them in the app.

**Not fixed (outside this task):** `partials/platform/restaurants.php` (super-admin
"Manage Restaurants") also uses `CURDATE()` and errors. The restaurant screens (Floor Plan,
Waitlist) have the same problem, but they are slated for removal in the strip plan below.

---

# Plan: Strip the Affiliate and Restaurant products out of ZozoCal-Professional

**Status:** awaiting your approval — no files removed yet.

## Goal

Leave a standalone scheduling application for professional service providers. The restaurant
reservation product and the affiliate referral product come out entirely.

## Decisions you made

| Question | Decision |
|---|---|
| Platform super-admin area | **Keep**, minus the Affiliates screens |
| Billing / subscriptions | **Keep**, drop affiliate commissions only |
| Rename `restaurants` → `businesses` | **Defer** to a separate follow-up |
| Dead 4th-product code | **Remove**, called out separately for review |

## What the research found

**Safe to delete outright.** `ZozoCal-Restaurant` and `ZozoCal-Affiliate` already exist as full
copies with complete history, and every commit here is in git history, so nothing is lost by
removing code from this repository.

**The professional product barely touches the other two.** Only three lines in professional code
reference removed tables, all in `html/partials/professional/dashboard.php`:

- line 128 — `SELECT id FROM affiliates WHERE user_id = ?` (affiliate)
- line 171 — `COUNT(*) FROM restaurant_phone_numbers` (kept — shared voice infrastructure)
- line 181 — `COUNT(*) FROM restaurant_prompts` (kept — shared voice infrastructure)

So only line 128 has to change. The rest of the professional code is self-contained.

**A fourth, dead product is in here.** 87 files across `tasks/`, `models/`, `projects/`,
`kanban/`, `sessions/`, `teams/`, `organizations/`, `scoring/`, `coaching-calendar/`, `calendar/`,
`team/`, `activity/`, plus 11 root partials (`crm.php`, `customers.php`, `orders.php`, …) and 16
of the 17 files in `models/`. None are reachable from the nav, they only reference each other, and
they query tables (`tasks`, `sessions`, `roles`, `model_prompts`, `prospect_agents`) that do not
exist in the schema. `models/User.php` is the only model kept code uses.

**Two things are already broken and worth fixing while we are in here.** Both follow from your
"keep billing" decision:

1. `html/partials/billing/` queries `invoice_payments` and `prepay_transactions`, which do not
   exist in the database. They are defined only in `docs/sql/invoicing.sql`, in MySQL syntax that
   was never carried into `pg_schema.sql` during the PostgreSQL migration.
2. 16 nav ids in `app.php` are never granted by any `nav_permissions` row, so those menu items are
   invisible in every mode — including `nav-billing`, `nav-caption-billing` and
   `nav-platform-billing`. The billing UI you want to keep currently cannot be reached from the menu.

---

## Tasks

### Phase 1 — Safety net

- [ ] 1.1 Branch `strip-restaurant-affiliate` off `main` so `main` stays working
- [ ] 1.2 Dump the current database (schema + the 83 nav rows) to `/tmp` as a rollback point
- [ ] 1.3 Record a baseline: every professional page returns HTTP 200 with no PHP error

### Phase 2 — Remove the affiliate product

- [ ] 2.1 Delete `html/partials/affiliate/` (23), `html/partials/prospects/` (10),
      `html/partials/agents/` (7), `html/partials/products/` (3)
- [ ] 2.2 Delete the affiliate screens inside the platform area: `platform/affiliates.php`,
      `affiliate-detail.php`, `affiliate-form.php`, `affiliate-commission-form.php`,
      `affiliate-referral-form.php`, `save-affiliate.php`, `save-affiliate-commission.php`,
      `save-affiliate-referral.php`, `update-commission-status.php`
- [ ] 2.3 Delete `helpers/prospect-restaurant.php`; drop the prospect lookup from
      `html/api/sms/twilio_pro.php`
- [ ] 2.4 Remove the affiliate branch from `professional/dashboard.php` (line 128) and the
      affiliate nav block from `app.php`
- [ ] 2.5 Remove `is_affiliate` / affiliate handling from `partials/auth/register.php` and
      `google-complete.php`

### Phase 3 — Remove the restaurant product

- [ ] 3.1 Delete `html/partials/`: `reservations/` (11), `tables/` (8), `sections/` (4),
      `waitlist/` (9), `guests/` (5), `events/` (6), `reports/` (2), and `dashboard/`
- [ ] 3.2 Delete `html/booking/` (the restaurant public booking flow; `html/pro-booking/` stays)
- [ ] 3.3 Delete restaurant-only settings screens: `hours.php`, `hours-form.php`, `save-hours.php`,
      `turn-times.php`, `turn-time-form.php`, `save-turn-time.php`, `special-dates.php`,
      `special-date-form.php`, `save-special-date.php`, `special-date-reservations.php`,
      `save-meal-percentages.php`
- [ ] 3.4 Delete restaurant helpers: `availability.php`, `restaurant.php`, `meal-status.php`,
      `voice-api.php`, `send-reminders.php`; reduce `notifications.php` to the professional paths
      (`professional-notifications.php` already covers them)
- [ ] 3.5 Delete restaurant agent endpoints: `api/retell/{make,cancel,confirm,lookup}-reservation.php`,
      `check-availability.php`, `restaurant-webhook.php`, `webhook-call-begin.php`,
      `webhook-call-end.php`, `api/mcp/server.php`, `server-tools.php`, `api/sms/webhook.php`
      (the `pro-*` equivalents stay)
- [ ] 3.6 Remove the restaurant nav block from `app.php`

### Phase 4 — Remove the dead fourth product *(separate commit)*

- [ ] 4.1 Delete the 16 unreachable partial directories (87 files)
- [ ] 4.2 Delete the 11 unreferenced root partials, keeping `reset-password.php`
- [ ] 4.3 Delete all of `models/` except `User.php`
- [ ] 4.4 Delete `docs/migrations/001-salescoach-schema.sql`

### Phase 5 — Collapse the product switching

- [ ] 5.1 `register.php` / `partials/auth/register.php`: drop the location-type selector, always
      create `professional`. Also drop the restaurant seed data it writes on signup — the default
      sections, turn times and operating hours are all restaurant concepts
- [ ] 5.2 Delete `partials/auth/switch-mode.php` and its nav toggle
- [ ] 5.3 Remove `location_type` / `product_type` branching from the ~30 remaining files, keeping
      the professional branch
- [ ] 5.4 Delete the `product_type` column and the now-unused `location_type` filtering from
      `nav_permissions`, and delete the 56 restaurant and affiliate nav rows (27 professional and
      always-visible rows remain)

### Phase 6 — Database

- [ ] 6.1 Drop the restaurant tables: `reservations`, `reservation_tables`, `tables`,
      `combinable_tables`, `sections`, `waitlist`, `guests`, `operating_hours`, `turn_times`,
      `special_dates`, `events`
- [ ] 6.2 Drop the affiliate tables: `affiliates`, `affiliate_commissions`, `affiliate_referrals`,
      `prospects`, `prospect_activities`, `prospect_products`
- [ ] 6.3 Drop `location_type` from `restaurants` and `product_type` from `users`
- [ ] 6.4 Add the two missing billing tables `invoice_payments` and `prepay_transactions`,
      ported from `docs/sql/invoicing.sql` to PostgreSQL syntax
- [ ] 6.5 Regenerate `docs/sql/pg_schema.sql` from the live database so the schema file matches
      reality, and delete the superseded MySQL-era files in `docs/sql/`

### Phase 7 — Make kept features reachable *(judgment call — say if you would rather skip)*

- [ ] 7.1 Add the missing `nav_permissions` rows for billing, so the billing you chose to keep
      appears in the menu
- [ ] 7.2 Decide the same for the other unreachable items: message logs (SMS/voice/email), todos,
      phone numbers, and the Nav Permissions admin screen

### Phase 8 — Verify

- [ ] 8.1 `php -l` across every remaining PHP file
- [ ] 8.2 Grep for references to every deleted file, table and helper — expect zero hits
- [ ] 8.3 Register a professional account, log in, load every professional page and the kept
      platform/settings pages; confirm HTTP 200 and no PHP or SQL errors
- [ ] 8.4 Exercise the public `pro-booking` flow end to end
- [ ] 8.5 Delete the test account, leaving a clean database

### Phase 9 — Documentation and merge

- [ ] 9.1 Rewrite `README.md` to describe a single product, dropping the "what should be removed" section
- [ ] 9.2 Update `CLAUDE.md`, `tech-stack.md`, `requirements.md`; delete
      `restaurant-reservation-requirements.md`/`.docx`, `old_requirements.md`,
      `restaurant_reservations.sql`
- [ ] 9.3 Append to `docs/activity.md`; merge to `main` and push

## Expected outcome

Roughly 280 of 433 PHP files removed — about 95 restaurant, 45 affiliate, 90 dead code, plus
scattered files. 17 tables dropped, 2 added. The professional product itself is barely touched:
one line changes in `dashboard.php`, and the product-type branching collapses to its professional
branch.

## Sequencing note

Each phase is a separate commit, so any single step can be reverted on its own. Affiliate goes
first because it is the most self-contained; the dead code is isolated in its own commit so it
never obscures the product-split diff.

## Deliberately not in scope

- The `restaurants` → `businesses` rename (your decision to defer). Worth doing next, while the
  codebase is at its smallest.
- Moving the committed database password and API keys to environment variables, and cleaning them
  from history — still outstanding from the README's security note.

## Review

(to be completed after implementation)
