<?php

namespace App\Services\Education;

use App\Models\Lesson;
use App\Models\SecondBrainKnowledgeItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TradeXAiLearningService
{
    public function __construct(
        private readonly EducationResolver $resolver,
        private readonly EducationAccessService $access
    ) {}

    public function answer(User $user, string $question, ?string $lessonUuid): array
    {
        $lesson = null;
        if (filled($lessonUuid)) {
            $lesson = $this->resolver->lesson((string) $lessonUuid);
            $lesson->loadMissing('module.course');
            $this->access->assertLessonAccess($user, $lesson);
        }

        $terms = collect(preg_split('/[^\pL\pN]+/u', Str::lower($question)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->take(8)
            ->values();

        $query = SecondBrainKnowledgeItem::query()
            ->with('subject')
            ->whereIn('status', [
                SecondBrainKnowledgeItem::STATUS_APPROVED,
                SecondBrainKnowledgeItem::STATUS_VECTOR_INDEXED,
                SecondBrainKnowledgeItem::STATUS_MCP_SYNCED,
                SecondBrainKnowledgeItem::STATUS_ACTIVE,
            ]);

        if ($terms->isNotEmpty()) {
            $query->where(function (Builder $search) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.addcslashes($term, '%_\\').'%';
                    $search->orWhere('title', 'like', $like)
                        ->orWhere('heading', 'like', $like)
                        ->orWhere('notes', 'like', $like);
                }
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $sources = $query->orderByDesc('founder_approved_at')->limit(3)->get();
        $primary = $sources->first();

        return [
            'question' => $question,
            'answer' => $primary instanceof SecondBrainKnowledgeItem
                ? trim((string) ($primary->notes ?: $primary->title))
                : 'TradeX Second Brain does not yet have an approved answer for this question.',
            'retrieval' => [
                'source' => 'TRADEX_SECOND_BRAIN',
                'mode' => 'APPROVED_KNOWLEDGE_SEARCH',
                'generic_internet_used' => false,
            ],
            'based_on' => $lesson instanceof Lesson ? [
                'lesson_uuid' => $lesson->uuid,
                'lesson_title' => $lesson->title,
            ] : null,
            'sources' => $sources->map(fn (SecondBrainKnowledgeItem $item): array => [
                'uuid' => $item->uuid,
                'title' => $item->title,
                'subject' => $item->subject?->name,
                'knowledge_type' => $item->knowledge_type,
            ])->values()->all(),
        ];
    }
}
