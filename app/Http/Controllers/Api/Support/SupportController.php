<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Support\StoreSupportFeedbackRequest;
use App\Http\Requests\Api\Support\StoreSupportMessageRequest;
use App\Http\Requests\Api\Support\StoreSupportTicketRequest;
use App\Models\SupportAgentProfile;
use App\Models\SupportCategory;
use App\Models\SupportChatSession;
use App\Models\SupportFaq;
use App\Models\SupportFeedback;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportController extends Controller
{
    public function __construct(private readonly SupportTicketService $service) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $base = SupportTicket::query()->where('user_id', $user->id);
        $agentsOnline = $this->onlineAgentsCount();

        return $this->ok([
            'ticket_counts' => [
                'open' => (clone $base)->whereIn('status', ['open', 'in_progress', 'waiting_for_customer'])->count(),
                'resolved' => (clone $base)->where('status', 'resolved')->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
            ],
            'live_chat' => [
                'agents_online' => $agentsOnline,
                'available' => $agentsOnline > 0,
                'eta_minutes' => null,
            ],
            'mail_delivery_configured' => (bool) config('support.mail_enabled'),
        ]);
    }

    public function categories(): JsonResponse
    {
        $items = SupportCategory::query()->with('department')->where('enabled', true)->orderBy('sort_order')->get()->map(fn ($item) => [
            'uuid' => $item->uuid, 'slug' => $item->slug, 'label' => $item->label,
            'department' => $item->department ? ['uuid' => $item->department->uuid, 'name' => $item->department->name] : null,
            'intake_schema' => $item->intake_schema ?? [], 'schema_version' => $item->schema_version,
        ]);

        return $this->ok($items);
    }

    public function faq(Request $request): JsonResponse
    {
        $query = SupportFaq::query()->where('published', true)->orderBy('sort_order')->orderBy('id');
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('question', 'like', '%'.$request->string('q').'%')->orWhere('answer', 'like', '%'.$request->string('q').'%'));
        }
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('uuid', $request->string('category')));
        }
        $page = $query->paginate(min(50, max(1, $request->integer('per_page', 20))));

        return $this->page($page, fn ($item) => ['uuid' => $item->uuid, 'question' => $item->question, 'answer' => $item->answer]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->with(['category', 'department', 'assignedAdmin'])->where('user_id', $this->user($request)->id)->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('public_reference', 'like', '%'.$request->string('q').'%')->orWhere('subject', 'like', '%'.$request->string('q').'%'));
        }

        return $this->page($query->paginate(min(50, max(1, $request->integer('per_page', 20)))), fn ($ticket) => $this->ticketData($ticket));
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $key = $request->idempotencyKey();
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An Idempotency-Key header is required.']);
        }
        $ticket = $this->service->create($this->user($request), $request->validated(), $key);

        return $this->ok($this->ticketData($ticket), 201);
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        return $this->ok($this->ticketData($this->ownedTicket($ticket, $this->user($request))->load(['category', 'department', 'assignedAdmin', 'latestChatSession'])));
    }

    public function messages(Request $request, string $ticket): JsonResponse
    {
        $owned = $this->ownedTicket($ticket, $this->user($request));
        $query = $owned->messages()->with('attachments')->where('visibility', 'public')->orderBy('id');
        if ($request->filled('after')) {
            $query->where('id', '>', max(0, $request->integer('after')));
        }
        $messages = $query->limit(100)->get()->map(fn ($message) => $this->messageData($message));

        return $this->ok(['items' => $messages, 'next_cursor' => $messages->last()['cursor'] ?? null]);
    }

    public function reply(StoreSupportMessageRequest $request, string $ticket): JsonResponse
    {
        $key = $request->idempotencyKey();
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An Idempotency-Key header is required.']);
        }
        $message = $this->service->addCustomerMessage($this->ownedTicket($ticket, $this->user($request)), $this->user($request), $request->validated('message'), $key);

        return $this->ok($this->messageData($message), 201);
    }

    public function upload(Request $request, string $ticket): JsonResponse
    {
        $owned = $this->ownedTicket($ticket, $this->user($request));
        $request->validate(['files' => ['required', 'array', 'max:'.config('support.max_attachments_per_request')], 'files.*' => ['required', 'file', 'max:'.config('support.max_attachment_kb'), 'mimetypes:'.implode(',', config('support.allowed_mimes'))], 'message_uuid' => ['nullable', 'uuid']]);
        $message = $request->filled('message_uuid') ? $owned->messages()->where('uuid', $request->string('message_uuid'))->firstOrFail() : null;
        $stored = [];
        foreach ($request->file('files') as $file) {
            $uuid = (string) Str::uuid();
            $mime = (string) $file->getMimeType();
            if (! in_array($mime, config('support.allowed_mimes'), true)) {
                throw ValidationException::withMessages(['files' => 'Unsupported file signature.']);
            }
            $extension = match ($mime) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'
            };
            $path = $file->storeAs('support/'.$owned->uuid, $uuid.'.'.$extension, config('support.private_disk'));
            $attachment = SupportTicketAttachment::create([
                'uuid' => $uuid, 'ticket_id' => $owned->id, 'message_id' => $message?->id,
                'uploader_type' => 'customer', 'uploader_user_id' => $this->user($request)->id,
                'disk' => config('support.private_disk'), 'path' => $path,
                'original_name' => Str::limit(basename((string) $file->getClientOriginalName()), 255, ''),
                'mime_type' => $mime, 'byte_size' => $file->getSize(), 'content_hash' => hash_file('sha256', $file->getRealPath()),
            ]);
            $stored[] = $this->attachmentData($attachment);
        }

        return $this->ok($stored, 201);
    }

    public function download(Request $request, string $attachment): StreamedResponse
    {
        $item = SupportTicketAttachment::query()->where('uuid', $attachment)->firstOrFail();
        abort_unless($item->ticket()->where('user_id', $this->user($request)->id)->exists(), 404);
        abort_unless(Storage::disk($item->disk)->exists($item->path), 404);

        return Storage::disk($item->disk)->download($item->path, $item->original_name, ['Content-Type' => $item->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function reopen(Request $request, string $ticket): JsonResponse
    {
        $owned = $this->ownedTicket($ticket, $this->user($request));
        abort_unless(in_array($owned->status, ['resolved', 'closed'], true), 422);
        $closedAt = $owned->closed_at ?: $owned->resolved_at;
        abort_if($closedAt && $closedAt->lt(now()->subDays((int) config('support.reopen_days'))), 422, 'The reopen window has expired.');

        return $this->ok($this->ticketData($this->service->transition($owned, $this->user($request), 'in_progress')));
    }

    public function feedback(StoreSupportFeedbackRequest $request, string $ticket): JsonResponse
    {
        $owned = $this->ownedTicket($ticket, $this->user($request));
        abort_unless(in_array($owned->status, ['resolved', 'closed'], true) && $owned->resolution_cycle > 0, 422);
        if (SupportFeedback::query()->where('ticket_id', $owned->id)->where('resolution_cycle', $owned->resolution_cycle)->exists()) {
            throw ValidationException::withMessages(['feedback' => 'Feedback has already been submitted for this resolution.']);
        }
        $feedback = SupportFeedback::create(['ticket_id' => $owned->id, 'user_id' => $owned->user_id, 'resolution_cycle' => $owned->resolution_cycle, ...$request->validated()]);

        return $this->ok(['uuid' => $feedback->uuid, 'rating' => $feedback->rating, 'comment' => $feedback->comment], 201);
    }

    public function startChat(StoreSupportTicketRequest $request): JsonResponse
    {
        $key = $request->idempotencyKey();
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An Idempotency-Key header is required.']);
        }

        return $this->ok($this->chatData($this->service->startChat($this->user($request), $request->validated(), $key)), 201);
    }

    public function chat(Request $request, string $chat): JsonResponse
    {
        return $this->ok($this->chatData($this->ownedChat($chat, $this->user($request))));
    }

    public function cancelChat(Request $request, string $chat): JsonResponse
    {
        return $this->endChatState($request, $chat, 'cancelled', 'customer_cancelled');
    }

    public function endChat(Request $request, string $chat): JsonResponse
    {
        return $this->endChatState($request, $chat, 'ended', 'customer_ended');
    }

    public function heartbeat(Request $request, string $chat): JsonResponse
    {
        $session = $this->ownedChat($chat, $this->user($request));
        abort_unless(in_array($session->state, SupportChatSession::ACTIVE_STATES, true), 422);
        $session->update([
            'last_customer_heartbeat_at' => now(),
            'state' => $session->state === 'assigned' ? 'active' : $session->state,
        ]);

        return $this->ok($this->chatData($session->fresh()));
    }

    public function events(Request $request, string $chat): JsonResponse
    {
        $session = $this->ownedChat($chat, $this->user($request));
        $after = max(0, $request->integer('after'));
        $events = $session->ticket->messages()->where('visibility', 'public')->where('id', '>', $after)->orderBy('id')->limit(100)->get()->map(fn ($message) => $this->messageData($message));

        return $this->ok(['chat' => $this->chatData($session), 'events' => $events, 'next_cursor' => $events->last()['cursor'] ?? $after]);
    }

    private function endChatState(Request $request, string $chat, string $state, string $reason): JsonResponse
    {
        $session = $this->ownedChat($chat, $this->user($request));
        if (in_array($session->state, SupportChatSession::ACTIVE_STATES, true)) {
            $session->update(['state' => $state, 'active_user_id' => null, 'ended_at' => now(), 'closure_reason' => $reason]);
        }

        return $this->ok($this->chatData($session->fresh()));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function ownedTicket(string $uuid, User $user): SupportTicket
    {
        return SupportTicket::query()->where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();
    }

    private function ownedChat(string $uuid, User $user): SupportChatSession
    {
        return SupportChatSession::query()->with('ticket')->where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();
    }

    private function ticketData(SupportTicket $ticket): array
    {
        return [
            'uuid' => $ticket->uuid, 'reference' => $ticket->public_reference, 'source' => $ticket->source,
            'subject' => $ticket->subject, 'description' => $ticket->description, 'status' => $ticket->status,
            'priority' => $ticket->priority, 'category' => $ticket->category ? ['uuid' => $ticket->category->uuid, 'label' => $ticket->category->label, 'slug' => $ticket->category->slug] : null,
            'department' => $ticket->department ? ['uuid' => $ticket->department->uuid, 'name' => $ticket->department->name] : null,
            'assigned_agent' => $ticket->assignedAdmin ? ['name' => $ticket->assignedAdmin->name] : null,
            'first_response_due_at' => $ticket->first_response_due_at?->toISOString(), 'resolution_due_at' => $ticket->resolution_due_at?->toISOString(),
            'first_responded_at' => $ticket->first_responded_at?->toISOString(), 'resolved_at' => $ticket->resolved_at?->toISOString(),
            'created_at' => $ticket->created_at?->toISOString(), 'updated_at' => $ticket->updated_at?->toISOString(),
        ];
    }

    private function messageData($message): array
    {
        return ['uuid' => $message->uuid, 'cursor' => $message->id, 'sender_type' => $message->sender_type, 'message' => $message->body, 'created_at' => $message->created_at?->toISOString(), 'attachments' => $message->relationLoaded('attachments') ? $message->attachments->map(fn ($item) => $this->attachmentData($item))->values() : []];
    }

    private function attachmentData(SupportTicketAttachment $item): array
    {
        return ['uuid' => $item->uuid, 'name' => $item->original_name, 'mime_type' => $item->mime_type, 'byte_size' => $item->byte_size, 'download_url' => route('api.v1.support.attachments.download', $item->uuid)];
    }

    private function chatData(SupportChatSession $chat): array
    {
        $chat->loadMissing(['ticket.assignedAdmin']);
        $ahead = $chat->state === 'queued' ? SupportChatSession::query()->where('state', 'queued')->where('queued_at', '<', $chat->queued_at)->count() : 0;

        return [
            'uuid' => $chat->uuid,
            'ticket_uuid' => $chat->ticket->uuid,
            'ticket_reference' => $chat->ticket->public_reference,
            'state' => $chat->state,
            'queue_position' => $chat->state === 'queued' ? $ahead + 1 : null,
            'eta_minutes' => null,
            'agents_online' => $this->onlineAgentsCount(),
            'assigned_agent' => $chat->ticket->assignedAdmin ? ['name' => $chat->ticket->assignedAdmin->name] : null,
            'queued_at' => $chat->queued_at?->toISOString(),
            'accepted_at' => $chat->accepted_at?->toISOString(),
            'ended_at' => $chat->ended_at?->toISOString(),
        ];
    }

    private function onlineAgentsCount(): int
    {
        return SupportAgentProfile::query()
            ->where('enabled', true)
            ->where('availability', 'online')
            ->where('heartbeat_at', '>=', now()->subSeconds((int) config('support.agent_presence_ttl_seconds')))
            ->count();
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function page($page, callable $map): JsonResponse
    {
        return $this->ok(['items' => collect($page->items())->map($map)->values(), 'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }
}
