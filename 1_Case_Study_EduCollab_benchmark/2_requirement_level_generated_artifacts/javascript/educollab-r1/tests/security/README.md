node --test tests/security/test_exploit_vuln_001_hardcoded_admin.js
node --test tests/security/test_exploit_vuln_002_session_fixation.js
node --test tests/security/test_exploit_vuln_003_course_idor.js
node --test tests/security/test_exploit_vuln_004_csrf.js
node --test tests/security/test_exploit_vuln_005_default_session_secret.js


node --test tests/api.functional.test.js