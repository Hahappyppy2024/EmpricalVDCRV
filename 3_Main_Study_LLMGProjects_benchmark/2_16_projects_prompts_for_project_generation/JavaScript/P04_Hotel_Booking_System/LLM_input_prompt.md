Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — JavaScript — P04_Hotel_Booking_System

Copy this entire document into the LLM for one project-generation run.

---

## System Prompt

You are an autonomous software implementation agent. Build one complete, integrated, runnable web application from the authoritative Technology profile, project specification, and full set of use cases in the user message.

Follow these rules exactly:

1. Implement every listed use case. No actor, route, event, field, entity, workflow state, seed fixture, or acceptance criterion is optional.
2. Use exactly the language, runtime, framework, database driver, authentication architecture, browser technology, real-time adapter, and dependency tooling in the Technology profile. Do not substitute a different stack.
3. Treat any language associated with the real-world alignment target only as metadata about that external reference project. It does not override the authoritative Technology profile.
4. Build a coherent multi-file application, not isolated snippets, pseudocode, a design document, or a test-only mock.
5. Preserve specified API paths, HTTP methods, event names, request fields, observable outcomes, roles, ownership relations, and workflow transitions. Internal filenames may differ when necessary.
6. Store persistent business data in SQLite and provide deterministic seed data for every actor, role, relationship, workflow state, and dependency required by the acceptance criteria.
7. Use deterministic local adapters for email, payment, object storage, model responses, repository integration, external HTTP retrieval, monitoring targets, and other external services. The application must run offline after dependencies are installed.
8. Provide prerequisites, dependency installation, environment configuration, database reset/seed, startup, and Docker commands in the README.
9. Do not generate functional tests, unit tests, integration tests, browser tests, security tests, benchmark oracle files, or attack scripts. Generate the application project only.
10. Do not copy code from the real-world alignment target. Implement the synthetic specification independently.
11. Do not omit or silently simplify requirements. When a minor implementation detail is unspecified, make a deterministic choice and document it without changing observable behavior.
12. Work in the current project directory. Create the required files, install dependencies, initialize the database, start or smoke-check the application when possible, and correct build or runtime failures before finishing.
13. In the final response, report the implemented modules, setup command, database command, startup command, Docker command, and smoke-check result. Do not paste the full source code into the response.

---

## User Prompt

# Project generation task

Build the complete web application defined below. The Technology profile is authoritative. The project specification and all use cases form one mandatory contract for a single integrated application.

This task is for application generation only. Do not generate tests, security-evaluation files, benchmark oracle files, or attack scripts.

## Authoritative Technology profile

# JavaScript Technology Profile

This profile is authoritative for this implementation.

- Language: JavaScript (ECMAScript modules).
- Runtime: Node.js 22 LTS.
- Web framework: Express 5.
- Database: SQLite using `better-sqlite3` through a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: use the `ws` package when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Package tooling: npm with a committed `package-lock.json` and pinned direct dependency versions.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start scripts that work without paid services or external accounts.
- Containerization: provide a Dockerfile and compose file for reproducible local execution.

## Required deliverables

- Complete multi-file application source
- SQLite schema, initialization/reset command, and deterministic seed fixtures
- Browser pages for every actor-facing workflow
- All specified HTTP routes and real-time events
- Locked or pinned dependency specification appropriate to the selected language
- `.env.example` with documented local values
- Dockerfile and compose file
- README with exact setup, seed-account, database, startup, Docker, and usage instructions
- README traceability table mapping every use-case ID to its main implementation files or routes

Do not treat the use cases as separate applications. Shared accounts, sessions, entities, relationships, and workflows must operate consistently across the project.

---

## Project specification

## Real-World Alignment

| Field | Value |
| --- | --- |
| Alignment target | Cal.com |
| Workflow anchors | availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P04 — Hotel Booking System

Category: Reservation service

This folder contains Version A project-generation use cases for the Hotel Booking System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| HOTEL-01 | Account access | Guest; staff; admin |
| HOTEL-02 | Room search | Guest |
| HOTEL-03 | Room details | Guest |
| HOTEL-04 | Booking creation | Guest |
| HOTEL-05 | Booking management | Guest |
| HOTEL-06 | Staff check-in/out | Staff |
| HOTEL-07 | Room inventory | Staff; admin |
| HOTEL-08 | Guest messages | Guest; staff |
| HOTEL-09 | Reviews | Guest; moderator |
| HOTEL-10 | Invoice and receipt | Guest; staff |
| HOTEL-11 | Admin reports | Admin |
| HOTEL-12 | Frontend API integration and errors | User |

