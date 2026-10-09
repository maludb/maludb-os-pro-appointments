# Pro Appointments in the MaluDB Business OS — the adoption record

ZozoCal-Professional (appointment scheduling for professional services — consultants, coaches, therapists,
stylists, tutors) is being **adopted** by the Business OS kernel (the `os-adopt` route of the
`maludb-os-integration` plugin 0.9.0): it keeps its code, schema and users table, gains the kernel's sign-on
beside them, and hands the management of people and businesses to the kernel — while the flag `OS_ENABLED` is
on. With the flag off it runs exactly as it does today. The result is the application `pro_appointments`,
repository `github.com/maludb/maludb-os-pro-appointments`, installed by the kernel's installer (`os-install`),
never by hand.

The codebase is the pre-split ZozoCal (the restaurant, professional and affiliate products in one tree, switched
by `restaurants.location_type`); the Restaurant product was adopted on 2026-09-28 as `reservations`
(`maludb-os-reservations`, branch `os-adoption`). This adoption follows that one — same shape, re-fitted — and
records only what differs. The owner's standing decisions for ZozoCal (2026-09-25) carry over: each business is
a kernel **site**; the application declares its own roles and a grant is scope + role; application users are not
OS users by default; standalone mode stays behind `OS_ENABLED`; **fresh installs only**; the OpenAI, Retell and
Twilio keys stay in the application for now.

## 1. Survey (2026-10-09, at source commit 4dde841)

Code wins over documentation; every answer cites the code.

### 1.1 Stack
- PHP 8.3 (`never` return types in `html/api/v1/_bootstrap.php`). Page-per-file plus HTMX partials:
  `html/app.php` is the shell (`requireAuth()` at the top), the sidebar loads `/partials/<area>/<x>.php` into
  `#page-content`; the sidebar items come from `nav_permissions` by the business's `location_type`
  (`getPermittedNavItems()`, `helpers/auth.php`). The fallback mode is `professional` (`html/app.php`, 2026-09-25).
- PostgreSQL 17. **The current schema is `docs/sql/pg_schema.sql`** (61 tables; the six professional tables at
  lines 846–1021). `docs/sql/nav_permissions.sql` — the sidebar seed — is **MySQL syntax** (ENUM, AUTO_INCREMENT,
  COMMENT): a fresh PostgreSQL install had no sidebar rows until this adoption converted it (db/002).
  `restaurant_reservations.sql`, `sql/multi-language-relay.sql` and the rest of `docs/sql/` are history.
- Configuration was hard-coded: the database password in `config/database.php`, the Google client secret in
  `config/google-oauth.php`, a Retell key in `helpers/retell-auth.php:63` and another in `scripts/launch-agent.php:21`.
  **Fixed before the first commit of this repository** (`config/app.php`: `app_config()` — the environment,
  `config/.env`, `config/local.php`, a default). The source repository's history still holds them; this one does not.
- Hard-coded per server: `zozocal.com` as the Retell webhook host (`html/partials/settings/retell-create-agent.php:100`,
  `retell-assign-phone.php:50`), the default `to_number` `+18473930142` (`html/api/retell/pro-webhook.php`,
  `restaurant-webhook.php`, `webhook-call-end.php`), `/var/www` paths in cron headers.
- Code that does not match the schema (recorded, out of scope): `models/User.php` (`users.password`, `status`,
  `remember_token`, `org_id` — none exist) so **forgot/reset password is broken**; `html/partials/team/*`,
  `activity/*`, `agents/*`, `kanban/*` and the other SalesCoach-era partials belong to `docs/migrations/001-salescoach-schema.sql`,
  which `pg_schema.sql` does not contain.

### 1.2 Every way into a session
- **The login function:** `login_user($user, $remember)` (`helpers/auth.php:50`). Sets `$_SESSION['user']`
  (`id, email, first_name, last_name, role, is_platform_admin`), `user_id`, `logged_in_at`, `is_affiliate`,
  `affiliate_id`, `current_restaurant_id`, `current_role`, `current_restaurant_name` (the first membership; a
  super-admin with none gets the first active business as `admin`), updates `users.last_login_at`.
- **Callers:** the password form `html/partials/auth/login.php:52`; Google `html/google-callback.php:110` (login) and
  `:146` (invitation); Google sign-up `html/partials/auth/google-complete.php:151`; registration
  `html/partials/auth/register.php:167`; invitation acceptance `html/partials/auth/accept-invite.php:71`.
