param(
    [string]$ProjectRoot = "."
)

$ErrorActionPreference = "Stop"
$ProjectRoot = (Resolve-Path $ProjectRoot).Path
Set-Location $ProjectRoot

Write-Host "=== ADMIN-03 FINAL HARDENING CHECK ==="

Write-Host "`n[1/8] Clear optimized caches"
php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[2/8] PHP syntax - hardening PHP files"
$phpFiles = @(
    "config/filesystems.php",
    "app/Http/Requests/Admin/Education/StoreVideoRequest.php",
    "app/Http/Requests/Admin/Education/UpdateVideoRequest.php",
    "app/Http/Requests/Admin/Education/StoreAssignmentRequest.php",
    "app/Http/Requests/Admin/Education/UpdateAssignmentRequest.php",
    "tests/Feature/Admin/Education/EducationManagementHardeningTest.php",
    "routes/admin.php"
)

foreach ($file in $phpFiles) {
    php -l $file
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "`n[3/8] Detect malformed Blade comments"
$badBlade = Get-ChildItem "resources/views/admin/education" -Recurse -Filter "*.blade.php" |
    Select-String -Pattern '^\{--'

if ($badBlade) {
    $badBlade | ForEach-Object { Write-Host $_ }
    throw "Malformed Blade comment marker found."
}

Write-Host "No malformed Blade comment markers found."

Write-Host "`n[4/8] Compile Blade views"
php artisan view:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan view:cache
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[5/8] Verify Education routes"
php artisan route:list --name=admin.education
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[6/8] Run ADMIN-03 Education feature tests"
php artisan test tests/Feature/Admin/Education
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[7/8] Run full Feature suite"
php artisan test --testsuite=Feature
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[8/8] Run full application test suite"
php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nADMIN-03 FINAL HARDENING: ALL LOCAL TESTS PASSED."
