# P10 Functional Test and Coverage Report

## Verified result

- Use-case functional tests: **12 passed, 0 failed**
- Line coverage: **100.00%**
- Branch coverage: **86.80%**
- Function coverage: **98.06%**
- Required branch threshold: **85% — passed**

Run the threshold-enforced measurement with:

```bash
npm run test:coverage
```

Coverage includes all `src/**` files except `src/server.js`. The excluded file only opens the configured database and starts the listener; the complete Express application, local model behavior, SSE handler, retrieval logic, and business rules remain included.

## Per-file coverage

| File | Lines | Branches | Functions |
| --- | ---: | ---: | ---: |
| `src/app.js` | 100.00% | 84.39% | 98.51% |
| `src/auth.js` | 100.00% | 84.62% | 100.00% |
| `src/database.js` | 100.00% | 100.00% | 100.00% |
| `src/errors.js` | 100.00% | 100.00% | 100.00% |
| `src/repository.js` | 100.00% | 100.00% | 92.31% |
| `src/security.js` | 100.00% | 100.00% | 100.00% |
| `src/seed.js` | 100.00% | 100.00% | 100.00% |

## Tested functional branches

The suite covers successful workflows and alternatives including invalid/disabled accounts, single-use password recovery, owner isolation, archive/delete state rules, private/global templates, disabled models, bounded temperatures and tokens, invalid/duplicate files, ingestion ownership, empty retrieval results, collection attachment/detachment, disabled and absent conversation tools, masked/rotated credentials, blocked and duplicate messages, persistent citations, JSON run status, SSE event frames, completed/running cancellation states, expired/revoked shares, scoped usage, bounded date ranges, settings allow lists, moderation actions, and repeated resolution.

The captured runner output is retained at `var/coverage-test.log` in the project package.
