<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserActivityIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_ingest_events(): void
    {
        $this->postJson('/api/v1/activity/events', ['events' => []])
            ->assertUnauthorized();
    }

    public function test_rejects_unrecognized_client_event(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/activity/events', ['events' => [[
            'event_id' => '01fe6b37-2403-4da1-96a0-47c668bf2670',
            'module' => 'app',
            'event_name' => 'payment_approved',
            'occurred_at' => now()->toIso8601String(),
        ]]])->assertUnprocessable();
    }
}
