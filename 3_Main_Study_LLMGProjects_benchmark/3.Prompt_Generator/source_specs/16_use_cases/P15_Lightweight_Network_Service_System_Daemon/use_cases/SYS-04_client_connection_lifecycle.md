# SYS-04 — Client connection lifecycle

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Maintain each accepted client as an owned connection object through receive, processing, response, and cleanup states.

## Actors

Network client; daemon

## Main flow

1. An accepted socket and peer address are received from listener management.
2. The daemon creates a Connection object containing descriptor ownership, buffers, request state, response state, timestamps, and peer information.
3. The connection is registered for the event required by its current state.
4. Incoming data is dispatched to request parsing and body processing.
5. Completed requests are passed to resource processing and response construction.
6. When work finishes, the connection is reset for another supported request or transitioned to closure.
7. Connection cleanup unregisters the descriptor, closes it, releases subordinate objects, updates counters, and frees the connection.

## Alternative outcomes

- If connection-object allocation fails, the newly accepted descriptor is closed immediately.
- Peer disconnect, timeout, read failure, parse failure, or write failure enters the same controlled terminal cleanup path.
- Partial reads and writes preserve progress.
- Failure of one connection does not terminate unrelated connections or the daemon.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Accepted socket descriptor, peer address, configuration limits. |
| Entity | Connection {fd, state, buffers, request, response, timestamps, peer}. |
| State model | ACCEPTED → READING → PROCESSING → WRITING → CLOSED with documented error transitions. |
| Success | Connection resources are fully released after completion. |

## Business rules

- After successful initialization the Connection owns its socket descriptor.
- Each connection has exactly one terminal cleanup path.
- Callbacks must not operate on terminal connection state.
- Per-connection storage is bounded by validated configuration.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
