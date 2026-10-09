# ZozoCal Professional Mobile REST API Specification

**Version:** 1.0
**Base URL:** `https://{domain}/api/v1`
**Content-Type:** `application/json` (all requests and responses)

---

## Authentication

All endpoints require a Bearer token in the `Authorization` header:

```
Authorization: Bearer {token}
```

### POST /auth/login

Authenticate and receive an access token.

**Request:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User email |
| password | string | Yes | User password |

**Response (200):**
```json
{
  "success": true,
  "token": "eyJhbGciOi...",
  "user": {
    "id": 1,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "role": "user"
  },
  "restaurant_id": 5,
  "restaurant_role": "admin"
}
```

**Errors:**
- `401` Invalid credentials

### POST /auth/logout

Invalidate the current token.

**Response (200):**
```json
{ "success": true }
```

---

## Profile

### GET /profile

Returns the professional profile and business settings for the authenticated user's restaurant.

**Response (200):**
```json
{
  "success": true,
  "profile": {
    "id": 1,
    "business_name": "Jane's Coaching",
    "display_name": "Jane Smith",
    "business_phone": "+15551234567",
    "business_email": "jane@example.com",
    "timezone": "America/New_York",
    "booking_slug": "janes-coaching",
    "slot_interval_minutes": 30,
    "default_buffer_before_minutes": 0,
    "default_buffer_after_minutes": 15,
    "minimum_booking_notice_hours": 2,
    "maximum_booking_horizon_days": 90,
    "default_location_type": "in_person",
    "default_location_label": "123 Main St",
    "booking_instructions": "Please arrive 5 minutes early.",
    "cancellation_policy": "Cancel at least 2 hours before your appointment.",
    "cancellation_notice_hours": 2,
    "is_public_booking_enabled": true
  }
}
```

---

## Services

### GET /services

List all active services for the business.

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| include_inactive | boolean | false | Include inactive services |

**Response (200):**
```json
{
  "success": true,
  "services": [
    {
      "id": 1,
      "name": "Coaching Session",
      "description": "60-minute one-on-one session",
      "duration_minutes": 60,
      "buffer_before_minutes": 0,
      "buffer_after_minutes": 15,
      "price": "150.00",
      "currency_code": "USD",
      "location_type": "in_person",
      "location_label": "Office",
      "color": "#4A90D9",
      "sort_order": 0,
      "is_active": true,
      "is_public_bookable": true
    }
  ],
  "total": 3
}
```

### GET /services/{id}

Get a single service by ID.

**Response (200):**
```json
{
  "success": true,
  "service": { ... }
}
```

**Errors:**
- `404` Service not found

---

## Availability

### GET /availability

Get available time slots for a specific service and date.

**Query Parameters:**
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| service_id | integer | Yes | Service ID |
| date | string | Yes | Date in `YYYY-MM-DD` format |

**Response (200):**
```json
{
  "success": true,
  "service_name": "Coaching Session",
  "service_id": 1,
  "date": "2026-04-01",
  "date_display": "Wednesday, April 1, 2026",
  "duration_minutes": 60,
  "slots": [
    {
      "time": "09:00",
      "time_display": "9:00am",
      "start_at": "2026-04-01 09:00:00",
      "end_at": "2026-04-01 10:00:00",
      "is_available": true,
      "location_type": "in_person",
      "location_label": "Office"
    },
    {
      "time": "10:00",
      "time_display": "10:00am",
      "start_at": "2026-04-01 10:00:00",
      "end_at": "2026-04-01 11:00:00",
      "is_available": false,
      "unavailable_reason": "appointment_conflict"
    }
  ],
  "total_available": 5
}
```

**Slot `unavailable_reason` values:**
| Value | Meaning |
|-------|---------|
| `null` | Available |
| `booking_notice` | Inside minimum booking notice window |
| `booking_horizon` | Beyond maximum booking horizon |
| `outside_availability` | Outside availability rules |
| `time_off` | Overlaps time off / blocked time |
| `appointment_conflict` | Overlaps existing appointment |

---

## Availability Rules (Weekly Schedule)

### GET /availability-rules

Get recurring weekly availability windows.

**Response (200):**
```json
{
  "success": true,
  "rules": [
    {
      "id": 1,
      "weekday": 1,
      "weekday_name": "Monday",
      "start_time": "09:00:00",
      "end_time": "17:00:00",
      "location_type": null,
      "location_label": null,
      "is_active": true
    }
  ]
}
```

