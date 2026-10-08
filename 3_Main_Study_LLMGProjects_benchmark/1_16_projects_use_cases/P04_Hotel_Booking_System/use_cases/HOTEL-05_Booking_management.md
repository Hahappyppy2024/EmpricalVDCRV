# HOTEL-05 — Booking management

> Version A — project-generation specification. This file must not cause the generator to create test files.

## Objective

View, modify eligible dates/guests, or cancel a booking under policy.

## Actors

Guest; staff

## Main flow

1. The actor opens the relevant page and the client requests only data visible to that actor.
2. The actor supplies the required identifiers and fields.
3. The server authenticates the request, resolves referenced records, and checks role, ownership, and workflow state.
4. The server validates the input and performs the operation atomically when multiple records change.
5. The server returns the resulting resource or a stable confirmation; the client updates the page from that response.

## Alternative outcomes

- Missing or invalid fields return `422` with `{ "error": { "code", "message", "fields" } }`.
- No valid authentication returns `401`; an authenticated actor outside the permitted scope returns `403`.
- An unknown in-scope resource returns `404`; an invalid state transition or stale version returns `409`.
- Errors never expose stack traces, file paths, database messages, credentials, or hidden records.

## Interface and data contract

| Field | Specification |
| --- | --- |
| API / event contract | GET /api/bookings; GET /api/bookings/{bookingId}; PATCH /api/bookings/{bookingId}; POST /api/bookings/{bookingId}/cancel |
| Request data | Booking, CancellationRecord, BookingQuote |
| Success response | JSON resource or collection appropriate to the endpoint; creation returns `201`, reads/updates/actions return `200`, deletion may return `204`. |
| Persistent entities | new dates/occupancy or cancellation reason |

## Business rules

- Guests access only their bookings; staff may manage hotel bookings; modification rechecks price and availability; checked-in bookings cannot be cancelled.
- Collection endpoints use bounded pagination and deterministic ordering.
- The server derives actor identity and privileged fields from the authenticated session, never from client-supplied role or owner fields.
- Repeated create/action requests use a natural uniqueness rule or an explicit idempotency key where duplicate side effects are possible.

## Generation boundary

Implement the pages, routes/events, validation, persistence, seed data, and role-aware behavior described above. Do **not** generate test code, test directories, test dependencies, test configuration, or test documentation.
