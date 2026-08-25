<?php

namespace Tests\Feature\Api\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickUnlockAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_unlock_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/security/quick-unlock'
        )->assertUnauthorized();

        $this->patchJson(
            '/api/v1/security/quick-unlock',
            [
                'enabled' => true,
                'method' => 'PIN',
                'totp_code' => '123456',
            ]
        )->assertUnauthorized();
    }
}
