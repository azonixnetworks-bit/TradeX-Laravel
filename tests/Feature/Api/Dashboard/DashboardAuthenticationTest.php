<?php

namespace Tests\Feature\Api\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/dashboard'
        )->assertUnauthorized();
    }
}
