<?php

namespace App\Http\Controllers\Admin\SecondBrainKnowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SecondBrainKnowledge\StoreSecondBrainKnowledgeSubjectRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\SecondBrainKnowledgeSubject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class KnowledgeSubjectController extends Controller
{
    public function index(): Response
    {
        $this->admin();

        return $this->view('admin.second_brain_knowledge.subjects', [
            'subjects' => SecondBrainKnowledgeSubject::query()
                ->withCount('items')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'marketScopes' => SecondBrainKnowledgeSubject::MARKET_SCOPES,
        ]);
    }

    public function store(
        StoreSecondBrainKnowledgeSubjectRequest $request
    ): RedirectResponse {
        $admin = $this->admin();

        $subject = SecondBrainKnowledgeSubject::query()->create([
            ...$request->validated(),
            'created_by_admin_id' => $admin->id,
            'is_active' => true,
        ]);

        AuditLog::record(
            'ADMIN',
            $admin->id,
            'SECOND_BRAIN_SUBJECT_CREATED',
            'SECOND_BRAIN_KNOWLEDGE',
            'second_brain_knowledge_subject',
            $subject->id,
            null,
            [
                'uuid' => $subject->uuid,
                'name' => $subject->name,
                'market_scope' => $subject->market_scope,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return redirect()
            ->route('admin.second_brain_knowledge.subjects')
            ->with('success', 'Knowledge subject created successfully.');
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
