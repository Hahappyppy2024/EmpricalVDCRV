Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P16_Server_Monitoring_Job_Control_Panel

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
| Alignment target | Cacti 1.2.26 |
| Workflow anchors | devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P16 — Server Monitoring & Job Control Panel

Category: Systems software

This folder contains Version A project-generation use cases for the Server Monitoring & Job Control Panel. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| SYS-01 | Account access | Operator; admin |
| SYS-02 | Server dashboard | Operator |
| SYS-03 | Log viewer | Operator |
| SYS-04 | Service control | Operator; admin |
| SYS-05 | Job scheduler | Operator; admin |
| SYS-06 | Job execution history | Operator |
| SYS-07 | Backup manager | Operator; admin |
| SYS-08 | Configuration editor | Admin |
| SYS-09 | Alert center | Operator |
| SYS-10 | Health check targets | Operator; admin |
| SYS-11 | API token manager | Admin |
| SYS-12 | Audit logs and admin operations | Admin |

---

# Complete use-case specifications

<!-- Source: SYS-01_Account_access.md -->

# SYS-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-01 |
| Title | Account access |
| Primary actors | Operator; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Account access. Users authenticate and access system management dashboards.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/account_access; POST /api/sys/account_access; PATCH /api/sys/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | server_monitoring_job_control_panel/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| SYS-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| SYS-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-02_Server_dashboard.md -->

# SYS-02 — Server dashboard

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-02 |
| Title | Server dashboard |
| Primary actors | Operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Server dashboard. Operators view CPU, memory, disk, uptime, and service status.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator is signed in when required; related seed records exist |
| Trigger | User starts the server dashboard workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the server dashboard page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Metric snapshot<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Server dashboard page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/server_dashboard; POST /api/sys/server_dashboard; PATCH /api/sys/server_dashboard/{id} when updates are needed |
| Request data | Metric snapshot |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ServerDashboard |
| Logical module | server_monitoring_job_control_panel/server_dashboard service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-02-FA-01 | valid server dashboard workflow returns the expected confirmation or data view |
| SYS-02-FA-02 | invalid input is rejected without unintended persistence |
| SYS-02-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-03_Log_viewer.md -->

# SYS-03 — Log viewer

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-03 |
| Title | Log viewer |
| Primary actors | Operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Log viewer. Operators search, filter, preview, and download application/system logs.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator is signed in when required; related seed records exist |
| Trigger | User starts the log viewer workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the log viewer page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Log file, filter<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Log viewer page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/log_viewer; POST /api/sys/log_viewer; PATCH /api/sys/log_viewer/{id} when updates are needed |
| Request data | Log file, filter |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; LogViewer |
| Logical module | server_monitoring_job_control_panel/log_viewer service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-03-FA-01 | valid log viewer workflow returns the expected confirmation or data view |
| SYS-03-FA-02 | invalid input is rejected without unintended persistence |
| SYS-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-04_Service_control.md -->

# SYS-04 — Service control

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-04 |
| Title | Service control |
| Primary actors | Operator; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Service control. Authorized users start, stop, restart, and inspect mock services.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator; admin is signed in when required; related seed records exist |
| Trigger | User starts the service control workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the service control page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Service action<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Service control page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/service_control; POST /api/sys/service_control; PATCH /api/sys/service_control/{id} when updates are needed |
| Request data | Service action |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ServiceControl |
| Logical module | server_monitoring_job_control_panel/service_control service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-04-FA-01 | authorized privileged action updates the correct record |
| SYS-04-FA-02 | invalid privileged action is rejected |
| SYS-04-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-05_Job_scheduler.md -->

# SYS-05 — Job scheduler

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-05 |
| Title | Job scheduler |
| Primary actors | Operator; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Job scheduler. Users create, pause, run, and delete background jobs from approved profiles.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator; admin is signed in when required; related seed records exist |
| Trigger | User starts the job scheduler workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the job scheduler page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Job definition<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Job scheduler page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/job_scheduler; POST /api/sys/job_scheduler; PATCH /api/sys/job_scheduler/{id} when updates are needed |
| Request data | Job definition |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; JobScheduler |
| Logical module | server_monitoring_job_control_panel/job_scheduler service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-05-FA-01 | valid job scheduler workflow returns the expected confirmation or data view |
| SYS-05-FA-02 | invalid input is rejected without unintended persistence |
| SYS-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-06_Job_execution_history.md -->

# SYS-06 — Job execution history

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-06 |
| Title | Job execution history |
| Primary actors | Operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Job execution history. Users inspect job outputs, exit status, duration, and retry history.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator is signed in when required; related seed records exist |
| Trigger | User starts the job execution history workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the job execution history page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Job run log<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Job execution history page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/job_execution_history; POST /api/sys/job_execution_history; PATCH /api/sys/job_execution_history/{id} when updates are needed |
| Request data | Job run log |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; JobExecutionHistory |
| Logical module | server_monitoring_job_control_panel/job_execution_history service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-06-FA-01 | valid job execution history workflow returns the expected confirmation or data view |
| SYS-06-FA-02 | invalid input is rejected without unintended persistence |
| SYS-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-07_Backup_manager.md -->

