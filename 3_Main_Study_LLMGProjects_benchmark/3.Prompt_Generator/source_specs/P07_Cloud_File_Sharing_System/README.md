# P07 — Cloud File Sharing System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| FILE-01 | Account access | Visitor; user; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| FILE-02 | File upload | User | POST /api/folders/{folderId}/files |
| FILE-03 | Folder management | User | GET /api/folders/{folderId}/children; POST /api/folders; PATCH /api/folders/{folderId}; POST /api/folders/{folderId}/move; DELETE /api/folders/{folderId} |
| FILE-04 | Download and preview | User; share recipient | GET /api/files/{fileId}/content; GET /api/files/{fileId}/preview; GET /api/files/{fileId}/versions/{versionId}/content |
| FILE-05 | Sharing links | User | POST /api/files/{fileId}/shares; POST /api/folders/{folderId}/shares; DELETE /api/shares/{shareId}; GET /api/shares/{token} |
| FILE-06 | Team spaces | User; team admin | POST /api/team-spaces; GET /api/team-spaces; POST /api/team-spaces/{spaceId}/members; PATCH /api/team-spaces/{spaceId}/members/{userId}; DELETE /api/team-spaces/{spaceId}/members/{userId} |
| FILE-07 | Search | User | GET /api/search/files?q=&type=&owner=&modifiedAfter=&page= |
| FILE-08 | Version history | User | GET /api/files/{fileId}/versions; POST /api/files/{fileId}/versions; POST /api/files/{fileId}/versions/{versionId}/restore |
| FILE-09 | Trash and restore | User | GET /api/trash; POST /api/files/{fileId}/trash; POST /api/folders/{folderId}/trash; POST /api/trash/{itemId}/restore; DELETE /api/trash/{itemId} |
| FILE-10 | Storage quota | User; admin | GET /api/storage/quota; GET /api/admin/users/{userId}/quota; PATCH /api/admin/users/{userId}/quota |
| FILE-11 | Audit log and exports | Team admin; admin | GET /api/team-spaces/{spaceId}/audit-events; GET /api/team-spaces/{spaceId}/audit-events.csv |
| FILE-12 | Admin console | Admin | GET /api/admin/users; PATCH /api/admin/users/{userId}; PATCH /api/admin/retention-settings |
