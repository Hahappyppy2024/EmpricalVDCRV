# P08 — Enterprise Expense Approval System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| EXP-01 | Account access | Employee; manager; finance; admin | POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| EXP-02 | Expense report creation | Employee | POST /api/expense-reports; PATCH /api/expense-reports/{reportId}; POST /api/expense-reports/{reportId}/items; PATCH /api/expense-items/{itemId}; DELETE /api/expense-items/{itemId} |
| EXP-03 | Receipt upload | Employee | POST /api/expense-items/{itemId}/receipts; GET /api/receipts/{receiptId}/content; DELETE /api/receipts/{receiptId} |
| EXP-04 | Report submission | Employee | POST /api/expense-reports/{reportId}/submit |
| EXP-05 | Manager approval | Manager | GET /api/manager/expense-reports?status=pending; POST /api/manager/expense-reports/{reportId}/approve; POST /api/manager/expense-reports/{reportId}/reject; POST /api/manager/expense-reports/{reportId}/return |
| EXP-06 | Finance review | Finance | GET /api/finance/expense-reports?status=approved; POST /api/finance/expense-reports/{reportId}/verify; POST /api/finance/expense-reports/{reportId}/return |
| EXP-07 | Policy rules | Finance; admin | GET /api/admin/expense-policies; POST /api/admin/expense-policies; PATCH /api/admin/expense-policies/{policyId} |
| EXP-08 | Comments and activity | Employee; manager; finance | GET /api/expense-reports/{reportId}/activity; POST /api/expense-reports/{reportId}/comments |
| EXP-09 | Employee data access | Employee; manager; finance | GET /api/expense-reports; GET /api/expense-reports/{reportId}; GET /api/manager/employees/{employeeId}/expense-reports |
| EXP-10 | Reimbursement export | Finance | POST /api/finance/reimbursement-batches; GET /api/finance/reimbursement-batches/{batchId}.csv; POST /api/finance/reimbursement-batches/{batchId}/processed |
| EXP-11 | Admin configuration | Admin | GET /api/admin/departments; POST /api/admin/departments; PATCH /api/admin/users/{userId}/manager; PATCH /api/admin/users/{userId}/roles; PATCH /api/admin/currencies |
| EXP-12 | Dashboard and notifications | Employee; manager; finance | GET /api/dashboard; GET /api/notifications; POST /api/notifications/{notificationId}/read |
