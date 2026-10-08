Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — JavaScript — P10_AI_Assistant_LLM_WebUI

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
| Alignment target | Lobe Chat |
| Workflow anchors | conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P10 — AI Assistant / LLM WebUI

Category: AI service

This folder contains Version A project-generation use cases for the AI Assistant / LLM WebUI. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| AI-01 | Account access | User; admin |
| AI-02 | Conversation management | User |
| AI-03 | Prompt templates | User; admin |
| AI-04 | Model configuration | User; admin |
| AI-05 | Knowledge file upload | User |
| AI-06 | Retrieval collection | User |
| AI-07 | Tool/plugin registry | Admin; user |
| AI-08 | API key management | User; admin |
| AI-09 | Chat execution | User |
| AI-10 | Share conversation | User |
| AI-11 | Usage and audit logs | User; admin |
| AI-12 | Admin moderation and settings | Admin |

---

# Complete use-case specifications

<!-- Source: AI-01_Account_access.md -->

# AI-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-01 |
| Title | Account access |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Account access. Users authenticate and access personal AI workspaces.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


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
| API / event contract | GET /api/ai/account_access; POST /api/ai/account_access; PATCH /api/ai/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | ai_assistant_llm_webui/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| AI-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| AI-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-02_Conversation_management.md -->

# AI-02 — Conversation management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-02 |
| Title | Conversation management |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Conversation management. Users create, rename, archive, and search chat conversations.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the conversation management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Conversation, message<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Conversation management page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/conversation_management; POST /api/ai/conversation_management; PATCH /api/ai/conversation_management/{id} when updates are needed |
| Request data | Conversation, message |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ConversationManagement; AuditEvent |
| Logical module | ai_assistant_llm_webui/conversation_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-02-FA-01 | authorized privileged action updates the correct record |
| AI-02-FA-02 | invalid privileged action is rejected |
| AI-02-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-03_Prompt_templates.md -->

# AI-03 — Prompt templates

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-03 |
| Title | Prompt templates |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Prompt templates. Users save reusable prompts and admins publish shared templates.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the prompt templates workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the prompt templates page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Template text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Prompt templates page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/prompt_templates; POST /api/ai/prompt_templates; PATCH /api/ai/prompt_templates/{id} when updates are needed |
| Request data | Template text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PromptTemplates |
| Logical module | ai_assistant_llm_webui/prompt_templates service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-03-FA-01 | valid prompt templates workflow returns the expected confirmation or data view |
| AI-03-FA-02 | invalid input is rejected without unintended persistence |
| AI-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-04_Model_configuration.md -->

# AI-04 — Model configuration

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-04 |
| Title | Model configuration |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Model configuration. Users choose model profile, temperature, context length, and safety mode.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the model configuration workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the model configuration page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Model settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Model configuration page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/model_configuration; POST /api/ai/model_configuration; PATCH /api/ai/model_configuration/{id} when updates are needed |
| Request data | Model settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ModelConfiguration |
| Logical module | ai_assistant_llm_webui/model_configuration service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-04-FA-01 | authorized privileged action updates the correct record |
| AI-04-FA-02 | invalid privileged action is rejected |
| AI-04-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-05_Knowledge_file_upload.md -->

# AI-05 — Knowledge file upload

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-05 |
| Title | Knowledge file upload |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Knowledge file upload. Users upload files to attach to conversations or collections.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the knowledge file upload page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Uploaded document<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Knowledge file upload page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/knowledge_file_upload; POST /api/ai/knowledge_file_upload; PATCH /api/ai/knowledge_file_upload/{id} when updates are needed |
| Request data | Uploaded document |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; KnowledgeFileUpload; StoredFile |
| Logical module | ai_assistant_llm_webui/knowledge_file_upload service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-05-FA-01 | valid file operation stores or returns the correct file metadata |
| AI-05-FA-02 | invalid file operation is rejected without orphan records |
| AI-05-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-06_Retrieval_collection.md -->

# AI-06 — Retrieval collection

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-06 |
| Title | Retrieval collection |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Retrieval collection. Users create collections and search uploaded knowledge.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the retrieval collection workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the retrieval collection page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Collection, query<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Retrieval collection page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/retrieval_collection; POST /api/ai/retrieval_collection; PATCH /api/ai/retrieval_collection/{id} when updates are needed |
| Request data | Collection, query |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RetrievalCollection |
| Logical module | ai_assistant_llm_webui/retrieval_collection service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-06-FA-01 | valid retrieval collection workflow returns the expected confirmation or data view |
| AI-06-FA-02 | invalid input is rejected without unintended persistence |
| AI-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-07_Tool_plugin_registry.md -->

