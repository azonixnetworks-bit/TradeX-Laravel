<?php

namespace Tests\Feature\Support;

use App\Jobs\DeliverSupportNotification;
use App\Mail\SupportTicketMail;
use App\Models\NotificationMessage;
use App\Models\SupportCategory;
use App\Models\SupportNotificationDelivery;
use App\Models\SupportTicket;
use App\Models\UserNotificationReceipt;
use Database\Seeders\SupportSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class SupportNotificationTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_mail_and_in_app_delivery_are_idempotent_and_reuse_notification_pipeline(): void
    {
        $this->seed(SupportSystemSeeder::class);
        $user = $this->createApiUser();
        $ticket = $this->ticket($user->id);
        Mail::fake();
        config()->set('support.mail_enabled', true);
        config()->set('mail.default', 'array');

        $mail = SupportNotificationDelivery::create([
            'uuid' => (string) Str::uuid(), 'ticket_id' => $ticket->id, 'user_id' => $user->id,
            'channel' => 'mail', 'template' => 'ticket-received', 'deduplication_key' => 'test:mail',
            'payload' => ['reference' => $ticket->public_reference, 'subject' => $ticket->subject], 'status' => 'pending',
        ]);
        (new DeliverSupportNotification($mail->id))->handle();
        (new DeliverSupportNotification($mail->id))->handle();
        Mail::assertSent(SupportTicketMail::class, 1);

        $inApp = SupportNotificationDelivery::create([
            'uuid' => (string) Str::uuid(), 'ticket_id' => $ticket->id, 'user_id' => $user->id,
            'channel' => 'in_app', 'template' => 'admin-reply', 'deduplication_key' => 'test:in-app',
            'payload' => ['reference' => $ticket->public_reference, 'body' => 'A safe public support reply.'], 'status' => 'pending',
        ]);
        (new DeliverSupportNotification($inApp->id))->handle();
        (new DeliverSupportNotification($inApp->id))->handle();
        $this->assertSame(1, NotificationMessage::where('uuid', $inApp->uuid)->count());
        $this->assertSame(1, UserNotificationReceipt::where('user_id', $user->id)->count());
        $notification = NotificationMessage::where('uuid', $inApp->uuid)->firstOrFail();
        $this->assertSame('You have a new reply in the TradeX support centre.', $notification->body);
        $this->assertStringNotContainsString('A safe public support reply.', $notification->body);
    }

    private function ticket(int $userId): SupportTicket
    {
        $category = SupportCategory::query()->firstOrFail();

        return SupportTicket::create([
            'public_reference' => 'TX-SUP-900001', 'user_id' => $userId,
            'department_id' => $category->department_id, 'category_id' => $category->id,
            'source' => 'ticket', 'subject' => 'Notification delivery test',
            'description' => 'Support notification delivery uses the established customer notification pipeline.',
            'priority' => 'normal', 'status' => 'open',
        ]);
    }
}
