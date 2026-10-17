<?php

namespace Tests\Feature\Api\Education;

use App\Models\Course;
use App\Models\SecondBrainKnowledgeItem;
use App\Models\SecondBrainKnowledgeSubject;
use App\Models\UserLessonBookmark;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class LearningHubApiTest extends TestCase
{
    use CreatesEducationContent;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_overview_and_my_learning_use_persisted_progress(): void
    {
        [, , $lesson] = $this->createEducationTree(Course::ACCESS_FREE);
        $user = $this->createApiUser();
        UserLessonProgress::query()->create([
            'user_id' => $user->getKey(),
            'lesson_id' => $lesson->getKey(),
            'status' => UserLessonProgress::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
            'last_activity_at' => now(),
            'last_position_seconds' => 42,
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)->getJson('/api/v1/education/overview')
            ->assertOk()
            ->assertJsonPath('data.learning.continue_learning.lesson.uuid', $lesson->uuid);

        $this->withToken($token)->getJson('/api/v1/education/my-learning?view=CONTINUE')
            ->assertOk()
            ->assertJsonPath('data.items.0.lesson.uuid', $lesson->uuid)
            ->assertJsonPath('data.items.0.last_position_seconds', 42);
    }

    public function test_bookmarks_are_user_scoped_and_premium_access_is_enforced(): void
    {
        [, , $lesson] = $this->createEducationTree(Course::ACCESS_PREMIUM);
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->putJson('/api/v1/education/lessons/'.$lesson->uuid.'/bookmark')
            ->assertForbidden();

        $this->assertDatabaseCount((new UserLessonBookmark)->getTable(), 0);
    }

    public function test_ai_does_not_return_unapproved_second_brain_content(): void
    {
        $subject = SecondBrainKnowledgeSubject::query()->create([
            'name' => 'Market Structure',
            'slug' => 'market-structure',
            'market_scope' => 'COMMON',
            'is_active' => true,
        ]);
        SecondBrainKnowledgeItem::query()->create([
            'subject_id' => $subject->getKey(),
            'title' => 'Secret liquidity model',
            'notes' => 'This draft must never be returned.',
            'market' => 'COMMON',
            'knowledge_type' => 'NOTE',
            'status' => SecondBrainKnowledgeItem::STATUS_DRAFT,
            'version' => 1,
        ]);
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)->postJson('/api/v1/education/ai/ask', [
            'question' => 'Explain the secret liquidity model',
        ])->assertOk()
            ->assertJsonPath('data.retrieval.generic_internet_used', false)
            ->assertJsonCount(0, 'data.sources')
            ->assertJsonMissing(['answer' => 'This draft must never be returned.']);
    }
}
