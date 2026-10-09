# Professional Scheduling Product Requirements

## Purpose

Define the requirements for a `product_type = 'professional'` mode inside the existing LAMP, HTMX, Bootstrap application. This mode is for a single-person professional service business and must replace the restaurant-specific workflow with appointment scheduling functionality.

## Design Direction

- Keep the existing authentication, session, and tenant model in place to avoid a large refactor.
- Continue using the current `restaurants` and `user_restaurants` tenant context internally for now, even when the visible product is a professional business.
- Treat `product_type` as the primary switch for application behavior:
  - `restaurant` = reservation system
  - `professional` = single-provider scheduling system
  - `affiliate` = referral pipeline
- Keep the first release focused on one provider per business account.
- Use the existing LAMP, HTMX, Bootstrap 5 stack and the current HTMX partial pattern under `html/partials/`.

## Database Changes Required

### Existing Tables Reused

- `users`
  - Reuse `product_type` to switch the application into professional mode.
- `restaurants`
  - Reuse the existing tenant/account record as the professional business container for now.
- `user_restaurants`
  - Reuse restaurant membership and role mapping for account access.

### New Table: `professional_profiles`

One row per professional business account.

- `id` BIGINT UNSIGNED PK
- `restaurant_id` BIGINT UNSIGNED NOT NULL UNIQUE
- `owner_user_id` BIGINT UNSIGNED NOT NULL UNIQUE
- `business_name` VARCHAR(255) NOT NULL
- `display_name` VARCHAR(255) NOT NULL
- `business_phone` VARCHAR(20) NULL
- `business_email` VARCHAR(255) NULL
- `timezone` VARCHAR(64) NOT NULL DEFAULT 'America/New_York'
- `booking_slug` VARCHAR(150) NOT NULL UNIQUE
- `slot_interval_minutes` INT NOT NULL DEFAULT 30
- `default_buffer_before_minutes` INT NOT NULL DEFAULT 0
- `default_buffer_after_minutes` INT NOT NULL DEFAULT 0
- `minimum_booking_notice_hours` INT NOT NULL DEFAULT 2
- `maximum_booking_horizon_days` INT NOT NULL DEFAULT 90
- `default_location_type` VARCHAR(30) NOT NULL DEFAULT 'in_person'
- `default_location_label` VARCHAR(255) NULL
- `booking_instructions` TEXT NULL
- `cancellation_policy` TEXT NULL
- `cancellation_notice_hours` INT NOT NULL DEFAULT 24
- `is_public_booking_enabled` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

### New Table: `professional_services`

Service catalog for the single provider.

- `id` BIGINT UNSIGNED PK
- `restaurant_id` BIGINT UNSIGNED NOT NULL
- `name` VARCHAR(255) NOT NULL
- `description` TEXT NULL
- `duration_minutes` INT NOT NULL
- `buffer_before_minutes` INT NOT NULL DEFAULT 0
- `buffer_after_minutes` INT NOT NULL DEFAULT 0
- `price` DECIMAL(10,2) NULL
- `currency_code` VARCHAR(10) NOT NULL DEFAULT 'USD'
- `location_type` VARCHAR(30) NULL
- `location_label` VARCHAR(255) NULL
- `color` VARCHAR(20) NULL
- `sort_order` INT NOT NULL DEFAULT 0
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `is_public_bookable` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:

- `idx_professional_services_restaurant_active (restaurant_id, is_active, sort_order)`

### New Table: `professional_availability_rules`

Recurring weekly working hours. Multiple rows per weekday are allowed.

- `id` BIGINT UNSIGNED PK
- `restaurant_id` BIGINT UNSIGNED NOT NULL
- `weekday` TINYINT UNSIGNED NOT NULL
- `start_time` TIME NOT NULL
- `end_time` TIME NOT NULL
- `location_type` VARCHAR(30) NULL
- `location_label` VARCHAR(255) NULL
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:

- `idx_professional_availability_restaurant_day (restaurant_id, weekday, is_active)`

### New Table: `professional_time_off`

Date-specific blocks, vacations, holidays, and manual unavailable windows.

- `id` BIGINT UNSIGNED PK
- `restaurant_id` BIGINT UNSIGNED NOT NULL
- `starts_at` DATETIME NOT NULL
- `ends_at` DATETIME NOT NULL
- `reason` VARCHAR(255) NULL
- `notes` TEXT NULL
- `is_all_day` TINYINT(1) NOT NULL DEFAULT 0
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:

- `idx_professional_time_off_restaurant_range (restaurant_id, starts_at, ends_at)`

