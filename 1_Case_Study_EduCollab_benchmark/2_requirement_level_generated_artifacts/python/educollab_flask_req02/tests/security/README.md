pytest tests/security/test_exploit_vuln_005_membership_roster_bac.py -q
pytest tests/security/test_exploit_vuln_006_membership_csrf.py -q

python -m pytest tests\test_r2_functional.py -v