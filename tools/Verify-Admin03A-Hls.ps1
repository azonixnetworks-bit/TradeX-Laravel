param(
    [string]$ProjectRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "TradeX ADMIN-03A Video Upload + HLS Verification" -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot"
Write-Host ""

Set-Location $ProjectRoot

$requiredFiles = @(
    "app\Http\Controllers\Admin\Education\VideoController.php",
    "app\Http\Requests\Admin\Education\StoreVideoRequest.php",
    "app\Jobs\ProcessVideo.php",
    "resources\views\admin\education\videos\create.blade.php",
    "config\tradex.php",
    "tests\Feature\Admin\Education\VideoUploadHlsIntegrationTest.php"
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        throw "Missing required ADMIN-03A file: $file"
    }
}

function Test-MediaBinary {
    param(
        [Parameter(Mandatory = $true)]
        [string]$CommandName,

        [Parameter(Mandatory = $true)]
        [string]$FriendlyName
    )

    $resolved = Get-Command $CommandName -ErrorAction SilentlyContinue

    if (-not $resolved) {
        throw "$FriendlyName is not available in PATH. Install it or configure the corresponding binary path in .env."
    }

    $output = & $resolved.Source -version 2>&1
    $exitCode = $LASTEXITCODE

    $firstLine = $output | Select-Object -First 1

    if ($firstLine) {
        Write-Host $firstLine
    }

    if ($exitCode -ne 0) {
        throw "$FriendlyName exists but its version check returned exit code $exitCode."
    }
}

Write-Host "[1/5] PHP syntax..." -ForegroundColor Yellow

$phpFiles = @(
    "app\Http\Controllers\Admin\Education\VideoController.php",
    "app\Http\Requests\Admin\Education\StoreVideoRequest.php",
    "app\Jobs\ProcessVideo.php",
    "config\tradex.php",
    "tests\Feature\Admin\Education\VideoUploadHlsIntegrationTest.php"
)

foreach ($file in $phpFiles) {
    php -l $file

    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $file"
    }
}

Write-Host "[2/5] FFmpeg..." -ForegroundColor Yellow
Test-MediaBinary -CommandName "ffmpeg" -FriendlyName "FFmpeg"

Write-Host "[3/5] FFprobe..." -ForegroundColor Yellow
Test-MediaBinary -CommandName "ffprobe" -FriendlyName "FFprobe"

Write-Host "[4/5] Clear cached config..." -ForegroundColor Yellow
php artisan optimize:clear

if ($LASTEXITCODE -ne 0) {
    throw "artisan optimize:clear failed."
}

Write-Host "[5/5] ADMIN-03 + ADMIN-03A tests..." -ForegroundColor Yellow

php artisan test --filter=VideoManagement

if ($LASTEXITCODE -ne 0) {
    throw "Existing ADMIN-03 VideoManagement tests failed."
}

php artisan test --filter=VideoUploadHlsIntegrationTest

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-03A upload integration tests failed."
}

Write-Host ""
Write-Host "ADMIN-03A verification PASSED." -ForegroundColor Green
Write-Host ""
Write-Host "Queue worker command:" -ForegroundColor Cyan
Write-Host "php artisan queue:work --queue=video-processing,default --timeout=7200 --tries=2"
