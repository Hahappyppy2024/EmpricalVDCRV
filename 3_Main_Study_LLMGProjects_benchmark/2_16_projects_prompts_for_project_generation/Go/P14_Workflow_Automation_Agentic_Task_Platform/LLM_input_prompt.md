Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — Go — P14_Workflow_Automation_Agentic_Task_Platform

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
| Alignment target | Woodpecker CI |
| Workflow anchors | repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P14 — Workflow Automation / Agentic Task Platform

Category: Agentic workflow

This folder contains Version A project-generation use cases for the Workflow Automation / Agentic Task Platform. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| AGENT-01 | Account access | User; admin |
| AGENT-02 | Workflow creation | User |
| AGENT-03 | Tool catalog | User; admin |
| AGENT-04 | Task execution | User |
| AGENT-05 | Scheduled runs | User |
| AGENT-06 | Workspace files | User |
| AGENT-07 | Webhook triggers | User |
| AGENT-08 | External HTTP action | User |
| AGENT-09 | Secrets manager | User; admin |
| AGENT-10 | Run logs and replay | User; admin |
| AGENT-11 | Sharing and templates | User; admin |
| AGENT-12 | Admin governance | Admin |

---

# Complete use-case specifications

<!-- Source: AGENT-01_Account_access.md -->

# AGENT-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-01 |
| Title | Account access |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Account access. Users authenticate and access personal automation workspaces.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/account_access; POST /api/agent/account_access; PATCH /api/agent/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | workflow_automation_agentic_task_platform/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| AGENT-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| AGENT-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-02_Workflow_creation.md -->

# AGENT-02 — Workflow creation

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-02 |
| Title | Workflow creation |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Workflow creation. Users create workflows with triggers, steps, conditions, and names.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the workflow creation workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the workflow creation page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Workflow definition<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Workflow creation page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/workflow_creation; POST /api/agent/workflow_creation; PATCH /api/agent/workflow_creation/{id} when updates are needed |
| Request data | Workflow definition |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; WorkflowCreation |
| Logical module | workflow_automation_agentic_task_platform/workflow_creation service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-02-FA-01 | valid workflow creation workflow returns the expected confirmation or data view |
| AGENT-02-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-02-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-03_Tool_catalog.md -->

# AGENT-03 — Tool catalog

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-03 |
| Title | Tool catalog |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Tool catalog. Admins define tools; users add allowed tools to workflows.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin can access the relevant listing; seed records exist |
| Trigger | User starts the tool catalog workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the tool catalog page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Tool definition<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Tool catalog page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/tool_catalog; POST /api/agent/tool_catalog; PATCH /api/agent/tool_catalog/{id} when updates are needed |
| Request data | Tool definition |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ToolCatalog |
| Logical module | workflow_automation_agentic_task_platform/tool_catalog service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-03-FA-01 | known filters return only matching visible records |
| AGENT-03-FA-02 | empty or invalid filters return a bounded empty/error response |
| AGENT-03-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-04_Task_execution.md -->

# AGENT-04 — Task execution

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-04 |
| Title | Task execution |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Task execution. Users run workflows manually and inspect step-by-step results.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the task execution workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the task execution page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Run request<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Task execution page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/task_execution; POST /api/agent/task_execution; PATCH /api/agent/task_execution/{id} when updates are needed |
| Request data | Run request |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; TaskExecution |
| Logical module | workflow_automation_agentic_task_platform/task_execution service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-04-FA-01 | valid task execution workflow returns the expected confirmation or data view |
| AGENT-04-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-05_Scheduled_runs.md -->

# AGENT-05 — Scheduled runs

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-05 |
| Title | Scheduled runs |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Scheduled runs. Users configure recurring or delayed workflow runs.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the scheduled runs workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the scheduled runs page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Schedule<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Scheduled runs page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/scheduled_runs; POST /api/agent/scheduled_runs; PATCH /api/agent/scheduled_runs/{id} when updates are needed |
| Request data | Schedule |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ScheduledRuns |
| Logical module | workflow_automation_agentic_task_platform/scheduled_runs service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-05-FA-01 | valid scheduled runs workflow returns the expected confirmation or data view |
| AGENT-05-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-06_Workspace_files.md -->

# AGENT-06 — Workspace files

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-06 |
| Title | Workspace files |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Workspace files. Workflows read and write files within a scoped workspace.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in; target record exists; upload storage is configured |
| Trigger | User starts the workspace files workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the workspace files page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits File path/ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Workspace files page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/workspace_files; POST /api/agent/workspace_files; PATCH /api/agent/workspace_files/{id} when updates are needed |
| Request data | File path/ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; WorkspaceFiles; StoredFile |
| Logical module | workflow_automation_agentic_task_platform/workspace_files service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-06-FA-01 | valid file operation stores or returns the correct file metadata |
| AGENT-06-FA-02 | invalid file operation is rejected without orphan records |
| AGENT-06-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-07_Webhook_triggers.md -->

