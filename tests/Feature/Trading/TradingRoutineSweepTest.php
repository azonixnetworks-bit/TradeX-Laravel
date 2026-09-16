<?php

namespace Tests\Feature\Trading;

use App\Contracts\Trading\TradingRoutineWhatsAppGateway;
use App\Models\TradingRoutineDailyPlan;
use App\Models\TradingRoutineDelivery;
use App\Models\TradingRoutinePreference;
use App\Services\Trading\TradingRoutineSweepService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradingRoutineSweepTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_due_customer_gets_one_plan_and_one_provider_submission_across_repeated_sweeps(): void
    {
        $user = $this->createApiUser([
            'whatsapp_number' => '+94771234567',
        ]);

        TradingRoutinePreference::query()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'timezone' => 'Asia/Colombo',
            'delivery_time_local' => '06:00:00',
            'planning_balance' => 10000,
            'risk_per_trade_percent' => 0.5,
            'daily_risk_cap_percent' => 1.0,
            'max_trades' => 3,
            'max_consecutive_losses' => 2,
            'minimum_rr' => 2,
            'markets_json' => ['FOREX'],
            'sessions_json' => ['LONDON'],
            'whatsapp_pdf_enabled' => true,
            'whatsapp_opted_in_at' => now(),
        ]);

        $fake = new class implements TradingRoutineWhatsAppGateway {
            public int $calls = 0;

            public function sendPdf(string $recipient, string $pdfBytes, string $filename): array
            {
                $this->calls++;
                if (! str_starts_with($pdfBytes, '%PDF-1.4')) {
                    throw new \RuntimeException('Expected PDF bytes.');
                }

                return [
                    'provider' => 'TEST',
                    'message_id' => 'message-1',
                    'media_id' => 'media-1',
                ];
            }
        };

        $this->app->instance(TradingRoutineWhatsAppGateway::class, $fake);
        $service = app(TradingRoutineSweepService::class);
        $now = CarbonImmutable::parse('2026-09-15 00:30:00', 'UTC');

        $first = $service->sweep(null, $now);
        $second = $service->sweep(null, $now->addMinute());

        $this->assertSame(1, $first['accepted']);
        $this->assertSame(1, $second['accepted']);
        $this->assertSame(1, $fake->calls);
        $this->assertSame(1, TradingRoutineDailyPlan::query()->count());
        $this->assertSame(1, TradingRoutineDelivery::query()->count());
        $this->assertSame(
            TradingRoutineDelivery::STATUS_ACCEPTED,
            TradingRoutineDelivery::query()->firstOrFail()->status
        );
    }

    public function test_unverified_destination_is_skipped_without_calling_provider(): void
    {
        $user = $this->createApiUser([
            'whatsapp_verified_at' => null,
        ]);

        TradingRoutinePreference::query()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'timezone' => 'Asia/Colombo',
            'delivery_time_local' => '06:00:00',
            'planning_balance' => 10000,
            'risk_per_trade_percent' => 0.5,
            'daily_risk_cap_percent' => 1.0,
            'max_trades' => 3,
            'max_consecutive_losses' => 2,
            'minimum_rr' => 2,
            'whatsapp_pdf_enabled' => true,
            'whatsapp_opted_in_at' => now(),
        ]);

        $fake = new class implements TradingRoutineWhatsAppGateway {
            public int $calls = 0;

            public function sendPdf(string $recipient, string $pdfBytes, string $filename): array
            {
                $this->calls++;
                return ['provider' => 'TEST', 'message_id' => 'x', 'media_id' => 'y'];
            }
        };

        $this->app->instance(TradingRoutineWhatsAppGateway::class, $fake);
        $result = app(TradingRoutineSweepService::class)->sweep(
            null,
            CarbonImmutable::parse('2026-09-15 00:30:00', 'UTC')
        );

        $this->assertSame(0, $fake->calls);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(
            TradingRoutineDelivery::STATUS_SKIPPED,
            TradingRoutineDelivery::query()->firstOrFail()->status
        );
    }
}
