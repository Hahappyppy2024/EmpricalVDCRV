pytest tests/security/test_exploit_vuln_001_quiz_window_bypass.py 
pytest tests/security/test_exploit_vuln_002_quiz_attempt_tampering.py 
pytest tests/security/test_exploit_vuln_003_quiz_csrf.py -q

python -m pytest tests\test_r5_functional.py -v