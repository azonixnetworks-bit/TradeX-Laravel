<?php

namespace App\Http\Controllers\Admin\SecondBrainKnowledge;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SecondBrainKnowledgeController extends Controller
{
    public function index(): View { return $this->render('dashboard', 'Knowledge Dashboard', 'Capture, review and prepare founder-approved trading knowledge for the Second Brain.'); }
    public function subjects(): View { return $this->render('subjects', 'Subjects', 'Organize trading knowledge by concept, market and methodology subject.'); }
    public function library(): View { return $this->render('library', 'Knowledge Library', 'Manage notes and source material before AI extraction and approval.'); }
    public function rules(): View { return $this->render('rules', 'Rules', 'Structured confirmations, invalidations, exceptions and no-trade rules.'); }
    public function examples(): View { return $this->render('examples', 'Examples', 'Valid, invalid, winning, losing and borderline chart examples.'); }
    public function media(): View { return $this->render('media', 'Media Library', 'Images, PDFs and videos linked to trading concepts and rules.'); }
    public function review(): View { return $this->render('review', 'AI Extraction Review', 'Review AI-extracted knowledge before founder approval.'); }
    public function rag(): View { return $this->render('rag', 'RAG / Vector Status', 'Future chunking, embeddings and retrieval indexing status.'); }
    public function mcpSync(): View { return $this->render('mcp', 'MCP Knowledge Sync', 'Future controlled sync of approved knowledge to the Second Brain MCP layer.'); }
    public function versions(): View { return $this->render('versions', 'Version History', 'Track revisions, approvals and deprecated knowledge safely.'); }

    private function render(string $section, string $title, string $description): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin && $admin->isSuperAdmin(), 403);

        return view('admin.second_brain_knowledge.index', compact('section', 'title', 'description'));
    }
}
