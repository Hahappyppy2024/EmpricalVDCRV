Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — Python — P06_Real_Time_Team_Chat_System

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

# Python Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: Python 3.12.
- Web framework: Flask 3.
- Database: SQLite through SQLAlchemy 2 using a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: use Flask-Sock when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Dependency tooling: a pinned `requirements.txt` or lock file with documented virtual-environment commands.
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
| Alignment target | Zulip |
| Workflow anchors | organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P06 — Real-Time Team Chat System

Category: Real-time collaboration

This folder contains Version A project-generation use cases for the Real-Time Team Chat System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| CHAT-01 | Accounts | Visitor; member; admin |
| CHAT-02 | Workspaces and channels | Member; owner |
| CHAT-03 | Membership lifecycle | Owner; member |
| CHAT-04 | Real-time messaging | Member |
| CHAT-05 | Message history and search | Member |
| CHAT-06 | Direct messages | Member |
| CHAT-07 | Attachments | Member |
| CHAT-08 | Link preview | Member |
| CHAT-09 | Channel management | Owner; admin |
| CHAT-10 | Connection and message handling | Member |
| CHAT-11 | Frontend API integration | User |
| CHAT-12 | Errors | User |

---

# Complete use-case specifications

<!-- Source: CHAT-01_Accounts.md -->

# CHAT-01 — Accounts

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-01 |
| Title | Accounts |
| Primary actors | Visitor; member; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Accounts. Users register, sign in, recover accounts, and manage profiles.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Visitor opens the accounts page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Accounts page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/accounts; POST /api/chat/accounts; PATCH /api/chat/accounts/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Accounts |
| Logical module | real_time_team_chat_system/accounts service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| CHAT-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| CHAT-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-02_Workspaces_and_channels.md -->

# CHAT-02 — Workspaces and channels

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-02 |
| Title | Workspaces and channels |
| Primary actors | Member; owner |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Workspaces and channels. Users create workspaces and public/private channels.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member; owner is signed in when required; related seed records exist |
| Trigger | User starts the workspaces and channels workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the workspaces and channels page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Workspace, channel<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Workspaces and channels page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/workspaces_and_channels; POST /api/chat/workspaces_and_channels; PATCH /api/chat/workspaces_and_channels/{id} when updates are needed |
| Request data | Workspace, channel |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; WorkspacesAndChannels |
| Logical module | real_time_team_chat_system/workspaces_and_channels service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-02-FA-01 | valid workspaces and channels workflow returns the expected confirmation or data view |
| CHAT-02-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-02-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-03_Membership_lifecycle.md -->

# CHAT-03 — Membership lifecycle

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-03 |
| Title | Membership lifecycle |
| Primary actors | Owner; member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Membership lifecycle. Owners invite, remove, and change member roles.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Owner; member is signed in when required; related seed records exist |
| Trigger | User starts the membership lifecycle workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Owner opens the membership lifecycle page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Invitation, membership<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Membership lifecycle page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/membership_lifecycle; POST /api/chat/membership_lifecycle; PATCH /api/chat/membership_lifecycle/{id} when updates are needed |
| Request data | Invitation, membership |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; MembershipLifecycle |
| Logical module | real_time_team_chat_system/membership_lifecycle service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-03-FA-01 | valid membership lifecycle workflow returns the expected confirmation or data view |
| CHAT-03-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-04_Real_time_messaging.md -->

# CHAT-04 — Real-time messaging

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-04 |
| Title | Real-time messaging |
| Primary actors | Member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Real-time messaging. Members send, edit, delete, and receive channel messages.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member is signed in when required; related seed records exist |
| Trigger | User starts the real-time messaging workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the real-time messaging page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Message body<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Real-time messaging page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/real_time_messaging; POST /api/chat/real_time_messaging; PATCH /api/chat/real_time_messaging/{id} when updates are needed |
| Request data | Message body |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RealTimeMessaging |
| Logical module | real_time_team_chat_system/real_time_messaging service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-04-FA-01 | valid real-time messaging workflow returns the expected confirmation or data view |
| CHAT-04-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-05_Message_history_and_search.md -->

# CHAT-05 — Message history and search

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-05 |
| Title | Message history and search |
| Primary actors | Member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Message history and search. Members search visible message history by keyword and date.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the message history and search page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Search query<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Message history and search page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/message_history_and_search; POST /api/chat/message_history_and_search; PATCH /api/chat/message_history_and_search/{id} when updates are needed |
| Request data | Search query |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; MessageHistoryAndSearch |
| Logical module | real_time_team_chat_system/message_history_and_search service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-05-FA-01 | known filters return only matching visible records |
| CHAT-05-FA-02 | empty or invalid filters return a bounded empty/error response |
| CHAT-05-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-06_Direct_messages.md -->