# AI-07 — Tool/plugin registry

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-07 |
| Title | Tool/plugin registry |
| Primary actors | Admin; user |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Tool/plugin registry. Admins configure tools and users enable allowed tools.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin; user is signed in when required; related seed records exist |
| Trigger | User starts the tool/plugin registry workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the tool/plugin registry page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Tool config<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Tool/plugin registry page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/tool_plugin_registry; POST /api/ai/tool_plugin_registry; PATCH /api/ai/tool_plugin_registry/{id} when updates are needed |
| Request data | Tool config |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ToolPluginRegistry |
| Logical module | ai_assistant_llm_webui/tool_plugin_registry service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-07-FA-01 | valid tool/plugin registry workflow returns the expected confirmation or data view |
| AI-07-FA-02 | invalid input is rejected without unintended persistence |
| AI-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-08_API_key_management.md -->

# AI-08 — API key management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-08 |
| Title | API key management |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

API key management. Users store masked provider keys and rotate them.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the api key management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Secret record<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | API key management page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/api_key_management; POST /api/ai/api_key_management; PATCH /api/ai/api_key_management/{id} when updates are needed |
| Request data | Secret record |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ApiKeyManagement; AuditEvent |
| Logical module | ai_assistant_llm_webui/api_key_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-08-FA-01 | authorized privileged action updates the correct record |
| AI-08-FA-02 | invalid privileged action is rejected |
| AI-08-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-09_Chat_execution.md -->

# AI-09 — Chat execution

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-09 |
| Title | Chat execution |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Chat execution. Users send prompts and receive assistant responses with citations when available.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the chat execution workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the chat execution page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Prompt, response<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Chat execution page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/chat_execution; POST /api/ai/chat_execution; PATCH /api/ai/chat_execution/{id} when updates are needed |
| Request data | Prompt, response |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ChatExecution |
| Logical module | ai_assistant_llm_webui/chat_execution service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-09-FA-01 | valid chat execution workflow returns the expected confirmation or data view |
| AI-09-FA-02 | invalid input is rejected without unintended persistence |
| AI-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-10_Share_conversation.md -->

# AI-10 — Share conversation

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-10 |
| Title | Share conversation |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Share conversation. Users create limited public share links for selected conversations.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the share conversation workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the share conversation page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Share token<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Share conversation page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/share_conversation; POST /api/ai/share_conversation; PATCH /api/ai/share_conversation/{id} when updates are needed |
| Request data | Share token |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ShareConversation |
| Logical module | ai_assistant_llm_webui/share_conversation service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-10-FA-01 | valid share conversation workflow returns the expected confirmation or data view |
| AI-10-FA-02 | invalid input is rejected without unintended persistence |
| AI-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-11_Usage_and_audit_logs.md -->

# AI-11 — Usage and audit logs

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-11 |
| Title | Usage and audit logs |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Usage and audit logs. Users view usage; admins review system activity.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the usage and audit logs workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the usage and audit logs page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Audit log<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Usage and audit logs page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/usage_and_audit_logs; POST /api/ai/usage_and_audit_logs; PATCH /api/ai/usage_and_audit_logs/{id} when updates are needed |
| Request data | Audit log |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; UsageAndAuditLogs; AuditEvent |
| Logical module | ai_assistant_llm_webui/usage_and_audit_logs service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-11-FA-01 | known filters return only matching visible records |
| AI-11-FA-02 | empty or invalid filters return a bounded empty/error response |
| AI-11-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: AI-12_Admin_moderation_and_settings.md -->

# AI-12 — Admin moderation and settings

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P10 — AI Assistant / LLM WebUI |
| Use case | AI-12 |
| Title | Admin moderation and settings |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Lobe Chat |

## 2. Business objective

Admin moderation and settings. Admin manages users, model access, blocked terms, and system defaults.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Lobe Chat**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes, API keys, sharing, and audit logs.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin moderation and settings page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin moderation and settings page, form, list, and detail view when applicable |
| API / event contract | GET /api/ai/admin_moderation_and_settings; POST /api/ai/admin_moderation_and_settings; PATCH /api/ai/admin_moderation_and_settings/{id} when updates are needed |
| Request data | Admin settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminModerationAndSettings; AuditEvent |
| Logical module | ai_assistant_llm_webui/admin_moderation_and_settings service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| AI-12-FA-01 | authorized privileged action updates the correct record |
| AI-12-FA-02 | invalid privileged action is rejected |
| AI-12-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
