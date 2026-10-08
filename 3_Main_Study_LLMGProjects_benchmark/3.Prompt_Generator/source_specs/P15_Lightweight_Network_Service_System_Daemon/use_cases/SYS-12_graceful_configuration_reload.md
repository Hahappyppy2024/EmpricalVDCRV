# SYS-12 — Graceful configuration reload

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Reload mutable configuration without abruptly terminating established connections or replacing valid runtime state with an invalid configuration.

## Actors

Operator; daemon

## Main flow

1. The operator sends the documented reload signal.
2. The signal handler records a reload request; normal event-loop control performs the reload.
3. The daemon loads and validates a complete candidate Config using the same rules as startup.
4. Settings that can be changed in place are published at a controlled synchronization point.
5. If supported listener settings changed, a replacement listener is created successfully before the old listener is retired.
6. Established connections retain the configuration state they require until completion, after which obsolete configuration objects are released.

## Alternative outcomes

- An invalid candidate configuration is rejected and the current configuration remains active.
- If creation of a replacement listener fails, the old listener remains active.
- Non-reloadable settings produce a diagnostic requiring full restart.
- A shutdown request takes precedence over beginning additional reload work.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Reload signal and current configuration path. |
| Runtime entities | Current Config, candidate Config, optional old/new Listener generations. |
| Success | Validated replacement settings become active without interrupting unrelated established work. |
| Failure | Previous validated configuration remains active. |

## Business rules

- Candidate configuration is fully validated before publication.
- Old configuration and listener resources remain alive while active runtime objects still depend on them.
- Ownership transitions during reload are explicit.
- Parsing, allocation, socket creation, logging, and cleanup occur outside the signal handler.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
