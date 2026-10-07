# PHP R2 membership tests

This package focuses only on the **R2 membership-related functionality**.
It does not treat R1 auth/course CRUD as target coverage, except where those endpoints are needed as setup.

## Included files

- `tests/functional_tests/test_api_functional_r2_memberships.py`
- `tests/security_tests/exploit_tests/test_api_exploit_r2_memberships.py`
- `tests/conftest.py`
- `tests/helpers.py`

## What the tests cover

### Functional tests
- add a member to a course
- list course members
- allow an enrolled member to view the member list
- update a member role
- list the current user's memberships
- remove a member
- authorization and validation checks for membership APIs

### Exploit tests
These tests are written against the current code behavior and demonstrate **course-management orphaning** flaws in the R2 membership feature:

1. the only teacher can delete their own membership
2. the only teacher can demote themself to `student`

Both behaviors can leave a course without any managing membership.

## Suggested run commands
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests
```bash
pip install pytest requests
pytest -q tests/test_api_functional_r2_memberships.py
pytest -q tests/test_api_exploit_r2_memberships.py
```

```bash
pip install pytest requests
```

## Run all

```bash
pytest -q tests
```

## Run only functional tests

```bash
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests

python -m pytest tests\test_api_functional_r2_memberships.py -vv -s
```

## Run only exploit tests

```bash
python -m pytest tests\test_api_exploit_r2_memberships.py -vv -s



## Notes

- Extract this package into the PHP app root directory.
- The fixture starts the PHP built-in server with `php -S 127.0.0.1:<port> -t public`.
- The environment must have PHP with `pdo_sqlite` / SQLite support enabled.
