# Function-level tests (Jest + Supertest)

## Run
```bash
npm install
npm start
npm test
```

- `functional/function_level.test.js` covers FL-FR1..6
- `security/function_level.security.test.js` covers Zip Slip, traversal, grades import validation

# 从项目根目录（有 package.json 的那个目录）运行：
# Windows PowerShell / CMD 都可以直接复制粘贴
npx jest --runInBand --testPathPattern="tests\\functional" 
npx jest --runInBand --runTestsByPath .\tests\functional\R07_auth__PL_AUTH_register_login_me_logout.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R08_integrity__FL_FR1_2_unzip_and_list.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R08_integrity__FL_FR3_4_export_and_download.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R08_integrity__FR_CSV1_export_members.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R08_integrity__FR_CSV2_import_members.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R08_integrity__FR_CSV3_export_grades.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R08_integrity__PL_UPLOADS.test.js pass
npx jest --runInBand --runTestsByPath .\tests\functional\R09_auditlog__PL_A09_admin_audit_view.test.js pass

Test Suites: 8 passed, 8 total                                                                                                                            
Tests:       8 passed, 8 total
Snapshots:   0 total
Time:        3.877 s, estimated 5 s



## Run Security Test Files (one-by-one)

> Run these commands from the project root.

```bash
npx jest --runInBand --runTestsByPath .\tests\security\R01_access_control__auth_access_control.security.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\src\routes\api.js
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\src\routes\web.js

npx jest --runInBand --runTestsByPath .\tests\security\R02_misconfig.security.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\app.js

npx jest --runInBand --runTestsByPath .\tests\security\R02_session_cookie.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\app.js


npx jest --runInBand --runTestsByPath .\tests\security\R04_crypto__invite_link.crypto.security.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\src\controllers\inviteController.js


npx jest --runInBand --runTestsByPath .\tests\security\R05_injection__csv_formula_export.security.test.js pass
# src\controllers\csvController.js


npx jest --runInBand --runTestsByPath .\tests\security\R05_injection__sqli_xss.security.test.js pass
# src\repos\postRepo.js

npx jest --runInBand --runTestsByPath .\tests\security\R08_integrity__function_level_endpoints.security.test.js 1 fail 2 pass
# 158 D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\src\controllers\csvController.js


npx jest --runInBand --runTestsByPath .\tests\security\R08_integrity__requirement_level_features.security.test.js pass

npx jest --runInBand --runTestsByPath .\tests\security\R08_integrity__upload_path_traversal.security.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\src\controllers\submissionZipController.js

npx jest --runInBand --runTestsByPath .\tests\security\R09_auditlog__audit_missing_critical_events.security.test.js fail
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\src\controllers\authController.js


npx jest --runInBand --runTestsByPath .\tests\security\R10_exception.security.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\app.js


npx jest --runInBand --runTestsByPath .\tests\security\R10_stacktrace_test.test.js pass
# D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\iteration1\educollab_JS\app.js
