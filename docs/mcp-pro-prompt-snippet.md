# ZozoCal Professional MCP Server Prompt Snippet

Source of truth for this file:
- `html/api/mcp/pro.php`
- `html/api/mcp/pro-tools.php`
- `helpers/professional-voice-api.php`

## Why a normal fetch does not show the tools

`/api/mcp/pro.php` is not a browsable manifest URL. The endpoint only accepts `POST` requests and expects a JSON-RPC 2.0 body. A normal browser visit or simple `GET` request will return an error instead of a tool list. To inspect the tools, call `initialize` and then `tools/list`.

## Actual MCP contract

- Endpoint: `https://zozocal.com/api/mcp/pro.php`
- Transport: `POST` only
- Content-Type: `application/json`
- Auth: `Authorization: Bearer <mcp_api_key>`
- Auth fallback in code: if `settings.mcp_api_key` is blank, the endpoint currently allows requests without a bearer token
- Supported JSON-RPC methods:
  - `initialize`
  - `notifications/initialized`
  - `tools/list`
  - `tools/call`
- `initialize` returns:
  - `protocolVersion`: `2024-11-05`
  - `serverInfo.name`: `ZozoCal Professional Scheduling`
  - `serverInfo.version`: `1.0.0`
  - `capabilities.tools.listChanged`: `false`

## Exact tools exposed by `/api/mcp/pro.php`

### `list_services`
- Required: `booking_slug`
- Purpose: list public-bookable services and return the `service_id` values needed by other tools

### `check_availability`
- Required: `booking_slug`, `service_id`, `date`
- Purpose: return available slots for a service on a date
- Date input accepted by code/docs: `today`, `tomorrow`, weekday names such as `monday`, or `YYYY-MM-DD`

### `book_appointment`
- Required: `booking_slug`, `service_id`, `date`, `time`, `client_name`, `client_phone`
- Optional: `client_email`, `client_notes`, `internal_notes`, `service_contact_name`, `service_phone`, `service_contact_method`, `service_address_1`, `service_city`, `service_state`, `service_postal_code`
- Purpose: create a new appointment and return an 8-character confirmation code
- Notes from implementation:
  - time must be `HH:MM` in 24-hour format
  - appointment is created with status `confirmed`
  - `service_contact_method` is described as `ph`, `em`, `tx`, or `ip`

### `lookup_appointment`
- Required: `booking_slug`
- Also provide one of: `confirmation_code` or `client_phone`
- Purpose: find appointments by code or phone
- Notes from implementation:
  - confirmation codes are 8 characters
  - phone lookup returns all active appointments for that client, not just one

### `cancel_appointment`
- Required: `booking_slug`, `confirmation_code`
- Purpose: cancel a pending or confirmed appointment
- Note from implementation: cancellation can be blocked by the professional profile's cancellation notice window

### `confirm_appointment`
- Required: `booking_slug`, `confirmation_code`
- Purpose: confirm a pending appointment
- Note from implementation: if the appointment is already confirmed, the tool returns success with an "already confirmed" message

### `modify_appointment`
- Required: `booking_slug`, `confirmation_code`, `service_id`, `date`, `time`, `client_name`, `client_phone`
- Optional: `client_email`, `client_notes`, `internal_notes`, `service_contact_name`, `service_phone`, `service_contact_method`, `service_address_1`, `service_city`, `service_state`, `service_postal_code`
- Purpose: change an existing appointment
- Notes from implementation:
  - use this instead of separate cancel + book calls
  - the current implementation updates the appointment in place and preserves the existing confirmation code

### `update_client_preferences`
- Required: `booking_slug`, `client_phone`
- Optional: `marketing_opt_in`, `preferred_contact_method`
- Purpose: update the client's contact preferences
- Allowed `preferred_contact_method` values in code: `email`, `phone`, `sms`, or empty string to clear it

## Prompt snippet

```text
You are connected to ZozoCal's Professional Scheduling MCP server at https://zozocal.com/api/mcp/pro.php.

This endpoint is JSON-RPC 2.0 over HTTP, not a normal browsable manifest URL. Do not assume tool names. Use the exact tool names below. If you need to inspect the server, the correct flow is POST `initialize`, then `tools/list`, then `tools/call`.

Use these exact tools:
- list_services(booking_slug)
- check_availability(booking_slug, service_id, date)
- book_appointment(booking_slug, service_id, date, time, client_name, client_phone, client_email?, client_notes?, internal_notes?, service_contact_name?, service_phone?, service_contact_method?, service_address_1?, service_city?, service_state?, service_postal_code?)
- lookup_appointment(booking_slug, confirmation_code? or client_phone?)
- cancel_appointment(booking_slug, confirmation_code)
- confirm_appointment(booking_slug, confirmation_code)
- modify_appointment(booking_slug, confirmation_code, service_id, date, time, client_name, client_phone, client_email?, client_notes?, internal_notes?, service_contact_name?, service_phone?, service_contact_method?, service_address_1?, service_city?, service_state?, service_postal_code?)
- update_client_preferences(booking_slug, client_phone, marketing_opt_in?, preferred_contact_method?)

Tool usage rules:
- Always call list_services before booking or modifying unless a valid service_id is already known.
- Always call check_availability before booking or modifying an appointment.
- Use modify_appointment instead of separate cancel_appointment + book_appointment calls.
- If the user does not know the confirmation code, use lookup_appointment with client_phone.
- Dates may be `today`, `tomorrow`, a weekday name, or `YYYY-MM-DD`.
- Time should be `HH:MM` in 24-hour format.
- Appointment confirmation codes are 8 characters.
- `service_contact_method` should be `ph`, `em`, `tx`, or `ip`.
- `preferred_contact_method` should be `email`, `phone`, `sms`, or an empty string to clear it.
- Phone-based lookup can return multiple active appointments, so check the full appointments list before speaking.
```

## Grounded sample prompts

- "Use `list_services` for booking slug `YOUR_BOOKING_SLUG` and tell me which services are publicly bookable."
- "For booking slug `YOUR_BOOKING_SLUG`, use `check_availability` for service `SERVICE_ID` on `tomorrow` and list the open times."
- "Book an appointment with `book_appointment` for booking slug `YOUR_BOOKING_SLUG`, service `SERVICE_ID`, date `2026-03-20`, time `14:30`, client name `John Smith`, and client phone `5551234567`."
- "Look up all active appointments for phone `5551234567` using `lookup_appointment` for booking slug `YOUR_BOOKING_SLUG`."
- "Modify appointment `AB12CD34` with `modify_appointment` to service `SERVICE_ID` on `monday` at `10:00` for client `John Smith`."
- "Update client preferences with `update_client_preferences` for phone `5551234567`, set `marketing_opt_in` to `false`, and set `preferred_contact_method` to `sms`."

## Optional curl check

```bash
curl -X POST https://zozocal.com/api/mcp/pro.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```
