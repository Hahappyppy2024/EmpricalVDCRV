# P09 Functional Test and Coverage Report

## Measurement command

Linux/macOS/Git Bash:

```bash
./scripts/coverage.sh
```

Windows PowerShell:

```powershell
.\scripts\coverage.ps1
```

## Metrics produced

- Functional test execution: `go test ./tests/function -count=1`
- Native Go line/statement coverage for `internal/app`
- HTML line report: `coverage/line.html`
- Instrumented branch-outcome coverage for `if`, conditional `for`, `switch`, type-switch, and `select`
- Branch details: `coverage/branch.json`
- Default branch threshold: 85%

## Current packaged-result status

No numeric percentage is pre-filled in this report because the packaging sandbox does not have the required Go 1.24 dependency/toolchain environment available to execute the project. Run the command above in the target Go 1.24 environment; the scripts report the measured values and fail if the measured branch percentage is below the configured threshold.

The threshold does not alter measurement. A result such as 82.40% remains 82.40% and is reported as FAIL.

See `BRANCH_COVERAGE_METHOD.md` for the metric definition.
