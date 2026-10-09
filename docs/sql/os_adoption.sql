-- Pro Appointments — changes made while adopting the application into the MaluDB Business OS (2026-10).
-- Load after pg_schema.sql and nav_permissions.sql, on a new install and on an existing one:
-- every statement is safe to run again.

-- Invitations carry a one-time code (security fix S1): accepting one needs the code the admin was shown,
-- not just the email address. Only a SHA-256 of the code is kept.
ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_code_hash char(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_expires_at timestamp;

-- ---------------------------------------------------------------------------------------------------------------
-- Sign-on from the Business OS kernel (docs/os-adoption.md). Nothing here changes a standalone install's behaviour:
-- it is read only while OS_ENABLED is on.

-- The kernel's ids, beside the application's own: a user is one kernel member, a business one kernel scope (a site).
ALTER TABLE users ADD COLUMN IF NOT EXISTS os_member_id bigint;
CREATE UNIQUE INDEX IF NOT EXISTS users_os_member_id_key ON users (os_member_id);
ALTER TABLE restaurants ADD COLUMN IF NOT EXISTS os_scope_id bigint;
CREATE UNIQUE INDEX IF NOT EXISTS restaurants_os_scope_id_key ON restaurants (os_scope_id);

-- Who made a membership: 'local' (the application's own screens) or 'os' (the kernel's grant; replaced whole on
-- every sign-on and directory sync). While OS_ENABLED is on only 'os' rows admit anyone.
ALTER TABLE user_restaurants ADD COLUMN IF NOT EXISTS source varchar(5) NOT NULL DEFAULT 'local';
DO $$ BEGIN
    ALTER TABLE user_restaurants ADD CONSTRAINT user_restaurants_source_check CHECK (source IN ('local', 'os'));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- The sign-on kit's tables, verbatim (maludb-os-integration, php-sign-on-kit.md §2; the canonical definitions
-- every application of the estate carries).
-- A hand-off token is single use: its nonce is kept until the token would have expired.
CREATE TABLE IF NOT EXISTS sso_nonces (
    nonce       text PRIMARY KEY,
    member_id   bigint NOT NULL,
    expires_at  timestamptz NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS sso_nonces_expires_idx ON sso_nonces (expires_at);

-- Every session opened through the kernel, so a sign-out notice or a revocation ends them all.
CREATE TABLE IF NOT EXISTS member_sessions (
    session_hash  text PRIMARY KEY,              -- sha256 of the PHP session id
    member_id     bigint NOT NULL,               -- the kernel's member id
    created_at    timestamptz NOT NULL DEFAULT now(),
    last_seen_at  timestamptz NOT NULL DEFAULT now(),
    ended_at      timestamptz,
    ended_by      text CHECK (ended_by IN ('member', 'kernel', 'expired', 'directory'))
);
CREATE INDEX IF NOT EXISTS member_sessions_member_idx ON member_sessions (member_id) WHERE ended_at IS NULL;

-- The kernel's change feed cursor, one row.
CREATE TABLE IF NOT EXISTS directory_sync_state (
    id           smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    next_cursor  text,
    full_at      timestamptz,
    last_run_at  timestamptz,
    last_error   text,
    updated_at   timestamptz NOT NULL DEFAULT now()
);
INSERT INTO directory_sync_state (id) VALUES (1) ON CONFLICT DO NOTHING;

-- The roles the application publishes to the kernel (app_roles on /api/mcp/kernel.php) and the rights each gives —
-- in the application's own words, matching what its guards already allow: requireAuth (the dashboard, to-dos, the
-- message logs), requireManager (the calendar, clients, services, availability, time off, reports), requireAdmin (settings,
-- integrations, keys, staff).
CREATE TABLE IF NOT EXISTS app_rights (
    right_key    text PRIMARY KEY,
    description  text NOT NULL,
    sort_order   integer NOT NULL
);
CREATE TABLE IF NOT EXISTS app_roles (
    role_key     text PRIMARY KEY,
    name         text NOT NULL,
    description  text NOT NULL,
    capability   text NOT NULL CHECK (capability IN ('read', 'write', 'admin')),
    is_admin     boolean NOT NULL DEFAULT false,
    rights       text[] NOT NULL,
    sort_order   integer NOT NULL
);
DELETE FROM app_rights WHERE right_key NOT IN ('desk.view', 'appointments.manage', 'business.admin');
INSERT INTO app_rights (right_key, description, sort_order) VALUES
    ('desk.view',           'See the dashboard and today''s appointments, keep the to-do list, read the SMS, voice-call and email logs', 1),
    ('appointments.manage', 'The calendar: make, change, confirm, complete and cancel appointments; clients; services; availability and time off; reports', 2),
    ('business.admin',      'Settings and the booking page, notifications, integrations, voice prompts, API and agent keys, staff', 3)
ON CONFLICT (right_key) DO UPDATE SET description = EXCLUDED.description, sort_order = EXCLUDED.sort_order;
INSERT INTO app_roles (role_key, name, description, capability, is_admin, rights, sort_order) VALUES
    ('admin',   'Business admin', 'Runs the business in Pro Appointments: everything, settings, integrations and keys included.', 'admin', true,
        ARRAY['desk.view', 'appointments.manage', 'business.admin'], 1),
    ('manager', 'Manager', 'Runs the day and the business''s setup: appointments, clients, services, availability, time off, reports.', 'write', false,
        ARRAY['desk.view', 'appointments.manage'], 2),
    ('user',    'Staff', 'Sees the dashboard, keeps the to-do list and reads the message logs.', 'write', false,
        ARRAY['desk.view'], 3)
ON CONFLICT (role_key) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description, capability = EXCLUDED.capability,
    is_admin = EXCLUDED.is_admin, rights = EXCLUDED.rights, sort_order = EXCLUDED.sort_order;
