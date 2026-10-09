param([int]$port = 8765)

Set-Location "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P16"
$base = "http://127.0.0.1:$port"

# Start the server in a persistent background job
$job = Start-Job -ScriptBlock {
    param($p)
    Set-Location "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P16"
    $proc = Start-Process -FilePath "php" -ArgumentList "-S","127.0.0.1:$p","-t","public","public/index.php" -RedirectStandardOutput "tmp_out.log" -RedirectStandardError "tmp_err.log" -PassThru
    $proc.Id | Out-File -Encoding ASCII "tmp_pid.txt"
    Write-Output "Server PID: $($proc.Id)"
    Start-Sleep -Seconds 86400  # keep job alive
} -ArgumentList $port

Start-Sleep -Seconds 3
if (-not (Test-Path tmp_pid.txt)) {
    Write-Host "Server PID file not found"
    exit 1
}
$serverPid = Get-Content tmp_pid.txt
$running = Get-Process -Id $serverPid -ErrorAction SilentlyContinue
if (-not $running) {
    Write-Host "Server is not running. Logs:"
    Get-Content tmp_err.log
    Stop-Job $job
    Remove-Job $job
    exit 1
}
Write-Host "Server PID $serverPid running"
Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | Format-Table

# Now run smoke checks
$cookie = "tmp_cookies.txt"
Remove-Item $cookie -ErrorAction SilentlyContinue

function Run([string]$url, [string]$method = 'GET', [string]$body = '', [string]$label = '', [string]$contentType = 'application/json') {
    $args = @('-s','-o','tmp_last.html','-w','%{http_code}','--max-time','5')
    if ($cookie) { $args += @('-c',$cookie,'-b',$cookie) }
    if ($method -ne 'GET') { $args += @('-X',$method) }
    if ($body -ne '') { $args += @('-H',"Content-Type: $contentType"); $args += @('--data-binary',$body) }
    $args += @($url)
    $code = & curl.exe @args
    Write-Host ("{0,-50} {1,-6} {2}" -f $label, $method, $code)
    if ($code -ge 400 -and (Test-Path tmp_last.html)) {
        $raw = Get-Content tmp_last.html -Raw -ErrorAction SilentlyContinue
        if ($raw.Length -gt 0) { Write-Host ("   -> " + $raw.Substring(0, [Math]::Min(200, $raw.Length))) }
    }
    return $code
}

Write-Host ""
Write-Host "===== End-to-end smoke check ====="
Write-Host ""
Run "$base/login" "POST" 'identifier=admin&password=Admin#12345' "POST /login as admin" "application/x-www-form-urlencoded"
Run "$base/dashboard" "GET" "" "GET /dashboard"
Run "$base/logs" "GET" "" "GET /logs"
Run "$base/services" "GET" "" "GET /services"
Run "$base/jobs" "GET" "" "GET /jobs"
Run "$base/job_runs" "GET" "" "GET /job_runs"
Run "$base/backups" "GET" "" "GET /backups"
Run "$base/alerts" "GET" "" "GET /alerts"
Run "$base/health_targets" "GET" "" "GET /health_targets"
Run "$base/configuration" "GET" "" "GET /configuration (admin)"
Run "$base/api_tokens" "GET" "" "GET /api_tokens (admin)"
Run "$base/audit" "GET" "" "GET /audit (admin)"

Run "$base/api/sys/server_dashboard" "GET" "" "GET /api/sys/server_dashboard"
Run "$base/api/sys/service_control" "GET" "" "GET /api/sys/service_control"
Run "$base/api/sys/job_scheduler" "GET" "" "GET /api/sys/job_scheduler"
Run "$base/api/sys/configuration_editor" "GET" "" "GET /api/sys/configuration_editor"
Run "$base/api/sys/alert_center" "GET" "" "GET /api/sys/alert_center"
Run "$base/api/sys/audit_logs_and_admin_operations" "GET" "" "GET /api/sys/audit_logs"

# Form-driven POST
Run "$base/alerts" "POST" 'title=smoke&message=demo&severity=info&source=manual' "POST /alerts form" "application/x-www-form-urlencoded"
Run "$base/services/2/act" "POST" 'action=start' "POST /services/2/act start" "application/x-www-form-urlencoded"
Run "$base/services/2/act" "POST" 'action=stop' "POST /services/2/act stop" "application/x-www-form-urlencoded"
Run "$base/logout" "POST" "" "POST /logout" "application/x-www-form-urlencoded"

# Stop server
Stop-Process -Id $serverPid -Force
Stop-Job $job
Remove-Job $job
Write-Host ""
Write-Host "===== Fatal errors in error log ====="
$fatal = Select-String -Path tmp_err.log -Pattern "Fatal|Warning|Notice" -ErrorAction SilentlyContinue
if ($fatal) { $fatal | ForEach-Object { Write-Host $_.Line } } else { Write-Host "(none)" }