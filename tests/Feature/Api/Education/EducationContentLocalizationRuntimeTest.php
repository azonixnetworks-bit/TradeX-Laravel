<?php

namespace Tests\Feature\Api\Education;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\EducationContentLocalization;
use App\Models\LanguageProfile;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Services\Education\EducationContentLocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssignmentContent;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationContentLocalizationRuntimeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;
    use CreatesAssignmentContent;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'tradex_registration.allowed_languages',
            ['en', 'ta', 'si', 'hi', 'ml', 'te', 'ur']
        );

        foreach (
            [
                'en' => ['English', 'English', 'LTR', true],
                'ta' => ['Tamil', 'தமிழ்', 'LTR', false],
                'si' => ['Sinhala', 'සිංහල', 'LTR', false],
                'hi' => ['Hindi', 'हिन्दी', 'LTR', false],
                'ml' => ['Malayalam', 'മലയാളം', 'LTR', false],
                'te' => ['Telugu', 'తెలుగు', 'LTR', false],
                'ur' => ['Urdu', 'اردو', 'RTL', false],
            ] as $code => $definition
        ) {
            LanguageProfile::query()->updateOrCreate(
                ['code' => $code],
                [
                    'english_name' => $definition[0],
                    'native_name' => $definition[1],
                    'direction' => $definition[2],
                    'is_source' => $definition[3],
                    'is_translation_enabled' => true,
                    'version' => 1,
                ]
            );
        }
    }

    public function test_reviewed_tamil_content_is_used_across_course_module_lesson_and_video(): void
    {
        [$course, $module, $lesson, $video] =
            $this->createEducationTree(Course::ACCESS_FREE);

        $this->reviewedLocalization(
            $course,
            EducationContentLocalization::TYPE_COURSE,
            'ta',
            [
                'title' => 'TradeX தமிழ் பாடநெறி',
                'short_description' => 'பாடநெறி சுருக்கம்',
                'description' => 'பாடநெறி முழு விளக்கம்',
            ]
        );

        $this->reviewedLocalization(
            $module,
            EducationContentLocalization::TYPE_MODULE,
            'ta',
            [
                'title' => 'தொகுதி 1',
                'short_description' => 'தொகுதி சுருக்கம்',
                'description' => 'தொகுதி விளக்கம்',
            ]
        );

        $this->reviewedLocalization(
            $lesson,
            EducationContentLocalization::TYPE_LESSON,
            'ta',
            [
                'title' => 'பாடம் 1',
                'heading' => 'பாடத்தின் தலைப்பு',
                'short_description' => 'பாடச் சுருக்கம்',
                'description' => 'பாதுகாக்கப்பட்ட பாட விளக்கம்',
            ]
        );

        $this->reviewedLocalization(
            $video,
            EducationContentLocalization::TYPE_VIDEO,
            'ta',
            [
                'title' => 'பாட வீடியோ',
                'short_description' => 'வீடியோ சுருக்கம்',
                'description' => null,
            ]
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
                .'?locale=ta'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.course.title',
                'TradeX தமிழ் பாடநெறி'
            )
            ->assertJsonPath(
                'data.module.title',
                'தொகுதி 1'
            )
            ->assertJsonPath(
                'data.lesson.heading',
                'பாடத்தின் தலைப்பு'
            )
            ->assertJsonPath(
                'data.videos.0.title',
                'பாட வீடியோ'
            )
            ->assertJsonPath(
                'data.lesson.localization.requested_language_code',
                'ta'
            )
            ->assertJsonPath(
                'data.lesson.localization.resolved_language_code',
                'ta'
            )
            ->assertJsonPath(
                'data.lesson.localization.reviewed_translation_used',
                true
            )
            ->assertJsonPath(
                'data.lesson.localization.fallback_applied',
                false
            );
    }

    public function test_user_profile_language_is_used_when_locale_is_not_explicit(): void
    {
        [$course] = $this->createEducationTree(Course::ACCESS_FREE);

        $this->reviewedLocalization(
            $course,
            EducationContentLocalization::TYPE_COURSE,
            'ta',
            [
                'title' => 'தமிழ் பாடநெறி',
                'short_description' => 'தமிழ் சுருக்கம்',
                'description' => 'தமிழ் விளக்கம்',
            ]
        );

        $user = $this->createApiUser([
            'language_code' => 'ta',
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/education/courses')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.title',
                'தமிழ் பாடநெறி'
            )
            ->assertJsonPath(
                'data.items.0.language_code',
                'en'
            )
            ->assertJsonPath(
                'data.items.0.localization.resolved_language_code',
                'ta'
            );
    }

    public function test_explicit_locale_overrides_profile_language(): void
    {
        [$course] = $this->createEducationTree(Course::ACCESS_FREE);

        $this->reviewedLocalization(
            $course,
            EducationContentLocalization::TYPE_COURSE,
            'ta',
            [
                'title' => 'தமிழ் பாடநெறி',
                'short_description' => 'தமிழ் சுருக்கம்',
                'description' => 'தமிழ் விளக்கம்',
            ]
        );

        $user = $this->createApiUser([
            'language_code' => 'ta',
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/education/courses?locale=en')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.title',
                'TradeX Course'
            )
            ->assertJsonPath(
                'data.items.0.localization.status',
                'SOURCE'
            );
    }

    public function test_stale_reviewed_translation_falls_back_to_current_source_content(): void
    {
        [, , $lesson] =
            $this->createEducationTree(Course::ACCESS_FREE);

        $this->reviewedLocalization(
            $lesson,
            EducationContentLocalization::TYPE_LESSON,
            'ta',
            [
                'title' => 'தமிழ் பாடம்',
                'heading' => 'தமிழ் தலைப்பு',
                'short_description' => 'தமிழ் சுருக்கம்',
                'description' => 'தமிழ் விளக்கம்',
            ]
        );

        $lesson->forceFill([
            'heading' => 'Updated source heading',
        ])->save();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
                .'?locale=ta'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.lesson.title',
                'Lesson 1'
            )
            ->assertJsonPath(
                'data.lesson.heading',
                'Updated source heading'
            )
            ->assertJsonPath(
                'data.lesson.localization.fallback_applied',
                true
            )
            ->assertJsonPath(
                'data.lesson.localization.fallback_reason',
                'REVIEWED_TRANSLATION_NOT_AVAILABLE'
            );
    }

    public function test_draft_translation_is_never_served(): void
    {
        [$course] = $this->createEducationTree(Course::ACCESS_FREE);

        $this->reviewedLocalization(
            $course,
            EducationContentLocalization::TYPE_COURSE,
            'ta',
            [
                'title' => 'Draft Tamil Title',
                'short_description' => 'Draft Tamil Summary',
                'description' => 'Draft Tamil Description',
            ],
            EducationContentLocalization::STATUS_DRAFT
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/education/courses?locale=ta')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.title',
                'TradeX Course'
            )
            ->assertJsonPath(
                'data.items.0.localization.fallback_applied',
                true
            );
    }

    public function test_unsupported_explicit_locale_is_rejected(): void
    {
        $this->createEducationTree(Course::ACCESS_FREE);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/education/courses?locale=fr')
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'EDUCATION_LANGUAGE_NOT_SUPPORTED'
            );
    }

    public function test_course_specific_source_language_is_the_fallback_not_global_english(): void
    {
        [$course] = $this->createEducationTree(Course::ACCESS_FREE);

        $course->forceFill([
            'language_code' => 'ta',
            'title' => 'மூல தமிழ் பாடநெறி',
            'short_description' => 'மூல தமிழ் சுருக்கம்',
            'description' => 'மூல தமிழ் விளக்கம்',
        ])->save();

        $user = $this->createApiUser([
            'language_code' => 'hi',
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/education/courses')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.title',
                'மூல தமிழ் பாடநெறி'
            )
            ->assertJsonPath(
                'data.items.0.localization.source_language_code',
                'ta'
            )
            ->assertJsonPath(
                'data.items.0.localization.resolved_language_code',
                'ta'
            )
            ->assertJsonPath(
                'data.items.0.localization.fallback_applied',
                true
            );
    }

    public function test_assignment_and_whatsapp_action_use_reviewed_localized_content(): void
    {
        [, , $lesson] =
            $this->createEducationTree(
                Course::ACCESS_PREMIUM,
                false
            );
        $assignment = $this->createAssignment($lesson);

        $this->reviewedLocalization(
            $assignment,
            EducationContentLocalization::TYPE_ASSIGNMENT,
            'ta',
            [
                'title' => 'தமிழ் பணிப்பு',
                'heading' => 'சந்தை அமைப்பு பயிற்சியை முடிக்கவும்',
                'description' => 'பணிப்பை முடித்து WhatsApp மூலம் அனுப்பவும்.',
                'submission_instructions' => 'தெளிவான PDF அல்லது படத்தை அனுப்பவும்.',
            ]
        );

        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/education/assignments/'
                .$assignment->uuid
                .'?locale=ta'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.assignment.title',
                'தமிழ் பணிப்பு'
            )
            ->assertJsonPath(
                'data.assignment.heading',
                'சந்தை அமைப்பு பயிற்சியை முடிக்கவும்'
            )
            ->assertJsonPath(
                'data.assignment.localization.resolved_language_code',
                'ta'
            );

        $response = $this->withToken($token)
            ->postJson(
                '/api/v1/education/assignments/'
                .$assignment->uuid
                .'/whatsapp-action?locale=ta'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.localization.resolved_language_code',
                'ta'
            );

        $this->assertStringContainsString(
            'தமிழ் பணிப்பு',
            (string) $response->json('data.prefilled_message')
        );
        $this->assertStringContainsString(
            'தெளிவான PDF அல்லது படத்தை அனுப்பவும்.',
            (string) $response->json('data.prefilled_message')
        );
    }

    private function reviewedLocalization(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content,
        string $contentType,
        string $languageCode,
        array $fields,
        string $status = EducationContentLocalization::STATUS_REVIEWED
    ): EducationContentLocalization {
        $service = app(
            EducationContentLocalizationService::class
        );

        return EducationContentLocalization::query()->create([
            'content_type' => $contentType,
            'content_id' => $content->getKey(),
            'language_code' => $languageCode,
            'title' => $fields['title'] ?? null,
            'heading' => $fields['heading'] ?? null,
            'short_description' =>
                $fields['short_description'] ?? null,
            'description' => $fields['description'] ?? null,
            'submission_instructions' =>
                $fields['submission_instructions'] ?? null,
            'source_sha256' =>
                $service->sourceSha256($content),
            'translation_sha256' =>
                $service->translationSha256(
                    $content,
                    $fields
                ),
            'status' => $status,
            'version' => 1,
            'reviewed_at' =>
                $status === EducationContentLocalization::STATUS_REVIEWED
                    ? now()
                    : null,
        ]);
    }
}
