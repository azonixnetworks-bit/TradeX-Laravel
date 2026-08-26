<?php

namespace App\Services\Education;

use App\Exceptions\TranslationApiException;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\EducationContentLocalization;
use App\Models\LanguageProfile;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\User;
use Illuminate\Http\Request;
use JsonException;

class EducationContentLocalizationService
{
    public function __construct(
        private readonly Request $request
    ) {
    }

    public function course(User $user, Course $course): array
    {
        return $this->resolve($user, $course);
    }

    public function module(User $user, CourseModule $module): array
    {
        return $this->resolve($user, $module);
    }

    public function lesson(User $user, Lesson $lesson): array
    {
        return $this->resolve($user, $lesson);
    }

    public function video(User $user, LessonVideo $video): array
    {
        return $this->resolve($user, $video);
    }

    public function assignment(User $user, Assignment $assignment): array
    {
        return $this->resolve($user, $assignment);
    }

    public function sourceSha256(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content
    ): string {
        return $this->hashPayload(
            $this->canonicalPayload(
                $this->sourceFields($content),
                $this->fieldNames($content)
            )
        );
    }

    public function translationSha256(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content,
        array $translatedFields
    ): string {
        return $this->hashPayload(
            $this->canonicalPayload(
                $translatedFields,
                $this->fieldNames($content)
            )
        );
    }

    private function resolve(
        User $user,
        Course|CourseModule|Lesson|LessonVideo|Assignment $content
    ): array {
        $course = $this->sourceCourse($content);
        $sourceLanguage = $this->normalizeLanguageCode(
            (string) $course->language_code
        );

        if (! $this->languageKnown($sourceLanguage)) {
            throw new TranslationApiException(
                503,
                'EDUCATION_SOURCE_LANGUAGE_INVALID',
                'The education source language is not registered.'
            );
        }

        $requestedLanguage = $this->requestedLanguage(
            $user,
            $sourceLanguage
        );

        $sourceFields = $this->sourceFields($content);
        $sourceHash = $this->sourceSha256($content);

        if ($requestedLanguage === $sourceLanguage) {
            return $this->result(
                $sourceFields,
                $requestedLanguage,
                $sourceLanguage,
                $sourceLanguage,
                false,
                false,
                'SOURCE',
                null
            );
        }

        $profile = LanguageProfile::query()
            ->where('code', $requestedLanguage)
            ->first();

        if (
            ! $profile instanceof LanguageProfile
            || ! $profile->is_translation_enabled
        ) {
            return $this->result(
                $sourceFields,
                $requestedLanguage,
                $sourceLanguage,
                $sourceLanguage,
                true,
                false,
                'FALLBACK_SOURCE',
                'TRANSLATION_PROGRAM_DISABLED_OR_UNAVAILABLE'
            );
        }

        $localization = EducationContentLocalization::query()
            ->where('content_type', $this->contentType($content))
            ->where('content_id', $content->getKey())
            ->where('language_code', $requestedLanguage)
            ->where('status', EducationContentLocalization::STATUS_REVIEWED)
            ->first();

        if (
            ! $localization instanceof EducationContentLocalization
            || ! $this->isCurrentReviewedLocalization(
                $content,
                $localization,
                $sourceHash
            )
        ) {
            return $this->result(
                $sourceFields,
                $requestedLanguage,
                $sourceLanguage,
                $sourceLanguage,
                true,
                false,
                'FALLBACK_SOURCE',
                'REVIEWED_TRANSLATION_NOT_AVAILABLE'
            );
        }

        $translatedFields = [];

        foreach ($this->fieldNames($content) as $field) {
            $translatedFields[$field] = $localization->{$field};
        }

        return $this->result(
            $translatedFields,
            $requestedLanguage,
            $sourceLanguage,
            $requestedLanguage,
            false,
            true,
            'REVIEWED',
            null
        );
    }

    private function isCurrentReviewedLocalization(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content,
        EducationContentLocalization $localization,
        string $sourceHash
    ): bool {
        if (
            $localization->status !== EducationContentLocalization::STATUS_REVIEWED
            || blank($localization->reviewed_at)
            || blank($localization->source_sha256)
            || blank($localization->translation_sha256)
        ) {
            return false;
        }

        if (! hash_equals(
            strtolower($sourceHash),
            strtolower((string) $localization->source_sha256)
        )) {
            return false;
        }

        $translatedFields = [];
        $sourceFields = $this->sourceFields($content);

        foreach ($this->fieldNames($content) as $field) {
            $translatedFields[$field] = $localization->{$field};

            if (
                $this->hasText($sourceFields[$field] ?? null)
                && ! $this->hasText($translatedFields[$field] ?? null)
            ) {
                return false;
            }
        }

        $actualTranslationHash = $this->translationSha256(
            $content,
            $translatedFields
        );

        return hash_equals(
            strtolower((string) $localization->translation_sha256),
            strtolower($actualTranslationHash)
        );
    }