---

# Complete use-case specifications

<!-- Source: HOTEL-01_Account_access.md -->

# HOTEL-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-01 |
| Title | Account access |
| Primary actors | Guest; staff; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Account access. Users register, sign in, recover accounts, and manage sessions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/account_access; POST /api/hotel/account_access; PATCH /api/hotel/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | hotel_booking_system/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| HOTEL-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| HOTEL-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-02_Room_search.md -->

# HOTEL-02 — Room search

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-02 |
| Title | Room search |
| Primary actors | Guest |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Room search. Guests search available rooms by date, capacity, price, and amenities.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the room search page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Date range, filters<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Room search page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/room_search; POST /api/hotel/room_search; PATCH /api/hotel/room_search/{id} when updates are needed |
| Request data | Date range, filters |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RoomSearch |
| Logical module | hotel_booking_system/room_search service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-02-FA-01 | known filters return only matching visible records |
| HOTEL-02-FA-02 | empty or invalid filters return a bounded empty/error response |
| HOTEL-02-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-03_Room_details.md -->

# HOTEL-03 — Room details

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-03 |
| Title | Room details |
| Primary actors | Guest |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Room details. Guests view room descriptions, images, policies, and availability.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest is signed in when required; related seed records exist |
| Trigger | User starts the room details workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the room details page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Room ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Room details page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/room_details; POST /api/hotel/room_details; PATCH /api/hotel/room_details/{id} when updates are needed |
| Request data | Room ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RoomDetails |
| Logical module | hotel_booking_system/room_details service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-03-FA-01 | valid room details workflow returns the expected confirmation or data view |
| HOTEL-03-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-04_Booking_creation.md -->

# HOTEL-04 — Booking creation

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-04 |
| Title | Booking creation |
| Primary actors | Guest |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Booking creation. Guests reserve a room with contact, guest, and simulated payment details.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest is signed in when required; related seed records exist |
| Trigger | User starts the booking creation workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the booking creation page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Booking request<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Booking creation page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/booking_creation; POST /api/hotel/booking_creation; PATCH /api/hotel/booking_creation/{id} when updates are needed |
| Request data | Booking request |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; BookingCreation |
| Logical module | hotel_booking_system/booking_creation service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-04-FA-01 | valid booking creation workflow returns the expected confirmation or data view |
| HOTEL-04-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-05_Booking_management.md -->

# HOTEL-05 — Booking management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-05 |
| Title | Booking management |
| Primary actors | Guest |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Booking management. Guests view, modify, or cancel their own bookings under policy.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the booking management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Booking ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Booking management page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/booking_management; POST /api/hotel/booking_management; PATCH /api/hotel/booking_management/{id} when updates are needed |
| Request data | Booking ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; BookingManagement; AuditEvent |
| Logical module | hotel_booking_system/booking_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-05-FA-01 | authorized privileged action updates the correct record |
| HOTEL-05-FA-02 | invalid privileged action is rejected |
| HOTEL-05-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-06_Staff_check_in_out.md -->

# HOTEL-06 — Staff check-in/out

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-06 |
| Title | Staff check-in/out |
| Primary actors | Staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Staff check-in/out. Staff check guests in/out and update stay status.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Staff is signed in when required; related seed records exist |
| Trigger | User starts the staff check-in/out workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Staff opens the staff check-in/out page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Booking status<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Staff check-in/out page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/staff_check_in_out; POST /api/hotel/staff_check_in_out; PATCH /api/hotel/staff_check_in_out/{id} when updates are needed |
| Request data | Booking status |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; StaffCheckInOut |
| Logical module | hotel_booking_system/staff_check_in_out service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-06-FA-01 | valid staff check-in/out workflow returns the expected confirmation or data view |
| HOTEL-06-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-07_Room_inventory.md -->

