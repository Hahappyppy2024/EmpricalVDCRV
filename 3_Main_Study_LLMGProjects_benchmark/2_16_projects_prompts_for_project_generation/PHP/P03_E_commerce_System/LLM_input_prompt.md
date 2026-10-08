Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P03_E_commerce_System

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
| Alignment target | PrestaShop 8.1.0 |
| Workflow anchors | catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P03 — E-commerce System

Category: E-commerce

This folder contains Version A project-generation use cases for the E-commerce System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| SHOP-01 | Accounts | Visitor; customer; seller; admin |
| SHOP-02 | Catalog search | Customer |
| SHOP-03 | Reviews | Customer; moderator |
| SHOP-04 | Catalog management | Seller; admin |
| SHOP-05 | Shopping cart | Customer |
| SHOP-06 | Checkout | Customer |
| SHOP-07 | Order access | Customer; seller; admin |
| SHOP-08 | Order lifecycle | Customer; seller; admin |
| SHOP-09 | Inventory | Seller; admin |
| SHOP-10 | Seller and administrator operations | Seller; admin |
| SHOP-11 | Customer data | Customer; admin |
| SHOP-12 | Reports | Seller; admin |
| SHOP-13 | Frontend API integration | User |

---

# Complete use-case specifications

<!-- Source: SHOP-01_Accounts.md -->

# SHOP-01 — Accounts

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-01 |
| Title | Accounts |
| Primary actors | Visitor; customer; seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Accounts. Users register, sign in, manage profiles, and recover accounts.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Visitor opens the accounts page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session, profile<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Accounts page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/accounts; POST /api/shop/accounts; PATCH /api/shop/accounts/{id} when updates are needed |
| Request data | Account, session, profile |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Accounts |
| Logical module | e_commerce_system/accounts service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| SHOP-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| SHOP-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-02_Catalog_search.md -->

# SHOP-02 — Catalog search

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-02 |
| Title | Catalog search |
| Primary actors | Customer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Catalog search. Customers browse products by keyword, category, price, and availability.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the catalog search page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Search query, filters<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Catalog search page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/catalog_search; POST /api/shop/catalog_search; PATCH /api/shop/catalog_search/{id} when updates are needed |
| Request data | Search query, filters |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; CatalogSearch |
| Logical module | e_commerce_system/catalog_search service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-02-FA-01 | known filters return only matching visible records |
| SHOP-02-FA-02 | empty or invalid filters return a bounded empty/error response |
| SHOP-02-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-03_Reviews.md -->

# SHOP-03 — Reviews

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-03 |
| Title | Reviews |
| Primary actors | Customer; moderator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Reviews. Customers post product reviews and moderators manage inappropriate content.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer; moderator is signed in when required; related seed records exist |
| Trigger | User starts the reviews workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the reviews page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Review text, rating<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Reviews page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/reviews; POST /api/shop/reviews; PATCH /api/shop/reviews/{id} when updates are needed |
| Request data | Review text, rating |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Reviews |
| Logical module | e_commerce_system/reviews service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-03-FA-01 | valid reviews workflow returns the expected confirmation or data view |
| SHOP-03-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-04_Catalog_management.md -->

# SHOP-04 — Catalog management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-04 |
| Title | Catalog management |
| Primary actors | Seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Catalog management. Sellers create and update products, images, descriptions, and prices.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Seller; admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Seller opens the catalog management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Product data, image upload<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Catalog management page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/catalog_management; POST /api/shop/catalog_management; PATCH /api/shop/catalog_management/{id} when updates are needed |
| Request data | Product data, image upload |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; CatalogManagement; AuditEvent |
| Logical module | e_commerce_system/catalog_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-04-FA-01 | known filters return only matching visible records |
| SHOP-04-FA-02 | empty or invalid filters return a bounded empty/error response |
| SHOP-04-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-05_Shopping_cart.md -->

# SHOP-05 — Shopping cart

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-05 |
| Title | Shopping cart |
| Primary actors | Customer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Shopping cart. Customers add, update, and remove cart items.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer is signed in when required; related seed records exist |
| Trigger | User starts the shopping cart workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the shopping cart page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Cart item, quantity<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Shopping cart page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/shopping_cart; POST /api/shop/shopping_cart; PATCH /api/shop/shopping_cart/{id} when updates are needed |
| Request data | Cart item, quantity |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ShoppingCart |
| Logical module | e_commerce_system/shopping_cart service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-05-FA-01 | valid shopping cart workflow returns the expected confirmation or data view |
| SHOP-05-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-06_Checkout.md -->

# SHOP-06 — Checkout

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-06 |
| Title | Checkout |
| Primary actors | Customer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Checkout. Customer submits shipping and simulated payment information to create an order.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer is signed in when required; related seed records exist |
| Trigger | User starts the checkout workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the checkout page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Address, payment fixture<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Checkout page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/checkout; POST /api/shop/checkout; PATCH /api/shop/checkout/{id} when updates are needed |
| Request data | Address, payment fixture |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Checkout |
| Logical module | e_commerce_system/checkout service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-06-FA-01 | valid checkout workflow returns the expected confirmation or data view |
| SHOP-06-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-07_Order_access.md -->

