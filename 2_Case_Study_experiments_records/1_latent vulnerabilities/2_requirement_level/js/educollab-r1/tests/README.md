# Generated API tests for educollab-r1

## Files

- `tests/api.functional.test.js`: baseline API functional coverage
- `tests/api.exploit.test.js`: exploit-oriented tests that demonstrate the current authorization flaw
- `tests/_helper.js`: starts the app on a random port using an isolated temp copy

## How to use

Copy the `tests` folder into the root of `educollab-r1`, then run:

```bash
node --test tests/api.functional.test.js
node --test tests/api.exploit.test.js
```

Or run both:

```bash
node --test tests/*.test.js
```

## Expected behavior

- `api.functional.test.js` should pass on the current codebase.
- `api.exploit.test.js` is an exploit test suite, so it also passes on the current vulnerable code because the unauthorized actions succeed.
- After the authorization bug is fixed, the exploit suite should be rewritten into regression tests that expect `403` instead of `200`.
