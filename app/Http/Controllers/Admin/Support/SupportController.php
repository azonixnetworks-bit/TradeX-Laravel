<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\SupportAgentProfile;
use App\Models\SupportCategory;
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

        $countQuery = SupportTicket::query();
        $this->scope($countQuery, $admin);
        $profile = SupportAgentProfile::query()->where('admin_user_id', $admin->id)->first();

        return view('admin.support.index', [
            'tickets' => $tickets,
            'counts' => [
                'open' => (clone $countQuery)->whereIn('status', ['open', 'in_progress', 'waiting_for_customer'])->count(),
                'unassigned' => (clone $countQuery)->whereNull('assigned_admin_id')->whereIn('status', ['open', 'in_progress'])->count(),
                'overdue' => (clone $countQuery)->whereNull('first_responded_at')->where('first_response_due_at', '<', now())->whereIn('status', ['open', 'in_progress'])->count(),
            ],
            'canViewAll' => $admin->hasPermission('support.tickets.view_all'),
            'canManage' => $admin->hasPermission('support.tickets.manage'),
            'agentProfile' => $profile,
        ]);
    }

    public function show(Request $request, SupportTicket $supportTicket): View
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $supportTicket->load(['user', 'category', 'department', 'assignedAdmin', 'messages.attachments', 'latestChatSession']);
        $canAct = $this->canActOnTicket($admin, $supportTicket);

        return view('admin.support.show', [
            'ticket' => $supportTicket,
            'canReply' => $canAct && $admin->hasPermission('support.tickets.reply'),
            'canNote' => $canAct && $admin->hasPermission('support.notes.write'),
            'canClaim' => $this->canClaim($admin, $supportTicket),
            'canManage' => $canAct && $admin->hasPermission('support.tickets.manage'),
        ]);
    }

    public function claim(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        abort_unless($this->canClaim($admin, $supportTicket), 403);
        $this->service->claim($supportTicket, $admin);

        return back()->with('status', 'Ticket claimed successfully.');
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicketAction($admin, $supportTicket);
        $this->require($admin, 'support.tickets.reply');
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $this->service->addAdminMessage($supportTicket, $admin, $data['message'], 'public');

        return back()->with('status', 'Customer reply saved.');
    }

    public function note(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicketAction($admin, $supportTicket);
        $this->require($admin, 'support.notes.write');
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $this->service->addAdminMessage($supportTicket, $admin, $data['message'], 'internal');

        return back()->with('status', 'Internal note saved.');
    }

    public function transition(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicketAction($admin, $supportTicket);
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

    public function events(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $admin = $this->admin($request);
        $this->authorizeTicket($admin, $supportTicket);
        $after = max(0, $request->integer('after'));
        $messages = $supportTicket->messages()
            ->with('attachments')
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(100)
            ->get();

        $supportTicket->messages()
            ->whereIn('id', $messages->where('sender_type', 'customer')->pluck('id'))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $messages->map(fn ($message) => [
                    'uuid' => $message->uuid,
                    'cursor' => $message->id,
                    'sender_type' => $message->sender_type,
                    'visibility' => $message->visibility,
                    'message' => $message->body,
                    'created_at' => $message->created_at?->toISOString(),
                    'attachments' => $message->attachments->map(fn ($attachment) => [
                        'name' => $attachment->original_name,
                        'download_url' => route('admin.support.attachments.download', $attachment),
                    ])->values(),
                ])->values(),
                'next_cursor' => $messages->last()?->id ?? $after,
                'status' => $supportTicket->fresh()->status,
            ],
        ]);
    }

    public function presenceHeartbeat(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $this->require($admin, 'support.chat.participate');
        $profile = SupportAgentProfile::query()->where('admin_user_id', $admin->id)->firstOrFail();
        abort_unless($profile->enabled && in_array($profile->availability, ['online', 'away'], true), 422);
        $profile->update(['heartbeat_at' => now()]);

        return response()->json(['success' => true, 'data' => ['availability' => $profile->availability, 'heartbeat_at' => $profile->heartbeat_at?->toISOString()]]);
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
            $canViewAssigned = $admin->hasPermission('support.tickets.view_assigned');
            $canViewQueue = $admin->hasPermission('support.chat.participate');
            if (! $canViewAssigned && ! $canViewQueue) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function ($scope) use ($admin): void {
                $canViewAssigned = $admin->hasPermission('support.tickets.view_assigned');
                if ($canViewAssigned) {
                    $scope->where('assigned_admin_id', $admin->id);
                }

                if ($admin->hasPermission('support.chat.participate')) {
                    $method = $canViewAssigned ? 'orWhere' : 'where';
                    $scope->{$method}(function ($queued) use ($admin): void {
                        $queued->whereNull('assigned_admin_id')
                            ->whereHas('latestChatSession', fn ($chat) => $chat->where('state', 'queued'));
                        $this->scopeToAgentDepartments($queued, $admin);
                    });
                }
            });
        }
    }

    private function authorizeTicketAction(AdminUser $admin, SupportTicket $ticket): void
    {
        $this->require($admin, 'support.view');
        abort_unless($this->canActOnTicket($admin, $ticket), 403);
    }

    private function canActOnTicket(AdminUser $admin, SupportTicket $ticket): bool
    {
        return $admin->hasPermission('support.tickets.view_all')
            || ($admin->hasPermission('support.tickets.view_assigned') && $ticket->assigned_admin_id === $admin->id);
    }

    private function authorizeTicket(AdminUser $admin, SupportTicket $ticket): void
    {
        $this->require($admin, 'support.view');
        if (! $admin->hasPermission('support.tickets.view_all')) {
            $assigned = $admin->hasPermission('support.tickets.view_assigned') && $ticket->assigned_admin_id === $admin->id;
            abort_unless($assigned || $this->canViewQueuedChat($admin, $ticket), 403);
        }
    }

    private function canClaim(AdminUser $admin, SupportTicket $ticket): bool
    {
        if ($ticket->assigned_admin_id !== null) {
            return false;
        }

        if ($admin->hasPermission('support.tickets.assign')) {
            return true;
        }

        if (! $this->canViewQueuedChat($admin, $ticket)) {
            return false;
        }

        $profile = SupportAgentProfile::query()->where('admin_user_id', $admin->id)->first();
        if (! $profile?->isOnline()) {
            return false;
        }

        $departmentIds = $profile->departments()->pluck('support_departments.id');

        return $departmentIds->isEmpty() || $departmentIds->contains($ticket->department_id);
    }

    private function canViewQueuedChat(AdminUser $admin, SupportTicket $ticket): bool
    {
        if ($ticket->assigned_admin_id !== null || ! $admin->hasPermission('support.chat.participate') || ! $ticket->latestChatSession()->where('state', 'queued')->exists()) {
            return false;
        }

        $profile = SupportAgentProfile::query()->where('admin_user_id', $admin->id)->first();
        if (! $profile) {
            return false;
        }

        $departmentIds = $profile->departments()->pluck('support_departments.id');

        return $departmentIds->isEmpty() || $departmentIds->contains($ticket->department_id);
    }

    private function scopeToAgentDepartments($query, AdminUser $admin): void
    {
        $profile = SupportAgentProfile::query()->where('admin_user_id', $admin->id)->first();
        if (! $profile) {
            $query->whereRaw('1 = 0');

            return;
        }

        $departmentIds = $profile->departments()->pluck('support_departments.id');
        if ($departmentIds->isNotEmpty()) {
            $query->whereIn('department_id', $departmentIds);
        }
    }
}