- **Remember-me does nothing:** the token is set (`auth.php:103–114`) and never read back.
- **Password reset** does not log in, and is broken (1.1).
- **Other session writers:** `switchRestaurant()` (`auth.php:137`) from `html/partials/auth/switch-restaurant.php`
  (POST, **no CSRF**); `html/partials/auth/switch-mode.php` writes `users.product_type` (POST, **no CSRF**; the
  column is not read by the shell any more). No impersonation.
- **The guard:** `requireAuth()` (`auth.php:168`) checks only that `$_SESSION['user']` exists — HTMX gets 401 +
  `HX-Redirect: /login.php`, others `Location: /login.php`. Deactivating a user or membership does not end an open
  session. 234 of 394 PHP files under `html/` carry a guard (`requireAuth`, `requireAdmin`, `requireManager`,
  `requireSuperAdmin`).
- **Unguarded on purpose:** index, login, register, forgot/reset password, logout, google-callback, privacy, terms,
  sms-signup, landing pages, the auth partials, the public booking pages (`html/pro-booking/*`, by the profile's
  `booking_slug`; confirm/cancel/modify check CSRF), the bearer-token REST API (`html/api/v1/*`) and the MCP servers
  (`html/api/mcp/*`), the webhooks.
- **Unguarded gaps:** 1.6.

### 1.3 People and tenants (`docs/sql/pg_schema.sql`)
- `users` (46–72): `email` UNIQUE, `password_hash` NOT NULL (markers `!INVITED`, `!GOOGLE`), `google_id`,
  `auth_provider`, `user_type`, `user_mode`, `product_type`, `role` (`user` | `affiliate` | `super-admin`),
  `is_platform_admin`, `is_active` smallint, `is_affiliate`, `referral_code`.
