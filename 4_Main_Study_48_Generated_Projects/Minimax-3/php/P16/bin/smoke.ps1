param([int]$port = 8850)

Set-Location "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P16"
$base = "http://127.0.0.1:$port"
$proc = Start-Process -FilePath "php" -ArgumentList "-S","127.0.0.1:$port","-t","public","public/index.php" -RedirectStandardOutput "tmp_out.log" -RedirectStandardError "tmp_err.log" -PassThru
Write-Output "PID:$($proc.Id)"
Start-Sleep -Seconds 1

$cookieAdmin = "tmp_admin.txt"
$cookieOp    = "tmp_op.txt"
Remove-Item $cookieAdmin, $cookieOp -ErrorAction SilentlyContinue
Remove-Item tmp_*.html,tmp_*.json,tmp_*.payload -ErrorAction SilentlyContinue

function Run([string]$url, [string]$method = 'GET', [string]$body = '', [string]$cookie = '', [string]$label = '', [string]$contentType = 'application/json') {
    $args = @('-s','-o','tmp_last.html','-w','%{http_code}','--max-time','5')
    if ($cookie -ne '') { $args += @('-c',$cookie,'-b',$cookie) }
    if ($method -ne 'GET') { $args += @('-X',$method) }
    if ($body -ne '') {
        $args += @('-H',"Content-Type: $contentType")
        $args += @('--data-binary',$body)
    }
    $args += @($url)
    $code = & curl.exe @args
    Write-Output ("{0,-40} {1} {2}" -f $label, $method, $code)
    if ($code -ge 400) {
        if (Test-Path tmp_last.html) {
            $raw = Get-Content tmp_last.html -Raw -ErrorAction SilentlyContinue
            if ($raw.Length -gt 0) {
                Write-Output ("  body: " + $raw.Substring(0, [Math]::Min(200, $raw.Length)))
            }
        }
    }
    return $code
}

Write-Output "==== login ===="
Run "$base/login" "POST" 'identifier=admin&password=Admin#12345' $cookieAdmin "login admin" "application/x-www-form-urlencoded"
Run "$base/login" "POST" 'identifier=operator&password=Operator#12345' $cookieOp "login operator" "application/x-www-form-urlencoded"

Write-Output "==== admin page ===="
Run "$base/dashboard" "GET" "" $cookieAdmin "dashboard"
Run "$base/logs" "GET" "" $cookieAdmin "logs"
Run "$base/services" "GET" "" $cookieAdmin "services"
Run "$base/jobs" "GET" "" $cookieAdmin "jobs"
Run "$base/job_runs" "GET" "" $cookieAdmin "job_runs"
Run "$base/backups" "GET" "" $cookieAdmin "backups"
Run "$base/alerts" "GET" "" $cookieAdmin "alerts"
Run "$base/health_targets" "GET" "" $cookieAdmin "health"
Run "$base/configuration" "GET" "" $cookieAdmin "configuration"
Run "$base/api_tokens" "GET" "" $cookieAdmin "api_tokens"
Run "$base/audit" "GET" "" $cookieAdmin "audit"

Write-Output "==== admin API ===="
Run "$base/api/sys/account_access" "GET" "" $cookieAdmin "GET account_access"
Run "$base/api/sys/server_dashboard" "GET" "" $cookieAdmin "GET server_dashboard"
Run "$base/api/sys/log_viewer" "GET" "" $cookieAdmin "GET log_viewer"
Run "$base/api/sys/service_control" "GET" "" $cookieAdmin "GET service_control"
Run "$base/api/sys/job_scheduler" "GET" "" $cookieAdmin "GET job_scheduler"
Run "$base/api/sys/job_execution_history" "GET" "" $cookieAdmin "GET job_history"
Run "$base/api/sys/backup_manager" "GET" "" $cookieAdmin "GET backup_manager"
Run "$base/api/sys/configuration_editor" "GET" "" $cookieAdmin "GET configuration_editor"
Run "$base/api/sys/alert_center" "GET" "" $cookieAdmin "GET alert_center"
Run "$base/api/sys/health_check_targets" "GET" "" $cookieAdmin "GET health_check_targets"
Run "$base/api/sys/api_token_manager" "GET" "" $cookieAdmin "GET api_token_manager"
Run "$base/api/sys/audit_logs_and_admin_operations" "GET" "" $cookieAdmin "GET audit_logs"

