<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class UserActivityIngestionTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('tradex_activity.enabled', true);
    }

    public function test_guest_cannot_ingest_events(): void
    {
        $this->postJson('/api/v1/activity/events', ['events' => []])
            ->assertUnauthorized();
    }

    public function test_rejects_unrecognized_client_event(): void
    {
        $user = $this->createApiUser();

        $this->withToken($this->issueBoundToken($user))
            ->postJson('/api/v1/activity/events', ['events' => [[
                'event_id' => '01fe6b37-2403-4da1-96a0-47c668bf2670',
                'module' => 'app',
                'event_name' => 'payment_approved',
                'occurred_at' => now()->toIso8601String(),
            ]]])->assertUnprocessable();
    }

    public function test_authenticated_batches_are_account_scoped_and_idempotent(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $event = [
            'event_id' => '01fe6b37-2403-4da1-96a0-47c668bf2671',
            'module' => 'learning',
            'event_name' => 'lesson_viewed',
            'session_id' => 'session_1',
            'subject_id' => 'lesson_1',
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->withToken($token)
            ->postJson('/api/v1/activity/events', ['events' => [$event]])
            ->assertAccepted()
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.inserted', 1);

        $this->withToken($token)
            ->postJson('/api/v1/activity/events', ['events' => [$event]])
            ->assertAccepted()
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.inserted', 0);

        $this->assertDatabaseHas('user_activity_events', [
            'user_id' => $user->getKey(),
            'event_id' => $event['event_id'],
        ]);
        $this->assertDatabaseCount('user_activity_events', 1);
    }
}
