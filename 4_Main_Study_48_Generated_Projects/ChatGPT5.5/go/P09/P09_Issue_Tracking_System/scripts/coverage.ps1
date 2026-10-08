$ErrorActionPreference = "Stop"
New-Item -ItemType Directory -Force -Path coverage | Out-Null
if (-not $env:BRANCH_THRESHOLD) { $env:BRANCH_THRESHOLD = "85" }

Write-Host "== Functional tests + Go line/statement coverage =="
go test ./tests/function -count=1 -coverpkg=./internal/app -covermode=atomic -coverprofile=coverage/line.out 2>&1 | Tee-Object -FilePath coverage/functional-test.log
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

go tool cover -func=coverage/line.out | Tee-Object -FilePath coverage/line.txt
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
go tool cover -html=coverage/line.out -o coverage/line.html
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$totalLine = (go tool cover -func=coverage/line.out | Select-String '^total:').ToString()
Write-Host $totalLine

Write-Host "`n== Instrumented branch-outcome coverage =="
go run ./tools/branchcov -target internal/app -tests ./tests/function -threshold $env:BRANCH_THRESHOLD -out coverage/branch.json 2>&1 | Tee-Object -FilePath coverage/branch.txt
$branchExit = $LASTEXITCODE

Write-Host "`nArtifacts:"
Write-Host "  coverage/line.out"
Write-Host "  coverage/line.html"
Write-Host "  coverage/line.txt"
Write-Host "  coverage/branch.json"
Write-Host "  coverage/branch.txt"
exit $branchExit
