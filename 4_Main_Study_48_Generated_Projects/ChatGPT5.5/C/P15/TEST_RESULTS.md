# Verified test and coverage results

Environment: GCC 13.3.0 on Linux x86-64. Commands were executed against the real `netd` process and SQLite runtime library; no service mock was used.

| Measurement | Verified result |
|---|---:|
| Strict C17 build | PASS |
| Functional tests | 32/32 PASS |
| Use-case files represented | 12/12 |
| Line coverage | 211/213 = 99.06% |
| Raw GCC branch/edge coverage | 479/618 = 77.51% |
| Requested raw branch threshold | **NOT MET** (target 85%) |

The branch threshold is deliberately reported as not met. GCC counts short-circuit subexpressions plus SQLite, socket, process, allocation, and thread failure edges. The functional suite reaches all use-case success/error outcomes and nearly every source line, but it does not use compile-time fault injection to fabricate rare library/OS failures. Treating those edges as covered or silently excluding them would overstate the evidence.

Reproduce with:

```bash
make coverage
```

The command exits nonzero while raw branch coverage remains below 85%, so the unmet threshold cannot be overlooked in automation.

## Security-suite addition

The project now includes 24 two-layer PowerShell CWE files under `tests/exploit`: 19 executable scenarios and 5 explicit `NOT_APPLICABLE` determinations. Equivalent oracle requests were replayed against the real daemon in the generation environment; 18/18 checked secure/candidate observations matched the intended oracle. PowerShell 7 was unavailable in that environment, so the `.ps1` runner itself must be replayed under Linux/WSL `pwsh` before its JSON is used as experimental evidence. See `tests/exploit/VALIDATION_STATUS.md`.
