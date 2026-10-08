Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — Go — P09_Issue_Tracking_System

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

# Go Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: Go 1.24.
- Web framework/router: `github.com/go-chi/chi/v5` with the standard `net/http` server.
- Database: SQLite through the pure-Go `modernc.org/sqlite` driver using a repository/data-access layer; do not require CGO.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML templates, CSS, and vanilla JavaScript.
- Real-time transport: use `github.com/coder/websocket` when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Dependency tooling: Go modules with committed `go.mod` and `go.sum`.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start commands that work without paid services or external accounts.
- Containerization: provide a multi-stage Dockerfile and compose file for reproducible local execution.

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
| Alignment target | Gitea |
| Workflow anchors | repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P09 — Issue Tracking System

Category: Developer tool

This folder contains Version A project-generation use cases for the Issue Tracking System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| ISSUE-01 | Account access | Developer; maintainer; admin |
| ISSUE-02 | Project management | Maintainer; admin |
| ISSUE-03 | Issue creation | Developer; reporter |
| ISSUE-04 | Issue search | User |
| ISSUE-05 | Comments | User |
| ISSUE-06 | Assignment and workflow | Maintainer |
| ISSUE-07 | Attachments | User |
| ISSUE-08 | Private projects | Member; maintainer |
| ISSUE-09 | Webhooks | Maintainer |
| ISSUE-10 | Import/export | Maintainer; admin |
| ISSUE-11 | Admin operations | Admin |
| ISSUE-12 | Frontend API integration and errors | User |

---

# Complete use-case specifications

<!-- Source: ISSUE-01_Account_access.md -->

# ISSUE-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-01 |
| Title | Account access |
| Primary actors | Developer; maintainer; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Account access. Users authenticate and manage profiles.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Developer opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/account_access; POST /api/issue/account_access; PATCH /api/issue/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | issue_tracking_system/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| ISSUE-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| ISSUE-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-02_Project_management.md -->

# ISSUE-02 — Project management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-02 |
| Title | Project management |
| Primary actors | Maintainer; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Project management. Maintainers create projects, repositories, teams, and labels.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Maintainer; admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Maintainer opens the project management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Project data<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Project management page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/project_management; POST /api/issue/project_management; PATCH /api/issue/project_management/{id} when updates are needed |
| Request data | Project data |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ProjectManagement; AuditEvent |
| Logical module | issue_tracking_system/project_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-02-FA-01 | authorized privileged action updates the correct record |
| ISSUE-02-FA-02 | invalid privileged action is rejected |
| ISSUE-02-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-03_Issue_creation.md -->

# ISSUE-03 — Issue creation

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-03 |
| Title | Issue creation |
| Primary actors | Developer; reporter |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Issue creation. Users create issues with title, body, labels, and priority.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Developer; reporter is signed in when required; related seed records exist |
| Trigger | User starts the issue creation workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Developer opens the issue creation page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Issue body<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Issue creation page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/issue_creation; POST /api/issue/issue_creation; PATCH /api/issue/issue_creation/{id} when updates are needed |
| Request data | Issue body |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; IssueCreation |
| Logical module | issue_tracking_system/issue_creation service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-03-FA-01 | valid issue creation workflow returns the expected confirmation or data view |
| ISSUE-03-FA-02 | invalid input is rejected without unintended persistence |
| ISSUE-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-04_Issue_search.md -->

# ISSUE-04 — Issue search

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-04 |
| Title | Issue search |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Issue search. Users search and filter visible issues.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the issue search page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Search query<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Issue search page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/issue_search; POST /api/issue/issue_search; PATCH /api/issue/issue_search/{id} when updates are needed |
| Request data | Search query |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; IssueSearch |
| Logical module | issue_tracking_system/issue_search service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-04-FA-01 | known filters return only matching visible records |
| ISSUE-04-FA-02 | empty or invalid filters return a bounded empty/error response |
| ISSUE-04-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-05_Comments.md -->

# ISSUE-05 — Comments

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-05 |
| Title | Comments |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Comments. Users add, edit, and delete comments on issues.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the comments workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the comments page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Comment text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Comments page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/comments; POST /api/issue/comments; PATCH /api/issue/comments/{id} when updates are needed |
| Request data | Comment text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Comments |
| Logical module | issue_tracking_system/comments service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-05-FA-01 | valid comments workflow returns the expected confirmation or data view |
| ISSUE-05-FA-02 | invalid input is rejected without unintended persistence |
| ISSUE-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-06_Assignment_and_workflow.md -->

