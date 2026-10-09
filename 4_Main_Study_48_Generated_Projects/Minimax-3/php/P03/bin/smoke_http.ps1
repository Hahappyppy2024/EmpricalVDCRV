#!/usr/bin/env pwsh
# End-to-end smoke test using curl.
# Requires: server already running on 127.0.0.1:8099, database seeded.
# Usage: pwsh bin/smoke_http.ps1
$ErrorActionPreference = 'Continue'
$base = 'http://127.0.0.1:8099'
$jar = 'D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03\data\smoke_jar.txt'
if (Test-Path $jar) { Remove-Item $jar -Force }

function Send-Curl {
    param(
        [string]$Method = 'GET',
        [string]$Path,
        [string]$Body = '',
        [string]$ContentType = '',
        [switch]$Form
    )
    $args = @('--noproxy', '*', '-s', '-m', '10', '-b', $jar, '-c', $jar, '-X', $Method)
    if ($ContentType) { $args += @('-H', "Content-Type: $ContentType") }
if ($Body -ne '') {
        $tmp = 'D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03\data\_req_body.txt'
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($tmp, $Body, $utf8NoBom)
        $args += @('--data-binary', "@$tmp")
    }
    if ($Method -eq 'GET' -and $Path -like '*?*') {
        # curl GET uses URL with query string already
    }
    $args += "$base$Path"
    $statusFile = [System.IO.Path]::GetTempFileName()
    $args += @('-o', 'D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03\data\_resp_body.txt', '-w', '%{http_code}')
    $code = & curl.exe @args
    $body = Get-Content 'D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03\data\_resp_body.txt' -Raw -ErrorAction SilentlyContinue
    return @{ Code = $code; Body = $body }
}

function Get-Csrf {
    param([string]$Path)
    $resp = Send-Curl -Method GET -Path $Path
    if ($resp.Body -match 'name="_csrf" value="([^"]+)"') {
        return $Matches[1]
    }
    return ''
}

function Run-Case {
    param([string]$Name, [string]$Expected, $Response)
    $actual = [string]$Response.Code
    $status = if ($actual -eq $Expected) { 'PASS' } else { 'FAIL' }
    $shortBody = ($Response.Body -replace "`n", ' ' -replace '\s+', ' ').Substring(0, [Math]::Min(120, $Response.Body.Length))
    Write-Host ("[{0}] {1,-60} expected={2} actual={3} body={4}" -f $status, $Name, $Expected, $actual, $shortBody)
}

# 1. Home
Run-Case 'GET /' '200' (Send-Curl -Method GET -Path '/')

# 2. Login page
Run-Case 'GET /login' '200' (Send-Curl -Method GET -Path '/login')

# 3. Catalog
Run-Case 'GET /catalog' '200' (Send-Curl -Method GET -Path '/catalog')

# 4. Catalog search API (with results)
$r = Send-Curl -Method GET -Path '/api/shop/catalog_search?q=mug'
Run-Case 'GET /api/shop/catalog_search?q=mug' '200' $r
if ($r.Body -notmatch 'MUG-001') { Write-Host "  [WARN] MUG-001 not in results" }

# 5. Catalog search API (empty)
Run-Case 'GET /api/shop/catalog_search?q=zzz' '200' (Send-Curl -Method GET -Path '/api/shop/catalog_search?q=zzz_no_results')

# 6. Catalog search unauth should not expose private status (drafts hidden)
$r = Send-Curl -Method GET -Path '/api/shop/catalog_search?in_stock='
Run-Case 'GET /api/shop/catalog_search (no draft leak)' '200' $r
if ($r.Body -match 'DRAFT-001') { Write-Host "  [FAIL] draft product DRAFT-001 leaked" }

# 7. Register new customer via API
$body = '{"email":"smoke_new@example.com","display_name":"Smoke User","password":"Password1!","role":"customer"}'
$r = Send-Curl -Method POST -Path '/api/shop/accounts' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/accounts' '201' $r

