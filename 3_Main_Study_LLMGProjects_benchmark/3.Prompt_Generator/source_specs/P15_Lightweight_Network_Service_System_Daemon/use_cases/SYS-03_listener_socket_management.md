# SYS-03 — Listener and socket management

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Create and manage the listening TCP endpoint and transfer accepted client descriptors into connection management.

## Actors

Daemon; operating system

## Main flow

1. The daemon creates a TCP socket for the configured address family.
2. Supported socket options are applied before binding.
3. The socket is bound to the configured interface and port and placed into listening mode with a bounded backlog.
4. The listener is registered with the daemon polling or event mechanism.
5. When the listener is ready, pending client connections are accepted and handed to the client-connection use case.

## Alternative outcomes

- Socket creation, bind, or listen failure aborts startup and closes the listener descriptor.
- Transient accept failures are handled without terminating the daemon.
- When the configured connection limit is reached, new clients are rejected or deferred according to a documented policy.
- During shutdown the listener is removed from polling and closed before active connections are drained.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Validated listen address, port, backlog, and connection limit. |
| Runtime entities | Listener and listener file descriptor. |
| Success | One active listening endpoint registered with the event loop. |
| Handoff | Accepted descriptor, peer address, and acceptance timestamp. |

## Business rules

- Descriptors have one clear owner at every stage.
- Failure paths close descriptors created by the failed operation.
- Accepted descriptors are configured consistently before activation.
- The listener is never processed as if it were a normal client connection.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