# SYS-07 — Backup manager

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-07 |
| Title | Backup manager |
| Primary actors | Operator; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Backup manager. Users create, download, upload, restore, and delete backups.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator; admin is signed in when required; related seed records exist |
| Trigger | User starts the backup manager workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the backup manager page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Backup file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Backup manager page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/backup_manager; POST /api/sys/backup_manager; PATCH /api/sys/backup_manager/{id} when updates are needed |
| Request data | Backup file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; BackupManager; StoredFile |
| Logical module | server_monitoring_job_control_panel/backup_manager service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-07-FA-01 | valid file operation stores or returns the correct file metadata |
| SYS-07-FA-02 | invalid file operation is rejected without orphan records |
| SYS-07-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-08_Configuration_editor.md -->

# SYS-08 — Configuration editor

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-08 |
| Title | Configuration editor |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Configuration editor. Admin edits approved configuration keys and reviews pending changes.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in when required; related seed records exist |
| Trigger | User starts the configuration editor workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the configuration editor page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Config key/value<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Configuration editor page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/configuration_editor; POST /api/sys/configuration_editor; PATCH /api/sys/configuration_editor/{id} when updates are needed |
| Request data | Config key/value |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ConfigurationEditor |
| Logical module | server_monitoring_job_control_panel/configuration_editor service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-08-FA-01 | authorized privileged action updates the correct record |
| SYS-08-FA-02 | invalid privileged action is rejected |
| SYS-08-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-09_Alert_center.md -->

# SYS-09 — Alert center

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-09 |
| Title | Alert center |
| Primary actors | Operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Alert center. Operators view, acknowledge, assign, and comment on alerts.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator is signed in when required; related seed records exist |
| Trigger | User starts the alert center workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the alert center page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Alert record<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Alert center page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/alert_center; POST /api/sys/alert_center; PATCH /api/sys/alert_center/{id} when updates are needed |
| Request data | Alert record |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AlertCenter |
| Logical module | server_monitoring_job_control_panel/alert_center service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-09-FA-01 | valid alert center workflow returns the expected confirmation or data view |
| SYS-09-FA-02 | invalid input is rejected without unintended persistence |
| SYS-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-10_Health_check_targets.md -->

# SYS-10 — Health check targets

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-10 |
| Title | Health check targets |
| Primary actors | Operator; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Health check targets. Users configure HTTP/TCP health checks for internal services.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Operator; admin is signed in when required; related seed records exist |
| Trigger | User starts the health check targets workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Operator opens the health check targets page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Target URL/host<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Health check targets page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/health_check_targets; POST /api/sys/health_check_targets; PATCH /api/sys/health_check_targets/{id} when updates are needed |
| Request data | Target URL/host |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; HealthCheckTargets |
| Logical module | server_monitoring_job_control_panel/health_check_targets service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-10-FA-01 | valid health check targets workflow returns the expected confirmation or data view |
| SYS-10-FA-02 | invalid input is rejected without unintended persistence |
| SYS-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-11_API_token_manager.md -->

# SYS-11 — API token manager

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-11 |
| Title | API token manager |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

API token manager. Admin creates, revokes, and audits monitoring API tokens.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in when required; related seed records exist |
| Trigger | User starts the api token manager workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the api token manager page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Token metadata<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | API token manager page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/api_token_manager; POST /api/sys/api_token_manager; PATCH /api/sys/api_token_manager/{id} when updates are needed |
| Request data | Token metadata |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ApiTokenManager |
| Logical module | server_monitoring_job_control_panel/api_token_manager service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-11-FA-01 | valid api token manager workflow returns the expected confirmation or data view |
| SYS-11-FA-02 | invalid input is rejected without unintended persistence |
| SYS-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SYS-12_Audit_logs_and_admin_operations.md -->

# SYS-12 — Audit logs and admin operations

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P16 — Server Monitoring & Job Control Panel |
| Use case | SYS-12 |
| Title | Audit logs and admin operations |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Cacti 1.2.26 |

## 2. Business objective

Audit logs and admin operations. Admin reviews privileged operations and manages operators.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Cacti 1.2.26**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: devices, graphs, templates, data sources, package imports, logs, service-like jobs, users/permissions, reports, and monitoring settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the audit logs and admin operations page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Audit filter, role<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Audit logs and admin operations page, form, list, and detail view when applicable |
| API / event contract | GET /api/sys/audit_logs_and_admin_operations; POST /api/sys/audit_logs_and_admin_operations; PATCH /api/sys/audit_logs_and_admin_operations/{id} when updates are needed |
| Request data | Audit filter, role |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AuditLogsAndAdminOperations; AuditEvent |
| Logical module | server_monitoring_job_control_panel/audit_logs_and_admin_operations service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SYS-12-FA-01 | known filters return only matching visible records |
| SYS-12-FA-02 | empty or invalid filters return a bounded empty/error response |
| SYS-12-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