### New Table: `professional_clients`

Client/contact records for the professional business.

- `id` BIGINT UNSIGNED PK
- `restaurant_id` BIGINT UNSIGNED NOT NULL
- `first_name` VARCHAR(120) NOT NULL
- `last_name` VARCHAR(120) NOT NULL
- `email` VARCHAR(255) NULL
- `phone` VARCHAR(20) NULL
- `birth_date` DATE NULL
- `notes` TEXT NULL
- `internal_notes` TEXT NULL
- `preferred_contact_method` VARCHAR(20) NULL
- `marketing_opt_in` TINYINT(1) NOT NULL DEFAULT 0
- `last_appointment_at` DATETIME NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:

- `idx_professional_clients_restaurant_name (restaurant_id, last_name, first_name)`
- `idx_professional_clients_restaurant_email (restaurant_id, email)`
- `idx_professional_clients_restaurant_phone (restaurant_id, phone)`

### New Table: `professional_appointments`

Core appointment records. This table stores the service snapshot needed for historical accuracy even if a service changes later.

- `id` BIGINT UNSIGNED PK
- `restaurant_id` BIGINT UNSIGNED NOT NULL
- `professional_user_id` BIGINT UNSIGNED NOT NULL
- `client_id` BIGINT UNSIGNED NOT NULL
- `service_id` BIGINT UNSIGNED NULL
- `status` VARCHAR(30) NOT NULL DEFAULT 'confirmed'
- `source` VARCHAR(30) NOT NULL DEFAULT 'staff'
- `appointment_date` DATE NOT NULL
- `start_at` DATETIME NOT NULL
- `end_at` DATETIME NOT NULL
- `service_name` VARCHAR(255) NOT NULL
- `duration_minutes` INT NOT NULL
- `buffer_before_minutes` INT NOT NULL DEFAULT 0
- `buffer_after_minutes` INT NOT NULL DEFAULT 0
- `price` DECIMAL(10,2) NULL
- `currency_code` VARCHAR(10) NOT NULL DEFAULT 'USD'
- `location_type` VARCHAR(30) NULL
- `location_label` VARCHAR(255) NULL
- `confirmation_code` VARCHAR(32) NULL
- `client_notes` TEXT NULL
- `internal_notes` TEXT NULL
- `cancelled_at` DATETIME NULL
- `completed_at` DATETIME NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:

- `idx_professional_appointments_restaurant_start (restaurant_id, start_at)`
- `idx_professional_appointments_restaurant_status (restaurant_id, status, appointment_date)`
- `idx_professional_appointments_client (client_id, start_at)`
- `idx_professional_appointments_confirmation_code (confirmation_code)`

## Functional Requirements

### Professional Mode Shell

When `product_type = 'professional'`, the app must hide restaurant navigation and replace it with professional scheduling navigation.

Required professional navigation:

- Dashboard
- Calendar
- Appointments
- Clients
- Services
- Availability
- Time Off
- Settings
- Reports

Restaurant-only screens such as Reservations, Tables, Waitlist, Sections, Turn Times, and Reservation Rules must not be shown in professional mode.

### Dashboard

The professional dashboard should show:

- appointments for today
- next upcoming appointments
- cancellations and no-shows
- available hours remaining today
- quick actions for new appointment, new client, block time, and view booking page

### Services

The provider must be able to:

- create, edit, archive, and sort services
- define duration, buffers, price, location, and public booking availability
- set service-specific colors for the calendar

### Availability and Time Off

The provider must be able to:

- define recurring weekly working hours
- define multiple working windows in one day
- block specific dates or time ranges
- manage vacations and holidays
- set booking notice, horizon, slot interval, and default buffers

### Appointments

The scheduling system must support:

- create, edit, reschedule, cancel, and complete appointments
- statuses: `pending`, `confirmed`, `completed`, `cancelled`, `no_show`
- internal notes and client-facing notes
- manual appointments created by staff
- public-booked appointments created by clients
- conflict prevention so no overlapping appointments are allowed for the single provider
- duration and price snapshots stored on each appointment

### Calendar Views

The professional calendar should support:

- day view
- week view
- month overview
- upcoming list view
- status colors
- service colors
- filter by status and service

Drag-and-drop rescheduling can be treated as a later enhancement after the first stable CRUD release.

### Client Management

The professional product must include:

- searchable client directory
- appointment history per client
- contact info and communication preference tracking
- internal notes
- quick creation of a client during appointment booking

### Public Booking

The first public booking flow should allow a client to:

- choose a service
- choose an available date and time
- enter name, phone, and email
- submit an appointment request or confirmed booking
- receive a confirmation code
- reschedule or cancel using the confirmation code

