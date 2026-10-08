# P16 — Server Monitoring & Job Control Panel — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| SYS-01 | Account access | Operator; admin | POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| SYS-02 | Server dashboard | Operator | GET /api/servers; GET /api/servers/{serverId}; GET /api/servers/{serverId}/metrics?from=&to=&interval= |
| SYS-03 | Log viewer | Operator | GET /api/servers/{serverId}/log-sources; GET /api/servers/{serverId}/logs?sourceId=&level=&q=&before=&limit= |
| SYS-04 | Service control | Operator; admin | GET /api/servers/{serverId}/services; GET /api/services/{serviceId}; POST /api/services/{serviceId}/actions |
| SYS-05 | Job scheduler | Operator; admin | GET /api/servers/{serverId}/jobs; POST /api/servers/{serverId}/jobs; PATCH /api/jobs/{jobId}; POST /api/jobs/{jobId}/pause; POST /api/jobs/{jobId}/resume; DELETE /api/jobs/{jobId} |
| SYS-06 | Job execution history | Operator | GET /api/jobs/{jobId}/runs; GET /api/job-runs/{runId}; POST /api/jobs/{jobId}/runs |
| SYS-07 | Backup manager | Operator; admin | GET /api/servers/{serverId}/backups; POST /api/servers/{serverId}/backups; GET /api/backups/{backupId}/content; POST /api/backups/{backupId}/restore |
| SYS-08 | Configuration editor | Admin | GET /api/servers/{serverId}/configuration; PATCH /api/servers/{serverId}/configuration |
| SYS-09 | Alert center | Operator | GET /api/alerts?serverId=&severity=&status=&page=; POST /api/alerts/{alertId}/acknowledge; POST /api/alerts/{alertId}/resolve |
| SYS-10 | Health-check targets | Operator; admin | GET /api/health-check-targets; POST /api/health-check-targets; PATCH /api/health-check-targets/{targetId}; DELETE /api/health-check-targets/{targetId}; GET /api/health-check-targets/{targetId}/results |
| SYS-11 | API token manager | Admin | GET /api/admin/api-tokens; POST /api/admin/api-tokens; POST /api/admin/api-tokens/{tokenId}/rotate; DELETE /api/admin/api-tokens/{tokenId} |
| SYS-12 | Audit logs and administration | Admin | GET /api/admin/audit-events; GET /api/admin/users; PATCH /api/admin/users/{userId}/roles; POST /api/admin/servers; PATCH /api/admin/servers/{serverId} |
