node --test tests/security/test_exploit_vuln_001_hardcoded_admin.js
node --test tests/security/test_exploit_vuln_002_session_fixation.js---fail
node --test tests/security/test_exploit_vuln_003_course_idor.js---faile
node --test tests/security/test_exploit_vuln_004_csrf.js--faile
node --test tests/security/test_exploit_vuln_005_default_session_secret.js--faile


$env:SESSION_SECRET="test-session-secret"; $env:INITIAL_ADMIN_PASSWORD="adminpass"; node --test tests/api.functional.test.js