# ZozoCal-Professional

Appointment scheduling for anyone who offers professional services — consultants, coaches,
therapists, stylists, tutors and similar single-provider businesses. The professional defines
services, availability and time off; clients book, change and cancel appointments online, by SMS
or by talking to an AI voice agent.

## Why this repository exists

ZozoCal began as a restaurant reservation system. It was later stretched into a generic scheduler
for professional services, and then into an affiliate system, all inside one codebase switched by
a `product_type` column. Those three products should not live together, so on 2026-09-21 the
combined codebase was split into three repositories:

| Repository | Product |
|------------|---------|
| [ZozoCal-Restaurant](https://github.com/maludb-ed/ZozoCal-Restaurant) | Restaurant reservation system |
| **ZozoCal-Professional** (this one) | Appointment scheduling for professional services |
| [ZozoCal-Affiliate](https://github.com/maludb-ed/ZozoCal-Affiliate) | Affiliate referral and prospect pipeline |

**Current state:** this repository is a full copy of the combined codebase, with its complete
history. It still contains the restaurant and affiliate code. Removing that code is the next
step, and it happens here, independently of the other two repositories.

## What belongs here

- Professional dashboard, services, availability rules, time off, appointments, calendar,
  client directory, reports and settings — `html/partials/professional/`
- Public client booking flow — `html/pro-booking/`
- Slot engine, booking and notification logic — `helpers/professional-availability.php`,
  `helpers/professional-booking.php`, `helpers/professional-notifications.php`,
  `helpers/send-professional-reminders.php`
- AI voice and SMS scheduling agents — `helpers/professional-voice-api.php`,
  `html/api/mcp/pro-tools.php`, `html/api/retell/pro-webhook.php`, `html/api/sms/pro-webhook.php`
- Tables: `professional_profiles`, `professional_services`, `professional_availability_rules`,
  `professional_time_off`, `professional_clients`, `professional_appointments`
  (`docs/sql/professional_scheduling.sql`)
- Requirements — `requirements.md`

## What should be removed from this repository

- Restaurant reservations: `html/partials/reservations/`, `tables/`, `sections/`, `waitlist/`,
  `guests/`, `html/booking/`, the restaurant voice/SMS/email agents, the restaurant-only tables,
  and `restaurant-reservation-requirements.*`
- Affiliate system: `html/partials/affiliate/`, `html/partials/platform/affiliate*.php`,
  and the `affiliates`, `affiliate_*`, `prospects` and `prospect_*` tables
- The `product_type` switching in `html/app.php` and `html/register.php`, once only the
  `professional` mode remains

## Known design debt

The professional product was built on top of the restaurant tenant model to avoid a refactor:
a professional business is a row in `restaurants`, linked to its users through
`user_restaurants`. Now that this product has its own repository, that tenant model should be
renamed to something neutral (for example `businesses`) as part of the cleanup.

## Technology

- PHP on Apache (traditional LAMP layout — `html/` is the web root, everything above it is private)
- PostgreSQL 17 — full schema in `docs/sql/pg_schema.sql`
- HTMX partials under `html/partials/`, Bootstrap 5 (Kobie theme)
- Retell AI for voice agents; SMS agent backed by OpenAI

See `tech-stack.md` for the details.

## Setup

1. Create a PostgreSQL database and load `docs/sql/pg_schema.sql`.
2. Set the connection details in `config/database.php`.
3. Run `composer install` (`vendor/` is not committed).
4. Point the Apache document root at `html/`.

## Security note

This repository is private because its history contains credentials (database password, Google
OAuth client secret, Retell API key, tokens in `logs/`). Move them to environment variables,
rotate them, and clean the history before this repository is ever made public.

## License

Apache License 2.0 — see `LICENSE`.