**Weekday values:** 0 = Sunday, 1 = Monday, ... 6 = Saturday

---

## Time Off

### GET /time-off

List time-off blocks within a date range.

**Query Parameters:**
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| start_date | string | Yes | Range start `YYYY-MM-DD` |
| end_date | string | Yes | Range end `YYYY-MM-DD` |

**Response (200):**
```json
{
  "success": true,
  "time_off": [
    {
      "id": 1,
      "starts_at": "2026-04-05 00:00:00",
      "ends_at": "2026-04-05 23:59:59",
      "reason": "Vacation",
      "notes": "",
      "is_all_day": true
    }
  ]
}
```

---

## Appointments

### GET /appointments

List appointments with optional filters. This is the primary endpoint for populating the calendar view.

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| start_date | string | today | Range start `YYYY-MM-DD` |
| end_date | string | start_date + 30d | Range end `YYYY-MM-DD` |
| status | string | all | Filter: `pending`, `confirmed`, `completed`, `cancelled`, `no_show`, or `active` (pending + confirmed) |
| client_id | integer | | Filter by client |
| service_id | integer | | Filter by service |
| page | integer | 1 | Page number |
| per_page | integer | 50 | Results per page (max 100) |

**Response (200):**
```json
{
  "success": true,
  "appointments": [
    {
      "id": 42,
      "confirmation_code": "ABCD1234",
      "status": "confirmed",
      "source": "api",
      "appointment_date": "2026-04-01",
      "start_at": "2026-04-01 09:00:00",
      "end_at": "2026-04-01 10:00:00",
      "service": {
        "id": 1,
        "name": "Coaching Session",
        "duration_minutes": 60,
        "price": "150.00",
        "currency_code": "USD",
        "color": "#4A90D9"
      },
      "client": {
        "id": 10,
        "first_name": "John",
        "last_name": "Doe",
        "phone": "+15559876543",
        "email": "john@example.com"
      },
      "location_type": "in_person",
      "location_label": "Office",
      "client_notes": "First session",
      "internal_notes": "",
      "service_contact_name": "",
      "service_phone": "",
      "service_contact_method": "",
      "service_address_1": "",
      "service_city": "",
      "service_state": "",
      "service_postal_code": "",
      "buffer_before_minutes": 0,
      "buffer_after_minutes": 15,
      "created_at": "2026-03-25 14:30:00"
    }
  ],
  "total": 12,
  "page": 1,
  "per_page": 50
}
```

### GET /appointments/{id}

Get a single appointment by ID.

**Response (200):**
```json
{
  "success": true,
  "appointment": { ... }
}
```

**Errors:**
- `404` Appointment not found

### GET /appointments/lookup

Look up an appointment by confirmation code or client phone.

**Query Parameters (one required):**
| Param | Type | Description |
|-------|------|-------------|
| confirmation_code | string | 8-character code |
| client_phone | string | Client phone number |

**Response (200):**
```json
{
  "success": true,
  "appointments": [ ... ],
  "total": 1
}
```

### POST /appointments

Book a new appointment.

**Request:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| service_id | integer | Yes | Service to book |
| date | string | Yes | `YYYY-MM-DD` |
| time | string | Yes | `HH:MM` (24-hour) |
| client_name | string | Yes | Full name ("First Last") |
| client_phone | string | Yes | Phone number |
| client_email | string | No | Email address |
| client_notes | string | No | Notes from client |
| internal_notes | string | No | Staff-only notes |
| service_contact_name | string | No | On-site contact name |
| service_phone | string | No | On-site contact phone |
| service_contact_method | string | No | `ph`, `em`, `tx`, or `ip` |
| service_address_1 | string | No | Service address |
| service_city | string | No | City |
| service_state | string | No | State |
| service_postal_code | string | No | Postal code |

**Response (201):**
```json
{
  "success": true,
  "confirmation_code": "ABCD1234",
  "service_name": "Coaching Session",
  "date": "2026-04-01",
  "date_display": "Wednesday, April 1, 2026",
  "time": "09:00",
  "time_display": "9:00am",
  "duration_minutes": 60,
  "client_name": "John Doe",
  "status": "confirmed",
  "message": "Appointment confirmed for Wednesday, April 1, 2026 at 9:00am."
}
```