# AGENT-07 — Webhook triggers

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-07 |
| Title | Webhook triggers |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Webhook triggers. Users create inbound webhook URLs to trigger workflows.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the webhook triggers workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the webhook triggers page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Webhook token<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Webhook triggers page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/webhook_triggers; POST /api/agent/webhook_triggers; PATCH /api/agent/webhook_triggers/{id} when updates are needed |
| Request data | Webhook token |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; WebhookTriggers |
| Logical module | workflow_automation_agentic_task_platform/webhook_triggers service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-07-FA-01 | valid webhook triggers workflow returns the expected confirmation or data view |
| AGENT-07-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-08_External_HTTP_action.md -->

# AGENT-08 — External HTTP action

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-08 |
| Title | External HTTP action |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

External HTTP action. Workflows call configured HTTP endpoints with bounded request options.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the external http action workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the external http action page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits URL, method, payload<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | External HTTP action page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/external_http_action; POST /api/agent/external_http_action; PATCH /api/agent/external_http_action/{id} when updates are needed |
| Request data | URL, method, payload |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ExternalHttpAction |
| Logical module | workflow_automation_agentic_task_platform/external_http_action service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-08-FA-01 | valid external http action workflow returns the expected confirmation or data view |
| AGENT-08-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-09_Secrets_manager.md -->

# AGENT-09 — Secrets manager

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-09 |
| Title | Secrets manager |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Secrets manager. Users store masked tokens for workflow actions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the secrets manager workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the secrets manager page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Secret value<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Secrets manager page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/secrets_manager; POST /api/agent/secrets_manager; PATCH /api/agent/secrets_manager/{id} when updates are needed |
| Request data | Secret value |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; SecretsManager |
| Logical module | workflow_automation_agentic_task_platform/secrets_manager service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-09-FA-01 | valid secrets manager workflow returns the expected confirmation or data view |
| AGENT-09-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-10_Run_logs_and_replay.md -->

# AGENT-10 — Run logs and replay

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-10 |
| Title | Run logs and replay |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Run logs and replay. Users inspect logs, retry failed steps, and compare outputs.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the run logs and replay workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the run logs and replay page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Run log<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Run logs and replay page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/run_logs_and_replay; POST /api/agent/run_logs_and_replay; PATCH /api/agent/run_logs_and_replay/{id} when updates are needed |
| Request data | Run log |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RunLogsAndReplay |
| Logical module | workflow_automation_agentic_task_platform/run_logs_and_replay service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-10-FA-01 | known filters return only matching visible records |
| AGENT-10-FA-02 | empty or invalid filters return a bounded empty/error response |
| AGENT-10-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-11_Sharing_and_templates.md -->

# AGENT-11 — Sharing and templates

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-11 |
| Title | Sharing and templates |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Sharing and templates. Users publish reusable workflow templates.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the sharing and templates workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the sharing and templates page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Template definition<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Sharing and templates page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/sharing_and_templates; POST /api/agent/sharing_and_templates; PATCH /api/agent/sharing_and_templates/{id} when updates are needed |
| Request data | Template definition |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; SharingAndTemplates |
| Logical module | workflow_automation_agentic_task_platform/sharing_and_templates service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-11-FA-01 | valid sharing and templates workflow returns the expected confirmation or data view |
| AGENT-11-FA-02 | invalid input is rejected without unintended persistence |
| AGENT-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AGENT-12_Admin_governance.md -->

# AGENT-12 — Admin governance

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P14 — Workflow Automation / Agentic Task Platform |
| Use case | AGENT-12 |
| Title | Admin governance |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Woodpecker CI |

## 2. Business objective

Admin governance. Admin manages users, tool permissions, quotas, and disabled actions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Woodpecker CI**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: repositories, pipelines, workflow steps, webhook triggers, scheduled runs, secrets, runners/agents, logs, replay, and templates.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin governance page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin governance page, form, list, and detail view when applicable |
| API / event contract | GET /api/agent/admin_governance; POST /api/agent/admin_governance; PATCH /api/agent/admin_governance/{id} when updates are needed |
| Request data | Admin settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminGovernance; AuditEvent |
| Logical module | workflow_automation_agentic_task_platform/admin_governance service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AGENT-12-FA-01 | authorized privileged action updates the correct record |
| AGENT-12-FA-02 | invalid privileged action is rejected |
| AGENT-12-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
