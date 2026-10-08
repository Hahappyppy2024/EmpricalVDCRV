# P10 — AI Assistant / LLM WebUI — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| AI-01 | Account access | Visitor; user; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| AI-02 | Conversation management | User | POST /api/conversations; GET /api/conversations; GET /api/conversations/{conversationId}; PATCH /api/conversations/{conversationId}; POST /api/conversations/{conversationId}/archive; DELETE /api/conversations/{conversationId} |
| AI-03 | Prompt templates | User; admin | GET /api/prompt-templates; POST /api/prompt-templates; PATCH /api/prompt-templates/{templateId}; DELETE /api/prompt-templates/{templateId} |
| AI-04 | Model configuration | User; admin | GET /api/models; GET /api/profile/model-settings; PATCH /api/profile/model-settings; PATCH /api/admin/models/{modelId} |
| AI-05 | Knowledge file upload | User | POST /api/knowledge-collections/{collectionId}/files; GET /api/knowledge-files/{fileId}; DELETE /api/knowledge-files/{fileId} |
| AI-06 | Retrieval collections | User | POST /api/knowledge-collections; GET /api/knowledge-collections; GET /api/knowledge-files/{fileId}/ingestion; POST /api/knowledge-collections/{collectionId}/search; PUT /api/conversations/{conversationId}/knowledge-collection |
| AI-07 | Tool registry | User; admin | GET /api/tools; PUT /api/conversations/{conversationId}/tools/{toolId}; DELETE /api/conversations/{conversationId}/tools/{toolId}; PATCH /api/admin/tools/{toolId} |
| AI-08 | API key management | User | GET /api/provider-credentials; POST /api/provider-credentials; POST /api/provider-credentials/{credentialId}/rotate; DELETE /api/provider-credentials/{credentialId} |
| AI-09 | Chat execution | User | POST /api/conversations/{conversationId}/messages; GET /api/chat-runs/{runId}; POST /api/chat-runs/{runId}/cancel; SSE /api/chat-runs/{runId}/events |
| AI-10 | Conversation sharing | User | POST /api/conversations/{conversationId}/shares; DELETE /api/conversation-shares/{shareId}; GET /api/shared/conversations/{token} |
| AI-11 | Usage and audit logs | User; admin | GET /api/usage?from=&to=; GET /api/admin/usage?from=&to=; GET /api/admin/audit-events |
| AI-12 | Moderation and settings | Admin | GET /api/admin/settings; PATCH /api/admin/settings; GET /api/admin/moderation-cases; POST /api/admin/moderation-cases/{caseId}/resolve |
