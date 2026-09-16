<?php

namespace Tests\Feature\Api\TradingTools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradingRoutineApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_routine_requires_authenticated_bound_mobile_session(): void
    {
        $this->getJson('/api/v1/trading-routine')->assertUnauthorized();
    }

    public function test_customer_identity_and_whatsapp_state_are_automatic(): void
    {
        $user = $this->createApiUser([
            'name' => 'Routine Customer',
            'email' => 'routine-customer@tradex.test',
            'whatsapp_number' => '+94771234567',
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/trading-routine')
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Routine Customer')
            ->assertJsonPath('data.customer.email', 'routine-customer@tradex.test')
            ->assertJsonPath('data.customer.whatsapp_verified', true)
            ->assertJsonPath('data.customer.whatsapp_destination', '+947***567');
    }

    public function test_customer_can_save_preferences_and_generate_today_pdf(): void
    {
        $user = $this->createApiUser([
            'name' => 'Planner Customer',
            'email' => 'planner-customer@tradex.test',
            'whatsapp_number' => '+94771234567',
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->putJson('/api/v1/trading-routine/preferences', $this->validPreference())
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.whatsapp_pdf_enabled', true);

        $today = $this->withToken($token)
            ->getJson('/api/v1/trading-routine/today')
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Planner Customer')
            ->assertJsonPath('data.plan.risk_per_trade_amount', 50)
            ->assertJsonPath('data.plan.daily_risk_cap_amount', 100);

        $uuid = (string) $today->json('data.uuid');
        $pdf = $this->withToken($token)
            ->get('/api/v1/trading-routine/plans/'.$uuid.'/pdf');

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
    }

    public function test_identity_and_destination_cannot_be_spoofed_in_preferences(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $payload = array_merge($this->validPreference(), [
            'customer_name' => 'Spoofed',
            'customer_email' => 'spoofed@example.test',
            'whatsapp_number' => '+10000000000',
        ]);

        $this->withToken($token)
            ->putJson('/api/v1/trading-routine/preferences', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email', 'whatsapp_number']);
    }

    public function test_unverified_whatsapp_cannot_enable_morning_pdf_delivery(): void
    {
        $user = $this->createApiUser([
            'whatsapp_verified_at' => null,
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->putJson('/api/v1/trading-routine/preferences', $this->validPreference())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_pdf_enabled']);
    }

    /** @return array<string, mixed> */
    private function validPreference(): array
    {
        return [
            'enabled' => true,
            'timezone' => 'Asia/Colombo',
            'delivery_time_local' => '06:00',
            'planning_balance' => 10000,
            'risk_per_trade_percent' => 0.5,
            'daily_risk_cap_percent' => 1.0,
            'max_trades' => 3,
            'max_consecutive_losses' => 2,
            'minimum_rr' => 2,
            'markets' => ['FOREX'],
            'sessions' => ['LONDON', 'NEW_YORK'],
            'pre_market_checklist' => [
                'Check focus and emotional state.',
                'Review key levels before entry.',
            ],
            'no_trade_rules' => [
                'No revenge trading.',
                'Stop after the configured loss limit.',
            ],
            'focus_statement' => 'Protect capital first.',
            'whatsapp_pdf_enabled' => true,
        ];
    }
}
