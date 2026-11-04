<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class UserActivityRolloutTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_disabled_by_default_returns_no_tracking_capability(): void
    {
        config()->set('tradex_activity.enabled', false);
        $user = $this->createApiUser();

        $this->withToken($this->issueBoundToken($user))
            ->getJson('/api/v1/activity/config')
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.policy_version', 1);
    }

    public function test_authenticated_device_can_discover_ready_activity_api(): void
    {
        config()->set('tradex_activity.enabled', true);
        $user = $this->createApiUser();

        $this->withToken($this->issueBoundToken($user))
            ->getJson('/api/v1/activity/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.max_batch_size', 25)
            ->assertJsonPath('data.retention_days', 45);
    }

    public function test_guest_cannot_read_tracking_configuration(): void
    {
        $this->getJson('/api/v1/activity/config')->assertUnauthorized();
    }

    public function test_disabled_ingestion_never_persists_events(): void
    {
        config()->set('tradex_activity.enabled', false);
        $user = $this->createApiUser();

        $this->withToken($this->issueBoundToken($user))
            ->postJson('/api/v1/activity/events', [
                'events' => [[
                    'event_id' => '99bc68a0-2c13-48c1-9c4d-23db916b1303',
                    'module' => 'learning',
                    'event_name' => 'lesson_viewed',
                    'occurred_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'ACTIVITY_DISABLED');

        $this->assertDatabaseCount('user_activity_events', 0);
    }

    public function test_rollout_command_requires_flag_and_is_safe_for_staging(): void
    {
        config()->set('tradex_activity.enabled', false);
        $this->artisan('tradex:user-activity:check', ['--allow-disabled' => true])
            ->assertSuccessful();

        $this->artisan('tradex:user-activity:check')
            ->assertExitCode(1);

        config()->set('tradex_activity.enabled', true);
        $this->artisan('tradex:user-activity:check')
            ->assertSuccessful();
    }
}
