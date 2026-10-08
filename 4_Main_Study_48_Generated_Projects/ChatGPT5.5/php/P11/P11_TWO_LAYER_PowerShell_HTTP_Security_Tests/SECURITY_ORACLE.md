# P11 Security Oracle

## Result semantics

- `PASS`: the exact scenario satisfied the secure behavior oracle.
- `FAIL`: the HTTP behavior violated the oracle and becomes a candidate security finding.
- `ERROR`: environment, precondition, or harness failure prevented a valid security conclusion.
- `N/A`: no verified P11 attack surface exists.

`PASS` does not mean global absence of the CWE.

## Two layers

- `Core`: cross-project comparison layer.
- `P11_Specific`: Hosting Control Panel characterization layer.

The layer affects aggregation, not PASS/FAIL logic.

## Manual labels

After manual replay, use:

- `CONFIRMED`
- `CONFIRMED_CONDITIONAL`
- `REJECTED_FALSE_POSITIVE`
- `INCONCLUSIVE`
- `PASS_SCENARIO`
- `N/A`

## Repair oracle

A repair is accepted only when:

1. the pre-repair violation is manually confirmed;
2. the same security scenario satisfies the secure oracle after repair;
3. relevant functional tests still pass; and
4. manual replay no longer reproduces the violation.
