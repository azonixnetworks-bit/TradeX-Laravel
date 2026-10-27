<?php

namespace Tests\Feature\Support;

use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Database\Seeders\SupportSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SupportSystemSeeder::class);
    }

    public function test_unauthenticated_customer_is_rejected(): void
    {
        $this->getJson('/api/v1/support/overview')->assertUnauthorized();
    }

    public function test_customer_can_create_list_view_and_idempotently_retry_own_ticket(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $category = SupportCategory::query()->firstOrFail();
        $payload = ['category_uuid' => $category->uuid, 'subject' => 'Unable to open the learning screen', 'description' => 'The learning screen remains blank after a secure sign in.'];

        $first = $this->withToken($token)->withHeader('Idempotency-Key', 'ticket-client-1')->postJson('/api/v1/support/tickets', $payload)->assertCreated()->assertJsonPath('data.reference', 'TX-SUP-000001');
        $second = $this->withToken($token)->withHeader('Idempotency-Key', 'ticket-client-1')->postJson('/api/v1/support/tickets', $payload)->assertCreated();

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertDatabaseCount('support_tickets', 1);
        $this->withToken($token)->getJson('/api/v1/support/tickets')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->withToken($token)->getJson('/api/v1/support/tickets/'.$first->json('data.uuid'))->assertOk()->assertJsonMissingPath('data.user_id');
    }

    public function test_customer_cannot_read_foreign_ticket_or_internal_note(): void
    {
        $owner = $this->createApiUser();
        $other = $this->createApiUser();
        $ownerToken = $this->issueBoundToken($owner);
        $otherToken = $this->issueBoundToken($other);
        $category = SupportCategory::query()->firstOrFail();
        $created = $this->withToken($ownerToken)->withHeader('Idempotency-Key', 'owner-ticket')->postJson('/api/v1/support/tickets', ['category_uuid' => $category->uuid, 'subject' => 'Payment status is not updated', 'description' => 'My verified payment status has not updated in the application.'])->assertCreated();
        $ticket = SupportTicket::where('uuid', $created->json('data.uuid'))->firstOrFail();
        SupportTicketMessage::create(['ticket_id' => $ticket->id, 'sender_type' => 'admin', 'visibility' => 'internal', 'body' => 'Sensitive internal triage note']);

        $this->withToken($otherToken)->getJson('/api/v1/support/tickets/'.$ticket->uuid)->assertNotFound();
        $messages = $this->withToken($ownerToken)->getJson('/api/v1/support/tickets/'.$ticket->uuid.'/messages')->assertOk();
        $this->assertStringNotContainsString('Sensitive internal triage note', $messages->getContent());
    }

    public function test_message_and_live_chat_retries_are_idempotent(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $category = SupportCategory::query()->firstOrFail();
        $payload = ['category_uuid' => $category->uuid, 'subject' => 'Copy trading account review', 'description' => 'Please help me understand my copy trading account review status.'];
        $ticketUuid = $this->withToken($token)->withHeader('Idempotency-Key', 'ticket-2')->postJson('/api/v1/support/tickets', $payload)->json('data.uuid');

        $this->withToken($token)->withHeader('Idempotency-Key', 'message-1')->postJson('/api/v1/support/tickets/'.$ticketUuid.'/messages', ['message' => 'This is my additional information.'])->assertCreated();
        $this->withToken($token)->withHeader('Idempotency-Key', 'message-1')->postJson('/api/v1/support/tickets/'.$ticketUuid.'/messages', ['message' => 'This is my additional information.'])->assertCreated();
        $this->assertDatabaseCount('support_ticket_messages', 2);

        $chatOne = $this->withToken($token)->withHeader('Idempotency-Key', 'chat-1')->postJson('/api/v1/support/live-chat/start', $payload)->assertCreated();
        $chatTwo = $this->withToken($token)->withHeader('Idempotency-Key', 'chat-1')->postJson('/api/v1/support/live-chat/start', $payload)->assertCreated();
        $this->assertSame($chatOne->json('data.uuid'), $chatTwo->json('data.uuid'));
        $this->assertDatabaseCount('support_chat_sessions', 1);
        $this->assertNull($chatOne->json('data.eta_minutes'));
    }

    public function test_resolved_ticket_supports_one_feedback_per_resolution_cycle_and_reopen(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $category = SupportCategory::query()->firstOrFail();
        $uuid = $this->withToken($token)->withHeader('Idempotency-Key', 'ticket-3')->postJson('/api/v1/support/tickets', ['category_uuid' => $category->uuid, 'subject' => 'Affiliate commission question', 'description' => 'Please review the commission status shown in my affiliate dashboard.'])->json('data.uuid');
        $ticket = SupportTicket::where('uuid', $uuid)->firstOrFail();
        $ticket->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution_cycle' => 1]);

        $this->withToken($token)->postJson('/api/v1/support/tickets/'.$uuid.'/feedback', ['rating' => 5, 'comment' => 'Helpful'])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/support/tickets/'.$uuid.'/feedback', ['rating' => 4])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/support/tickets/'.$uuid.'/reopen')->assertOk()->assertJsonPath('data.status', 'in_progress');
    }

    public function test_attachments_are_private_mime_checked_and_owner_scoped(): void
    {
        Storage::fake('private');
        $owner = $this->createApiUser();
        $other = $this->createApiUser();
        $ownerToken = $this->issueBoundToken($owner);
        $otherToken = $this->issueBoundToken($other);
        $category = SupportCategory::query()->firstOrFail();
        $uuid = $this->withToken($ownerToken)->withHeader('Idempotency-Key', 'upload-ticket')->postJson('/api/v1/support/tickets', ['category_uuid' => $category->uuid, 'subject' => 'Screenshot attachment validation', 'description' => 'A valid screenshot is attached for secure support review.'])->json('data.uuid');

        $png = UploadedFile::fake()->createWithContent(
            'screen.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true)
        );
        $upload = $this->withToken($ownerToken)->post('/api/v1/support/tickets/'.$uuid.'/attachments', ['files' => [$png]], ['Accept' => 'application/json'])->assertCreated();
        $attachmentUuid = $upload->json('data.0.uuid');
        $this->withToken($ownerToken)->get('/api/v1/support/attachments/'.$attachmentUuid.'/download')->assertOk();
        $this->withToken($otherToken)->get('/api/v1/support/attachments/'.$attachmentUuid.'/download')->assertNotFound();

        $this->withToken($ownerToken)->post('/api/v1/support/tickets/'.$uuid.'/attachments', ['files' => [UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')]], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_live_chat_queue_position_uses_actual_fifo_without_fake_eta(): void
    {
        $category = SupportCategory::query()->firstOrFail();
        $payload = ['category_uuid' => $category->uuid, 'subject' => 'Live support queue request', 'description' => 'I need an authenticated live support conversation for this issue.'];
        $first = $this->createApiUser();
        $second = $this->createApiUser();
        $this->withToken($this->issueBoundToken($first))->withHeader('Idempotency-Key', 'queue-first')->postJson('/api/v1/support/live-chat/start', $payload)->assertCreated()->assertJsonPath('data.queue_position', 1)->assertJsonPath('data.eta_minutes', null);
        $this->travel(1)->seconds();
        $this->withToken($this->issueBoundToken($second))->withHeader('Idempotency-Key', 'queue-second')->postJson('/api/v1/support/live-chat/start', $payload)->assertCreated()->assertJsonPath('data.queue_position', 2)->assertJsonPath('data.eta_minutes', null);
    }
}
