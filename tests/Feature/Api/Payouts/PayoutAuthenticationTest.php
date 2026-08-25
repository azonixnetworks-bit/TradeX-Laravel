<?php
namespace Tests\Feature\Api\Payouts;
use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class PayoutAuthenticationTest extends TestCase { use RefreshDatabase; public function test_all_payout_routes_require_authentication():void { $this->getJson('/api/v1/payouts/summary')->assertUnauthorized(); $this->getJson('/api/v1/payouts/methods')->assertUnauthorized(); $this->getJson('/api/v1/payouts/history')->assertUnauthorized(); $this->getJson('/api/v1/payouts/11111111-1111-4111-8111-111111111111')->assertUnauthorized(); $this->postJson('/api/v1/payouts',['method'=>'PAYPAL','amount'=>'15.00'])->assertUnauthorized(); } }
