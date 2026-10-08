$ErrorActionPreference = "Stop"
$base = "http://127.0.0.1:8081"
$pass = 0
$fail = 0
$tmp = Join-Path (Get-Location) "storage\smoke-cookies"
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
New-Item -ItemType Directory -Path $tmp | Out-Null

function Check($name, $cond, $extra = "") {
    if ($cond) { $script:pass++; Write-Host ("PASS  {0}" -f $name) -ForegroundColor Green }
    else { $script:fail++; Write-Host ("FAIL  {0} {1}" -f $name, $extra) -ForegroundColor Red }
}

function Login($user, $pwd, $jar) {
    & curl.exe -s -c $jar -o NUL -X POST "$base/login" -d "username=$user&password=$pwd"
}

function GetJ($url, $jar) {
    if ($jar -eq "") { $raw = & curl.exe -s $url }
    else { $raw = & curl.exe -s -b $jar $url }
    return ($raw | ConvertFrom-Json)
}

function BodyFile($obj) {
    $path = "$tmp\body-$([guid]::NewGuid().ToString('N')).json"
    ($obj | ConvertTo-Json -Depth 10) | Set-Content -Path $path -Encoding ASCII
    return $path
}

function PostJ($url, $jar, $body) {
    $f = BodyFile $body
    $raw = & curl.exe -s -b $jar -H "Content-Type: application/json" -d "@$f" $url
    Remove-Item $f -ErrorAction SilentlyContinue
    return ($raw | ConvertFrom-Json)
}

function PatchJ($url, $jar, $body) {
    $f = BodyFile $body
    $raw = & curl.exe -s -b $jar -X PATCH -H "Content-Type: application/json" -d "@$f" $url
    Remove-Item $f -ErrorAction SilentlyContinue
    return ($raw | ConvertFrom-Json)
}

function HttpCode($url, $jar, $method = "GET", $body = $null) {
    $a = @("-s", "-o", "NUL", "-w", "%{http_code}", "-X", $method)
    if ($jar -ne "") { $a += @("-b", $jar) }
    if ($body -ne $null) { $f = BodyFile $body; $a += @("-H", "Content-Type: application/json", "-d", "@$f") }
    $a += $url
    $code = & curl.exe @a
    if ($body -ne $null) { Remove-Item $f -ErrorAction SilentlyContinue }
    return $code
}

# ---- start server (kill any stale listener first) ----
$existing = Get-NetTCPConnection -LocalPort 8081 -State Listen -ErrorAction SilentlyContinue
foreach ($c in $existing) { Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue }
Start-Sleep -Milliseconds 800
$proc = Start-Process -FilePath "php" -ArgumentList "-S","127.0.0.1:8081","-t","public","public/index.php" `
    -WorkingDirectory (Get-Location) -RedirectStandardOutput "storage/server.log" `
    -RedirectStandardError "storage/server.err.log" -PassThru
Start-Sleep -Seconds 2
if ($proc.HasExited) {
    Write-Host "FATAL: server failed to start" -ForegroundColor Red
    exit 2
}

