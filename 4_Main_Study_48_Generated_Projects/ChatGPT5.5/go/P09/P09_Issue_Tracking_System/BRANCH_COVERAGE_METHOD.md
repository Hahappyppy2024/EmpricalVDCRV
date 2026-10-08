# Coverage Method

This project reports two distinct measurements and does not rename one metric as another.

## 1. Line/statement coverage

Go's native coverage instrumentation is used for executable source in `internal/app`:

```bash
go test ./tests/function -count=1 -coverpkg=./internal/app -covermode=atomic -coverprofile=coverage/line.out
go tool cover -func=coverage/line.out
go tool cover -html=coverage/line.out -o coverage/line.html
```

The resulting percentage is the native Go statement coverage value. `coverage/line.html` provides line-level visualization.

## 2. Instrumented branch-outcome coverage

`tools/branchcov` creates a temporary copy of the project and instruments branch outcomes in `internal/app` before running the unchanged functional test suite.

The instrumented branch inventory includes:

- `if`: true and false outcomes;
- conditional `for`: true (enter/continue) and false (condition rejected) outcomes;
- `switch`: each explicit case and default case;
- type switch: each explicit case and default case;
- `select`: each communication case and default case.

Each outcome receives a unique ID. A branch is covered only when the functional-test process actually executes the injected counter for that outcome. The instrumented copy is temporary; the application source in the working tree is not rewritten.

The JSON result is written to `coverage/branch.json`, including every branch ID, file, line, branch kind, outcome, and covered/uncovered status.

### Scope note

This custom Go metric is an explicit branch-outcome metric. It is not claimed to be byte-for-byte identical to Node/V8's branch accounting used by P10. In particular, short-circuit boolean operands (`&&` and `||`) are not separately counted as branch points by this tool. For empirical comparison, use this exact tool/version consistently across all Go projects and report the metric definition.

## 3. Threshold behavior

The default required branch threshold is 85%:

```bash
./scripts/coverage.sh
```

or on Windows PowerShell:

```powershell
.\scripts\coverage.ps1
```

The threshold is a gate only. It never modifies the measured value. For example, a measured 82.40% is reported as 82.40% and the command fails.

To change the experimental threshold without changing source code:

```bash
BRANCH_THRESHOLD=90 ./scripts/coverage.sh
```

```powershell
$env:BRANCH_THRESHOLD="90"
.\scripts\coverage.ps1
```
