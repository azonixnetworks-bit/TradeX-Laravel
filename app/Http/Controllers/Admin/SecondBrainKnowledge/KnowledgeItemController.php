<?php

namespace App\Http\Controllers\Admin\SecondBrainKnowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SecondBrainKnowledge\StoreSecondBrainKnowledgeItemRequest;
use App\Models\AdminUser;
use App\Models\SecondBrainKnowledgeItem;
use App\Models\SecondBrainKnowledgeMedia;
use App\Models\SecondBrainKnowledgeSubject;
use App\Services\Admin\SecondBrainKnowledge\SecondBrainKnowledgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeItemController extends Controller
{
    public function index(Request $request): Response
    {
        $this->admin();

        $market = strtoupper(trim((string) $request->query('market', '')));
        $status = strtoupper(trim((string) $request->query('status', '')));
        $type = strtoupper(trim((string) $request->query('type', '')));
        $search = trim((string) $request->query('q', ''));

        $items = SecondBrainKnowledgeItem::query()
            ->with('subject')
            ->withCount('media')
            ->when(
                in_array($market, SecondBrainKnowledgeItem::MARKETS, true),
                fn ($query) => $query->where('market', $market)
            )
            ->when(
                in_array($status, SecondBrainKnowledgeItem::STATUSES, true),
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                in_array($type, SecondBrainKnowledgeItem::KNOWLEDGE_TYPES, true),
                fn ($query) => $query->where('knowledge_type', $type)
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('heading', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->view('admin.second_brain_knowledge.library', [
            'items' => $items,
            'markets' => SecondBrainKnowledgeItem::MARKETS,
            'statuses' => SecondBrainKnowledgeItem::STATUSES,
            'knowledgeTypes' => SecondBrainKnowledgeItem::KNOWLEDGE_TYPES,
        ]);
    }

    public function create(): Response
    {
        $this->admin();

        return $this->view('admin.second_brain_knowledge.create', [
            'subjects' => SecondBrainKnowledgeSubject::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'markets' => SecondBrainKnowledgeItem::MARKETS,
            'timeframes' => SecondBrainKnowledgeItem::TIMEFRAMES,
            'knowledgeTypes' => SecondBrainKnowledgeItem::KNOWLEDGE_TYPES,
        ]);
    }

    public function store(
        StoreSecondBrainKnowledgeItemRequest $request,
        SecondBrainKnowledgeService $service
    ): RedirectResponse {
        $admin = $this->admin();

        $item = $service->createItem(
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route('admin.second_brain_knowledge.library.show', $item)
            ->with(
                'success',
                'Knowledge saved as DRAFT. It is not available to RAG, MCP or the Second Brain yet.'
            );
    }

    public function show(
        SecondBrainKnowledgeItem $knowledgeItem
    ): Response {
        $this->admin();

        $knowledgeItem->load([
            'subject',
            'media',
            'createdByAdmin',
            'approvedByAdmin',
        ]);

        return $this->view('admin.second_brain_knowledge.show', [
            'item' => $knowledgeItem,
        ]);
    }

    public function download(
        SecondBrainKnowledgeMedia $media
    ): StreamedResponse {
        $this->admin();

        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($media->path), 404);

        return $disk->download(
            $media->path,
            $media->original_name,
            [
                'Cache-Control' => 'private, no-store, no-cache, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser && $admin->isSuperAdmin(),
            403
        );

        return $admin;
    }

    private function view(string $view, array $data): Response
    {
        return response()->view($view, $data, 200, [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