# Write JSON payloads to files to avoid PowerShell/Bash quoting issues
Set-Content -Path tmp_*.payload -Value "" -Force -ErrorAction SilentlyContinue
'{"host":"edge-02","cpu_pct":42.5,"memory_pct":33.1,"disk_pct":68.0,"uptime_sec":1200,"load_avg":0.6}' | Set-Content -Path tmp_metric.payload
'{"file_id":1,"level":"warning","message":"smoke-test entry"}' | Set-Content -Path tmp_log.payload
'{"name":"smoke-service","description":"smoke"}' | Set-Content -Path tmp_svc.payload
'{"action":"start"}' | Set-Content -Path tmp_svc_act.payload
'{"name":"smoke job","profile_id":1,"cron_expr":"*/10 * * * *"}' | Set-Content -Path tmp_job.payload
'{"state":"paused"}' | Set-Content -Path tmp_job_st.payload
'{}' | Set-Content -Path tmp_empty.payload
'{"name":"smoke.txt","payload":"smoke","description":"smoke"}' | Set-Content -Path tmp_bk.payload
'{"key":"smoke.key","value":"42","category":"smoke","description":"smoke"}' | Set-Content -Path tmp_cfg.payload
'{"title":"smoke","message":"smoke","severity":"warning","source":"smoke"}' | Set-Content -Path tmp_alert.payload
'{"name":"smoke","kind":"tcp","target":"127.0.0.1:80","interval_sec":30,"timeout_ms":1000}' | Set-Content -Path tmp_hc.payload
'{"name":"smoke","scopes":"read","expires_in_days":7}' | Set-Content -Path tmp_tok.payload
'{"action":"disable","user_id":3}' | Set-Content -Path tmp_au.payload
'{"action":"acknowledge"}' | Set-Content -Path tmp_ack.payload
'{"action":"check"}' | Set-Content -Path tmp_hcc.payload
'{"action":"stage","pending_value":"900"}' | Set-Content -Path tmp_stg.payload
'{"action":"approve"}' | Set-Content -Path tmp_app.payload

Write-Output "==== admin POST/PATCH (via @file) ===="
Run "$base/api/sys/server_dashboard" "POST" "@tmp_metric.payload" $cookieAdmin "POST server_dashboard"
Run "$base/api/sys/log_viewer" "POST" "@tmp_log.payload" $cookieAdmin "POST log_viewer"
Run "$base/api/sys/service_control" "POST" "@tmp_svc.payload" $cookieAdmin "POST service_control"
Run "$base/api/sys/service_control/2" "PATCH" "@tmp_svc_act.payload" $cookieAdmin "PATCH service_control/2 start"
Run "$base/api/sys/job_scheduler" "POST" "@tmp_job.payload" $cookieAdmin "POST job_scheduler"
Run "$base/api/sys/job_scheduler/1" "PATCH" "@tmp_job_st.payload" $cookieAdmin "PATCH job_scheduler/1 paused"
Run "$base/api/sys/job_execution_history/1" "PATCH" "@tmp_empty.payload" $cookieAdmin "PATCH job_history/1"
Run "$base/api/sys/backup_manager" "POST" "@tmp_bk.payload" $cookieAdmin "POST backup_manager"
Run "$base/api/sys/configuration_editor" "POST" "@tmp_cfg.payload" $cookieAdmin "POST configuration_editor"
Run "$base/api/sys/configuration_editor/1" "PATCH" "@tmp_stg.payload" $cookieAdmin "PATCH configuration stage"
Run "$base/api/sys/configuration_editor/1" "PATCH" "@tmp_app.payload" $cookieAdmin "PATCH configuration approve"
Run "$base/api/sys/alert_center" "POST" "@tmp_alert.payload" $cookieAdmin "POST alert_center"
Run "$base/api/sys/alert_center/1" "PATCH" "@tmp_ack.payload" $cookieAdmin "PATCH alert acknowledge"
Run "$base/api/sys/health_check_targets" "POST" "@tmp_hc.payload" $cookieAdmin "POST health_check_targets"
Run "$base/api/sys/health_check_targets/1" "PATCH" "@tmp_hcc.payload" $cookieAdmin "PATCH health check"
Run "$base/api/sys/api_token_manager" "POST" "@tmp_tok.payload" $cookieAdmin "POST api_token_manager"
Run "$base/api/sys/audit_logs_and_admin_operations" "POST" "@tmp_au.payload" $cookieAdmin "POST audit disable user 3"

