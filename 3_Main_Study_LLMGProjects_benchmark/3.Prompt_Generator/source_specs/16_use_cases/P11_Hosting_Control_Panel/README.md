# P11 — Hosting Control Panel — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| HOST-01 | Account access | Customer; operator; admin | POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| HOST-02 | Domain management | Customer | GET /api/domains; POST /api/domains; PATCH /api/domains/{domainId}; GET /api/domains/{domainId}/dns-records; POST /api/domains/{domainId}/dns-records; DELETE /api/dns-records/{recordId} |
| HOST-03 | Site management | Customer | GET /api/sites; POST /api/sites; PATCH /api/sites/{siteId}; POST /api/sites/{siteId}/deploy; POST /api/sites/{siteId}/suspend |
| HOST-04 | File manager | Customer | GET /api/sites/{siteId}/files?path=; POST /api/sites/{siteId}/files; GET /api/sites/{siteId}/files/content?path=; POST /api/sites/{siteId}/files/move; DELETE /api/sites/{siteId}/files?path= |
| HOST-05 | Database management | Customer | GET /api/sites/{siteId}/databases; POST /api/sites/{siteId}/databases; POST /api/databases/{databaseId}/users; DELETE /api/databases/{databaseId} |
| HOST-06 | Backup and restore | Customer | GET /api/sites/{siteId}/backups; POST /api/sites/{siteId}/backups; GET /api/backups/{backupId}/content; POST /api/backups/{backupId}/restore |
| HOST-07 | TLS certificate management | Customer | GET /api/domains/{domainId}/certificates; POST /api/domains/{domainId}/certificates; POST /api/certificates/{certificateId}/renew; DELETE /api/certificates/{certificateId} |
| HOST-08 | Scheduled tasks | Customer | GET /api/sites/{siteId}/scheduled-tasks; POST /api/sites/{siteId}/scheduled-tasks; PATCH /api/scheduled-tasks/{taskId}; DELETE /api/scheduled-tasks/{taskId} |
| HOST-09 | Resource usage | Customer; operator | GET /api/sites/{siteId}/metrics?from=&to=&interval= |
| HOST-10 | Support tickets | Customer; operator | GET /api/support-tickets; POST /api/support-tickets; GET /api/support-tickets/{ticketId}; POST /api/support-tickets/{ticketId}/replies; POST /api/support-tickets/{ticketId}/close |
| HOST-11 | Audit logs | Customer; admin | GET /api/sites/{siteId}/audit-events; GET /api/admin/audit-events |
| HOST-12 | Admin operations | Admin | GET /api/admin/accounts; PATCH /api/admin/accounts/{accountId}; GET /api/admin/plans; POST /api/admin/plans; PATCH /api/admin/maintenance |