### Notifications

The system should support:

- booking confirmation email/SMS
- reminder email/SMS
- cancellation confirmation
- optional provider-facing new booking alert

The first release can reuse the app's existing notification infrastructure where possible.

### Settings

Professional settings should include:

- business display details
- timezone
- booking slug
- default location
- booking instructions
- cancellation policy
- notification preferences

### Reports

The first reporting pass should include:

- appointments by day/week/month
- revenue by service using appointment price snapshots
- cancellations and no-shows
- top clients by visit count
- service utilization

## Out of Scope for First Release

- multi-provider businesses
- room or resource scheduling
- package memberships
- online payments or invoicing
- recurring appointments
- Google/Outlook sync
- drag-and-drop calendar editing

## Implementation Plan

### Phase 1: Database and Mode-Aware Shell

- add the professional scheduling tables
- add professional navigation to `html/app.php`
- hide restaurant-specific navigation when `product_type = 'professional'`
- create a professional dashboard placeholder partial

### Phase 2: Professional Profile and Services

- create settings screen for the professional business profile
- build service CRUD
- build the booking slug and public booking entry point

### Phase 3: Availability Engine

- build weekly availability CRUD
- build time-off CRUD
- implement slot generation logic that respects service duration, buffers, time off, and existing appointments

### Phase 4: Appointment Management

- build calendar views
- build appointment create/edit/cancel/reschedule screens
- add conflict validation and status workflow

### Phase 5: Client CRM

- build client directory and client detail screens
- link clients to appointment history
- allow quick client creation during appointment creation

### Phase 6: Public Booking and Notifications

- build the public booking flow
- build confirmation, reminder, cancellation, and self-service reschedule flow
- wire notifications into existing messaging infrastructure

### Phase 7: Reporting and Polish

- add reporting screens
- refine dashboard metrics
- harden permissions, empty states, and validation

## Prompt Sequence for Building the Application

Use the prompts below as the implementation series. Each prompt should follow the repo workflow: update `tasks/todo.md`, append to `docs/activity.md`, keep changes simple, verify affected PHP files, and push successful changes.

### Prompt 1: Database Schema

"Add the database schema for the professional scheduling product. Reuse the existing tenant model keyed by `restaurant_id`. Create SQL for `professional_profiles`, `professional_services`, `professional_availability_rules`, `professional_time_off`, `professional_clients`, and `professional_appointments`, including practical indexes. Do not implement UI yet."

### Prompt 2: Professional Mode Shell

"Update `html/app.php` so when the current user's `product_type` is `professional`, the restaurant navigation is hidden and replaced with a professional navigation for Dashboard, Calendar, Appointments, Clients, Services, Availability, Time Off, Settings, and Reports. Add a simple professional dashboard placeholder partial."

### Prompt 3: Professional Profile Settings

"Create the professional profile/settings screen and save handler. The screen should manage the business profile, timezone, booking slug, default location, booking instructions, and cancellation policy using HTMX and Bootstrap."

### Prompt 4: Services CRUD

"Build CRUD for professional services using HTMX partials and Bootstrap modals. Services need name, duration, buffers, price, color, location, active status, and public booking status."

### Prompt 5: Availability and Time Off

"Build the availability management screens for recurring weekly working hours and date-specific time-off blocks for professional mode. Keep the implementation simple and compatible with the existing HTMX partial architecture."

### Prompt 6: Slot Engine

"Create the server-side availability engine for professional scheduling. It must generate bookable slots from weekly availability, time-off blocks, service duration, service buffers, profile settings, and existing appointments. Prevent overlapping bookings for the single provider."

### Prompt 7: Appointment Calendar and CRUD

"Build the professional appointment calendar and appointment CRUD screens. Support day view, week view, month overview, status colors, service colors, and create/edit/cancel/reschedule flows."

### Prompt 8: Client Directory

"Create the professional client directory and client detail screens. Include search, contact data, internal notes, and appointment history. Allow quick client creation from the appointment form."

### Prompt 9: Public Booking Flow

"Create the public booking flow for professional services using the booking slug. Clients must be able to choose a service, choose an available slot, enter contact information, and receive a confirmation code."

### Prompt 10: Self-Service Changes and Notifications

"Add appointment confirmation, reminders, cancellations, and self-service reschedule/cancel flows for professional bookings. Reuse the existing notification approach where practical."

### Prompt 11: Reports

"Build the first professional reporting screens for appointment counts, revenue by service, cancellations/no-shows, client visit counts, and service utilization."
