# P09 — Issue Tracking System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| ISSUE-01 | Account access | Visitor; member; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| ISSUE-02 | Project management | Project owner; admin | POST /api/projects; PATCH /api/projects/{projectId}; POST /api/projects/{projectId}/members; PATCH /api/projects/{projectId}/members/{userId}; DELETE /api/projects/{projectId}/members/{userId} |
| ISSUE-03 | Issue creation | Project member | POST /api/projects/{projectId}/issues; GET /api/issues/{issueId}; PATCH /api/issues/{issueId} |
| ISSUE-04 | Issue search | Project member | GET /api/issues?q=&projectId=&status=&assigneeId=&label=&page= |
| ISSUE-05 | Comments | Project member | GET /api/issues/{issueId}/comments; POST /api/issues/{issueId}/comments; PATCH /api/comments/{commentId}; DELETE /api/comments/{commentId} |
| ISSUE-06 | Assignment and workflow | Project member; project owner | PUT /api/issues/{issueId}/assignee; POST /api/issues/{issueId}/transitions |
| ISSUE-07 | Attachments | Project member | POST /api/issues/{issueId}/attachments; GET /api/attachments/{attachmentId}/content; DELETE /api/attachments/{attachmentId} |
| ISSUE-08 | Private projects | Project owner; member | POST /api/projects; GET /api/projects; GET /api/projects/{projectId} |
| ISSUE-09 | Webhooks | Project owner | GET /api/projects/{projectId}/webhooks; POST /api/projects/{projectId}/webhooks; POST /api/webhooks/{webhookId}/rotate-secret; GET /api/webhooks/{webhookId}/deliveries |
| ISSUE-10 | Import and export | Project owner | POST /api/projects/{projectId}/imports; GET /api/projects/{projectId}/issues.csv |
| ISSUE-11 | Admin operations | Admin | GET /api/admin/users; PATCH /api/admin/users/{userId}; GET /api/admin/audit-events; POST /api/admin/labels |
| ISSUE-12 | Watchers and notifications | Project member | POST /api/issues/{issueId}/watchers/me; DELETE /api/issues/{issueId}/watchers/me; GET /api/notifications; POST /api/notifications/{notificationId}/read |