Write-Output "==== operator access ===="
Run "$base/api/sys/account_access" "GET" "" $cookieOp "operator GET account_access"
Run "$base/api/sys/configuration_editor" "GET" "" $cookieOp "operator -> configuration (403)"
Run "$base/api/sys/api_token_manager" "GET" "" $cookieOp "operator -> tokens (403)"
Run "$base/api/sys/audit_logs_and_admin_operations" "GET" "" $cookieOp "operator -> audit (403)"
Run "$base/api/sys/service_control" "POST" "@tmp_svc.payload" $cookieOp "operator POST service (403)"
Run "$base/api/sys/service_control/1" "PATCH" "@tmp_svc_act.payload" $cookieOp "operator PATCH service (403)"

Write-Output "==== cross-user protection (operator cannot modify admin's job) ===="
$jobsResp = curl.exe -s -b $cookieOp "$base/api/sys/job_scheduler"
Write-Output "operator jobs response (truncated):"
Write-Output $jobsResp.Substring(0, [Math]::Min(300, $jobsResp.Length))

Write-Output "==== Form-based workflows ===="
# Schedule a new job
Run "$base/jobs" "POST" 'name=smoke-form-job&profile_id=1&cron_expr=*/15+*+*+*' $cookieAdmin "POST /jobs form" "application/x-www-form-urlencoded"
Run "$base/jobs/2/transition" "POST" 'state=paused' $cookieAdmin "POST /jobs/{id}/transition form" "application/x-www-form-urlencoded"
Run "$base/jobs/2/run" "POST" "" $cookieAdmin "POST /jobs/{id}/run form"
Run "$base/services/2/act" "POST" 'action=start' $cookieAdmin "POST /services/{id}/act start" "application/x-www-form-urlencoded"
Run "$base/services/2/act" "POST" 'action=stop' $cookieAdmin "POST /services/{id}/act stop" "application/x-www-form-urlencoded"
Run "$base/alerts" "POST" 'title=smoke-form&message=hello&severity=info&source=manual' $cookieAdmin "POST /alerts form" "application/x-www-form-urlencoded"
Run "$base/health_targets" "POST" 'name=smoke-form&kind=http&target=http%3A%2F%2Flocalhost%3A8080%2F&interval_sec=30&timeout_ms=1000' $cookieAdmin "POST /health_targets form" "application/x-www-form-urlencoded"
Run "$base/configuration" "POST" 'key=smoke.form&value=42&category=smoke&description=form' $cookieAdmin "POST /configuration form" "application/x-www-form-urlencoded"
Run "$base/api_tokens" "POST" 'name=smoke-form&scopes=read&expires_in_days=14' $cookieAdmin "POST /api_tokens form" "application/x-www-form-urlencoded"
Run "$base/audit/users" "POST" 'action=enable&user_id=3' $cookieAdmin "POST /audit/users form enable" "application/x-www-form-urlencoded"

Write-Output "==== logout ===="
Run "$base/logout" "POST" "" $cookieAdmin "logout admin" "application/x-www-form-urlencoded"
Run "$base/dashboard" "GET" "" $cookieAdmin "dashboard after logout (302)"

Write-Output "==== error log (only FATAL) ===="
Get-Content tmp_err.log -ErrorAction SilentlyContinue | Select-String -Pattern "Fatal" | Select-Object -First 50

Start-Sleep -Seconds 1
Get-Process -Id $proc.Id -ErrorAction SilentlyContinue | Stop-Process -Force