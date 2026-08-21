param(
    [string]$ProjectRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "TradeX ADMIN-05 REV3 Verification" -ForegroundColor Cyan
Write-Host "Global Trade Ideas + Optional Structure/Context + Chart Media"
Write-Host "Project: $ProjectRoot"
Write-Host ""

Set-Location $ProjectRoot

$requiredFiles = @(
    "routes\web.php",
    "routes\admin_trade_ideas.php",
    "app\Models\TradeIdea.php",
    "app\Models\TradeIdeaTarget.php",
    "app\Models\TradeIdeaUpdate.php",
    "app\Services\TradeIdeas\TradeIdeaStructureValidator.php",
    "app\Support\TradingViewUrl.php",
    "app\Http\Controllers\Admin\TradeIdeas\TradeIdeaController.php",
    "app\Http\Requests\Admin\TradeIdeas\TradeIdeaPayloadRequest.php",
    "app\Http\Requests\Admin\TradeIdeas\StoreTradeIdeaRequest.php",
    "app\Http\Requests\Admin\TradeIdeas\UpdateTradeIdeaRequest.php",
    "app\Http\Requests\Admin\TradeIdeas\UpdateTradeIdeaPublicationRequest.php",
    "app\Http\Requests\Admin\TradeIdeas\StoreTradeIdeaUpdateRequest.php",
    "app\Http\Requests\Admin\TradeIdeas\CloseTradeIdeaRequest.php",
    "database\migrations\2026_08_21_155900_create_trade_ideas_table.php",
    "database\migrations\2026_08_21_160000_create_trade_idea_targets_table.php",
    "database\migrations\2026_08_21_160100_create_trade_idea_updates_table.php",
    "database\migrations\2026_08_21_160200_add_media_fields_to_trade_ideas_table.php",
    "database\migrations\2026_08_21_160300_make_trade_structure_optional_and_remove_language_validity.php",
    "resources\views\admin\layouts\sidebar.blade.php",
    "resources\views\admin\trade_ideas\index.blade.php",
    "resources\views\admin\trade_ideas\create.blade.php",
    "resources\views\admin\trade_ideas\edit.blade.php",
    "resources\views\admin\trade_ideas\show.blade.php",
    "resources\views\admin\trade_ideas\_form.blade.php",
    "tests\Feature\Admin\TradeIdeas\TradeIdeaManagementTest.php",
    "tests\Unit\TradeIdeas\TradeIdeaStructureValidatorTest.php",
    "tests\Unit\TradeIdeas\TradingViewUrlTest.php"
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        throw "Missing required ADMIN-05 REV3 file: $file"
    }
}

Write-Host "[1/7] PHP / Blade syntax..." -ForegroundColor Yellow

foreach ($file in $requiredFiles) {
    if ($file -like "*.php") {
        php -l $file

        if ($LASTEXITCODE -ne 0) {
            throw "PHP syntax check failed: $file"
        }
    }
}

Write-Host "[2/7] Clear Laravel caches..." -ForegroundColor Yellow

php artisan optimize:clear

if ($LASTEXITCODE -ne 0) {
    throw "artisan optimize:clear failed."
}

Write-Host "[3/7] Run migrations..." -ForegroundColor Yellow

php artisan migrate --force

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-05 REV3 migrations failed."
}

Write-Host "[4/7] Frozen ADMIN-03 / 03A / 03B..." -ForegroundColor Yellow

php artisan test --filter=VideoManagement
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03 tests failed." }

php artisan test --filter=VideoUploadHlsIntegrationTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03A tests failed." }

php artisan test --filter=VideoProtectedPlaybackTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03B tests failed." }

php artisan test --filter=ProtectedHlsMediaServiceTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03B media tests failed." }

Write-Host "[5/7] Frozen ADMIN-04..." -ForegroundColor Yellow

php artisan test --filter=ChannelVideoManagementTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-04 feature tests failed." }

php artisan test --filter=YouTubeVideoTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-04 YouTube tests failed." }

Write-Host "[6/7] ADMIN-05 REV3 unit tests..." -ForegroundColor Yellow

php artisan test --filter=TradeIdeaStructureValidatorTest
if ($LASTEXITCODE -ne 0) { throw "Optional Trade Structure tests failed." }

php artisan test --filter=TradingViewUrlTest
if ($LASTEXITCODE -ne 0) { throw "TradingView URL tests failed." }

Write-Host "[7/7] ADMIN-05 REV3 feature tests..." -ForegroundColor Yellow

php artisan test --filter=TradeIdeaManagementTest

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-05 REV3 Trade Idea feature tests failed."
}

Write-Host ""
Write-Host "ADMIN-05 REV3 verification PASSED." -ForegroundColor Green
Write-Host ""
Write-Host "Manual smoke:" -ForegroundColor Cyan
Write-Host "1. Create idea with only title/category/symbol/direction/timeframe."
Write-Host "2. Confirm no Language Code and no Valid Until fields."
Write-Host "3. Publish with Trade Structure and Context completely empty."
Write-Host "4. Create another idea with optional image + TradingView + Entry/SL/TP."
Write-Host "5. Confirm both flows work."
