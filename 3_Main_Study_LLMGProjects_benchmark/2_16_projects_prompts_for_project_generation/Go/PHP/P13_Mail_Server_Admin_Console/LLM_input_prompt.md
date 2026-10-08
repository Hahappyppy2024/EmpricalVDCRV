Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P13_Mail_Server_Admin_Console

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
| Alignment target | Roundcube Webmail |
| Workflow anchors | mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P13 — Mail Server / Admin Console

Category: Communication infrastructure

This folder contains Version A project-generation use cases for the Mail Server / Admin Console. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| MAIL-01 | Account access | Mail user; domain admin; system admin |
| MAIL-02 | Mailbox overview | Mail user |
| MAIL-03 | Message compose | Mail user |
| MAIL-04 | Message reading | Mail user |
| MAIL-05 | Attachment handling | Mail user |
| MAIL-06 | Contact management | Mail user |
| MAIL-07 | Filters and rules | Mail user |
| MAIL-08 | Domain management | Domain admin |
| MAIL-09 | Quarantine | Domain admin |
| MAIL-10 | Admin audit logs | System admin |
| MAIL-11 | Import/export | Mail user; admin |
| MAIL-12 | Frontend API integration and errors | User |

---

# Complete use-case specifications

<!-- Source: MAIL-01_Account_access.md -->

# MAIL-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-01 |
| Title | Account access |
| Primary actors | Mail user; domain admin; system admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Account access. Users authenticate and access mailbox/admin views.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/account_access; POST /api/mail/account_access; PATCH /api/mail/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | mail_server_admin_console/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| MAIL-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| MAIL-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-02_Mailbox_overview.md -->

# MAIL-02 — Mailbox overview

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-02 |
| Title | Mailbox overview |
| Primary actors | Mail user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Mailbox overview. Users view folders, messages, unread counts, and search results.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user is signed in when required; related seed records exist |
| Trigger | User starts the mailbox overview workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the mailbox overview page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Mailbox, folder<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Mailbox overview page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/mailbox_overview; POST /api/mail/mailbox_overview; PATCH /api/mail/mailbox_overview/{id} when updates are needed |
| Request data | Mailbox, folder |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; MailboxOverview |
| Logical module | mail_server_admin_console/mailbox_overview service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-02-FA-01 | valid mailbox overview workflow returns the expected confirmation or data view |
| MAIL-02-FA-02 | invalid input is rejected without unintended persistence |
| MAIL-02-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-03_Message_compose.md -->

# MAIL-03 — Message compose

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-03 |
| Title | Message compose |
| Primary actors | Mail user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Message compose. Users compose, save drafts, and send simulated email.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user is signed in when required; related seed records exist |
| Trigger | User starts the message compose workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the message compose page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Recipients, subject, body<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Message compose page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/message_compose; POST /api/mail/message_compose; PATCH /api/mail/message_compose/{id} when updates are needed |
| Request data | Recipients, subject, body |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; MessageCompose |
| Logical module | mail_server_admin_console/message_compose service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-03-FA-01 | valid message compose workflow returns the expected confirmation or data view |
| MAIL-03-FA-02 | invalid input is rejected without unintended persistence |
| MAIL-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-04_Message_reading.md -->

# MAIL-04 — Message reading

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-04 |
| Title | Message reading |
| Primary actors | Mail user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Message reading. Users read messages, attachments, headers, and thread views.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user is signed in when required; related seed records exist |
| Trigger | User starts the message reading workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the message reading page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Message ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Message reading page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/message_reading; POST /api/mail/message_reading; PATCH /api/mail/message_reading/{id} when updates are needed |
| Request data | Message ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; MessageReading |
| Logical module | mail_server_admin_console/message_reading service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-04-FA-01 | valid message reading workflow returns the expected confirmation or data view |
| MAIL-04-FA-02 | invalid input is rejected without unintended persistence |
| MAIL-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-05_Attachment_handling.md -->

# MAIL-05 — Attachment handling

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-05 |
| Title | Attachment handling |
| Primary actors | Mail user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Attachment handling. Users upload outgoing attachments and download received attachments.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the attachment handling page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Attachment file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Attachment handling page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/attachment_handling; POST /api/mail/attachment_handling; PATCH /api/mail/attachment_handling/{id} when updates are needed |
| Request data | Attachment file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AttachmentHandling; StoredFile |
| Logical module | mail_server_admin_console/attachment_handling service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-05-FA-01 | valid file operation stores or returns the correct file metadata |
| MAIL-05-FA-02 | invalid file operation is rejected without orphan records |
| MAIL-05-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-06_Contact_management.md -->

# MAIL-06 — Contact management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-06 |
| Title | Contact management |
| Primary actors | Mail user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Contact management. Users create, edit, import, and search contacts.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the contact management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Contact record<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Contact management page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/contact_management; POST /api/mail/contact_management; PATCH /api/mail/contact_management/{id} when updates are needed |
| Request data | Contact record |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ContactManagement; AuditEvent |
| Logical module | mail_server_admin_console/contact_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-06-FA-01 | authorized privileged action updates the correct record |
| MAIL-06-FA-02 | invalid privileged action is rejected |
| MAIL-06-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-07_Filters_and_rules.md -->

