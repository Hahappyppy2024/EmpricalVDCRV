# P05 — Content Management System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| CMS-01 | Account access | Visitor; author; editor; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| CMS-02 | Content authoring | Author; editor | POST /api/content; GET /api/content/{contentId}; PATCH /api/content/{contentId}; DELETE /api/content/{contentId} |
| CMS-03 | Rich-text editing | Author; editor | PUT /api/content/{contentId}/body; POST /api/content/{contentId}/preview |
| CMS-04 | Media library | Author; editor | GET /api/media; POST /api/media; PATCH /api/media/{mediaId}; GET /api/media/{mediaId}/content; DELETE /api/media/{mediaId} |
| CMS-05 | Publishing workflow | Author; editor | POST /api/content/{contentId}/submit; POST /api/content/{contentId}/approve; POST /api/content/{contentId}/reject; POST /api/content/{contentId}/schedule; POST /api/content/{contentId}/unpublish |
| CMS-06 | Public site | Visitor | GET /api/public/content?type=&tag=&category=&page=; GET /api/public/content/{slug} |
| CMS-07 | Comments | Visitor; moderator | GET /api/public/content/{contentId}/comments; POST /api/public/content/{contentId}/comments; POST /api/admin/comments/{commentId}/moderate |
| CMS-08 | Page templates | Editor; admin | GET /api/templates; POST /api/templates; PATCH /api/templates/{templateId}; PUT /api/content/{contentId}/template |
| CMS-09 | User and role management | Admin | GET /api/admin/users; PATCH /api/admin/users/{userId}/roles; GET /api/admin/roles; PATCH /api/admin/roles/{roleId} |
| CMS-10 | Site settings | Admin | GET /api/admin/settings; PATCH /api/admin/settings; GET /api/admin/integrations; PATCH /api/admin/integrations/{integrationId} |
| CMS-11 | Import and export | Editor; admin | POST /api/content-imports; GET /api/content-exports?type=&format=json |
| CMS-12 | Navigation and redirects | Editor; admin | GET /api/navigation; PUT /api/navigation; GET /api/admin/redirects; POST /api/admin/redirects; DELETE /api/admin/redirects/{redirectId} |