# SHOP-07 — Order access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-07 |
| Title | Order access |
| Primary actors | Customer; seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Order access. Users view orders according to role and ownership.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer; seller; admin is signed in when required; related seed records exist |
| Trigger | User starts the order access workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the order access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Order ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Order access page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/order_access; POST /api/shop/order_access; PATCH /api/shop/order_access/{id} when updates are needed |
| Request data | Order ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; OrderAccess |
| Logical module | e_commerce_system/order_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-07-FA-01 | valid order access workflow returns the expected confirmation or data view |
| SHOP-07-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-08_Order_lifecycle.md -->

# SHOP-08 — Order lifecycle

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-08 |
| Title | Order lifecycle |
| Primary actors | Customer; seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Order lifecycle. Orders move through paid, shipped, delivered, cancelled, and refunded states.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer; seller; admin is signed in when required; related seed records exist |
| Trigger | User starts the order lifecycle workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the order lifecycle page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Order status<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Order lifecycle page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/order_lifecycle; POST /api/shop/order_lifecycle; PATCH /api/shop/order_lifecycle/{id} when updates are needed |
| Request data | Order status |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; OrderLifecycle |
| Logical module | e_commerce_system/order_lifecycle service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-08-FA-01 | valid order lifecycle workflow returns the expected confirmation or data view |
| SHOP-08-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-09_Inventory.md -->

# SHOP-09 — Inventory

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-09 |
| Title | Inventory |
| Primary actors | Seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Inventory. Sellers manage stock counts and restock events.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Seller; admin is signed in when required; related seed records exist |
| Trigger | User starts the inventory workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Seller opens the inventory page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Inventory adjustment<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Inventory page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/inventory; POST /api/shop/inventory; PATCH /api/shop/inventory/{id} when updates are needed |
| Request data | Inventory adjustment |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Inventory |
| Logical module | e_commerce_system/inventory service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-09-FA-01 | valid inventory workflow returns the expected confirmation or data view |
| SHOP-09-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-10_Seller_and_administrator_operations.md -->

# SHOP-10 — Seller and administrator operations

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-10 |
| Title | Seller and administrator operations |
| Primary actors | Seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Seller and administrator operations. Privileged users manage products, users, promotions, and platform settings.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Seller; admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Seller opens the seller and administrator operations page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin action<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Seller and administrator operations page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/seller_and_administrator_operations; POST /api/shop/seller_and_administrator_operations; PATCH /api/shop/seller_and_administrator_operations/{id} when updates are needed |
| Request data | Admin action |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; SellerAndAdministratorOperations; AuditEvent |
| Logical module | e_commerce_system/seller_and_administrator_operations service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-10-FA-01 | authorized privileged action updates the correct record |
| SHOP-10-FA-02 | invalid privileged action is rejected |
| SHOP-10-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-11_Customer_data.md -->

# SHOP-11 — Customer data

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-11 |
| Title | Customer data |
| Primary actors | Customer; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Customer data. Customers view and update addresses, saved preferences, and profile data.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Customer; admin is signed in when required; related seed records exist |
| Trigger | User starts the customer data workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Customer opens the customer data page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Customer record<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Customer data page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/customer_data; POST /api/shop/customer_data; PATCH /api/shop/customer_data/{id} when updates are needed |
| Request data | Customer record |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; CustomerData |
| Logical module | e_commerce_system/customer_data service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-11-FA-01 | valid customer data workflow returns the expected confirmation or data view |
| SHOP-11-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-12_Reports.md -->

# SHOP-12 — Reports

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-12 |
| Title | Reports |
| Primary actors | Seller; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Reports. Privileged users export sales, inventory, and customer reports.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Seller; admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Seller opens the reports page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Report filters, export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Reports page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/reports; POST /api/shop/reports; PATCH /api/shop/reports/{id} when updates are needed |
| Request data | Report filters, export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Reports |
| Logical module | e_commerce_system/reports service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-12-FA-01 | valid reports workflow returns the expected confirmation or data view |
| SHOP-12-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: SHOP-13_Frontend_API_integration.md -->

# SHOP-13 — Frontend API integration

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P03 — E-commerce System |
| Use case | SHOP-13 |
| Title | Frontend API integration |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | PrestaShop 8.1.0 |

## 2. Business objective

Frontend API integration. UI handles validation, payment simulation, and async order states.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **PrestaShop 8.1.0**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: catalog, product administration, cart, checkout, order lifecycle, customer data, stock, promotions, and reports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the frontend api integration workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the frontend api integration page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits API response states<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Frontend API integration page, form, list, and detail view when applicable |
| API / event contract | GET /api/shop/frontend_api_integration; POST /api/shop/frontend_api_integration; PATCH /api/shop/frontend_api_integration/{id} when updates are needed |
| Request data | API response states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegration |
| Logical module | e_commerce_system/frontend_api_integration service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| SHOP-13-FA-01 | valid frontend api integration workflow returns the expected confirmation or data view |
| SHOP-13-FA-02 | invalid input is rejected without unintended persistence |
| SHOP-13-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
