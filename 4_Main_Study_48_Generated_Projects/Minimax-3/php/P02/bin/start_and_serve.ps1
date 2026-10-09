$port = 8080
$root = "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P02"
$logOut = "$root\storage\server.out"
$logErr = "$root\storage\server.err"
"" | Out-File -FilePath $logOut
"" | Out-File -FilePath $logErr

# Reset DB
php "$root\bin\reset_db.php" | Out-Null
Write-Host "DB reset OK"

# Start the PHP server using Start-Process with -NoNewWindow to keep it in the background
$proc = Start-Process -FilePath "php" -ArgumentList @("-S", "127.0.0.1:$port", "-t", "public", "public/index.php") -WorkingDirectory $root -RedirectStandardOutput $logOut -RedirectStandardError $logErr -PassThru -WindowStyle Hidden
Write-Host "Started PHP server PID=$($proc.Id)"

# Wait for server to start
$listen = $null
for ($i=0; $i -lt 15; $i++) {
    Start-Sleep -Seconds 1
    $listen = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($listen) { break }
}
if ($listen) {
    Write-Host "Server listening on port $port" -ForegroundColor Green
} else {
    Write-Host "Server NOT listening" -ForegroundColor Red
    Get-Content $logErr | Select-Object -Last 10
    exit 1
}

# Don't kill it - let it run
Write-Host "Server is running in background. PID=$($proc.Id)"
Write-Host "Visit: http://127.0.0.1:$port/"
Write-Host "Login: chair1 / Password123!"
Write-Host ""
Write-Host "Press Enter to stop the server..."
[void][Console]::ReadLine()
Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
Write-Host "Server stopped."