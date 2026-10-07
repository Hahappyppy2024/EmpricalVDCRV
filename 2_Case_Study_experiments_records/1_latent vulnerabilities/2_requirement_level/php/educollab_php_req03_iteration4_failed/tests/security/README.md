pytest -q tests/security/test_vuln_001_post_idor.py
pytest -q tests/security/test_vuln_002_comment_idor.py

pytest -q tests/test_api_functional_r3_posts_comments.py