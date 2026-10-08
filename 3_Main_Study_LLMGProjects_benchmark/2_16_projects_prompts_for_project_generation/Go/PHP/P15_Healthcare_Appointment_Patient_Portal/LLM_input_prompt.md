Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P15_Healthcare_Appointment_Patient_Portal

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

# PHP Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: PHP 8.3.
- Web framework: Slim 4 with PSR-7 request and response handling.
- Database: SQLite through PDO using a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: when a use case requires WebSocket behavior, provide a Workerman-based local WebSocket process sharing the same SQLite data; otherwise use ordinary HTTP.
- Dependency tooling: Composer with exact direct dependency constraints and a committed `composer.lock`.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start commands that work without paid services or external accounts.
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
| Alignment target | OpenEMR |
| Workflow anchors | patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P15 — Healthcare Appointment / Patient Portal

Category: Healthcare system

This folder contains Version A project-generation use cases for the Healthcare Appointment / Patient Portal. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| HEALTH-01 | Account access | Patient; clinician; staff; admin |
| HEALTH-02 | Patient profile | Patient; staff; clinician |
| HEALTH-03 | Clinician directory | Patient |
| HEALTH-04 | Appointment booking | Patient; staff |
| HEALTH-05 | Clinician schedule | Clinician; staff |
| HEALTH-06 | Visit notes | Clinician |
| HEALTH-07 | Lab results | Patient; clinician |
| HEALTH-08 | Secure messages | Patient; clinician; staff |
| HEALTH-09 | Prescription requests | Patient; clinician |
| HEALTH-10 | Document upload | Patient; staff |
| HEALTH-11 | Billing and visit summaries | Patient; staff |
| HEALTH-12 | Admin operations and audit | Admin |

---

# Complete use-case specifications

<!-- Source: HEALTH-01_Account_access.md -->

# HEALTH-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-01 |
| Title | Account access |
| Primary actors | Patient; clinician; staff; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Account access. Users authenticate and access role-specific healthcare dashboards.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/account_access; POST /api/health/account_access; PATCH /api/health/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | healthcare_appointment_patient_portal/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| HEALTH-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| HEALTH-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-02_Patient_profile.md -->

# HEALTH-02 — Patient profile

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-02 |
| Title | Patient profile |
| Primary actors | Patient; staff; clinician |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Patient profile. Patients maintain demographics, emergency contacts, and insurance-like data.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; staff; clinician is signed in; target record exists; upload storage is configured |
| Trigger | User starts the patient profile workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the patient profile page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Patient profile<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Patient profile page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/patient_profile; POST /api/health/patient_profile; PATCH /api/health/patient_profile/{id} when updates are needed |
| Request data | Patient profile |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PatientProfile; StoredFile |
| Logical module | healthcare_appointment_patient_portal/patient_profile service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-02-FA-01 | valid file operation stores or returns the correct file metadata |
| HEALTH-02-FA-02 | invalid file operation is rejected without orphan records |
| HEALTH-02-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-03_Clinician_directory.md -->

# HEALTH-03 — Clinician directory

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-03 |
| Title | Clinician directory |
| Primary actors | Patient |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Clinician directory. Patients browse clinicians by specialty, location, and availability.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient is signed in when required; related seed records exist |
| Trigger | User starts the clinician directory workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the clinician directory page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Search filters<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Clinician directory page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/clinician_directory; POST /api/health/clinician_directory; PATCH /api/health/clinician_directory/{id} when updates are needed |
| Request data | Search filters |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ClinicianDirectory |
| Logical module | healthcare_appointment_patient_portal/clinician_directory service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-03-FA-01 | valid clinician directory workflow returns the expected confirmation or data view |
| HEALTH-03-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-04_Appointment_booking.md -->

# HEALTH-04 — Appointment booking

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-04 |
| Title | Appointment booking |
| Primary actors | Patient; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Appointment booking. Patients book, reschedule, and cancel appointments.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; staff is signed in when required; related seed records exist |
| Trigger | User starts the appointment booking workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the appointment booking page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Appointment request<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Appointment booking page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/appointment_booking; POST /api/health/appointment_booking; PATCH /api/health/appointment_booking/{id} when updates are needed |
| Request data | Appointment request |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AppointmentBooking |
| Logical module | healthcare_appointment_patient_portal/appointment_booking service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-04-FA-01 | valid appointment booking workflow returns the expected confirmation or data view |
| HEALTH-04-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-05_Clinician_schedule.md -->

# HEALTH-05 — Clinician schedule

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-05 |
| Title | Clinician schedule |
| Primary actors | Clinician; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Clinician schedule. Clinicians manage availability and review upcoming visits.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Clinician; staff is signed in when required; related seed records exist |
| Trigger | User starts the clinician schedule workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Clinician opens the clinician schedule page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Schedule slot<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Clinician schedule page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/clinician_schedule; POST /api/health/clinician_schedule; PATCH /api/health/clinician_schedule/{id} when updates are needed |
| Request data | Schedule slot |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ClinicianSchedule |
| Logical module | healthcare_appointment_patient_portal/clinician_schedule service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-05-FA-01 | valid clinician schedule workflow returns the expected confirmation or data view |
| HEALTH-05-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-06_Visit_notes.md -->

