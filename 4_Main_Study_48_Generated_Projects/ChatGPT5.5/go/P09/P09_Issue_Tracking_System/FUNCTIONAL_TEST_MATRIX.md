# Functional Test Matrix

`helpers_test.go` only provides setup; exactly 12 files declare use-case tests.

| Use case | Test file | Main behavior |
|---|---|---|
| ISSUE-01 Account access | `tests/function/issue01_account_access_test.go` | register, login, logout, reset request, invalid/duplicate input |
| ISSUE-02 Project management | `tests/function/issue02_project_management_test.go` | create/update project and member lifecycle |
| ISSUE-03 Issue creation | `tests/function/issue03_issue_creation_test.go` | create/read/update, validation, stale version, outsider denial |
| ISSUE-04 Issue search | `tests/function/issue04_issue_search_test.go` | text/structured filters, pagination, authentication |
| ISSUE-05 Comments | `tests/function/issue05_comments_test.go` | list/create/update/delete and author boundary |
| ISSUE-06 Assignment and workflow | `tests/function/issue06_assignment_workflow_test.go` | assignee membership and valid/invalid transitions |
| ISSUE-07 Attachments | `tests/function/issue07_attachments_test.go` | multipart upload, content retrieval, deletion |
| ISSUE-08 Private projects | `tests/function/issue08_private_projects_test.go` | member visibility and outsider isolation |
| ISSUE-09 Webhooks | `tests/function/issue09_webhooks_test.go` | create/list/rotate/deliveries and owner scope |
| ISSUE-10 Import/export | `tests/function/issue10_import_export_test.go` | transactional import, CSV export, validation and role scope |
| ISSUE-11 Admin operations | `tests/function/issue11_admin_operations_test.go` | users, labels, audit and non-admin denial |
| ISSUE-12 Frontend API integration and errors | `tests/function/issue12_frontend_api_integration_errors_test.go` | page/API data state, deterministic error, unauthenticated state |
