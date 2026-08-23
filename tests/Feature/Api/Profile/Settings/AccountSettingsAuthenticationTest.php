<?php

namespace Tests\Feature\Api\Profile\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSettingsAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api11_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile/settings')->assertUnauthorized();
        $this->getJson('/api/v1/profile/settings/security')->assertUnauthorized();
        $this->getJson('/api/v1/profile/settings/devices')->assertUnauthorized();
    }
}
