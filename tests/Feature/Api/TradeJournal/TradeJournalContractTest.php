<?php

namespace Tests\Feature\Api\TradeJournal;

use Tests\TestCase;

class TradeJournalContractTest extends TestCase
{
    public function test_trade_journal_v1_is_user_api_only_and_wired_through_mobile_security(): void
    {
        $providers = file_get_contents(base_path('bootstrap/providers.php'));
        $routes = file_get_contents(base_path('routes/api_trade_journal.php'));
        $config = file_get_contents(base_path('config/trade_journal.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_09_06_090000_create_trade_journal_tables.php'));

        $this->assertStringContainsString('TradeJournalServiceProvider::class', $providers);
        $this->assertStringContainsString("middleware(['auth:sanctum', 'tradex.mobile'])", $routes);
        $this->assertStringContainsString("Route::prefix('trade-journal')", $routes);
        $this->assertStringContainsString("'/home'", $routes);
        $this->assertStringContainsString("'/analytics'", $routes);
        $this->assertStringContainsString("'/reviews/{type}'", $routes);
        $this->assertStringContainsString("'/{uuid}/open'", $routes);
        $this->assertStringContainsString("'/{uuid}/close'", $routes);
        $this->assertStringContainsString("'/{uuid}/cancel'", $routes);
        $this->assertStringContainsString("'/{uuid}/screenshots/{type}'", $routes);

        $this->assertStringContainsString("'max_trades_per_day' => 3", $config);
        $this->assertStringContainsString("'stop_after_wins' => 2", $config);
        $this->assertStringNotContainsString('minimum_rr', $config);
        $this->assertStringContainsString('there is intentionally', strtolower($config));

        $this->assertStringContainsString("Schema::create('trade_journal_entries'", $migration);
        $this->assertStringContainsString("Schema::create('trade_journal_reviews'", $migration);
        $this->assertStringContainsString("$table->softDeletes()", $migration);

        $this->assertFileDoesNotExist(base_path('routes/admin_trade_journal.php'));
    }

    public function test_journal_private_media_never_exposes_public_storage_paths(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/TradeJournal/TradeJournalScreenshotController.php'));
        $filesystems = file_get_contents(base_path('config/filesystems.php'));

        $this->assertStringContainsString("config('trade_journal.screenshots.disk', 'private')", $controller);
        $this->assertStringContainsString("'root' => storage_path('app/private')", $filesystems);
        $this->assertStringContainsString("'Cache-Control' => 'private, no-store, no-cache, max-age=0'", $controller);
        $this->assertStringNotContainsString("Storage::disk('public')", $controller);
    }
}
