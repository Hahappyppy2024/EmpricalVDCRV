# SYS-10 — Timeouts, error isolation, and cleanup

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Detect expired work, isolate client-level failures, and release resources through one consistent cleanup path.

## Actors

Daemon; operating system

## Main flow

1. The event loop tracks last-activity timestamps and operation deadlines for active connections.
2. Expired connections are marked for closure.
3. Read, parse, processing, and write failures are converted into connection-state changes rather than unrelated ad-hoc deallocation.
4. Terminal cleanup unregisters polling state, closes the owned descriptor, releases request/response/header/body/resource state, updates counters, and frees the Connection.
5. Server-wide shutdown uses the same cleanup mechanism for remaining connections.

## Alternative outcomes

- Cleanup may occur after partial initialization and therefore checks ownership state before releasing each subresource.
- A connection already in terminal state is not released a second time.
- Logging or close errors do not recursively re-enter cleanup.
- Timer processing ignores connections that have already been removed from active runtime collections.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Connection state and timeout/error reason. |
| Runtime entities | Ownership flags, timers, descriptor-registration state. |
| Success | No owned resources or poll registrations remain for the closed connection. |
| Diagnostic | Stable closure reason for logs and counters. |

## Business rules

- Every resource has one owner and one release point.
- Cleanup supports partially initialized connection state.
- Connections are removed from runtime collections before their memory is released.
- Connection or request pointers are not dereferenced after terminal cleanup.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