# HEALTH-06 — Visit notes

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-06 |
| Title | Visit notes |
| Primary actors | Clinician |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Visit notes. Clinicians create visit notes and treatment summaries.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Clinician is signed in when required; related seed records exist |
| Trigger | User starts the visit notes workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Clinician opens the visit notes page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Clinical note<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Visit notes page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/visit_notes; POST /api/health/visit_notes; PATCH /api/health/visit_notes/{id} when updates are needed |
| Request data | Clinical note |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; VisitNotes |
| Logical module | healthcare_appointment_patient_portal/visit_notes service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-06-FA-01 | valid visit notes workflow returns the expected confirmation or data view |
| HEALTH-06-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-07_Lab_results.md -->

# HEALTH-07 — Lab results

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-07 |
| Title | Lab results |
| Primary actors | Patient; clinician |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Lab results. Clinicians upload results and patients view released results.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; clinician is signed in when required; related seed records exist |
| Trigger | User starts the lab results workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the lab results page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Result file/data<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Lab results page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/lab_results; POST /api/health/lab_results; PATCH /api/health/lab_results/{id} when updates are needed |
| Request data | Result file/data |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; LabResults |
| Logical module | healthcare_appointment_patient_portal/lab_results service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-07-FA-01 | valid lab results workflow returns the expected confirmation or data view |
| HEALTH-07-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-08_Secure_messages.md -->

# HEALTH-08 — Secure messages

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-08 |
| Title | Secure messages |
| Primary actors | Patient; clinician; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Secure messages. Patients and care teams exchange messages.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; clinician; staff is signed in when required; related seed records exist |
| Trigger | User starts the secure messages workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the secure messages page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Message text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Secure messages page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/secure_messages; POST /api/health/secure_messages; PATCH /api/health/secure_messages/{id} when updates are needed |
| Request data | Message text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; SecureMessages |
| Logical module | healthcare_appointment_patient_portal/secure_messages service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-08-FA-01 | valid secure messages workflow returns the expected confirmation or data view |
| HEALTH-08-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-09_Prescription_requests.md -->

# HEALTH-09 — Prescription requests

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-09 |
| Title | Prescription requests |
| Primary actors | Patient; clinician |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Prescription requests. Patients request renewals and clinicians approve or deny them.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; clinician is signed in when required; related seed records exist |
| Trigger | User starts the prescription requests workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the prescription requests page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Prescription request<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Prescription requests page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/prescription_requests; POST /api/health/prescription_requests; PATCH /api/health/prescription_requests/{id} when updates are needed |
| Request data | Prescription request |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PrescriptionRequests |
| Logical module | healthcare_appointment_patient_portal/prescription_requests service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-09-FA-01 | valid prescription requests workflow returns the expected confirmation or data view |
| HEALTH-09-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-10_Document_upload.md -->

# HEALTH-10 — Document upload

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-10 |
| Title | Document upload |
| Primary actors | Patient; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Document upload. Users upload referral letters, forms, and supporting documents.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; staff is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the document upload page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Uploaded file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Document upload page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/document_upload; POST /api/health/document_upload; PATCH /api/health/document_upload/{id} when updates are needed |
| Request data | Uploaded file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DocumentUpload; StoredFile |
| Logical module | healthcare_appointment_patient_portal/document_upload service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-10-FA-01 | valid file operation stores or returns the correct file metadata |
| HEALTH-10-FA-02 | invalid file operation is rejected without orphan records |
| HEALTH-10-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-11_Billing_and_visit_summaries.md -->

# HEALTH-11 — Billing and visit summaries

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-11 |
| Title | Billing and visit summaries |
| Primary actors | Patient; staff |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Billing and visit summaries. Users view visit summaries and simulated invoices.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Patient; staff is signed in when required; related seed records exist |
| Trigger | User starts the billing and visit summaries workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Patient opens the billing and visit summaries page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Invoice/summary<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Billing and visit summaries page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/billing_and_visit_summaries; POST /api/health/billing_and_visit_summaries; PATCH /api/health/billing_and_visit_summaries/{id} when updates are needed |
| Request data | Invoice/summary |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; BillingAndVisitSummaries |
| Logical module | healthcare_appointment_patient_portal/billing_and_visit_summaries service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-11-FA-01 | valid billing and visit summaries workflow returns the expected confirmation or data view |
| HEALTH-11-FA-02 | invalid input is rejected without unintended persistence |
| HEALTH-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: HEALTH-12_Admin_operations_and_audit.md -->

# HEALTH-12 — Admin operations and audit

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Healthcare Appointment / Patient Portal |
| Use case | HEALTH-12 |
| Title | Admin operations and audit |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | OpenEMR |

## 2. Business objective

Admin operations and audit. Admin manages users, roles, clinics, retention, and audit logs.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **OpenEMR**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: patients, appointments, clinician schedules, encounters, lab results, secure messages, prescriptions, documents, billing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin operations and audit page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin operations and audit page, form, list, and detail view when applicable |
| API / event contract | GET /api/health/admin_operations_and_audit; POST /api/health/admin_operations_and_audit; PATCH /api/health/admin_operations_and_audit/{id} when updates are needed |
| Request data | Admin settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminOperationsAndAudit; AuditEvent |
| Logical module | healthcare_appointment_patient_portal/admin_operations_and_audit service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| HEALTH-12-FA-01 | authorized privileged action updates the correct record |
| HEALTH-12-FA-02 | invalid privileged action is rejected |
| HEALTH-12-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
