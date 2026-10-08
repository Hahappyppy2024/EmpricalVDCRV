# P04 — Hotel Booking System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| HOTEL-01 | Account access | Visitor; guest; staff; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| HOTEL-02 | Room search | Visitor; guest | GET /api/room-types/availability?checkIn=&checkOut=&adults=&children=&amenities= |
| HOTEL-03 | Room details | Visitor; guest | GET /api/room-types/{roomTypeId}; GET /api/room-types/{roomTypeId}/rates?checkIn=&checkOut= |
| HOTEL-04 | Booking creation | Guest; staff | POST /api/booking-quotes; POST /api/bookings |
| HOTEL-05 | Booking management | Guest; staff | GET /api/bookings; GET /api/bookings/{bookingId}; PATCH /api/bookings/{bookingId}; POST /api/bookings/{bookingId}/cancel |
| HOTEL-06 | Check-in and check-out | Staff | POST /api/staff/bookings/{bookingId}/check-in; POST /api/staff/bookings/{bookingId}/check-out |
| HOTEL-07 | Room inventory | Staff; admin | GET /api/staff/rooms; POST /api/admin/rooms; PATCH /api/staff/rooms/{roomId}; PATCH /api/admin/room-types/{roomTypeId} |
| HOTEL-08 | Guest messages | Guest; staff | GET /api/bookings/{bookingId}/messages; POST /api/bookings/{bookingId}/messages |
| HOTEL-09 | Stay reviews | Guest; moderator | GET /api/room-types/{roomTypeId}/reviews; POST /api/bookings/{bookingId}/review; PATCH /api/reviews/{reviewId}; POST /api/admin/reviews/{reviewId}/moderate |
| HOTEL-10 | Invoice and receipt | Guest; staff | GET /api/bookings/{bookingId}/invoice; GET /api/bookings/{bookingId}/receipt.pdf |
| HOTEL-11 | Hotel reports | Admin | GET /api/admin/reports/occupancy?from=&to=; GET /api/admin/reports/revenue?from=&to=; GET /api/admin/reports/cancellations?from=&to= |
| HOTEL-12 | Rates and cancellation policies | Admin; staff | GET /api/admin/rate-plans; POST /api/admin/rate-plans; PATCH /api/admin/rate-plans/{ratePlanId}; GET /api/admin/cancellation-policies; POST /api/admin/cancellation-policies; PATCH /api/admin/cancellation-policies/{policyId} |