- **The tenant table is `restaurants`** (17–40): a professional business is a row with `location_type = 'professional'`
  (the README's "known design debt"); `slug` UNIQUE, `timezone`, `is_active` smallint, `status` (`in-setup`…),
  SaaS billing columns. `professional_profiles` (846–878) extends it one-to-one: `booking_slug` UNIQUE (the public
  page `/pro-booking/?professional=<slug>`), `owner_user_id` NOT NULL **UNIQUE**, the booking rules. A business
  works without its profile row until an admin saves Settings (`html/partials/professional/save-settings.php`
  inserts it; `getProfessionalProfile()` left-joins it).
- `user_restaurants` (199–215): `(user_id, restaurant_id)` UNIQUE, `role` ∈ admin | manager | user, `is_active`.
  A user may belong to several businesses (`helpers/restaurant.php:44–100`; affiliate users also see the businesses
  under their affiliate).
- **Platform super-admin** (`users.role = 'super-admin'`): `getUserRestaurants()` lists only affiliate locations in
  setup for them (`restaurant.php:52–60`) but `switchRestaurant()` admits them to any business as `admin`
  (`auth.php:146`); the platform screens `html/partials/platform/*`.
- **Screens that manage people and tenants** (read-only under `OS_ENABLED`): staff `partials/settings/users.php`,
  `user-form.php`, `save-user.php`, `toggle-user.php`; the REST `html/api/v1/staff.php` (same logic); platform users
  `partials/platform/users.php`, `platform-user-form.php`, `save-platform-user.php`, `toggle-platform-user.php`;
  businesses `platform/restaurants.php`, `restaurant-form.php`, `save-restaurant.php`, `toggle-restaurant.php`;
  the affiliate's client users `partials/affiliate/save-client-user.php`, `update-client-user.php`,
  `cancel-client-user.php`, `invite-client-user-form.php`, `edit-client-user-form.php`; self-service sign-up creates
  user + business + admin membership (`partials/auth/register.php:81–130`, `google-complete.php`).
- **Current tenant:** `$_SESSION['current_restaurant_id']`, chosen at login, changed by the switcher; `app.php`
  loads the first business when the session has none.
- **Its tables against the estate** (shared-schema.md §1): the application keeps its own schema. The tables this
  adoption **adds** are the canonical ones verbatim — `sso_nonces`, `member_sessions`, `directory_sync_state`
  (the sign-on kit §2, as `maludb-os-reservations` db/003 and every kit application's db/001 define them) and the
  roles catalogue `app_roles` / `app_rights` (Reservations' shape). `users` + `os_member_id` is the mirror;
  `user_restaurants` with `source = 'os'` is the holding; no `members`, `os_scopes` or `member_scope_roles` are
  added (adapter.md §1). Nothing is read from another application.

### 1.4 Machine access
- **API keys** (`api_tokens`, SHA-256, one year): `api_authenticate()` (`html/api/v1/_bootstrap.php:84`) checks expiry,
  `users.is_active` and an active membership in the key's business; the key acts as its creator with their role.
  `/api/v1/auth.php?action=login` issues 90-day keys from a password.
- **MCP:** `html/api/mcp/pro.php` (the receptionist's 13 tools: services, slots, book/lookup/cancel/confirm/modify,
  preferences, todos, good news) and `html/api/mcp/sms.php` (8), bearer = the business's `mcp_api_key` setting
  (`helpers/api-auth.php:25`), tools limited to that business. `html/api/mcp/server.php` is the restaurant's.
- **Webhooks:** the Retell custom functions (`html/api/retell/{check-availability,…}.php`) go through
  `parseRetellRequest()` whose **signature check is commented out** (`helpers/retell-auth.php:94–100`);
  `pro-webhook.php`, `restaurant-webhook.php`, `webhook-call-begin.php`, `webhook-call-end.php` have none; the
  Twilio webhooks `api/sms/webhook.php` and `api/sms/twilio_pro.php` have **no `X-Twilio-Signature` check**;
  the SMS agent's context webhooks `api/sms/pro-webhook.php` and `api/sms/text-agent-webhook.php` answer **anyone**
  who posts a business's number with the business, the client matched by `from_number` and their SMS history;
  `api/email/webhook.php` has **none** (and runs OpenAI + sends mail on the business's keys). `sms_agents.webhook_auth_token`
  exists in the schema and is used by no code.
- **Cron:** `html/cron/generate-invoices.php`, `mark-overdue.php` are **web-reachable** (no CLI check);
  `helpers/send-professional-reminders.php` and `scripts/process-voice-messages.php` are outside the web root.

### 1.5 What it holds that the kernel should (recorded; the move is deferred by the owner)
- Per-business keys in `settings`: `retell_api_key`, `openai_api_key`, `sms_api_key`/`sms_api_secret`/
  `sms_from_number`, `mailersend_api_key`, `mcp_api_key`. From config: Google OAuth, `RETELL_DEFAULT_API_KEY`.
- Audit log `activity_log` (pg_schema 558) written by direct inserts (booking confirm/modify/cancel, the Retell
  webhooks, `helpers/voice-api.php`, `professional-booking.php`); there is no one function.
- Mail: MailerSend (`helpers/notifications.php`, `professional-notifications.php`). SMS: Twilio REST. Voice: Retell.

### 1.6 Security gaps (fixed before sign-on, one commit each)
| # | Gap | Where |
|---|---|---|
| S1 | **Invitation takeover:** accepting an invitation needs only the email; anyone who knows an invited address sets its password and is signed in | `html/partials/auth/accept-invite.php:52–74`; invitations made by `settings/save-user.php` and `api/v1/staff.php` |
| S2 | Cross-business password takeover | **already fixed in the source** (395ff97, `save-user.php:81–90`, `staff.php`) |
| S3 | Retell signature check commented out; four Retell webhooks with none | `helpers/retell-auth.php:94–100`, `html/api/retell/pro-webhook.php`, `restaurant-webhook.php`, `webhook-call-begin.php`, `webhook-call-end.php` |
| S4 | Both Twilio SMS webhooks unsigned | `html/api/sms/webhook.php`, `twilio_pro.php` |
| S5 | Inbound email webhook unsigned (spends the business's OpenAI and mail) | `html/api/email/webhook.php` |
| S6 | One business's Retell key used for every other (`getRetellApiKey()` step 2; `retellApiCall()`, `create-web-call.php`, `create-demo-call.php`, `process-voice-messages.php` pass no business) | `helpers/retell-auth.php:36–64`, `helpers/retell-api.php:23` |
| S7 | Public diagnostics: `phpinfo()`, the table list, a mail sender that writes settings, the error-log viewer | `html/info.php`, `html/test-db.php`, `html/test-email.php`, `utils/error-log-viewer.php` |
| S8 | `mcp_api_key` handed out without login (any business by `?slug=`) | `html/downloads/retell-agent-import.php:11–43` |
| S9 | Public demo pages spend the server-wide Retell key | `html/demo.php`, `html/call.php`, `html/api/retell/create-demo-call.php` |
| S10 | Web-reachable cron | `html/cron/*.php` |
| S11 | Business switch and product-mode switch without CSRF | `html/partials/auth/switch-restaurant.php`, `switch-mode.php` |
| S12 | Secrets and personal data in logs: reset tokens, failed-login emails, raw Retell/SMS/MCP bodies, callers' numbers, message bodies | `partials/auth/forgot-password.php:50–51`, `login.php:45,54`, `reset-password.php`, `retell-auth.php:81,130`, `api/mcp/{pro,sms,server}.php:99`, `api/sms/*.php`, `api/retell/*.php`, `api/email/webhook.php` |
| S13 | **The SMS agent's context webhooks are open:** a business's number in, its client's name, phone, e-mail and SMS history out | `html/api/sms/pro-webhook.php`, `text-agent-webhook.php` |

## 2. Decisions

Proposed 2026-10-09 with the recommendation taken provisionally (the owner asked for the conversion to begin;
each can still be reversed, and the record says where).

| Decision | Proposal | State |
|---|---|---|
| Scope kind | `location` — each professional business is a kernel **site** (as Reservations; a practice trades from a place, and the kernel's site carries the name, time zone and address the business needs) | recommended, taken |
| Catalog key, DNS, repository | key `pro_appointments` (the installer admits `[a-z0-9_]`), label **`appointments`** → `appointments.<domain>`, install path `/srv/apps/pro_appointments`, database `<tenant>_pro_appointments`, repository `maludb/maludb-os-pro-appointments` (private, as Reservations) | recommended, taken |
| Name, business area, category | "Pro Appointments", Sales & Service, `calendar` | recommended, taken |
| Roles | the three it has: `admin` "Business admin" (capability `admin`, the one `is_admin`), `manager` "Manager" (`write`), `user` "Staff" (`write`); rights named after what the guards allow: `desk.view` (everyone: the dashboard, the to-do list, the message logs — the product gates the calendar, clients and services behind `requireManager()`), `appointments.manage` (manager+: the calendar and appointments, clients, services, availability, time off, reports), `business.admin` (admin: settings, the booking page, integrations, keys, staff) | recommended, taken |
| How roles reach the kernel | a new, tiny MCP endpoint `html/api/mcp/kernel.php` admitting the kernel's 60-second token to `app_roles` alone (the receptionist servers `pro.php`/`sms.php` keep their own bearer and stay customer-facing) | recommended, taken |
| Platform super-admin | the kernel's super-admin is the application's `super-admin` while `OS_ENABLED` is on, reaching businesses only through the kernel's grants; anyone else is `user` | recommended, taken |
| A site's business | the sync creates the `restaurants` row (`location_type = 'professional'`, the site's name, time zone, address, a slug from the name) with the notification settings a sign-up seeds; **the professional profile is not created** — the first admin saves Settings, which creates it as the product always did (`professional_profiles.owner_user_id` is NOT NULL and UNIQUE per user, so a sync with no person cannot write it) | recommended, taken |
| The restaurant and affiliate code | left in place and unreachable (every site is `professional`; the README's clean-up is a separate job, not the adoption's) | recommended, taken |
| SaaS billing, products, subscriptions | left as they are, super-admin only; the billing crons are not installed by the OS deploy | as Reservations |
| Model and provider keys | stay per business in `settings` | deferred (owner, 2026-09-25) |
| History | this repository starts clean (the source's history and tree carried credentials); the source stays at `maludb-ed/ZozoCal-Professional` | taken |

## 3. What the adoption owes afterwards (the rest of the contract, not built here)
The activity log shipped to MaluDB, the `mcp_*` views and the two MCP servers (records, activity) the kernel's
agents would use, the JSON-mode shim for the kernel's actions server, the keys moved to the kernel, an expert
agent, the command bar through the chat endpoint. Named here so nobody builds substitutes; every endpoint is
registered `agent_reachable: false` until then.
