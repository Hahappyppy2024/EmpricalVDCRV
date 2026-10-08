# P06 — Real-time Team Chat System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| CHAT-01 | Accounts | Visitor; member; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; GET /api/profile; PATCH /api/profile |
| CHAT-02 | Workspaces and channels | Member; workspace admin | POST /api/workspaces; GET /api/workspaces/{workspaceId}/channels; POST /api/workspaces/{workspaceId}/channels; PATCH /api/channels/{channelId} |
| CHAT-03 | Membership lifecycle | Member; workspace admin | POST /api/workspaces/{workspaceId}/invitations; POST /api/invitations/{token}/accept; PATCH /api/workspaces/{workspaceId}/members/{userId}; DELETE /api/workspaces/{workspaceId}/members/{userId} |
| CHAT-04 | Real-time messaging | Member | GET /api/channels/{channelId}/messages; POST /api/channels/{channelId}/messages; PATCH /api/messages/{messageId}; DELETE /api/messages/{messageId}; WS /ws/workspaces/{workspaceId} |
| CHAT-05 | Message history and search | Member | GET /api/channels/{channelId}/messages?before=&limit=; GET /api/search/messages?q=&channelId=&from=&to= |
| CHAT-06 | Direct messages | Member | POST /api/direct-threads; GET /api/direct-threads; GET /api/direct-threads/{threadId}/messages; POST /api/direct-threads/{threadId}/messages |
| CHAT-07 | Attachments | Member | POST /api/channels/{channelId}/attachments; GET /api/attachments/{attachmentId}/content; DELETE /api/attachments/{attachmentId} |
| CHAT-08 | Link previews | Member | POST /api/link-previews; GET /api/link-previews/{previewId} |
| CHAT-09 | Channel administration | Channel admin; workspace admin | POST /api/channels/{channelId}/archive; POST /api/channels/{channelId}/members; DELETE /api/channels/{channelId}/members/{userId} |
| CHAT-10 | Connection and delivery state | Member | POST /api/realtime/resume; POST /api/messages/{messageId}/delivered |
| CHAT-11 | Presence and read state | Member | PUT /api/presence; GET /api/workspaces/{workspaceId}/presence; PUT /api/channels/{channelId}/read-cursor |
| CHAT-12 | Moderation and audit | Workspace admin | POST /api/messages/{messageId}/reports; GET /api/admin/workspaces/{workspaceId}/reports; POST /api/admin/reports/{reportId}/resolve; GET /api/admin/workspaces/{workspaceId}/audit-events |
