param(
    [string]$ProjectRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "TradeX ADMIN-05A Distribution & Share Verification" -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot"
Write-Host ""

Set-Location $ProjectRoot

$requiredFiles = @(
    ".env.example",
    "routes\admin_trade_ideas.php",
    "config\tradex_trade_ideas.php",
    "app\Models\TradeIdea.php",
    "app\Models\TradeIdeaDistribution.php",
    "app\Exceptions\TradeIdeaDistributionException.php",
    "app\Http\Controllers\Admin\TradeIdeas\TradeIdeaController.php",
    "app\Services\TradeIdeas\TradeIdeaDistributionManager.php",
    "app\Services\TradeIdeas\TradeIdeaDistributionPayloadBuilder.php",
    "app\Services\TradeIdeas\TradeIdeaShareCardService.php",
    "app\Services\TradeIdeas\TelegramTradeIdeaClient.php",
    "app\Services\TradeIdeas\WhatsAppTradeIdeaClient.php",
    "app\Jobs\GenerateTradeIdeaShareImage.php",
    "app\Jobs\SendTelegramTradeIdea.php",
    "app\Jobs\SendWhatsAppTradeIdea.php",
    "database\migrations\2026_08_21_171000_create_trade_idea_distributions_table.php",
    "resources\views\admin\trade_ideas\show.blade.php",
    "tests\Feature\Admin\TradeIdeas\TradeIdeaDistributionTest.php",
    "tests\Unit\TradeIdeas\TradeIdeaDistributionPayloadBuilderTest.php",
    "tests\Unit\TradeIdeas\TradeIdeaShareCardServiceTest.php",
    "tests\Unit\TradeIdeas\TradeIdeaTransportClientTest.php"
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        throw "Missing required ADMIN-05A file: $file"
    }
}

Write-Host "[1/8] PHP / Blade syntax..." -ForegroundColor Yellow

foreach ($file in $requiredFiles) {
    if ($file -like "*.php") {
        php -l $file

        if ($LASTEXITCODE -ne 0) {
            throw "PHP syntax check failed: $file"
        }
    }
}

Write-Host "[2/8] Clear Laravel caches..." -ForegroundColor Yellow

php artisan optimize:clear

if ($LASTEXITCODE -ne 0) {
    throw "artisan optimize:clear failed."
}

Write-Host "[3/8] Run ADMIN-05A migration..." -ForegroundColor Yellow

php artisan migrate --force

if ($LASTEXITCODE -ne 0) {
    throw "ADMIN-05A migration failed."
}

Write-Host "[4/8] Confirm Trade Idea routes..." -ForegroundColor Yellow

php artisan route:list --name=admin.trade_ideas

if ($LASTEXITCODE -ne 0) {
    throw "Trade Idea route inventory failed."
}

Write-Host "[5/8] Frozen ADMIN-03 / 03A / 03B..." -ForegroundColor Yellow

php artisan test --filter=VideoManagement
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03 tests failed." }

php artisan test --filter=VideoUploadHlsIntegrationTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03A tests failed." }

php artisan test --filter=VideoProtectedPlaybackTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03B tests failed." }

php artisan test --filter=ProtectedHlsMediaServiceTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-03B media tests failed." }

Write-Host "[6/8] Frozen ADMIN-04..." -ForegroundColor Yellow

php artisan test --filter=ChannelVideoManagementTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-04 feature tests failed." }

php artisan test --filter=YouTubeVideoTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-04 YouTube tests failed." }

Write-Host "[7/8] Frozen ADMIN-05 REV3..." -ForegroundColor Yellow

php artisan test --filter=TradeIdeaStructureValidatorTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-05 structure tests failed." }

php artisan test --filter=TradingViewUrlTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-05 TradingView tests failed." }

php artisan test --filter=TradeIdeaManagementTest
if ($LASTEXITCODE -ne 0) { throw "Frozen ADMIN-05 management tests failed." }

Write-Host "[8/8] ADMIN-05A..." -ForegroundColor Yellow

php artisan test --filter=TradeIdeaDistributionPayloadBuilderTest
if ($LASTEXITCODE -ne 0) { throw "ADMIN-05A payload tests failed." }

php artisan test --filter=TradeIdeaShareCardServiceTest
if ($LASTEXITCODE -ne 0) { throw "ADMIN-05A share-card tests failed." }

php artisan test --filter=TradeIdeaTransportClientTest
if ($LASTEXITCODE -ne 0) { throw "ADMIN-05A transport tests failed." }

php artisan test --filter=TradeIdeaDistributionTest
if ($LASTEXITCODE -ne 0) { throw "ADMIN-05A distribution feature tests failed." }

Write-Host ""
Write-Host "ADMIN-05A verification PASSED." -ForegroundColor Green
Write-Host ""
Write-Host "External live sends remain OFF unless .env explicitly enables providers." -ForegroundColor Cyan