# MAIL-07 — Filters and rules

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-07 |
| Title | Filters and rules |
| Primary actors | Mail user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Filters and rules. Users create mailbox rules for folders, labels, and forwarding.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user is signed in when required; related seed records exist |
| Trigger | User starts the filters and rules workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the filters and rules page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Rule condition/action<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Filters and rules page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/filters_and_rules; POST /api/mail/filters_and_rules; PATCH /api/mail/filters_and_rules/{id} when updates are needed |
| Request data | Rule condition/action |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FiltersAndRules |
| Logical module | mail_server_admin_console/filters_and_rules service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-07-FA-01 | valid filters and rules workflow returns the expected confirmation or data view |
| MAIL-07-FA-02 | invalid input is rejected without unintended persistence |
| MAIL-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-08_Domain_management.md -->

# MAIL-08 — Domain management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-08 |
| Title | Domain management |
| Primary actors | Domain admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Domain management. Admin manages domains, aliases, quotas, and mailbox status.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Domain admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Domain admin opens the domain management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Domain/mailbox config<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Domain management page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/domain_management; POST /api/mail/domain_management; PATCH /api/mail/domain_management/{id} when updates are needed |
| Request data | Domain/mailbox config |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DomainManagement; AuditEvent |
| Logical module | mail_server_admin_console/domain_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-08-FA-01 | authorized privileged action updates the correct record |
| MAIL-08-FA-02 | invalid privileged action is rejected |
| MAIL-08-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-09_Quarantine.md -->

# MAIL-09 — Quarantine

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-09 |
| Title | Quarantine |
| Primary actors | Domain admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Quarantine. Admin reviews quarantined messages and releases or deletes them.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Domain admin is signed in when required; related seed records exist |
| Trigger | User starts the quarantine workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Domain admin opens the quarantine page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Quarantine item<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Quarantine page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/quarantine; POST /api/mail/quarantine; PATCH /api/mail/quarantine/{id} when updates are needed |
| Request data | Quarantine item |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Quarantine |
| Logical module | mail_server_admin_console/quarantine service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-09-FA-01 | valid quarantine workflow returns the expected confirmation or data view |
| MAIL-09-FA-02 | invalid input is rejected without unintended persistence |
| MAIL-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-10_Admin_audit_logs.md -->

# MAIL-10 — Admin audit logs

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-10 |
| Title | Admin audit logs |
| Primary actors | System admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Admin audit logs. Admin reviews login, sending, rule, and domain events.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | System admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. System admin opens the admin audit logs page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Audit filter<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin audit logs page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/admin_audit_logs; POST /api/mail/admin_audit_logs; PATCH /api/mail/admin_audit_logs/{id} when updates are needed |
| Request data | Audit filter |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminAuditLogs; AuditEvent |
| Logical module | mail_server_admin_console/admin_audit_logs service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-10-FA-01 | known filters return only matching visible records |
| MAIL-10-FA-02 | empty or invalid filters return a bounded empty/error response |
| MAIL-10-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-11_Import_export.md -->

# MAIL-11 — Import/export

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-11 |
| Title | Import/export |
| Primary actors | Mail user; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Import/export. Users import contacts and export mailbox or audit data.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Mail user; admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Mail user opens the import/export page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Import/export file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Import/export page, form, list, and detail view when applicable |
| API / event contract | GET /api/mail/import_export; POST /api/mail/import_export; PATCH /api/mail/import_export/{id} when updates are needed |
| Request data | Import/export file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ImportExport; StoredFile |
| Logical module | mail_server_admin_console/import_export service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-11-FA-01 | valid file operation stores or returns the correct file metadata |
| MAIL-11-FA-02 | invalid file operation is rejected without orphan records |
| MAIL-11-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: MAIL-12_Frontend_API_integration_and_errors.md -->

# MAIL-12 — Frontend API integration and errors

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P13 — Mail Server / Admin Console |
| Use case | MAIL-12 |
| Title | Frontend API integration and errors |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Roundcube Webmail |

## 2. Business objective

Frontend API integration and errors. UI handles delivery failures, invalid recipients, and permission errors.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Roundcube Webmail**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: mailbox folders, compose, message reading, attachments, contacts, filters, identities, search, imports/exports, and admin logs.


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
| API / event contract | GET /api/mail/frontend_api_integration_and_errors; POST /api/mail/frontend_api_integration_and_errors; PATCH /api/mail/frontend_api_integration_and_errors/{id} when updates are needed |
| Request data | API response states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegrationAndErrors |
| Logical module | mail_server_admin_console/frontend_api_integration_and_errors service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| MAIL-12-FA-01 | valid frontend api integration and errors workflow returns the expected confirmation or data view |
| MAIL-12-FA-02 | invalid input is rejected without unintended persistence |
| MAIL-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
