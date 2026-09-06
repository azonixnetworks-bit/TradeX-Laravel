<?php

namespace Tests\Feature\Api\TradeJournal;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeJournalScreenshotApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_before_screenshot_is_private_and_owner_scoped(): void
    {
        Storage::fake('private');
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));

        $owner = $this->createApiUser();
        $other = $this->createApiUser();
        $ownerToken = $this->issueBoundToken($owner);
        $otherToken = $this->issueBoundToken($other);

        $created = $this->withToken($ownerToken)->postJson('/api/v1/trade-journal', [
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'direction' => 'buy',
            'status' => 'planned',
            'trade_date' => '2026-09-01',
            'trade_time' => '09:00',
            'timezone' => 'UTC',
            'entry_reason' => 'Screenshot privacy test.',
        ])->assertCreated();

        $uuid = (string) $created->json('data.uuid');

        $upload = $this->withToken($ownerToken)->post(
            '/api/v1/trade-journal/'.$uuid.'/screenshots/before',
            ['image' => UploadedFile::fake()->image('before.png', 640, 360)]
        );

        $upload->assertCreated()->assertJsonPath('data.available', true);

        $this->withToken($ownerToken)
            ->get('/api/v1/trade-journal/'.$uuid.'/screenshots/before')
            ->assertOk()
            ->assertHeader('Cache-Control');

        $this->withToken($otherToken)
            ->get('/api/v1/trade-journal/'.$uuid.'/screenshots/before')
            ->assertNotFound();
    }
}
