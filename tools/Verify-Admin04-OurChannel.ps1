param(
    [string]$ProjectRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "TradeX ADMIN-04 Our Channel Verification" -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot"
Write-Host ""

Set-Location $ProjectRoot

$requiredFiles = @(
    "routes\web.php",
    "routes\admin_our_channel.php",
    "app\Http\Controllers\Admin\OurChannel\ChannelVideoController.php",
    "app\Http\Requests\Admin\OurChannel\StoreChannelVideoRequest.php",
    "app\Http\Requests\Admin\OurChannel\UpdateChannelVideoRequest.php",
    "app\Http\Requests\Admin\OurChannel\UpdateChannelVideoPublicationRequest.php",
    "app\Models\ChannelVideo.php",
    "app\Support\YouTubeVideo.php",
    "database\migrations\2026_08_21_145100_create_channel_videos_table.php",
    "resources\views\admin\our_channel\index.blade.php",
    "resources\views\admin\our_channel\create.blade.php",
    "resources\views\admin\our_channel\edit.blade.php",
    "tests\Feature\Admin\OurChannel\ChannelVideoManagementTest.php",
    "tests\Unit\Support\YouTubeVideoTest.php"
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        throw "Missing required ADMIN-04 file: $file"
    }
}

Write-Host "[1/6] PHP syntax..." -ForegroundColor Yellow

$phpFiles = @(
    "routes\web.php",
    "routes\admin_our_channel.php",
    "app\Http\Controllers\Admin\OurChannel\ChannelVideoController.php",
    "app\Http\Requests\Admin\OurChannel\StoreChannelVideoRequest.php",
    "app\Http\Requests\Admin\OurChannel\UpdateChannelVideoRequest.php",
    "app\Http\Requests\Admin\OurChannel\UpdateChannelVideoPublicationRequest.php",
    "app\Models\ChannelVideo.php",
    "app\Support\YouTubeVideo.php",
    "database\migrations\2026_08_21_145100_create_channel_videos_table.php",
    "resources\views\admin\layouts\sidebar.blade.php",
    "resources\views\admin\our_channel\index.blade.php",
    "resources\views\admin\our_channel\create.blade.php",
    "resources\views\admin\our_channel\edit.blade.php",
    "tests\Feature\Admin\OurChannel\ChannelVideoManagementTest.php",
    "tests\Unit\Support\YouTubeVideoTest.php"
)

foreach ($file in $phpFiles) {
    php -l $file

    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $file"
    }
}

Write-Host "[2/6] Clear Laravel caches..." -ForegroundColor Yellow

php artisan optimize:clear

if ($LASTEXITCODE -ne 0) {
    throw "artisan optimize:clear failed."
}

Write-Host "[3/6] Run migrations..." -ForegroundColor Yellow

php artisan migrate --force

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-04 migration failed."
}

Write-Host "[4/6] Frozen ADMIN-03 tests..." -ForegroundColor Yellow

php artisan test --filter=VideoManagement

if ($LASTEXITCODE -ne 0) {
    throw "Frozen ADMIN-03 tests failed."
}

Write-Host "[5/6] Frozen ADMIN-03A / ADMIN-03B tests..." -ForegroundColor Yellow

php artisan test --filter=VideoUploadHlsIntegrationTest

if ($LASTEXITCODE -ne 0) {
    throw "Frozen ADMIN-03A tests failed."
}

php artisan test --filter=VideoProtectedPlaybackTest

if ($LASTEXITCODE -ne 0) {
    throw "Frozen ADMIN-03B tests failed."
}

php artisan test --filter=ProtectedHlsMediaServiceTest

if ($LASTEXITCODE -ne 0) {
    throw "Frozen ADMIN-03B media service tests failed."
}

Write-Host "[6/6] ADMIN-04 tests..." -ForegroundColor Yellow

php artisan test --filter=ChannelVideoManagementTest

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-04 Our Channel feature tests failed."
}

php artisan test --filter=YouTubeVideoTest

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-04 YouTube URL unit tests failed."
}

Write-Host ""
Write-Host "ADMIN-04 verification PASSED." -ForegroundColor Green
Write-Host ""
Write-Host "Manual smoke test:" -ForegroundColor Cyan
Write-Host "Admin -> Our Channel -> Add YouTube Video -> Create Draft -> Publish"
