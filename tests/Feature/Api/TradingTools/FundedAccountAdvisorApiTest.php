<?php

namespace Tests\Feature\Api\TradingTools;

use App\Models\FundedAccountPartner;
use App\Models\FundedAdvisorReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class FundedAccountAdvisorApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_customer_advisor_requires_authenticated_bound_mobile_session(): void
    {
        $this->getJson('/api/v1/funded-account-advisor')
            ->assertUnauthorized();
    }

    public function test_customer_identity_is_returned_automatically_from_authenticated_profile(): void
    {
        $user = $this->createApiUser([
            'name' => 'Auto Identity Customer',
            'email' => 'auto-identity@tradex.test',
        ]);
        $token = $this->issueBoundToken($user);

        FundedAccountPartner::query()->create([
            'name' => 'Featured Funding Partner',
            'affiliate_url' => 'https://partner.example/tradex',
            'promo_code' => 'TRADEX10',
            'is_active' => true,
            'is_featured' => true,
            'priority' => 1,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/funded-account-advisor')
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Auto Identity Customer')
            ->assertJsonPath('data.customer.email', 'auto-identity@tradex.test')
            ->assertJsonPath('data.partners.0.name', 'Featured Funding Partner')
            ->assertJsonPath('data.partners.0.promo_code', 'TRADEX10');
    }

    public function test_report_snapshots_authenticated_customer_identity_and_partner(): void
    {
        $user = $this->createApiUser([
            'name' => 'Real TradeX Customer',
            'email' => 'real-customer@tradex.test',
        ]);
        $token = $this->issueBoundToken($user);

        $partner = FundedAccountPartner::query()->create([
            'name' => 'TradeX Funding Partner',
            'affiliate_url' => 'https://partner.example/ref/tradex',
            'promo_code' => 'TRADEX10',
            'offer_text' => 'TradeX customer offer',
            'is_active' => true,
            'is_featured' => true,
            'priority' => 1,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/funded-account-advisor/reports', array_merge(
                $this->validAdvisorInput(),
                ['partner_uuid' => $partner->uuid]
            ));

        $response->assertCreated()
            ->assertJsonPath('data.customer.name', 'Real TradeX Customer')
            ->assertJsonPath('data.customer.email', 'real-customer@tradex.test')
            ->assertJsonPath('data.partner.name', 'TradeX Funding Partner')
            ->assertJsonPath('data.partner.affiliate_url', 'https://partner.example/ref/tradex')
            ->assertJsonPath('data.output.recommended_risk_amount', 250);

        $report = FundedAdvisorReport::query()->firstOrFail();

        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('Real TradeX Customer', $report->customer_name);
        $this->assertSame('real-customer@tradex.test', $report->customer_email);
        $this->assertSame('TradeX Funding Partner', $report->partner_name_snapshot);
        $this->assertSame('https://partner.example/ref/tradex', $report->affiliate_url_snapshot);
        $this->assertNull($report->generated_by_admin_id);
    }

    public function test_customer_cannot_override_name_or_email_in_report_request(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/funded-account-advisor/reports', array_merge(
                $this->validAdvisorInput(),
                [
                    'customer_name' => 'Spoofed Customer',
                    'customer_email' => 'spoofed@example.test',
                ]
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email']);

        $this->assertDatabaseCount('funded_advisor_reports', 0);
    }

    public function test_customer_can_only_view_and_download_own_report(): void
    {
        $owner = $this->createApiUser([
            'email' => 'owner-funded-advisor@tradex.test',
        ]);
        $other = $this->createApiUser([
            'email' => 'other-funded-advisor@tradex.test',
        ]);
        $ownerToken = $this->issueBoundToken($owner);
        $otherToken = $this->issueBoundToken($other);

        $createResponse = $this->withToken($ownerToken)
            ->postJson('/api/v1/funded-account-advisor/reports', $this->validAdvisorInput())
            ->assertCreated();

        $uuid = (string) $createResponse->json('data.uuid');

        $this->withToken($ownerToken)
            ->getJson('/api/v1/funded-account-advisor/reports/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->withToken($otherToken)
            ->getJson('/api/v1/funded-account-advisor/reports/'.$uuid)
            ->assertNotFound();

        $this->withToken($otherToken)
            ->get('/api/v1/funded-account-advisor/reports/'.$uuid.'/pdf')
            ->assertNotFound();

        $pdf = $this->withToken($ownerToken)
            ->get('/api/v1/funded-account-advisor/reports/'.$uuid.'/pdf');

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
    }

    /** @return array<string, mixed> */
    private function validAdvisorInput(): array
    {
        return [
            'prop_firm_name' => 'Example Funding',
            'phase' => 'EVALUATION',
            'account_size' => 100000,
            'profit_target_percent' => 10,
            'daily_loss_limit_percent' => 5,
            'max_drawdown_percent' => 10,
            'daily_loss_basis' => 'EQUITY',
            'daily_start_reference' => 100000,
            'drawdown_type' => 'STATIC',
            'drawdown_basis' => 'EQUITY',
            'trailing_reference_amount' => null,
            'current_balance' => 100000,
            'current_equity' => 100000,
            'desired_risk_percent' => .25,
            'planned_max_entries' => 3,
            'minimum_rr' => 2,
            'minimum_trading_days' => 5,
            'completed_trading_days' => 1,
        ];
    }
}