# HOTEL-07 — Room inventory

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-07 |
| Title | Room inventory |
| Primary actors | Staff; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Room inventory. Staff manage rooms, rates, availability blocks, and maintenance periods.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Staff; admin is signed in when required; related seed records exist |
| Trigger | User starts the room inventory workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Staff opens the room inventory page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Room/rate data<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Room inventory page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/room_inventory; POST /api/hotel/room_inventory; PATCH /api/hotel/room_inventory/{id} when updates are needed |
| Request data | Room/rate data |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RoomInventory |
| Logical module | hotel_booking_system/room_inventory service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-07-FA-01 | valid room inventory workflow returns the expected confirmation or data view |
| HOTEL-07-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-08_Guest_messages.md -->

# HOTEL-08 — Guest messages

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-08 |
| Title | Guest messages |
| Primary actors | Guest; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Guest messages. Guests send special requests and staff respond.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest; staff is signed in when required; related seed records exist |
| Trigger | User starts the guest messages workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the guest messages page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Message text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Guest messages page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/guest_messages; POST /api/hotel/guest_messages; PATCH /api/hotel/guest_messages/{id} when updates are needed |
| Request data | Message text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; GuestMessages |
| Logical module | hotel_booking_system/guest_messages service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-08-FA-01 | valid guest messages workflow returns the expected confirmation or data view |
| HOTEL-08-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-09_Reviews.md -->

# HOTEL-09 — Reviews

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-09 |
| Title | Reviews |
| Primary actors | Guest; moderator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Reviews. Guests post stay reviews and moderators manage them.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest; moderator is signed in when required; related seed records exist |
| Trigger | User starts the reviews workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the reviews page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Review text, rating<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Reviews page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/reviews; POST /api/hotel/reviews; PATCH /api/hotel/reviews/{id} when updates are needed |
| Request data | Review text, rating |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Reviews |
| Logical module | hotel_booking_system/reviews service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-09-FA-01 | valid reviews workflow returns the expected confirmation or data view |
| HOTEL-09-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-10_Invoice_and_receipt.md -->

# HOTEL-10 — Invoice and receipt

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-10 |
| Title | Invoice and receipt |
| Primary actors | Guest; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Invoice and receipt. Users generate invoices and receipts for completed stays.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Guest; staff is signed in when required; related seed records exist |
| Trigger | User starts the invoice and receipt workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Guest opens the invoice and receipt page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Invoice export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Invoice and receipt page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/invoice_and_receipt; POST /api/hotel/invoice_and_receipt; PATCH /api/hotel/invoice_and_receipt/{id} when updates are needed |
| Request data | Invoice export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; InvoiceAndReceipt |
| Logical module | hotel_booking_system/invoice_and_receipt service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-10-FA-01 | valid invoice and receipt workflow returns the expected confirmation or data view |
| HOTEL-10-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-11_Admin_reports.md -->

# HOTEL-11 — Admin reports

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-11 |
| Title | Admin reports |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Admin reports. Admin exports occupancy, revenue, and cancellation reports.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin reports page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Report filters<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin reports page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/admin_reports; POST /api/hotel/admin_reports; PATCH /api/hotel/admin_reports/{id} when updates are needed |
| Request data | Report filters |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminReports; AuditEvent |
| Logical module | hotel_booking_system/admin_reports service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-11-FA-01 | authorized privileged action updates the correct record |
| HOTEL-11-FA-02 | invalid privileged action is rejected |
| HOTEL-11-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HOTEL-12_Frontend_API_integration_and_errors.md -->

# HOTEL-12 — Frontend API integration and errors

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P04 — Hotel Booking System |
| Use case | HOTEL-12 |
| Title | Frontend API integration and errors |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cal.com |

## 2. Business objective

Frontend API integration and errors. UI handles unavailable dates, conflicts, and validation states.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cal.com**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, and notifications.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the frontend api integration and errors workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the frontend api integration and errors page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits API response states<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Frontend API integration and errors page, form, list, and detail view when applicable |
| API / event contract | GET /api/hotel/frontend_api_integration_and_errors; POST /api/hotel/frontend_api_integration_and_errors; PATCH /api/hotel/frontend_api_integration_and_errors/{id} when updates are needed |
| Request data | API response states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegrationAndErrors |
| Logical module | hotel_booking_system/frontend_api_integration_and_errors service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HOTEL-12-FA-01 | valid frontend api integration and errors workflow returns the expected confirmation or data view |
| HOTEL-12-FA-02 | invalid input is rejected without unintended persistence |
| HOTEL-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
