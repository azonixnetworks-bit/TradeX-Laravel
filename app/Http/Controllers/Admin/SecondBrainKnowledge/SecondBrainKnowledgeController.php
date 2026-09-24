<?php

namespace App\Http\Controllers\Admin\SecondBrainKnowledge;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\SecondBrainKnowledgeItem;
use App\Models\SecondBrainKnowledgeMedia;
use App\Models\SecondBrainKnowledgeSubject;
use Illuminate\Http\Response;

class SecondBrainKnowledgeController extends Controller
{
    public function index(): Response
    {
        $this->admin();

        return $this->render(
            'dashboard',
            'Knowledge Dashboard',
            'Capture, review and prepare founder-approved trading knowledge for the Second Brain.'
        );
    }

    public function rules(): Response
    {
        return $this->render('rules', 'Rules', 'Structured confirmations, invalidations, exceptions and no-trade rules.');
    }

    public function examples(): Response
    {
        return $this->render('examples', 'Examples', 'Valid, invalid, winning, losing and borderline chart examples.');
    }

    public function media(): Response
    {
        return $this->render('media', 'Media Library', 'Images, PDFs and videos linked to trading concepts and rules.');
    }

    public function review(): Response
    {
        return $this->render('review', 'AI Extraction Review', 'Review AI-extracted knowledge before founder approval.');
    }

    public function rag(): Response
    {
        return $this->render('rag', 'RAG / Vector Status', 'Future chunking, embeddings and retrieval indexing status.');
    }

    public function mcpSync(): Response
    {
        return $this->render('mcp', 'MCP Knowledge Sync', 'Future controlled sync of approved knowledge to the Second Brain MCP layer.');
    }

    public function versions(): Response
    {
        return $this->render('versions', 'Version History', 'Track revisions, approvals and deprecated knowledge safely.');
    }

    private function render(
        string $section,
        string $title,
        string $description
    ): Response {
        $this->admin();

        return $this->view('admin.second_brain_knowledge.index', [
            'section' => $section,
            'title' => $title,
            'description' => $description,
            'metrics' => [
                'subjects' => SecondBrainKnowledgeSubject::query()->count(),
                'items' => SecondBrainKnowledgeItem::query()->count(),
                'drafts' => SecondBrainKnowledgeItem::query()
                    ->where('status', SecondBrainKnowledgeItem::STATUS_DRAFT)
                    ->count(),
                'approved' => SecondBrainKnowledgeItem::query()
                    ->whereNotNull('founder_approved_at')
                    ->count(),
                'images' => SecondBrainKnowledgeMedia::query()
                    ->where('media_type', SecondBrainKnowledgeMedia::TYPE_IMAGE)
                    ->count(),
                'pdfs' => SecondBrainKnowledgeMedia::query()
                    ->where('media_type', SecondBrainKnowledgeMedia::TYPE_PDF)
                    ->count(),
                'videos' => SecondBrainKnowledgeMedia::query()
                    ->where('media_type', SecondBrainKnowledgeMedia::TYPE_VIDEO)
                    ->count(),
            ],
        ]);
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
