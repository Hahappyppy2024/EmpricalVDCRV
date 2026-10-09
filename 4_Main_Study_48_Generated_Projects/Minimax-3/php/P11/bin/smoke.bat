@echo off
setlocal enabledelayedexpansion

REM AetherPanel smoke test (Windows). Starts a PHP server, runs the smoke
REM runner, and stops the server. All in one command.
cd /d "%~dp0\.."

if not exist storage mkdir storage

set PORT=8484

REM Kill any stale PHP server
taskkill /F /IM php.exe >nul 2>&1
timeout /t 1 /nobreak >nul

echo Starting PHP server on port %PORT% ...
start /b "" "D:\language\php83\php.exe" -S 127.0.0.1:%PORT% -t public public\index.php > storage\smoke.log 2>&1

REM Wait for the server to accept connections
set WAITED=0
:waitloop
"D:\language\php83\php.exe" "bin\_wait.php" %PORT% >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    set /a WAITED+=1
    if !WAITED! GEQ 15 (
        echo PHP server failed to start. Log:
        type storage\smoke.log
        taskkill /F /IM php.exe >nul 2>&1
        exit /b 1
    )
    ping -n 2 127.0.0.1 >nul
    goto waitloop
)

REM Run the smoke runner
"D:\language\php83\php.exe" "bin\smoke_runner.php" "http://127.0.0.1:%PORT%"
set RESULT=%ERRORLEVEL%

REM Cleanup
taskkill /F /IM php.exe >nul 2>&1
exit /b %RESULT%