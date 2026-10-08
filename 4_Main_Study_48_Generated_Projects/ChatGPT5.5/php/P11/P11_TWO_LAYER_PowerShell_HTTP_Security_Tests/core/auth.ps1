function Login-HostingUser {
    param([Parameter(Mandatory=$true)][string]$Role)

    if (-not $USERS.ContainsKey($Role)) { throw "Unknown seeded role: $Role" }
    $u=$USERS[$Role]
    if ($u.status -ne "active") { throw "$Role is not an active login fixture." }

    $session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $body=@{email=$u.email;password=$u.password}|ConvertTo-Json -Compress

    try {
        $response=Invoke-RestMethod `
            -Uri "$BASE_URL/api/auth/login" `
            -Method POST `
            -ContentType "application/json" `
            -Body $body `
            -WebSession $session `
            -ErrorAction Stop

        $cookie=$session.Cookies.GetCookies([uri]$BASE_URL) |
            Where-Object {$_.Name -eq $SESSION_COOKIE} |
            Select-Object -First 1

        if ($null -eq $response.user) { throw "No user object." }
        if ($null -eq $cookie) { throw "No $SESSION_COOKIE cookie." }
        if ([string]$response.user.email -ne [string]$u.email) { throw "Identity mismatch." }

        [pscustomobject]@{Session=$session;User=$response.user;Cookie=$cookie}
    } catch {
        throw "Login failed for $Role : $($_.Exception.Message)"
    }
}