# ISSUE-06 — Assignment and workflow

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-06 |
| Title | Assignment and workflow |
| Primary actors | Maintainer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Assignment and workflow. Maintainers assign issues and change status/milestone.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Maintainer is signed in when required; related seed records exist |
| Trigger | User starts the assignment and workflow workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Maintainer opens the assignment and workflow page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Status, assignee<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Assignment and workflow page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/assignment_and_workflow; POST /api/issue/assignment_and_workflow; PATCH /api/issue/assignment_and_workflow/{id} when updates are needed |
| Request data | Status, assignee |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AssignmentAndWorkflow |
| Logical module | issue_tracking_system/assignment_and_workflow service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-06-FA-01 | valid assignment and workflow workflow returns the expected confirmation or data view |
| ISSUE-06-FA-02 | invalid input is rejected without unintended persistence |
| ISSUE-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-07_Attachments.md -->

# ISSUE-07 — Attachments

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-07 |
| Title | Attachments |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Attachments. Users attach screenshots, logs, or reproduction files.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the attachments page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Uploaded file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Attachments page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/attachments; POST /api/issue/attachments; PATCH /api/issue/attachments/{id} when updates are needed |
| Request data | Uploaded file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Attachments; StoredFile |
| Logical module | issue_tracking_system/attachments service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-07-FA-01 | valid file operation stores or returns the correct file metadata |
| ISSUE-07-FA-02 | invalid file operation is rejected without orphan records |
| ISSUE-07-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-08_Private_projects.md -->

# ISSUE-08 — Private projects

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-08 |
| Title | Private projects |
| Primary actors | Member; maintainer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Private projects. Private project issues are visible only to authorized members.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Member; maintainer is signed in when required; related seed records exist |
| Trigger | User starts the private projects workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Member opens the private projects page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Project membership<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Private projects page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/private_projects; POST /api/issue/private_projects; PATCH /api/issue/private_projects/{id} when updates are needed |
| Request data | Project membership |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PrivateProjects |
| Logical module | issue_tracking_system/private_projects service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-08-FA-01 | valid private projects workflow returns the expected confirmation or data view |
| ISSUE-08-FA-02 | invalid input is rejected without unintended persistence |
| ISSUE-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-09_Webhooks.md -->

# ISSUE-09 — Webhooks

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-09 |
| Title | Webhooks |
| Primary actors | Maintainer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Webhooks. Maintainers configure outbound webhooks for issue events.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Maintainer is signed in when required; related seed records exist |
| Trigger | User starts the webhooks workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Maintainer opens the webhooks page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Webhook URL/secret<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Webhooks page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/webhooks; POST /api/issue/webhooks; PATCH /api/issue/webhooks/{id} when updates are needed |
| Request data | Webhook URL/secret |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Webhooks |
| Logical module | issue_tracking_system/webhooks service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-09-FA-01 | valid webhooks workflow returns the expected confirmation or data view |
| ISSUE-09-FA-02 | invalid input is rejected without unintended persistence |
| ISSUE-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-10_Import_export.md -->

# ISSUE-10 — Import/export

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-10 |
| Title | Import/export |
| Primary actors | Maintainer; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Import/export. Users import issues and export project reports.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Maintainer; admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Maintainer opens the import/export page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Import file, export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Import/export page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/import_export; POST /api/issue/import_export; PATCH /api/issue/import_export/{id} when updates are needed |
| Request data | Import file, export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ImportExport; StoredFile |
| Logical module | issue_tracking_system/import_export service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-10-FA-01 | valid file operation stores or returns the correct file metadata |
| ISSUE-10-FA-02 | invalid file operation is rejected without orphan records |
| ISSUE-10-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-11_Admin_operations.md -->

# ISSUE-11 — Admin operations

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-11 |
| Title | Admin operations |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Admin operations. Admin manages users, project ownership, and global settings.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin operations page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin action<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin operations page, form, list, and detail view when applicable |
| API / event contract | GET /api/issue/admin_operations; POST /api/issue/admin_operations; PATCH /api/issue/admin_operations/{id} when updates are needed |
| Request data | Admin action |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminOperations; AuditEvent |
| Logical module | issue_tracking_system/admin_operations service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-11-FA-01 | authorized privileged action updates the correct record |
| ISSUE-11-FA-02 | invalid privileged action is rejected |
| ISSUE-11-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: ISSUE-12_Frontend_API_integration_and_errors.md -->

# ISSUE-12 — Frontend API integration and errors

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P09 — Issue Tracking System |
| Use case | ISSUE-12 |
| Title | Frontend API integration and errors |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Gitea |

## 2. Business objective

Frontend API integration and errors. UI handles validation, permission, webhook, and upload errors.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Gitea**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories/projects, issues, comments, labels, milestones, private projects, webhooks, releases, and admin settings.


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
| API / event contract | GET /api/issue/frontend_api_integration_and_errors; POST /api/issue/frontend_api_integration_and_errors; PATCH /api/issue/frontend_api_integration_and_errors/{id} when updates are needed |
| Request data | API response states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegrationAndErrors |
| Logical module | issue_tracking_system/frontend_api_integration_and_errors service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| ISSUE-12-FA-01 | valid frontend api integration and errors workflow returns the expected confirmation or data view |
| ISSUE-12-FA-02 | invalid input is rejected without unintended persistence |
| ISSUE-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