# CHAT-06 — Direct messages

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-06 |
| Title | Direct messages |
| Primary actors | Member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Direct messages. Members exchange private direct messages.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member is signed in when required; related seed records exist |
| Trigger | User starts the direct messages workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the direct messages page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits DM thread<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Direct messages page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/direct_messages; POST /api/chat/direct_messages; PATCH /api/chat/direct_messages/{id} when updates are needed |
| Request data | DM thread |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DirectMessages |
| Logical module | real_time_team_chat_system/direct_messages service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-06-FA-01 | valid direct messages workflow returns the expected confirmation or data view |
| CHAT-06-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-07_Attachments.md -->

# CHAT-07 — Attachments

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-07 |
| Title | Attachments |
| Primary actors | Member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Attachments. Members upload and download message attachments.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the attachments page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Uploaded file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Attachments page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/attachments; POST /api/chat/attachments; PATCH /api/chat/attachments/{id} when updates are needed |
| Request data | Uploaded file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Attachments; StoredFile |
| Logical module | real_time_team_chat_system/attachments service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-07-FA-01 | valid file operation stores or returns the correct file metadata |
| CHAT-07-FA-02 | invalid file operation is rejected without orphan records |
| CHAT-07-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-08_Link_preview.md -->

# CHAT-08 — Link preview

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-08 |
| Title | Link preview |
| Primary actors | Member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Link preview. System retrieves metadata for URLs in messages.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member is signed in when required; related seed records exist |
| Trigger | User starts the link preview workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the link preview page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits URL, preview metadata<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Link preview page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/link_preview; POST /api/chat/link_preview; PATCH /api/chat/link_preview/{id} when updates are needed |
| Request data | URL, preview metadata |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; LinkPreview |
| Logical module | real_time_team_chat_system/link_preview service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-08-FA-01 | valid link preview workflow returns the expected confirmation or data view |
| CHAT-08-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-09_Channel_management.md -->

# CHAT-09 — Channel management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-09 |
| Title | Channel management |
| Primary actors | Owner; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Channel management. Owners archive, rename, and configure channels.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Owner; admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Owner opens the channel management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Channel settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Channel management page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/channel_management; POST /api/chat/channel_management; PATCH /api/chat/channel_management/{id} when updates are needed |
| Request data | Channel settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ChannelManagement; AuditEvent |
| Logical module | real_time_team_chat_system/channel_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-09-FA-01 | authorized privileged action updates the correct record |
| CHAT-09-FA-02 | invalid privileged action is rejected |
| CHAT-09-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-10_Connection_and_message_handling.md -->

# CHAT-10 — Connection and message handling

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-10 |
| Title | Connection and message handling |
| Primary actors | Member |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Connection and message handling. System handles reconnects, duplicate sends, and delivery states.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member is signed in when required; related seed records exist |
| Trigger | User starts the connection and message handling workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the connection and message handling page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Realtime events<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Connection and message handling page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/connection_and_message_handling; POST /api/chat/connection_and_message_handling; PATCH /api/chat/connection_and_message_handling/{id} when updates are needed |
| Request data | Realtime events |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ConnectionAndMessageHandling |
| Logical module | real_time_team_chat_system/connection_and_message_handling service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-10-FA-01 | valid connection and message handling workflow returns the expected confirmation or data view |
| CHAT-10-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-11_Frontend_API_integration.md -->

# CHAT-11 — Frontend API integration

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-11 |
| Title | Frontend API integration |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Frontend API integration. UI handles loading, typing indicators, errors, and empty states.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the frontend api integration workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the frontend api integration page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits API/event states<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Frontend API integration page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/frontend_api_integration; POST /api/chat/frontend_api_integration; PATCH /api/chat/frontend_api_integration/{id} when updates are needed |
| Request data | API/event states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegration |
| Logical module | real_time_team_chat_system/frontend_api_integration service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-11-FA-01 | valid frontend api integration workflow returns the expected confirmation or data view |
| CHAT-11-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CHAT-12_Errors.md -->

# CHAT-12 — Errors

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P06 — Real-Time Team Chat System |
| Use case | CHAT-12 |
| Title | Errors |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Zulip |

## 2. Business objective

Errors. Invalid operations return stable user-facing errors.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Zulip**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: organizations, streams/channels, topics, private conversations, messages, attachments, realtime events, and channel administration.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the errors workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the errors page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Error object<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Errors page, form, list, and detail view when applicable |
| API / event contract | GET /api/chat/errors; POST /api/chat/errors; PATCH /api/chat/errors/{id} when updates are needed |
| Request data | Error object |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Errors |
| Logical module | real_time_team_chat_system/errors service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CHAT-12-FA-01 | valid errors workflow returns the expected confirmation or data view |
| CHAT-12-FA-02 | invalid input is rejected without unintended persistence |
| CHAT-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
