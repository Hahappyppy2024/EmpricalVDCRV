# Functional Test and Coverage Report

## Result

- Test suites/use cases: **12 passed, 0 failed**
- Line coverage: **100.00%**
- Branch coverage: **90.48%**
- Function coverage: **97.85%**
- Required branch threshold: **85% — passed**

Measured with Node.js built-in test coverage using:

```bash
npm run test:coverage
```

Scope: `src/**`, excluding `src/server.js`, which is the process-listening bootstrap. The test harness starts the real Express application on an ephemeral TCP port and uses a fresh seeded SQLite database for every use-case file.

## Per-file coverage

| File | Lines | Branches | Functions |
| --- | ---: | ---: | ---: |
| `src/app.js` | 100.00% | 88.67% | 98.53% |
| `src/auth.js` | 100.00% | 93.33% | 100.00% |
| `src/database.js` | 100.00% | 100.00% | 100.00% |
| `src/errors.js` | 100.00% | 100.00% | 100.00% |
| `src/repository.js` | 100.00% | 100.00% | 100.00% |
| `src/security.js` | 100.00% | 100.00% | 100.00% |
| `src/seed.js` | 100.00% | 100.00% | 50.00% |

## Functional coverage

Every specification use case has a dedicated functional test file under `tests/functional/`. Tests cover successful workflows and observable alternatives including validation failures, unauthenticated/unauthorized access, ownership isolation, missing resources, stale versions, invalid state transitions, idempotency, payment rejection, room conflicts, cancellation fees, zero-incidentals checkout, moderation, bounded reports, and overlapping rate plans.

The captured runner output is retained at `var/coverage-test.log` in the project package.
