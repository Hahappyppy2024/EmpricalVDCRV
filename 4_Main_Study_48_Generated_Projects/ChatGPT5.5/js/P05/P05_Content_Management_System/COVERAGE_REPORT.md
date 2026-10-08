# P05 Functional Test and Coverage Report

## Verified result

- Use-case functional tests: **12 passed, 0 failed**
- Line coverage: **100.00%**
- Branch coverage: **88.50%**
- Function coverage: **100.00%**
- Required branch threshold: **85% — passed**

Run the same threshold-enforced measurement with:

```bash
npm run test:coverage
```

Coverage scope is `src/**`, excluding `src/server.js`. That excluded file only opens the configured database and starts the HTTP listener; `createApp()` and all business routes are included.

## Per-file coverage

| File | Lines | Branches | Functions |
| --- | ---: | ---: | ---: |
| `src/app.js` | 100.00% | 87.20% | 100.00% |
| `src/auth.js` | 100.00% | 84.62% | 100.00% |
| `src/database.js` | 100.00% | 100.00% | 100.00% |
| `src/errors.js` | 100.00% | 100.00% | 100.00% |
| `src/repository.js` | 100.00% | 100.00% | 100.00% |
| `src/security.js` | 100.00% | 100.00% | 100.00% |
| `src/seed.js` | 100.00% | 100.00% | 100.00% |

## Functional scope

Each specification use case has one dedicated file under `tests/functional/`. The suite exercises successful workflows and observable alternatives: validation errors, missing authentication, role denial, author ownership isolation, unknown resources, duplicate natural keys, stale versions, invalid workflow states, password reset reuse, escaped rich-text previews, media restrictions, content scheduling, comment moderation, compatible templates, capability allow lists, passive integration configuration, transactional import, bounded collections, local-only navigation, and redirects.

The full captured runner output is retained at `var/coverage-test.log` in the project package.
