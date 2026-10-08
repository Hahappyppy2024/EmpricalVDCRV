# SYS-09 — Response construction and transmission

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Construct a complete protocol response and transmit it correctly even when network writes complete only partially.

## Actors

Daemon; network client

## Main flow

1. The daemon chooses a response status and metadata from the request outcome.
2. Response headers are generated into bounded output state.
3. For static resources, the response references the opened Resource; generated responses use explicitly owned body memory.
4. The connection enters WRITING state and transmits headers and body while tracking successful progress.
5. After all response bytes have been sent, response resources are released and the connection follows its reuse or close policy.

## Alternative outcomes

- A partial write preserves the unsent offset and resumes when the socket is writable.
- A peer disconnect or fatal write failure enters connection cleanup.
- If response construction cannot acquire required memory, a minimal internal-error result is sent when feasible; otherwise the connection is closed.
- Supported body-less response semantics omit body transmission while preserving appropriate metadata.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Request outcome, optional Resource, connection policy. |
| Entity | Response {status, header buffer, body source, sent offsets}. |
| Success | Complete response transmitted exactly once. |
| Failure | Connection-level send error passed to cleanup. |

## Business rules

- Output offsets advance only by bytes reported as successfully written.
- Response-size arithmetic is checked before allocation or formatting.
- Resource and generated body ownership is explicit.
- Released response state cannot be transmitted again.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