try {
    $h = GetJ "$base/api/monitor/health" ""
    Check "health endpoint" ($h.ok -eq $true)

    # ---- operator ----
    $opJar = "$tmp\op.txt"
    Login "operator" "operator123" $opJar
    Check "operator session cookie set" ((Get-Content $opJar -Raw) -match "p16_session")
    $page = & curl.exe -s -b $opJar $base/
    Check "dashboard page 200" ($page -match "Server Dashboard")
    foreach ($p in @("/logs", "/services", "/jobs", "/job-history", "/backups", "/alerts", "/health-checks", "/account")) {
        $c = & curl.exe -s -b $opJar "$base$p"
        Check "page $p served" ($c -match "doctype html")
    }
    foreach ($p in @("/config", "/tokens", "/audit")) {
        $code = & curl.exe -s -b $opJar -o NUL -w "%{http_code}" "$base$p"
        Check "admin page $p blocked for operator" ($code -eq "403")
    }

    # bad credentials -> 302 back to login with flash marker
    $loc = & curl.exe -s -o NUL -w "%{redirect_url}" -X POST "$base/login" -d "username=operator&password=wrongpass"
    Check "bad credentials rejected" ($loc -match "flash=")

    # SYS-02
    $latest = GetJ "$base/api/sys/server_dashboard/latest" $opJar
    Check "dashboard latest > 0" ($latest.data.Count -gt 0)
    $snap = PostJ "$base/api/sys/server_dashboard" $opJar @{ server_name = "smoke-01"; cpu_pct = 33.3; memory_pct = 44.4; disk_pct = 55.5; uptime_seconds = 123; service_status = "ok" }
    Check "create snapshot" ($snap.ok -and $snap.data.server_name -eq "smoke-01")
    $badSnap = HttpCode "$base/api/sys/server_dashboard" $opJar "POST" @{ server_name = ""; cpu_pct = 999 }
    Check "invalid snapshot rejected 422" ($badSnap -eq "422")

    # SYS-03
    $files = GetJ "$base/api/sys/log_viewer/files" $opJar
    Check "log files present" ($files.data.Count -ge 4)
    $prev = GetJ "$base/api/sys/log_viewer/preview?file=app.log" $opJar
    Check "log preview" ($prev.data.Count -gt 0)
    $dl = & curl.exe -s -b $opJar -o NUL -w "%{http_code}" "$base/api/sys/log_viewer/download?file=app.log"
    Check "log download 200" ($dl -eq "200")
    $newLog = PostJ "$base/api/sys/log_viewer" $opJar @{ file_name = "smoke.log"; level = "info"; source = "smoke"; message = "smoke test entry" }
    Check "append log entry" ($newLog.ok)

    # SYS-04
    $svc = GetJ "$base/api/sys/service_control" $opJar
    Check "services listed" ($svc.data.Count -ge 5)
    $createdSvc = PostJ "$base/api/sys/service_control" $opJar @{ name = "smoke-service"; description = "smoke" }
    Check "register service" ($createdSvc.ok)
    $started = PatchJ "$base/api/sys/service_control/$($createdSvc.data.id)" $opJar @{ action = "start" }
    Check "start service" ($started.data.status -eq "running")
    $stopped = PatchJ "$base/api/sys/service_control/$($createdSvc.data.id)" $opJar @{ action = "stop" }
    Check "stop service" ($stopped.data.status -eq "stopped")
    $badTrans = HttpCode "$base/api/sys/service_control/$($createdSvc.data.id)" $opJar "PATCH" @{ action = "stop" }
    Check "invalid transition rejected 422" ($badTrans -eq "422")

    # SYS-05
    $job = PostJ "$base/api/sys/job_scheduler" $opJar @{ name = "smoke-job"; profile = "health_check"; schedule = "hourly"; description = "smoke" }
    Check "create job" ($job.ok)
    $run = PatchJ "$base/api/sys/job_scheduler/$($job.data.id)" $opJar @{ action = "run" }
    Check "run job" ($run.ok)
    $pause = PatchJ "$base/api/sys/job_scheduler/$($job.data.id)" $opJar @{ action = "pause" }
    Check "pause job" ($pause.data.status -eq "paused")
    $badProfile = HttpCode "$base/api/sys/job_scheduler" $opJar "POST" @{ name = "x"; profile = "evil" }
    Check "bad profile rejected 422" ($badProfile -eq "422")

    # SYS-06
    $hist = GetJ "$base/api/sys/job_execution_history" $opJar
    Check "history listed" ($hist.data.Count -gt 0)
    $newRun = PostJ "$base/api/sys/job_execution_history" $opJar @{ job_id = $job.data.id; exit_status = 0; duration_ms = 100; retry_count = 0; output = "manual smoke run" }
    Check "manual run recorded" ($newRun.ok)

    # SYS-07
    $bk = PostJ "$base/api/sys/backup_manager" $opJar @{ name = "smoke-backup"; backup_type = "manual" }
    Check "create backup" ($bk.ok -and $bk.data.size_bytes -gt 0)
    $dlb = & curl.exe -s -b $opJar -o NUL -w "%{http_code}" "$base/api/sys/backup_manager/$($bk.data.id)/download"
    Check "backup download 200" ($dlb -eq "200")
    $restored = PostJ "$base/api/sys/backup_manager/$($bk.data.id)/restore" $opJar @{}
    Check "backup restore" ($restored.data.status -eq "restored")

    # SYS-09
    $alert = PostJ "$base/api/sys/alert_center" $opJar @{ title = "smoke alert"; severity = "warning"; source = "smoke" }
    Check "open alert" ($alert.ok)
    $ack = PatchJ "$base/api/sys/alert_center/$($alert.data.id)" $opJar @{ action = "acknowledge" }
    Check "acknowledge alert" ($ack.data.status -eq "acknowledged")
    $cmt = PatchJ "$base/api/sys/alert_center/$($alert.data.id)" $opJar @{ action = "comment"; comment = "investigating" }
    Check "comment on alert" ($cmt.data.comments -match "investigating")

    # SYS-10
    $tgt = PostJ "$base/api/sys/health_check_targets" $opJar @{ name = "smoke-target"; protocol = "http"; target = "http://127.0.0.1:8787/"; port = 8787; interval_seconds = 60 }
    Check "create health target" ($tgt.ok)
    $chk = PostJ "$base/api/sys/health_check_targets/$($tgt.data.id)/check" $opJar @{}
    Check "health check up on 8787" ($chk.data.status -eq "up" -and $chk.data.last_code -eq 200)
    $tgt2 = PatchJ "$base/api/sys/health_check_targets/$($tgt.data.id)" $opJar @{ port = 8788 }
    $chkDown = PostJ "$base/api/sys/health_check_targets/$($tgt2.data.id)/check" $opJar @{}
    Check "health check down on 8788" ($chkDown.data.status -eq "down" -and $chkDown.data.last_code -eq 503)

    # operator blocked on admin APIs
    foreach ($m in @("configuration_editor", "api_token_manager", "audit_logs_and_admin_operations")) {
        $c = HttpCode "$base/api/sys/$m" $opJar
        Check "api $m blocked for operator 403" ($c -eq "403")
    }

    # ---- admin ----
    $admJar = "$tmp\adm.txt"
    Login "admin" "admin123" $admJar
    Check "admin session cookie set" ((Get-Content $admJar -Raw) -match "p16_session")

    $cfg = GetJ "$base/api/sys/configuration_editor" $admJar
    Check "config list admin" ($cfg.data.Count -gt 0)
    $newKey = PostJ "$base/api/sys/configuration_editor" $admJar @{ config_key = "smoke_key"; config_value = "1"; description = "smoke" }
    Check "add config key" ($newKey.ok)
    $pending = PatchJ "$base/api/sys/configuration_editor/$($newKey.data.id)" $admJar @{ config_value = "2" }
    Check "pending change" ($pending.data.status -eq "pending")
    $approved = PatchJ "$base/api/sys/configuration_editor/$($newKey.data.id)" $admJar @{ action = "approve" }
    Check "approve change" ($approved.data.config_value -eq "2" -and $approved.data.status -eq "active")

    $tokens = GetJ "$base/api/sys/api_token_manager" $admJar
    Check "token list admin" ($tokens.data.Count -ge 3)
    $tok = PostJ "$base/api/sys/api_token_manager" $admJar @{ name = "smoke-token"; owner_id = 2 }
    Check "token created with plain" ($tok.ok -and $tok.plain_token -like "p16_*")
    $metrics = GetJ "$base/api/monitor/metrics?token=$($tok.plain_token)" ""
    Check "monitor metrics via token" ($metrics.ok -and $metrics.data.Count -gt 0)
    $badTok = & curl.exe -s -o NUL -w "%{http_code}" "$base/api/monitor/metrics?token=bogus"
    Check "monitor metrics bad token 401" ($badTok -eq "401")
    $revoked = PatchJ "$base/api/sys/api_token_manager/$($tok.data.id)" $admJar @{ action = "revoke" }
    Check "revoke token" ($revoked.data.status -eq "revoked")
    $revokedUse = & curl.exe -s -o NUL -w "%{http_code}" "$base/api/monitor/metrics?token=$($tok.plain_token)"
    Check "revoked token rejected 401" ($revokedUse -eq "401")

    $audit = GetJ "$base/api/sys/audit_logs_and_admin_operations" $admJar
    Check "audit list" ($audit.data.Count -gt 0)
    $filtered = GetJ "$base/api/sys/audit_logs_and_admin_operations?module=service_control" $admJar
    $allMatch = $true
    foreach ($ev in $filtered.data) { if ($ev.module -ne "service_control") { $allMatch = $false } }
    Check "audit filter matches module" ($allMatch -and $filtered.data.Count -gt 0)
    $emptyFilter = GetJ "$base/api/sys/audit_logs_and_admin_operations?module=does_not_exist" $admJar
    Check "audit empty filter bounded" ($emptyFilter.ok -and $emptyFilter.data.Count -eq 0)
    $ops = GetJ "$base/api/sys/audit_logs_and_admin_operations/operators" $admJar
    Check "operators list" ($ops.data.Count -gt 0)
    $newOp = PostJ "$base/api/sys/audit_logs_and_admin_operations" $admJar @{ action = "operator_create"; username = "smokeop"; email = "smokeop@monitor.local"; password = "smokeop123"; full_name = "Smoke Op"; role = "operator" }
    Check "create operator via audit api" ($newOp.ok)

    # ---- viewer ----
    $vwJar = "$tmp\vw.txt"
    Login "viewer" "viewer123" $vwJar
    Check "viewer session cookie set" ((Get-Content $vwJar -Raw) -match "p16_session")
    $vwCtrl = HttpCode "$base/api/sys/service_control/$($createdSvc.data.id)" $vwJar "PATCH" @{ action = "start" }
    Check "viewer cannot control service 403" ($vwCtrl -eq "403")
    $vwAccess = GetJ "$base/api/sys/account_access" $vwJar
    $scoped = $true
    foreach ($a in $vwAccess.data) { if ($a.username -ne "viewer") { $scoped = $false } }
    Check "viewer sees only own access" ($scoped)
    $vwJob = HttpCode "$base/api/sys/job_scheduler/$($job.data.id)" $vwJar
    Check "viewer cannot read operator job 404" ($vwJob -eq "404")

    # ---- logout ----
    Login "operator" "operator123" $opJar
    & curl.exe -s -b $opJar -c $opJar -o NUL -X POST "$base/logout"
    $afterLogout = & curl.exe -s -b $opJar -o NUL -w "%{http_code}" "$base/api/sys/account_access"
    Check "api after logout blocked 401" ($afterLogout -eq "401")

    # ---- registration ----
    $regJar = "$tmp\reg.txt"
    $uniq = "user" + (Get-Random -Minimum 1000 -Maximum 99999)
    & curl.exe -s -c $regJar -o NUL -X POST "$base/register" -d "username=$uniq&email=$uniq@monitor.local&password=password123&full_name=Reg+User"
    Check "registration creates session" ((Get-Content $regJar -Raw) -match "p16_session")
    $regPage = & curl.exe -s -b $regJar $base/
    Check "registration reaches dashboard" ($regPage -match "Server Dashboard")

} finally {
    Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
}

Write-Host ""
Write-Host ("RESULT: {0} passed, {1} failed" -f $pass, $fail)
exit $(if ($fail -eq 0) { 0 } else { 1 })