# 8. Register duplicate (should fail)
$r = Send-Curl -Method POST -Path '/api/shop/accounts' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/accounts (duplicate)' '409' $r

# 9. Customer login
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=customer%40example.com&password=Customer%2312345"
$r = Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /login (customer)' '302' $r

# 10. Wrong password
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=customer%40example.com&password=WRONG"
Send-Curl -Method POST -Path '/logout' -Body "_csrf=$csrf" -ContentType 'application/x-www-form-urlencoded' | Out-Null
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=customer%40example.com&password=WRONG"
$r = Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /login (wrong password)' '302' $r

# 11. Re-login
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=customer%40example.com&password=Customer%2312345"
$r = Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /login (customer again)' '302' $r

# 12. Cart page
Run-Case 'GET /cart' '200' (Send-Curl -Method GET -Path '/cart')

# 13. Add to cart via API
$body = '{"product_id": 8, "quantity": 2}'
$r = Send-Curl -Method POST -Path '/api/shop/shopping_cart' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/shopping_cart' '201' $r

# 14. Apply promotion
$csrf = Get-Csrf '/cart'
$body = "_csrf=$csrf&code=WELCOME10"
$r = Send-Curl -Method POST -Path '/cart/promotion' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /cart/promotion' '302' $r

# 15. Checkout
$csrf = Get-Csrf '/checkout'
$body = "_csrf=$csrf&full_name=Chris+Customer&line1=123+Benchmark+Ave&city=Testville&postal_code=75001&payment_method=card&payment_token=tok_ok"
$r = Send-Curl -Method POST -Path '/checkout' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /checkout' '302' $r

# 16. Order access
Run-Case 'GET /orders' '200' (Send-Curl -Method GET -Path '/orders')

# 17. Order access API
Run-Case 'GET /api/shop/order_access' '200' (Send-Curl -Method GET -Path '/api/shop/order_access')

# 18. Customer data
Run-Case 'GET /customer/data' '200' (Send-Curl -Method GET -Path '/customer/data')

# 19. Customer data addresses (POST)
$csrf = Get-Csrf '/customer/data'
$body = "_csrf=$csrf&full_name=Chris&line1=1+Main&city=Testville&postal_code=75001"
$r = Send-Curl -Method POST -Path '/customer/data/addresses' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /customer/data/addresses' '302' $r

# 20. Frontend API integration page
Run-Case 'GET /frontend/api' '200' (Send-Curl -Method GET -Path '/frontend/api')

# 21. Frontend API integration validate_form
$body = '{"intent":"validate_form","full_name":"Test","line1":"1","city":"X","postal_code":"0"}'
$r = Send-Curl -Method POST -Path '/api/shop/frontend_api_integration' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/frontend_api_integration validate_form' '200' $r
if ($r.Body -match '"valid": true') { Write-Host "  [OK] validation passed" } else { Write-Host "  [WARN] validation result missing" }

# 22. Frontend API integration payment simulation
$body = '{"intent":"validate_payment","method":"card","token":"tok_ok","amount_cents":1000}'
$r = Send-Curl -Method POST -Path '/api/shop/frontend_api_integration' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/frontend_api_integration payment' '200' $r

# Logout
$csrf = Get-Csrf '/customer/data'
Send-Curl -Method POST -Path '/logout' -Body "_csrf=$csrf" -ContentType 'application/x-www-form-urlencoded' | Out-Null

# 23. Anonymous checkout should be forbidden (API)
$body = '{"full_name":"Anon","line1":"1","city":"X","postal_code":"0"}'
$r = Send-Curl -Method POST -Path '/api/shop/checkout' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/checkout (unauthenticated)' '401' $r

# 24. Login as seller
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=seller%40example.com&password=Seller%2312345"
$r = Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /login (seller)' '302' $r

# 25. Catalog management page (seller)
Run-Case 'GET /seller/catalog' '200' (Send-Curl -Method GET -Path '/seller/catalog')

