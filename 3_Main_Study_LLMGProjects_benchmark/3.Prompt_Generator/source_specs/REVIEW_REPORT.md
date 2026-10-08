# Use-case rationality review

## Decision

The original 16-project set has good domain breadth, but the API layer was not ready for project generation. The same generic CRUD template was applied to unrelated workflows, producing non-resource endpoints such as `POST /api/ai/chat_execution` and nonsensical update routes such as `PATCH /api/lms/error_responses/{id}`. The revised package keeps the projects and their intended scope, but replaces the interface contracts and several pseudo-use-cases.

## Main corrections

1. Resource-oriented routes replace title-oriented routes. Reads, creates, updates, state transitions, downloads, exports, real-time events, and asynchronous jobs now use different contracts.
2. “Frontend API integration” and “Error responses” are cross-cutting implementation requirements, not standalone business use cases. They were replaced with missing domain functions such as dashboards/notifications, policies, wishlists, presence, navigation, and mailbox settings.
3. Actor scopes are explicit. A broad role label no longer implies access to every record of the same type.
4. Workflow states are server-controlled. Submission, review, booking, order, publishing, approval, healthcare, and job-control transitions now have explicit action endpoints.
5. File download/upload, exports, credentials, WebSocket/SSE, link previews, outbound HTTP, and background ingestion are modeled separately instead of being hidden behind generic CRUD.
6. The package explicitly forbids generation of any tests. The use cases specify observable behavior only; validation artifacts belong to a later, separate phase.

## Important methodological warning

These revised use cases are suitable as Version A functional specifications, but they should not be presented as evidence that every category in a later vulnerability ground truth is naturally represented. Functional completeness and vulnerability coverage are different design layers. Version B should map selected operations to ground-truth conditions without changing the visible Version A requirements.
