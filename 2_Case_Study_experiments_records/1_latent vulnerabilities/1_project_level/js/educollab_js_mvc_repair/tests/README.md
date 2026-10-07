Functional tests
Adds Jest + Supertest functional smoke tests using the HTML routes and direct SQLite verification.
Install
npm install
Run
npm test
Or:

 npx jest .\tests\functional\project_level_api_smoke.test.js --runInBand --verbose



  npx jest tests/security/exploit_tests --runInBand



node --test .\tests\security\test_exploit_vuln_001_cross_course_membership_idor.js

node --test .\tests\security\test_exploit_vuln_002_post_idor.js   ---failed

node --test .\tests\security\test_exploit_vuln_003_comment_idor.js   ---failed


node --test .\tests\security\test_exploit_vuln_004_submission_idor.js  --failed

node --test .\tests\security\test_exploit_vuln_005_public_upload_bypass.js  --failed

node --test .\tests\security\test_exploit_vuln_006_active_content_upload.js  --failed

node --test .\tests\security\test_exploit_vuln_007_quiz_business_logic.js --failed


<!-- npx jest .\tests\functional\project_level_api_smoke.test.js --runInBand --verbose -->