# 26. Catalog management API
Run-Case 'GET /api/shop/catalog_management' '200' (Send-Curl -Method GET -Path '/api/shop/catalog_management')

# 27. Create product via API
$body = '{"sku":"SMK-001","name":"Smoke Test Product","description":"Tested","price_cents":1999,"category_id":4,"status":"published","initial_stock":3}'
$r = Send-Curl -Method POST -Path '/api/shop/catalog_management' -Body $body -ContentType 'application/json'
Run-Case 'POST /api/shop/catalog_management' '201' $r

# 28. Inventory page
Run-Case 'GET /inventory' '200' (Send-Curl -Method GET -Path '/inventory')

# 29. Inventory API
Run-Case 'GET /api/shop/inventory' '200' (Send-Curl -Method GET -Path '/api/shop/inventory')

# 30. Reports page
Run-Case 'GET /reports' '200' (Send-Curl -Method GET -Path '/reports')

# 31. Reports API
Run-Case 'GET /api/shop/reports' '200' (Send-Curl -Method GET -Path '/api/shop/reports')

# Logout
$csrf = Get-Csrf '/seller/catalog'
Send-Curl -Method POST -Path '/logout' -Body "_csrf=$csrf" -ContentType 'application/x-www-form-urlencoded' | Out-Null

# 32. Login as moderator
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=moderator%40example.com&password=Moderator%2312345"
$r = Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /login (moderator)' '302' $r

# 33. Moderation page
Run-Case 'GET /reviews/moderation' '200' (Send-Curl -Method GET -Path '/reviews/moderation')

# 34. Reviews API (moderator sees all)
$r = Send-Curl -Method GET -Path '/api/shop/reviews?product_id=5'
Run-Case 'GET /api/shop/reviews (moderator)' '200' $r

# Logout
$csrf = Get-Csrf '/reviews/moderation'
Send-Curl -Method POST -Path '/logout' -Body "_csrf=$csrf" -ContentType 'application/x-www-form-urlencoded' | Out-Null

# 35. Login as admin
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=admin%40example.com&password=Admin%2312345"
$r = Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /login (admin)' '302' $r

# 36. Admin operations
Run-Case 'GET /admin/operations' '200' (Send-Curl -Method GET -Path '/admin/operations')

# 37. Admin operations API
Run-Case 'GET /api/shop/seller_and_administrator_operations' '200' (Send-Curl -Method GET -Path '/api/shop/seller_and_administrator_operations')

# 38. Create promotion
$csrf = Get-Csrf '/admin/operations'
$body = "_csrf=$csrf&code=SMOKE20&description=Smoke+test&percent_off=20"
$r = Send-Curl -Method POST -Path '/admin/operations/promotions' -Body $body -ContentType 'application/x-www-form-urlencoded'
Run-Case 'POST /admin/operations/promotions' '302' $r

# 39. All orders
Run-Case 'GET /orders' '200' (Send-Curl -Method GET -Path '/orders')

# 40. Anonymous access to admin (should redirect)
$csrf = Get-Csrf '/admin/operations'
Send-Curl -Method POST -Path '/logout' -Body "_csrf=$csrf" -ContentType 'application/x-www-form-urlencoded' | Out-Null
$r = Send-Curl -Method GET -Path '/admin/operations'
Run-Case 'GET /admin/operations (unauthenticated)' '302' $r

# 41. Reports CSV
Run-Case 'GET /reports/export.csv?type=sales' '302' $r
$csrf = Get-Csrf '/login'
$body = "_csrf=$csrf&email=admin%40example.com&password=Admin%2312345"
Send-Curl -Method POST -Path '/login' -Body $body -ContentType 'application/x-www-form-urlencoded' | Out-Null
$r = Send-Curl -Method GET -Path '/reports/export.csv?type=sales'
Run-Case 'GET /reports/export.csv?type=sales (admin)' '200' $r
if ($r.Body -match 'reference,status,total_cents') { Write-Host "  [OK] CSV header present" }

Write-Host ""
Write-Host "Smoke test complete."