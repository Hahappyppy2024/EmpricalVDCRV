pytest tests/security/test_exploit_vuln_001_post_idor.py  -q
pytest tests/security/test_exploit_vuln_002_comment_idor.py  -q
pytest tests/security/test_exploit_vuln_003_discussion_csrf.py -q

python -m pytest tests\test_r3_functional.py -v