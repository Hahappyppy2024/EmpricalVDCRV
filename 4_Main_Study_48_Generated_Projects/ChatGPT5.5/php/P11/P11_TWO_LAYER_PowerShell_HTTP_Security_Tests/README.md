# P11 TWO-LAYER PowerShell HTTP Security Test Suite

This suite is grounded in the supplied **P11 Hosting Control Panel** source code and uses the same two-layer research design as P01/P02.

## Layers

- `Core` — cross-project Web-security scenarios.
- `P11_Specific` — Hosting Control Panel scenarios.

Folders remain organized by security type:

```text
tests/
├── A00_Precondition/
├── A01_Access_Control/
├── A02_Cryptographic_Failures/
├── A03_Injection/
├── A04_Insecure_Design/
├── A05_Misconfiguration/
├── A06_Vulnerable_Outdated_Components/
├── A07_Authentication_Failures/
├── A08_Data_Integrity/
├── A09_Logging/
└── A10_SSRF/
```

## Grounded fixtures

P11 runs at:

```text
http://localhost:8080
```

Session cookie:

```text
hosting_session
```

All seed accounts use:

```text
Password123!
```

Actors:

```text
alice@example.test       customer / active
bob@example.test         customer / active
operator@example.test    operator / active
admin@example.test       admin / active
disabled@example.test    customer / disabled
```

Alice owns account/domain/site ID 1. Bob owns account/domain/site ID 2.

## Start and run

From the project root:

```powershell
composer db:reset
composer start
```

Keep that server terminal running. In another PowerShell:

```powershell
cd tests\exploit
.\run_all_tests.ps1
```

Reports are written to:

```text
reports\latest.json
reports\security_results_YYYYMMDD_HHMMSS.json
```

The summary is layer-aware:

```text
Core         : PASS=x FAIL=x ERROR=x
P11_Specific : PASS=x FAIL=x ERROR=x
TOTAL        : PASS=x FAIL=x ERROR=x
```

Run `composer db:reset` before each clean repetition. Some P11-specific state-machine/logging tests create disposable sites or tickets.

## Important project-specific oracles

### CWE-22

The site file manager accepts virtual paths. The tests verify that `..` cannot escape the virtual site root.

### CWE-78

Scheduled tasks accept only `backup`, `cache-clear`, and `health-check`. A compound command such as `backup; whoami` must be rejected.

### CWE-434

P11 is a hosting control panel, so storing a PHP file is not automatically a vulnerability. The test asks whether hosted active content becomes executable from the **control-panel origin**. The probe contains only an inert PHP comment and no code execution is attempted.

### CWE-521

A disposable hosted database is created, then the suite checks whether the database-user API accepts the trivially weak password `aaaaaaaaaa`. The disposable database is deleted afterward.

### CWE-778

A disposable site is created and suspended. The site audit log is then checked for an identifiable suspension event.

## Manual review notes for likely FAILs

- **CWE-614**: if `Secure` is missing, confirm the HTTP cookie observation; retain deployment-dependent interpretation because local testing uses HTTP.
- **CWE-307**: report only the bounded 15-attempt observation, not “unlimited brute force.”
- **CWE-352**: PowerShell forces the session cookie with a hostile Origin. If mutation succeeds, server-side CSRF/origin protection is absent or insufficient. Because `SameSite=Lax` is used, validate browser exploitability separately.
- **CWE-1021**: manually inspect `X-Frame-Options` and CSP `frame-ancestors`.
- **CWE-521**: if the weak database password is accepted, manually replay with a fresh disposable database before confirming.
- **CWE-778**: if no suspension audit event appears, manually create/suspend a fresh site and inspect `/api/sites/{id}/audit-events`.

## Password reset boundary

P11 exposes a usable reset token only when `APP_ENV=test`. The normal development HTTP server returns `reset_token: null`. The external suite therefore does not silently switch the deployment into test mode. The supplied functional tests already cover reset-token single use and reset-driven session invalidation in their internal test-mode harness.

## Deliberate N/A cases

No fake tests are created for:

- CWE-601 Open Redirect — no caller-controlled redirect target.
- CWE-918 SSRF — no server-side URL fetch.
- CWE-502 Unsafe Deserialization — no corresponding HTTP surface.
- component vulnerabilities — assess `composer.lock` separately.
