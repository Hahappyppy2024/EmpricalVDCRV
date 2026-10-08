# P08 Functional Test and Coverage Report

## Verified result

- Use-case functional tests: **12 passed, 0 failed**
- Line coverage: **100.00%**
- Branch coverage: **86.38%**
- Function coverage: **99.01%**
- Required branch threshold: **85% — passed**

Reproduce the threshold-enforced run with:

```bash
npm run test:coverage
```

Coverage includes all files under `src/**` except `src/server.js`. The excluded file is only the process bootstrap that opens the configured database and starts listening; the Express application factory and all business routes remain included.

## Per-file coverage

| File | Lines | Branches | Functions |
| --- | ---: | ---: | ---: |
| `src/app.js` | 100.00% | 83.96% | 98.57% |
| `src/auth.js` | 100.00% | 84.85% | 100.00% |
| `src/database.js` | 100.00% | 100.00% | 100.00% |
| `src/errors.js` | 100.00% | 100.00% | 100.00% |
| `src/repository.js` | 100.00% | 100.00% | 100.00% |
| `src/security.js` | 100.00% | 100.00% | 100.00% |
| `src/seed.js` | 100.00% | 100.00% | 100.00% |

## Tested functional branches

The 12 files exercise success behavior and observable alternatives including invalid credentials, single-use reset tokens, employee ownership isolation, unsupported currencies, stale versions, non-editable states, receipt restrictions, empty reports, missing required receipts, policy limits, wrong manager assignment, required rejection/return comments, finance state checks, duplicate policies, comment idempotency, self/reporting-line/finance visibility, empty and repeated reimbursement actions, admin self-role protection, currency transaction rollback, role-scoped dashboard counts, and notification ownership/idempotent reads.

The full captured test output is retained at `var/coverage-test.log` in the project package.
