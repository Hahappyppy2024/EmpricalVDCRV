@echo off
REM Start the AetherPanel dev server on port 8080 in the current console.
REM Press Ctrl+C to stop it.
cd /d "%~dp0\.."

if not exist storage mkdir storage

REM Kill any stale server
taskkill /F /IM php.exe >nul 2>&1

echo.
echo  ===========================================
echo   AetherPanel P11 dev server
echo   URL: http://127.0.0.1:8080
echo   Sign in: alice  / Password123!
echo             admin  / Password123!
echo   Press Ctrl+C to stop
echo  ===========================================
echo.

"D:\language\php83\php.exe" -S 127.0.0.1:8080 -t public public\index.php