**Errors:**
- `400` Validation error (missing fields, invalid time format)
- `409` Slot unavailable (conflict, time off, outside hours, notice window, horizon)

### PUT /appointments/{confirmation_code}

Modify an existing appointment. The appointment must have status `pending` or `confirmed`.

**Request:** Same fields as POST (all required fields must be provided).

**Response (200):**
```json
{
  "success": true,
  "confirmation_code": "ABCD1234",
  "service_name": "Coaching Session",
  "date": "2026-04-02",
  "date_display": "Thursday, April 2, 2026",
  "time": "14:00",
  "time_display": "2:00pm",
  "duration_minutes": 60,
  "client_name": "John Doe",
  "status": "confirmed",
  "message": "Appointment updated."
}
```

**Errors:**
- `400` Validation error
- `404` Appointment not found
- `409` Slot unavailable
- `422` Cannot modify (completed/cancelled/no_show)

### POST /appointments/{confirmation_code}/confirm

Confirm a pending appointment.

**Response (200):**
```json
{
  "success": true,
  "confirmation_code": "ABCD1234",
  "message": "Appointment confirmed."
}
```

**Errors:**
- `404` Appointment not found
- `422` Not in `pending` status

### POST /appointments/{confirmation_code}/cancel

Cancel an appointment. Must be outside the cancellation notice window.

**Response (200):**
```json
{
  "success": true,
  "confirmation_code": "ABCD1234",
  "message": "Appointment cancelled."
}
```

**Errors:**
- `404` Appointment not found
- `422` Cannot cancel (wrong status or inside cancellation notice window)

### POST /appointments/{confirmation_code}/complete

Mark an appointment as completed.

**Response (200):**
```json
{
  "success": true,
  "confirmation_code": "ABCD1234",
  "message": "Appointment marked as completed."
}
```

**Errors:**
- `404` Appointment not found
- `422` Not in `confirmed` status

### POST /appointments/{confirmation_code}/no-show

Mark an appointment as no-show.

**Response (200):**
```json
{
  "success": true,
  "confirmation_code": "ABCD1234",
  "message": "Appointment marked as no-show."
}
```

**Errors:**
- `404` Appointment not found
- `422` Not in `confirmed` status

---

## Clients

### GET /clients

List clients for the business.

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| search | string | | Search by name, email, or phone |
| page | integer | 1 | Page number |
| per_page | integer | 50 | Results per page (max 100) |

**Response (200):**
```json
{
  "success": true,
  "clients": [
    {
      "id": 10,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "phone": "+15559876543",
      "birth_date": "1990-05-15",
      "notes": "Prefers morning sessions",
      "service_address_line1": "456 Oak Ave",
      "service_city": "Springfield",
      "service_state": "IL",
      "service_postal_code": "62704",
      "preferred_contact_method": "sms",
      "marketing_opt_in": true,
      "last_appointment_at": "2026-03-20 10:00:00",
      "created_at": "2026-01-15 09:00:00"
    }
  ],
  "total": 24,
  "page": 1,
  "per_page": 50
}
```

### GET /clients/{id}

Get a single client with their appointment history.

**Response (200):**
```json
{
  "success": true,
  "client": { ... },
  "upcoming_appointments": [ ... ],
  "past_appointments": [ ... ]
}
```

**Errors:**
- `404` Client not found

### PUT /clients/{id}/preferences

Update a client's contact preferences.

**Request:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| marketing_opt_in | boolean | No | Marketing opt-in |
| preferred_contact_method | string | No | `email`, `phone`, `sms`, or `""` |

**Response (200):**
```json
{
  "success": true,
  "updated": ["marketing_opt_in", "preferred_contact_method"],
  "message": "Preferences updated."
}
```

---

## Calendar (Aggregate View)

### GET /calendar

Returns everything needed to render a calendar view for a date range: appointments, time off, and availability windows.

**Query Parameters:**
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| start_date | string | Yes | `YYYY-MM-DD` |
| end_date | string | Yes | `YYYY-MM-DD` |

