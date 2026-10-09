$ErrorActionPreference = "Continue"
$port = 8080
$root = "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P02"
$logOut = "$root\storage\server.out"
$logErr = "$root\storage\server.err"
"" | Out-File -FilePath $logOut
"" | Out-File -FilePath $logErr

Write-Host "[1/4] Resetting database..." -ForegroundColor Cyan
php "$root\bin\reset_db.php" | Out-Null
Write-Host "  -> OK" -ForegroundColor Green
Write-Host ""

# Kill any existing server on this port
Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.OwningProcess -ne 0) {
        Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    }
}
Start-Sleep -Seconds 2

Write-Host "[2/4] Starting PHP built-in server on port $port..." -ForegroundColor Cyan
$proc = Start-Process -FilePath "cmd.exe" -ArgumentList @("/c", "cd /d `"$root`" && php -S 127.0.0.1:$port -t public public/index.php") -RedirectStandardOutput $logOut -RedirectStandardError $logErr -PassThru -WindowStyle Hidden
Write-Host "  Server PID = $($proc.Id)"
$listen = $null
for ($i=0; $i -lt 15; $i++) {
    Start-Sleep -Seconds 1
    $listen = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($listen) { break }
}
if (-not $listen) {
    Write-Host "  Server NOT listening on $port" -ForegroundColor Red
    Get-Content $logErr | Select-Object -Last 20
    exit 1
}
Write-Host "  -> Server listening on port $port" -ForegroundColor Green
Write-Host ""

Write-Host "[3/4] Running endpoint smoke tests..." -ForegroundColor Cyan
$base = "http://127.0.0.1:$port"
$endpoints = @(
    "/", "/login", "/dashboard",
    "/conference_phases", "/paper_submission", "/submission_discovery",
    "/reviewer_assignment", "/reviewing", "/rebuttal",
    "/decision_management", "/bulk_exports", "/frontend_api_integration_and_errors",
    "/api/conf/conference_phases", "/api/conf/paper_submission",
    "/api/conf/reviewer_assignment", "/api/conf/reviewing",
    "/api/conf/rebuttal", "/api/conf/decision_management",
    "/api/conf/double_blind_views", "/api/conf/bulk_exports",
    "/api/conf/frontend_api_integration_and_errors", "/api/conf/submission_discovery",
    "/api/conf/manuscript_access", "/api/conf/account_access_and_recovery",
    "/api/errors/404", "/api/errors/401", "/api/errors/422",
    "/static/app.css", "/static/app.js"
)
$ok = 0; $fail = 0
foreach ($path in $endpoints) {
    try {
        $resp = Invoke-WebRequest -Uri ($base + $path) -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
        $code = $resp.StatusCode
        if ($code -lt 500) {
            Write-Host ("  [OK  ] {0,-55} HTTP {1}" -f $path, $code) -ForegroundColor Green
            $ok++
        } else {
            Write-Host ("  [FAIL] {0,-55} HTTP {1}" -f $path, $code) -ForegroundColor Red
            $fail++
        }
    } catch {
        $err = $_.Exception.Response
        if ($err) {
            $code = [int]$err.StatusCode
            if ($code -lt 500) {
                Write-Host ("  [OK  ] {0,-55} HTTP {1}" -f $path, $code) -ForegroundColor Green
                $ok++
            } else {
                Write-Host ("  [FAIL] {0,-55} HTTP {1}" -f $path, $code) -ForegroundColor Red
                $fail++
            }
        } else {
            Write-Host ("  [FAIL] {0,-55} ERROR: {1}" -f $path, $_.Exception.Message) -ForegroundColor Red
            $fail++
        }
    }
}
Write-Host ""
Write-Host "  Smoke OK: $ok  Fail: $fail" -ForegroundColor $(if ($fail -gt 0) {"Red"} else {"Green"})
Write-Host ""

Write-Host "[4/4] Auth flow tests via curl..." -ForegroundColor Cyan
$cookieJar = Join-Path $env:TEMP "p02-cookies.txt"
Remove-Item $cookieJar -ErrorAction SilentlyContinue

# 1. /dashboard unauthenticated -> 302 to /login
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -c $cookieJar -b $cookieJar "$base/dashboard" 2>&1
Write-Host "  GET /dashboard (no login) -> HTTP $r (expect 302)"

# 2. Login as chair
$r = & curl.exe -s -L -o NUL -w "%{http_code}" --max-time 15 -c $cookieJar -b $cookieJar -d "username=chair1&password=Password123!" "$base/login" 2>&1
Write-Host "  POST /login (chair1) -> HTTP $r (expect 200)"

# 3. /dashboard with cookie
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -b $cookieJar "$base/dashboard" 2>&1
Write-Host "  GET /dashboard (as chair) -> HTTP $r (expect 200)"

# 4. Chair page works
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -b $cookieJar "$base/conference_phases" 2>&1
Write-Host "  GET /conference_phases (as chair) -> HTTP $r (expect 200)"

# 5. Login as author
Remove-Item $cookieJar -ErrorAction SilentlyContinue
$r = & curl.exe -s -L -o NUL -w "%{http_code}" --max-time 15 -c $cookieJar -b $cookieJar -d "username=author1&password=Password123!" "$base/login" 2>&1
Write-Host "  POST /login (author1) -> HTTP $r (expect 200)"

# 6. Author accesses author page
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -b $cookieJar "$base/paper_submission" 2>&1
Write-Host "  GET /paper_submission (as author) -> HTTP $r (expect 200)"

# 7. Author blocked from chair page
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -b $cookieJar "$base/conference_phases" 2>&1
Write-Host "  GET /conference_phases (as author) -> HTTP $r (expect 403)"

# 8. Wrong password
Remove-Item $cookieJar -ErrorAction SilentlyContinue
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -c $cookieJar -b $cookieJar -d "username=chair1&password=wrong" "$base/login" 2>&1
Write-Host "  POST /login (wrong pass) -> HTTP $r (expect 200 with invalid credentials)"

# 9. Logout
Remove-Item $cookieJar -ErrorAction SilentlyContinue
$r = & curl.exe -s -L -o NUL -w "%{http_code}" --max-time 15 -c $cookieJar -b $cookieJar -d "username=chair1&password=Password123!" "$base/login" 2>&1
& curl.exe -s -o NUL --max-time 15 -c $cookieJar -b $cookieJar "$base/logout" 2>&1 | Out-Null
$r = & curl.exe -s -o NUL -w "%{http_code}" --max-time 15 -b $cookieJar "$base/dashboard" 2>&1
Write-Host "  GET /dashboard (after logout) -> HTTP $r (expect 302 redirect to login)"

# Cleanup
Write-Host ""
Write-Host "Cleaning up..." -ForegroundColor Cyan
Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.OwningProcess -ne 0) {
        Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    }
}
Remove-Item $cookieJar -ErrorAction SilentlyContinue
Write-Host "Done." -ForegroundColor Green