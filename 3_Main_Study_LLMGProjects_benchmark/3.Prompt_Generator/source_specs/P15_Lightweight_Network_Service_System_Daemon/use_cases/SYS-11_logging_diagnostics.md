# SYS-11 — Access logging and runtime diagnostics

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Record request outcomes and operational events and maintain small runtime counters for operator diagnostics.

## Actors

Operator; daemon

## Main flow

1. At startup, the daemon opens configured access and error log destinations or uses documented stream fallbacks.
2. For each completed request, one access record is written containing timestamp, peer, method, normalized target, status, and bytes sent.
3. Operational failures produce concise error records with a stable subsystem/error code.
4. Runtime counters track accepted connections, active connections, completed requests, parse failures, timeouts, and transferred bytes.
5. During shutdown, buffered log data is flushed and owned log descriptors are closed.

## Alternative outcomes

- If an optional log file cannot be opened, startup follows a documented fallback or fail policy.
- A logging failure does not recursively log itself indefinitely.
- Untrusted request text is encoded before it is written as one logical record.
- Counter implementation avoids silent wraparound during ordinary benchmark operation.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Request completion, error events, lifecycle events. |
| Outputs | Access log, error log, RuntimeCounters. |
| Success | Deterministic record format and updated counters. |
| Excluded data | Raw request bodies, secret configuration values, internal memory addresses. |

## Business rules

- Logging must not change protocol outcomes.
- Untrusted values cannot create additional logical log records.
- Log descriptor ownership is explicit.
- Diagnostics use stable codes/messages rather than raw process-memory details.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
