<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\SupportAgentProfile;
use App\Models\SupportCategory;
use App\Models\SupportChatSession;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportController extends Controller
{
    public function __construct(private readonly SupportTicketService $service) {}

    public function index(Request $request): View
    {
        $admin = $this->admin($request);
        $this->require($admin, 'support.view');
        $query = SupportTicket::query()->with(['user', 'category', 'assignedAdmin'])->withCount('messages');
        $this->scope($query, $admin);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('public_reference', 'like', '%'.$request->string('q').'%')->orWhere('subject', 'like', '%'.$request->string('q').'%'));
        }
        $tickets = $query->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")->orderBy('first_response_due_at')->orderBy('created_at')->paginate(25)->withQueryString();

        return view('admin.support.index', [
            'tickets' => $tickets,
            'counts' => [
                'open' => SupportTicket::whereIn('status', ['open', 'in_progress', 'waiting_for_customer'])->count(),
                'unassigned' => SupportTicket::whereNull('assigned_admin_id')->whereIn('status', ['open', 'in_progress'])->count(),
                'overdue' => SupportTicket::whereNull('first_responded_at')->where('first_response_due_at', '<', now())->whereIn('status', ['open', 'in_progress'])->count(),
                'queued_chats' => SupportChatSession::where('state', 'queued')->count(),
                'agents_online' => SupportAgentProfile::query()->where('enabled', true)->where('availability', 'online')->where('heartbeat_at', '>=', now()->subSeconds((int) config('support.agent_presence_ttl_seconds')))->count(),
            ],
            'agentProfile' => SupportAgentProfile::query()->where('admin_user_id', $admin->id)->first(),
            'canViewAll' => $admin->hasPermission('support.tickets.view_all'),
            'canManage' => $admin->hasPermission('support.tickets.manage'),
        ]);
    }

    public function show(Request $request, SupportTicket $supportTicket): View
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $supportTicket->load(['user', 'category', 'department', 'assignedAdmin', 'messages.attachments', 'latestChatSession']);

        return view('admin.support.show', [
            'ticket' => $supportTicket,
            'canReply' => $admin->hasPermission('support.tickets.reply'),
            'canNote' => $admin->hasPermission('support.notes.write'),
            'canAssign' => $admin->hasPermission('support.tickets.assign'),
            'canManage' => $admin->hasPermission('support.tickets.manage'),
        ]);
    }

    public function claim(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        abort_unless($admin->hasPermission('support.tickets.assign') || $admin->hasPermission('support.tickets.reply'), 403);
        $this->service->claim($supportTicket, $admin);

        return back()->with('status', 'Ticket claimed successfully.');
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse|JsonResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $this->require($admin, 'support.tickets.reply');
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $message = $this->service->addAdminMessage($supportTicket, $admin, $data['message'], 'public');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $this->adminMessageData($message)]);
        }

        return back()->with('status', 'Customer reply saved.');
    }

    public function note(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $this->require($admin, 'support.notes.write');
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $this->service->addAdminMessage($supportTicket, $admin, $data['message'], 'internal');

        return back()->with('status', 'Internal note saved.');
    }

    public function transition(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $this->require($admin, 'support.tickets.manage');
        $data = $request->validate(['status' => ['required', Rule::in(SupportTicket::STATUSES)]]);
        $this->service->transition($supportTicket, $admin, $data['status']);

        return back()->with('status', 'Ticket status updated.');
    }

    public function availability(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->require($admin, 'support.chat.participate');
        $data = $request->validate(['availability' => ['required', Rule::in(['online', 'away', 'offline'])], 'capacity' => ['nullable', 'integer', 'between:1,20']]);
        SupportAgentProfile::updateOrCreate(['admin_user_id' => $admin->id], ['enabled' => true, 'availability' => $data['availability'], 'capacity' => $data['capacity'] ?? 3, 'heartbeat_at' => $data['availability'] === 'offline' ? null : now()]);

        return back()->with('status', 'Support availability updated.');
    }

    public function heartbeat(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $this->require($admin, 'support.chat.participate');
        SupportAgentProfile::updateOrCreate(['admin_user_id' => $admin->id], ['enabled' => true, 'availability' => 'online', 'heartbeat_at' => now()]);
        $supportTicket->chatSessions()->whereIn('state', ['assigned', 'active'])->where('accepted_by_admin_id', $admin->id)->update(['last_agent_heartbeat_at' => now(), 'state' => 'active']);

        return response()->json(['success' => true, 'data' => ['heartbeat_at' => now()->toISOString()]]);
    }

    public function agentHeartbeat(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $this->require($admin, 'support.chat.participate');
        $profile = SupportAgentProfile::query()->where('admin_user_id', $admin->id)->first();
        abort_unless($profile && in_array($profile->availability, ['online', 'away'], true), 422, 'Set your support availability before starting the heartbeat.');
        $profile->update(['heartbeat_at' => now()]);

        return response()->json(['success' => true, 'data' => [
            'heartbeat_at' => now()->toISOString(),
            'agents_online' => SupportAgentProfile::query()->where('enabled', true)->where('availability', 'online')->where('heartbeat_at', '>=', now()->subSeconds((int) config('support.agent_presence_ttl_seconds')))->count(),
            'queued_chats' => SupportChatSession::query()->where('state', 'queued')->count(),
        ]]);
    }

    public function liveEvents(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $after = max(0, $request->integer('after'));
        $messages = $supportTicket->messages()
            ->where('visibility', 'public')
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn ($message) => $this->adminMessageData($message))
            ->values();
        $chat = $supportTicket->latestChatSession()->first();

        return response()->json(['success' => true, 'data' => [
            'events' => $messages,
            'next_cursor' => $messages->last()['cursor'] ?? $after,
            'chat_state' => $chat?->state,
        ]]);
    }

    public function settings(Request $request): View
    {
        $admin = $this->admin($request);
        abort_unless($admin->hasPermission('support.categories.manage') || $admin->hasPermission('support.sla.manage'), 403);

        return view('admin.support.settings', ['categories' => SupportCategory::with('department')->orderBy('sort_order')->get(), 'policies' => SupportSlaPolicy::with('department')->orderBy('priority')->get(), 'canCategories' => $admin->hasPermission('support.categories.manage'), 'canSla' => $admin->hasPermission('support.sla.manage'), 'mailConfigured' => config('support.mail_enabled') && config('mail.default') !== 'log']);
    }

    public function updateCategory(Request $request, SupportCategory $supportCategory): RedirectResponse
    {
        $this->require($this->admin($request), 'support.categories.manage');
        $data = $request->validate(['label' => ['required', 'string', 'max:160'], 'enabled' => ['nullable', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,999']]);
        $supportCategory->update(['label' => $data['label'], 'enabled' => $request->boolean('enabled'), 'sort_order' => $data['sort_order']]);

        return back()->with('status', 'Category updated.');
    }

    public function updateSla(Request $request, SupportSlaPolicy $supportSlaPolicy): RedirectResponse
    {
        $this->require($this->admin($request), 'support.sla.manage');
        $data = $request->validate(['first_response_minutes' => ['required', 'integer', 'between:1,43200'], 'resolution_minutes' => ['required', 'integer', 'between:1,129600'], 'enabled' => ['nullable', 'boolean']]);
        $supportSlaPolicy->update([...$data, 'enabled' => $request->boolean('enabled')]);

        return back()->with('status', 'SLA policy updated.');
    }

    public function attachment(Request $request, SupportTicketAttachment $supportTicketAttachment): StreamedResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicketAttachment->ticket);
        abort_unless(Storage::disk($supportTicketAttachment->disk)->exists($supportTicketAttachment->path), 404);

        return Storage::disk($supportTicketAttachment->disk)->download($supportTicketAttachment->path, $supportTicketAttachment->original_name, ['Content-Type' => $supportTicketAttachment->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function admin(Request $request): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function require(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->hasPermission($permission), 403);
    }

    private function scope($query, AdminUser $admin): void
    {
        if (! $admin->hasPermission('support.tickets.view_all')) {
            $query->where(function ($scoped) use ($admin): void {
                $scoped->where('assigned_admin_id', $admin->id);
                if ($admin->hasPermission('support.chat.participate')) {
                    $scoped->orWhere(fn ($waiting) => $waiting->whereNull('assigned_admin_id')->where('source', 'chat'));
                }
            });
        }
    }

    private function authorizeTicket(AdminUser $admin, SupportTicket $ticket): void
    {
        $this->require($admin, 'support.view');
        if (! $admin->hasPermission('support.tickets.view_all')) {
            $assignedToAgent = $admin->hasPermission('support.tickets.view_assigned') && $ticket->assigned_admin_id === $admin->id;
            $waitingLiveChat = $admin->hasPermission('support.chat.participate') && $ticket->source === 'chat' && $ticket->assigned_admin_id === null;
            abort_unless($assignedToAgent || $waitingLiveChat, 403);
        }
    }

    private function adminMessageData($message): array
    {
        return [
            'uuid' => $message->uuid,
            'cursor' => $message->id,
            'sender_type' => $message->sender_type,
            'message' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
