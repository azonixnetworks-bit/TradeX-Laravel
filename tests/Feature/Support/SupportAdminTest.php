<?php

namespace Tests\Feature\Support;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\SupportAgentProfile;
use App\Models\SupportCategory;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use Database\Seeders\SupportSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class SupportAdminTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SupportSystemSeeder::class);
    }

    public function test_admin_support_is_deny_by_default(): void
    {
        $this->actingAs($this->admin([]), 'admin')->get(route('admin.support.index'))->assertForbidden();
    }

    public function test_support_manager_can_open_settings_with_sla_departments_loaded(): void
    {
        $manager = $this->admin(['support.sla.manage']);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.support.settings'))
            ->assertOk()
            ->assertSee('SLA policies');
    }

    public function test_agent_only_sees_assigned_ticket_and_internal_note_never_appears_in_customer_api(): void
    {
        $agent = $this->admin(['support.view', 'support.tickets.view_assigned', 'support.tickets.reply', 'support.notes.write']);
        $user = $this->createApiUser();
        $ticket = $this->ticket($user->id, $agent->id);
        $other = $this->ticket($this->createApiUser()->id, null);

        $this->actingAs($agent, 'admin')->get(route('admin.support.index'))->assertOk()->assertSee($ticket->public_reference)->assertDontSee($other->public_reference);
        $this->actingAs($agent, 'admin')->post(route('admin.support.notes.store', $ticket), ['message' => 'Private operational note'])->assertRedirect();

        $token = $this->issueBoundToken($user);
        $this->withToken($token)->getJson('/api/v1/support/tickets/'.$ticket->uuid.'/messages')->assertOk()->assertJsonMissing(['message' => 'Private operational note']);
    }

    public function test_manager_can_claim_unassigned_ticket_atomically_and_reply(): void
    {
        $manager = $this->admin(['support.view', 'support.tickets.view_all', 'support.tickets.assign', 'support.tickets.reply', 'support.tickets.manage']);
        $ticket = $this->ticket($this->createApiUser()->id, null);
        $this->actingAs($manager, 'admin')->post(route('admin.support.claim', $ticket))->assertRedirect();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'assigned_admin_id' => $manager->id, 'status' => 'in_progress']);
        $this->actingAs($manager, 'admin')->post(route('admin.support.reply', $ticket), ['message' => 'We are reviewing this case.'])->assertRedirect();
        $this->assertDatabaseHas('support_ticket_messages', ['ticket_id' => $ticket->id, 'visibility' => 'public', 'body' => 'We are reviewing this case.']);
    }

    public function test_online_support_agent_can_see_and_claim_queued_live_chat(): void
    {
        $agent = $this->admin(['support.view', 'support.tickets.view_assigned', 'support.tickets.reply', 'support.notes.write', 'support.chat.participate']);
        SupportAgentProfile::create(['admin_user_id' => $agent->id, 'enabled' => true, 'availability' => 'online', 'capacity' => 2, 'heartbeat_at' => now()]);
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $ticket = $this->ticket($user->id, null);
        $chat = SupportChatSession::create(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'active_user_id' => $ticket->user_id, 'state' => 'queued', 'idempotency_key' => 'admin-claim-chat', 'queued_at' => now(), 'last_customer_heartbeat_at' => now()]);

        $this->actingAs($agent, 'admin')->get(route('admin.support.index'))->assertOk()->assertSee($ticket->public_reference);
        $this->actingAs($agent, 'admin')->get(route('admin.support.show', $ticket))->assertOk()->assertSee('Claim ticket');
        $this->actingAs($agent, 'admin')->post(route('admin.support.claim', $ticket))->assertRedirect();

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'assigned_admin_id' => $agent->id]);
        $this->assertDatabaseHas('support_chat_sessions', ['id' => $chat->id, 'state' => 'assigned', 'accepted_by_admin_id' => $agent->id]);
        $this->withToken($token)->getJson('/api/v1/support/live-chat/'.$chat->uuid)->assertOk()->assertJsonPath('data.state', 'assigned')->assertJsonPath('data.assigned_agent.name', $agent->name);

        $this->actingAs($agent, 'admin')->post(route('admin.support.reply', $ticket), ['message' => 'Your live support connection is active.'])->assertRedirect();
        $this->withToken($token)->getJson('/api/v1/support/live-chat/'.$chat->uuid.'/events?after=0')->assertOk()->assertJsonPath('data.events.0.message', 'Your live support connection is active.');
        $this->actingAs($agent, 'admin')->getJson(route('admin.support.events', $ticket).'?after=0')->assertOk()->assertJsonPath('data.items.0.message', 'Your live support connection is active.');
    }

    public function test_offline_support_agent_cannot_claim_queued_live_chat(): void
    {
        $agent = $this->admin(['support.view', 'support.tickets.view_assigned', 'support.tickets.reply', 'support.chat.participate']);
        SupportAgentProfile::create(['admin_user_id' => $agent->id, 'enabled' => true, 'availability' => 'offline', 'capacity' => 2]);
        $ticket = $this->ticket($this->createApiUser()->id, null);
        SupportChatSession::create(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'active_user_id' => $ticket->user_id, 'state' => 'queued', 'idempotency_key' => 'offline-claim-chat', 'queued_at' => now(), 'last_customer_heartbeat_at' => now()]);

        $this->actingAs($agent, 'admin')->post(route('admin.support.reply', $ticket), ['message' => 'Reply without claiming'])->assertForbidden();
        $this->actingAs($agent, 'admin')->post(route('admin.support.claim', $ticket))->assertForbidden();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'assigned_admin_id' => null]);
        $this->assertDatabaseMissing('support_ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'Reply without claiming']);
    }

    public function test_status_transition_records_the_real_previous_state(): void
    {
        $manager = $this->admin(['support.view', 'support.tickets.view_all', 'support.tickets.manage']);
        $ticket = $this->ticket($this->createApiUser()->id, $manager->id);
        $ticket->update(['status' => 'in_progress']);

        $this->actingAs($manager, 'admin')->patch(route('admin.support.status.update', $ticket), ['status' => 'resolved'])->assertRedirect();

        $event = SupportTicketEvent::query()->where('ticket_id', $ticket->id)->where('event_type', 'ticket.status_changed')->latest('id')->firstOrFail();
        $this->assertSame('in_progress', $event->payload['from']);
        $this->assertSame('resolved', $event->payload['to']);
    }

    private function ticket(int $userId, ?int $adminId): SupportTicket
    {
        return SupportTicket::create(['public_reference' => 'TX-SUP-'.strtoupper(Str::random(6)), 'user_id' => $userId, 'department_id' => SupportCategory::first()->department_id, 'category_id' => SupportCategory::first()->id, 'assigned_admin_id' => $adminId, 'source' => 'ticket', 'subject' => 'Support admin authorization test', 'description' => 'A sufficiently detailed support request used for authorization testing.', 'priority' => 'normal', 'status' => 'open']);
    }

    private function admin(array $permissions): AdminUser
    {
        $admin = AdminUser::create(['uuid' => (string) Str::uuid(), 'name' => 'Support Tester', 'email' => Str::random(8).'@example.test', 'email_verified_at' => now(), 'password' => 'TestingPassword123!', 'is_active' => true]);
        $role = AdminRole::create(['code' => 'TEST_'.strtoupper(Str::random(8)), 'name' => 'Test support role', 'is_system' => false, 'is_active' => true]);
        $admin->roles()->attach($role);
        foreach ($permissions as $code) {
            $permission = AdminPermission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'SUPPORT']);
            $role->permissions()->attach($permission);
        }

        return $admin->fresh();
    }
}
