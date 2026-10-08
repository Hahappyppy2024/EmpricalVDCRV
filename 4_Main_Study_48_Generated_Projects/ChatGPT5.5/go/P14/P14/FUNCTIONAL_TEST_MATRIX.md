# Functional Test Matrix

| Use case | Test file | Acceptance behavior |
|---|---|---|
| AGENT-01 Account access | `tests/function/agent01_account_access_test.go` | registration/dashboard, invalid credentials, logout invalidation |
| AGENT-02 Workflow creation | `tests/function/agent02_workflow_creation_test.go` | valid definition, rejected trigger without persistence, ownership |
| AGENT-03 Tool catalog | `tests/function/agent03_tool_catalog_test.go` | filters, bounded empty result, private/admin visibility |
| AGENT-04 Task execution | `tests/function/agent04_task_execution_test.go` | execution/logs, required workflow, cross-user denial |
| AGENT-05 Scheduled runs | `tests/function/agent05_scheduled_runs_test.go` | valid cron, invalid cron rollback, ownership |
| AGENT-06 Workspace files | `tests/function/agent06_workspace_files_test.go` | upload/download metadata, traversal rejection, private file isolation |
| AGENT-07 Webhook triggers | `tests/function/agent07_webhook_triggers_test.go` | create/public trigger, validation, ownership |
| AGENT-08 External HTTP action | `tests/function/agent08_external_http_action_test.go` | deterministic echo, scheme validation, ownership |
| AGENT-09 Secrets manager | `tests/function/agent09_secrets_manager_test.go` | masked response, empty secret rollback, ownership |
| AGENT-10 Run logs and replay | `tests/function/agent10_run_logs_replay_test.go` | known/empty filters, replay, private logs |
| AGENT-11 Sharing and templates | `tests/function/agent11_sharing_templates_test.go` | create/publish, invalid JSON rollback, cross-user detail |
| AGENT-12 Admin governance | `tests/function/agent12_admin_governance_test.go` | setting/user update, invalid role, non-admin denial |
