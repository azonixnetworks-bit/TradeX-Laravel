<?php

namespace Tests\Feature\Api\TradeIdeas;

use App\Models\TradeIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicTradeResultApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_explicitly_published_completed_results_are_public(): void
    {
        Storage::fake('private');

        $visible = $this->tradeIdea([
            'title' => 'Completed EURUSD idea',
            'show_on_website' => true,
            'chart_image_path' => 'trade-ideas/before.jpg',
            'after_chart_image_disk' => 'private',
            'after_chart_image_path' => 'trade-ideas/after.jpg',
            'after_chart_image_mime_type' => 'image/jpeg',
        ]);

        $this->tradeIdea(['title' => 'Private completed idea']);
        $this->tradeIdea([
            'title' => 'Incomplete public idea',
            'show_on_website' => true,
            'after_chart_image_path' => null,
        ]);

        Storage::disk('private')->put('trade-ideas/before.jpg', 'before');
        Storage::disk('private')->put('trade-ideas/after.jpg', 'after');

        $this->getJson('/api/public/trade-results')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $visible->uuid)
            ->assertJsonPath('data.0.outcome', TradeIdea::OUTCOME_WIN);

        $this->get('/api/public/trade-results/'.$visible->id.'/before')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    private function tradeIdea(array $overrides = []): TradeIdea
    {
        return TradeIdea::query()->create(array_merge([
            'title' => 'Trade result',
            'summary' => 'Summary',
            'category' => TradeIdea::CATEGORY_FOREX,
            'symbol' => 'EURUSD',
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H1',
            'status' => TradeIdea::STATUS_CLOSED,
            'published_at' => now()->subDay(),
            'closed_at' => now(),
            'close_outcome' => TradeIdea::OUTCOME_WIN,
            'close_note' => 'Target completed.',
            'show_on_website' => false,
            'chart_image_disk' => 'private',
            'chart_image_path' => 'trade-ideas/default-before.jpg',
            'chart_image_mime_type' => 'image/jpeg',
        ], $overrides));
    }
}
