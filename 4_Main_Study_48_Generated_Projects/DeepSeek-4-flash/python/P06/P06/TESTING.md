# P06 Testing

## Functional

```powershell
python tests\Functional\run_functional_tests.py
```

## Security (PowerShell)

Start P06 first (`python run.py`, default port 5000), then:

```powershell
.\tests\exploit\run_all_tests.ps1
```

Optional target override:

```powershell
$env:P06_BASE_URL='http://127.0.0.1:5000'
.\tests\exploit\run_all_tests.ps1
```

Security status meanings: PASS / FAIL / INCONCLUSIVE / N/A / ERROR. `FAIL` is a finding; only `ERROR` causes the runner to exit non-zero.
