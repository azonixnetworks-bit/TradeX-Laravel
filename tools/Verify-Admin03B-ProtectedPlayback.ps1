param(
    [string]$ProjectRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "TradeX ADMIN-03B Protected HLS Preview Verification" -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot"
Write-Host ""

Set-Location $ProjectRoot

$requiredFiles = @(
    "routes\web.php",
    "routes\admin_video_playback.php",
    "app\Http\Controllers\Admin\Education\VideoPlaybackController.php",
    "app\Services\Education\ProtectedHlsMediaService.php",
    "resources\views\admin\education\videos\preview.blade.php",
    "tests\Feature\Admin\Education\VideoProtectedPlaybackTest.php",
    "tests\Unit\Education\ProtectedHlsMediaServiceTest.php"
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        throw "Missing required ADMIN-03B file: $file"
    }
}

Write-Host "[1/5] PHP syntax..." -ForegroundColor Yellow

$phpFiles = @(
    "routes\web.php",
    "routes\admin_video_playback.php",
    "app\Http\Controllers\Admin\Education\VideoPlaybackController.php",
    "app\Services\Education\ProtectedHlsMediaService.php",
    "resources\views\admin\education\videos\preview.blade.php",
    "resources\views\admin\education\videos\index.blade.php",
    "tests\Feature\Admin\Education\VideoProtectedPlaybackTest.php",
    "tests\Unit\Education\ProtectedHlsMediaServiceTest.php"
)

foreach ($file in $phpFiles) {
    php -l $file

    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $file"
    }
}

Write-Host "[2/5] Clear Laravel caches..." -ForegroundColor Yellow

php artisan optimize:clear

if ($LASTEXITCODE -ne 0) {
    throw "artisan optimize:clear failed."
}

Write-Host "[3/5] Frozen ADMIN-03 tests..." -ForegroundColor Yellow

php artisan test --filter=VideoManagement

if ($LASTEXITCODE -ne 0) {
    throw "Existing ADMIN-03 VideoManagement tests failed."
}

Write-Host "[4/5] Frozen ADMIN-03A tests..." -ForegroundColor Yellow

php artisan test --filter=VideoUploadHlsIntegrationTest

if ($LASTEXITCODE -ne 0) {
    throw "Existing ADMIN-03A upload/HLS tests failed."
}

Write-Host "[5/5] ADMIN-03B protected playback tests..." -ForegroundColor Yellow

php artisan test --filter=VideoProtectedPlaybackTest

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-03B feature tests failed."
}

php artisan test --filter=ProtectedHlsMediaServiceTest

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-03B media service tests failed."
}

Write-Host ""
Write-Host "ADMIN-03B verification PASSED." -ForegroundColor Green
Write-Host ""
Write-Host "Manual smoke test:" -ForegroundColor Cyan
Write-Host "Admin -> Education -> Course -> Module -> Lesson -> Videos -> Preview Protected HLS"