    private function sourceCourse(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content
    ): Course {
        if ($content instanceof Course) {
            return $content;
        }

        if ($content instanceof CourseModule) {
            $content->loadMissing('course');

            return $content->course;
        }

        if ($content instanceof Lesson) {
            $content->loadMissing('module.course');

            return $content->module->course;
        }

        if ($content instanceof LessonVideo) {
            $content->loadMissing('lesson.module.course');

            return $content->lesson->module->course;
        }

        $content->loadMissing('lesson.module.course');

        return $content->lesson->module->course;
    }

    private function sourceFields(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content
    ): array {
        $fields = [];

        foreach ($this->fieldNames($content) as $field) {
            $fields[$field] = $content->{$field};
        }

        return $fields;
    }

    private function fieldNames(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content
    ): array {
        return match (true) {
            $content instanceof Course => [
                'title',
                'short_description',
                'description',
            ],
            $content instanceof CourseModule => [
                'title',
                'short_description',
                'description',
            ],
            $content instanceof Lesson => [
                'title',
                'heading',
                'short_description',
                'description',
            ],
            $content instanceof LessonVideo => [
                'title',
                'short_description',
                'description',
            ],
            $content instanceof Assignment => [
                'title',
                'heading',
                'description',
                'submission_instructions',
            ],
        };
    }

    private function contentType(
        Course|CourseModule|Lesson|LessonVideo|Assignment $content
    ): string {
        return match (true) {
            $content instanceof Course => EducationContentLocalization::TYPE_COURSE,
            $content instanceof CourseModule => EducationContentLocalization::TYPE_MODULE,
            $content instanceof Lesson => EducationContentLocalization::TYPE_LESSON,
            $content instanceof LessonVideo => EducationContentLocalization::TYPE_VIDEO,
            $content instanceof Assignment => EducationContentLocalization::TYPE_ASSIGNMENT,
        };
    }

    private function requestedLanguage(
        User $user,
        string $sourceLanguage
    ): string {
        $queryParameter = (string) config(
            'tradex_education.localization.query_parameter',
            'locale'
        );
        $headerName = (string) config(
            'tradex_education.localization.header',
            'X-TradeX-Language'
        );

        $explicit = $this->request->query($queryParameter);

        if (! is_string($explicit) || trim($explicit) === '') {
            $explicit = $this->request->header($headerName);
        }

        if (is_string($explicit) && trim($explicit) !== '') {
            $normalized = $this->normalizeLanguageCode($explicit);

            if (! $this->languageAllowed($normalized)) {
                throw new TranslationApiException(
                    404,
                    'EDUCATION_LANGUAGE_NOT_SUPPORTED',
                    'The requested education language is not supported.'
                );
            }

            return $normalized;
        }

        $profileLanguage = $this->normalizeLanguageCode(
            (string) $user->language_code
        );

        return $this->languageAllowed($profileLanguage)
            ? $profileLanguage
            : $sourceLanguage;
    }

    private function result(
        array $fields,
        string $requestedLanguage,
        string $sourceLanguage,
        string $resolvedLanguage,
        bool $fallbackApplied,
        bool $reviewedTranslationUsed,
        string $status,
        ?string $fallbackReason
    ): array {
        return [
            'fields' => $fields,
            'localization' => [
                'runtime_state' => (string) config(
                    'tradex_languages.education_content_localization',
                    'WIRED_REVIEWED_RUNTIME'
                ),
                'requested_language_code' => $requestedLanguage,
                'source_language_code' => $sourceLanguage,
                'resolved_language_code' => $resolvedLanguage,
                'direction' => $this->direction($resolvedLanguage),
                'reviewed_translations_only' => true,
                'reviewed_translation_used' => $reviewedTranslationUsed,
                'fallback_applied' => $fallbackApplied,
                'fallback_reason' => $fallbackReason,
                'status' => $status,
            ],
        ];
    }

    private function languageAllowed(string $languageCode): bool
    {
        return in_array(
            $languageCode,
            array_map(
                fn (mixed $value): string => $this->normalizeLanguageCode(
                    (string) $value
                ),
                (array) config(
                    'tradex_registration.allowed_languages',
                    []
                )
            ),
            true
        );
    }

    private function languageKnown(string $languageCode): bool
    {
        return array_key_exists(
            $languageCode,
            (array) config('tradex_languages.languages', [])
        );
    }

    private function normalizeLanguageCode(string $languageCode): string
    {
        return strtolower(trim($languageCode));
    }

    private function direction(string $languageCode): string
    {
        return strtoupper(
            (string) config(
                'tradex_languages.languages.'.$languageCode.'.direction',
                'LTR'
            )
        );
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== '';
    }

    private function canonicalPayload(
        array $values,
        array $fields
    ): array {
        $payload = [];

        foreach ($fields as $field) {
            $value = $values[$field] ?? null;
            $payload[$field] = is_string($value)
                ? $this->canonicalText($value)
                : null;
        }

        return $payload;
    }

    private function canonicalText(string $text): string
    {
        return trim(
            str_replace(
                ["\r\n", "\r"],
                "\n",
                $text
            )
        );
    }

    /**
     * @throws JsonException
     */
    private function hashPayload(array $payload): string
    {
        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            )
        );
    }
}
