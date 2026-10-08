# SYS-08 — Request body and input-buffer handling

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Receive a bounded request body when required while preserving correctness across partial network reads and buffer growth.

## Actors

Network client; daemon

## Main flow

1. Header processing determines whether a request body is expected and establishes a validated expected length.
2. If the body length is zero, processing continues without body allocation.
3. For a supported non-zero body, request storage is allocated or grown within the configured maximum.
4. Each socket read appends only the number of bytes actually received and updates body progress.
5. When the expected number of bytes has been received, the body is marked complete and the request is dispatched to its supported handler.

## Alternative outcomes

- A declared body larger than the configured limit is rejected before allocating the requested amount.
- Premature disconnect creates an incomplete-body result and enters connection cleanup.
- Allocation failure releases existing request state and produces a controlled internal-error outcome.
- Unexpected extra input follows the documented request-reuse policy rather than being silently appended to the current body.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Expected body length, existing buffered bytes, subsequent network reads. |
| Entity | RequestBody {data, capacity, length, expected_length}. |
| Success | Exactly expected_length bytes associated with the request. |
| Limit | Config.max_body_bytes and checked capacity calculations. |

## Business rules

- Capacity growth is bounded and checked before allocation and copying.
- Received lengths, expected lengths, and capacities use consistent size-aware types.
- Body storage is owned by the current Request and released once.
- Processing never reads beyond bytes confirmed as received.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
