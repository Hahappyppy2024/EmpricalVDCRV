# P14 — Workflow Automation / Agentic Task Platform — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| AGENT-01 | Account access | User; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| AGENT-02 | Workflow creation | User | POST /api/workflows; GET /api/workflows/{workflowId}; PATCH /api/workflows/{workflowId}; POST /api/workflows/{workflowId}/versions |
| AGENT-03 | Tool catalog | User; admin | GET /api/tools; GET /api/tools/{toolId}; PATCH /api/admin/tools/{toolId} |
| AGENT-04 | Task execution | User | POST /api/workflows/{workflowId}/runs; GET /api/runs/{runId}; POST /api/runs/{runId}/cancel; SSE /api/runs/{runId}/events |
| AGENT-05 | Scheduled runs | User | GET /api/workflows/{workflowId}/schedules; POST /api/workflows/{workflowId}/schedules; PATCH /api/schedules/{scheduleId}; DELETE /api/schedules/{scheduleId} |
| AGENT-06 | Workspace files | User | GET /api/workspaces/{workspaceId}/files; POST /api/workspaces/{workspaceId}/files; GET /api/workspace-files/{fileId}/content; DELETE /api/workspace-files/{fileId} |
| AGENT-07 | Webhook triggers | User | POST /api/workflows/{workflowId}/webhook-triggers; POST /api/webhook-triggers/{triggerId}/rotate-token; PATCH /api/webhook-triggers/{triggerId}; GET /api/webhook-triggers/{triggerId}/deliveries |
| AGENT-08 | External HTTP action | User | POST /api/http-actions/validate; PUT /api/workflows/{workflowId}/nodes/{nodeId}/http-action |
| AGENT-09 | Secrets manager | User; admin | GET /api/workspaces/{workspaceId}/secrets; POST /api/workspaces/{workspaceId}/secrets; POST /api/secrets/{secretId}/rotate; DELETE /api/secrets/{secretId} |
| AGENT-10 | Run logs and replay | User; admin | GET /api/runs/{runId}/logs; POST /api/runs/{runId}/replay |
| AGENT-11 | Sharing and templates | User; admin | POST /api/workflows/{workflowId}/shares; DELETE /api/workflow-shares/{shareId}; GET /api/workflow-templates; POST /api/admin/workflow-templates |
| AGENT-12 | Admin governance | Admin | GET /api/admin/settings; PATCH /api/admin/settings; GET /api/admin/users; PATCH /api/admin/users/{userId}; GET /api/admin/audit-events |
