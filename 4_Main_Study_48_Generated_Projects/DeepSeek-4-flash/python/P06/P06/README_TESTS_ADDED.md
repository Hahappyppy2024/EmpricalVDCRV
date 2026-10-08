# P06 Tests Added / Revised

Functional tests are unchanged. The security suite was replaced with a PowerShell CWE-type suite based on the 31 unique CWE types in `tests(20260820-110818).zip`, rewritten for P06's real `/api/chat/...` routes, `chat_session` cookie, and seed fixtures.

- Functional: 12 primary + 1 auxiliary (unchanged)
- Security: 31 primary CWE PS1 files + 1 seeded-login precondition
- Runner: `tests\exploit\run_all_tests.ps1`