**Response (200):**
```json
{
  "success": true,
  "timezone": "America/New_York",
  "appointments": [
    {
      "id": 42,
      "confirmation_code": "ABCD1234",
      "status": "confirmed",
      "appointment_date": "2026-04-01",
      "start_at": "2026-04-01 09:00:00",
      "end_at": "2026-04-01 10:00:00",
      "buffer_before_minutes": 0,
      "buffer_after_minutes": 15,
      "service": {
        "id": 1,
        "name": "Coaching Session",
        "color": "#4A90D9"
      },
      "client": {
        "id": 10,
        "first_name": "John",
        "last_name": "Doe",
        "phone": "+15559876543"
      }
    }
  ],
  "time_off": [
    {
      "id": 1,
      "starts_at": "2026-04-05 00:00:00",
      "ends_at": "2026-04-05 23:59:59",
      "reason": "Vacation",
      "is_all_day": true
    }
  ],
  "availability_rules": [
    {
      "weekday": 1,
      "weekday_name": "Monday",
      "start_time": "09:00:00",
      "end_time": "17:00:00"
    }
  ]
}
```

---

## Appointment Statuses

| Status | Description | Transitions |
|--------|-------------|-------------|
| `pending` | Awaiting confirmation | confirm, cancel, modify |
| `confirmed` | Confirmed and active | cancel, complete, no-show, modify |
| `completed` | Service delivered | (terminal) |
| `cancelled` | Cancelled by client or staff | (terminal) |
| `no_show` | Client did not arrive | (terminal) |

---

## Appointment Sources

| Source | Description |
|--------|-------------|
| `staff` | Created by business owner/staff |
| `public_booking` | Client self-service booking |
| `api` | Created via API |
| `imported` | Bulk import |

---

## Service Contact Methods

| Code | Meaning |
|------|---------|
| `ph` | Phone |
| `em` | Email |
| `tx` | Text/SMS |
| `ip` | In person |

---

## Error Response Format

All errors follow this structure:

```json
{
  "success": false,
  "error": "Human-readable error message.",
  "code": "SLOT_UNAVAILABLE"
}
```

**Standard error codes:**
| Code | HTTP Status | Description |
|------|-------------|-------------|
| `AUTH_REQUIRED` | 401 | Missing or invalid token |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `VALIDATION_ERROR` | 400 | Missing or invalid fields |
| `SLOT_UNAVAILABLE` | 409 | Time slot conflict |
| `STATUS_INVALID` | 422 | Action not allowed for current status |
| `CANCELLATION_WINDOW` | 422 | Inside cancellation notice window |

---

## Business Rules

1. **Minimum booking notice:** Appointments cannot be booked within `minimum_booking_notice_hours` of the start time (default: 2 hours).
2. **Maximum booking horizon:** Appointments cannot be booked more than `maximum_booking_horizon_days` in the future (default: 90 days).
3. **Cancellation window:** Appointments cannot be cancelled within `cancellation_notice_hours` of the start time (default: 2 hours).
4. **Slot intervals:** Available times are generated at `slot_interval_minutes` increments (default: 30 min, minimum: 5 min).
5. **Buffers:** Each service can have before/after buffer time that blocks adjacent slots. Falls back to profile defaults.
6. **Time off:** Blocks all slots that overlap the time-off range.
7. **Conflict detection:** A slot is unavailable if it overlaps any non-cancelled, non-no-show appointment (including buffers).
8. **All times are in the business timezone** as configured in the profile (e.g., `America/New_York`). The timezone is returned in `/profile` and `/calendar` responses.
9. **Client matching:** When booking, existing clients are matched by email (case-insensitive) or phone. A new client record is created if no match is found.
10. **Confirmation codes:** 8-character uppercase alphanumeric, unique per business. Used as the public identifier for appointments.

---

## Database Tables Reference

| Table | Purpose |
|-------|---------|
| `professional_profiles` | Business settings, booking config, timezone |
| `professional_services` | Service catalog (name, duration, price, color) |
| `professional_availability_rules` | Weekly recurring hours (weekday + start/end time) |
| `professional_time_off` | Blocked date/time ranges |
| `professional_clients` | Client directory |
| `professional_appointments` | Booked appointments |

---

## Rate Limits

| Scope | Limit |
|-------|-------|
| Authentication | 10 requests/minute |
| All other endpoints | 60 requests/minute |

---

## Notes for Mobile Team

- The `/calendar` endpoint is designed as a single call to populate a day/week/month view without needing multiple requests.
- Appointment `color` comes from the service, useful for color-coding the calendar.
- Buffer times are included in appointment responses so the calendar can visually indicate blocked buffer windows.
- The `status` filter value `active` is a shorthand for `pending` + `confirmed` (most useful for calendar display).
- Confirmation codes are the primary public identifier. Use them in URLs/routes rather than database IDs.
