# P13 — Mail Server Admin Console — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| MAIL-01 | Account access | Mailbox user; domain admin; platform admin | POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| MAIL-02 | Mailbox overview | Mailbox user | GET /api/mail/folders; GET /api/mail/messages?folder=&q=&page= |
| MAIL-03 | Message compose | Mailbox user | POST /api/mail/drafts; PATCH /api/mail/drafts/{draftId}; POST /api/mail/drafts/{draftId}/send; DELETE /api/mail/drafts/{draftId} |
| MAIL-04 | Message reading | Mailbox user | GET /api/mail/messages/{messageId}; PATCH /api/mail/messages/{messageId}; POST /api/mail/messages/{messageId}/move; DELETE /api/mail/messages/{messageId} |
| MAIL-05 | Attachment handling | Mailbox user | POST /api/mail/drafts/{draftId}/attachments; GET /api/mail/attachments/{attachmentId}/content; DELETE /api/mail/drafts/{draftId}/attachments/{attachmentId} |
| MAIL-06 | Contact management | Mailbox user | GET /api/contacts?q=; POST /api/contacts; PATCH /api/contacts/{contactId}; DELETE /api/contacts/{contactId} |
| MAIL-07 | Filters and rules | Mailbox user | GET /api/mail/rules; POST /api/mail/rules; PATCH /api/mail/rules/{ruleId}; DELETE /api/mail/rules/{ruleId}; POST /api/mail/rules/reorder |
| MAIL-08 | Domain management | Domain admin | GET /api/admin/domains; POST /api/admin/domains; POST /api/admin/domains/{domainId}/aliases; PATCH /api/admin/mailboxes/{mailboxId}/quota |
| MAIL-09 | Quarantine | Domain admin; platform admin | GET /api/admin/quarantine?domainId=&reason=&page=; POST /api/admin/quarantine/{itemId}/release; DELETE /api/admin/quarantine/{itemId} |
| MAIL-10 | Admin audit logs | Domain admin; platform admin | GET /api/admin/audit-events?domainId=&actorId=&from=&to= |
| MAIL-11 | Import and export | Mailbox user; domain admin | POST /api/contacts/imports; GET /api/contacts.csv; GET /api/admin/domains/{domainId}/mailboxes.csv |
| MAIL-12 | Mailbox settings | Mailbox user | GET /api/mail/settings; PATCH /api/mail/settings |
