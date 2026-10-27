<?php

namespace Tests\Feature\Support;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\SupportAgentProfile;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
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

    public function test_online_agent_heartbeat_and_live_event_feed_are_available(): void
    {
        $agent = $this->admin(['support.view', 'support.tickets.view_assigned', 'support.tickets.reply', 'support.chat.participate']);
        SupportAgentProfile::create(['admin_user_id' => $agent->id, 'enabled' => true, 'availability' => 'online', 'capacity' => 3, 'heartbeat_at' => now()->subMinutes(5)]);
        $ticket = $this->ticket($this->createApiUser()->id, $agent->id);
        $message = $ticket->messages()->create(['sender_type' => 'customer', 'sender_user_id' => $ticket->user_id, 'visibility' => 'public', 'body' => 'I need help from a live agent.']);

        $this->actingAs($agent, 'admin')->postJson(route('admin.support.agent-heartbeat'))
            ->assertOk()
            ->assertJsonPath('data.agents_online', 1);
        $this->actingAs($agent, 'admin')->getJson(route('admin.support.live-events', ['supportTicket' => $ticket, 'after' => 0]))
            ->assertOk()
            ->assertJsonPath('data.events.0.cursor', $message->id)
            ->assertJsonPath('data.events.0.message', 'I need help from a live agent.');
        $this->actingAs($agent, 'admin')->postJson(route('admin.support.reply', $ticket), ['message' => 'Your agent is connected now.'])
            ->assertOk()
            ->assertJsonPath('data.sender_type', 'admin');
    }

    public function test_support_agent_can_see_and_claim_an_unassigned_live_chat(): void
    {
        $agent = $this->admin(['support.view', 'support.tickets.view_assigned', 'support.tickets.reply', 'support.chat.participate']);
        $ticket = $this->ticket($this->createApiUser()->id, null);
        $ticket->update(['source' => 'chat']);

        $this->actingAs($agent, 'admin')->get(route('admin.support.index'))
            ->assertOk()
            ->assertSee($ticket->public_reference);
        $this->actingAs($agent, 'admin')->post(route('admin.support.claim', $ticket))
            ->assertRedirect();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'assigned_admin_id' => $agent->id]);
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
