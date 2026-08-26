<?php

namespace App\Services\Languages;

use App\Exceptions\TranslationApiException;
use App\Models\LanguageProfile;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Support\Collection;
use JsonException;

class RuntimeTranslationService
{
    public function languages(): array
    {
        $allowed = $this->allowedLanguageCodes();

        $profiles = LanguageProfile::query()
            ->whereIn('code', $allowed)
            ->get()
            ->keyBy('code');

        $items = [];

        foreach ($allowed as $code) {
            $profile = $profiles->get($code);

            if (! $profile instanceof LanguageProfile) {
                throw new TranslationApiException(
                    503,
                    'LANGUAGE_PROFILE_NOT_AVAILABLE',
                    'The language registry is temporarily incomplete.'
                );
            }

            $items[] = $this->presentLanguage($profile);
        }

        $registryMaterial = array_map(
            static fn (array $item): array => [
                'code' => $item['code'],
                'direction' => $item['direction'],
                'is_source' => $item['is_source'],
                'translation_program_enabled' =>
                    $item['translation_program_enabled'],
            ],
            $items
        );

        return [
            'runtime_state' => (string) config(
                'tradex_translation_api.runtime_state',
                'WIRED_REVIEWED_RUNTIME'
            ),
            'source_language_code' => $this->sourceLanguageCode(),
            'fallback_language_code' => $this->sourceLanguageCode(),
            'registration_allowlist_preserved' => true,
            'items' => $items,
            'registry_sha256' => $this->hashPayload(
                $registryMaterial
            ),
        ];
    }

    public function bundle(
        string $languageCode,
        ?string $group = null
    ): array {
        $languageCode = strtolower(trim($languageCode));
        $group = $this->normalizeGroup($group);

        $profile = $this->languageProfile($languageCode);
        $sourceCode = $this->sourceLanguageCode();

        $query = TranslationKey::query()
            ->orderBy('group_name')
            ->orderBy('translation_key');

        if ($group !== null) {
            $query->where('group_name', $group);
        }

        /** @var Collection<int, TranslationKey> $keys */
        $keys = $query->get();

        $translations = collect();

        if (
            $languageCode !== $sourceCode
            && $keys->isNotEmpty()
        ) {
            $translations = TranslationValue::query()
                ->where('language_code', $languageCode)
                ->where(
                    'status',
                    TranslationValue::STATUS_REVIEWED
                )
                ->whereIn(
                    'translation_key_id',
                    $keys->pluck('id')
                )
                ->get()
                ->keyBy('translation_key_id');
        }

        $values = [];
        $reviewedCount = 0;
        $fallbackCount = 0;

        foreach ($keys as $key) {
            $sourceText = $this->verifiedSourceText($key);
            $resolvedText = $sourceText;

            if ($languageCode !== $sourceCode) {
                $translation = $translations->get($key->getKey());

                if (
                    $translation instanceof TranslationValue
                    && $this->isCurrentReviewedTranslation(
                        $translation,
                        $key
                    )
                ) {
                    $resolvedText = (string) $translation->translated_text;
                    $reviewedCount++;
                } else {
                    $fallbackCount++;
                }
            }

            $values[$key->group_name][$key->translation_key] =
                $resolvedText;
        }

        $keyCount = $keys->count();

        $bundleMaterial = [
            'language_code' => $languageCode,
            'source_language_code' => $sourceCode,
            'group' => $group,
            'values' => $values,
        ];

        return [
            'runtime_state' => (string) config(
                'tradex_translation_api.runtime_state',
                'WIRED_REVIEWED_RUNTIME'
            ),
            'reviewed_translations_only' => true,
            'fallback_applied' => $languageCode !== $sourceCode,
            'fallback_language_code' => $sourceCode,
            'language' => $this->presentLanguage($profile),
            'group' => $group,
            'key_count' => $keyCount,
            'reviewed_translation_count' =>
                $languageCode === $sourceCode
                    ? $keyCount
                    : $reviewedCount,
            'fallback_count' =>
                $languageCode === $sourceCode
                    ? 0
                    : $fallbackCount,
            'is_complete' =>
                $languageCode === $sourceCode
                    || $fallbackCount === 0,
            'values' => $values,
            'bundle_sha256' => $this->hashPayload(
                $bundleMaterial
            ),
        ];
    }

    private function languageProfile(
        string $languageCode
    ): LanguageProfile {
        if (
            ! in_array(
                $languageCode,
                $this->allowedLanguageCodes(),
                true
            )
        ) {
            throw new TranslationApiException(
                404,
                'LANGUAGE_NOT_SUPPORTED',
                'The requested language is not supported by this TradeX runtime.'
            );
        }

        $profile = LanguageProfile::query()
            ->where('code', $languageCode)
            ->first();

        if (! $profile instanceof LanguageProfile) {
            throw new TranslationApiException(
                503,
                'LANGUAGE_PROFILE_NOT_AVAILABLE',
                'The requested language profile is temporarily unavailable.'
            );
        }

        return $profile;
    }

    private function normalizeGroup(
        ?string $group
    ): ?string {
        if ($group === null || trim($group) === '') {
            return null;
        }

        $group = strtoupper(trim($group));

        if (
            ! array_key_exists(
                $group,
                (array) config(
                    'tradex_languages.groups',
                    []
                )
            )
        ) {
            throw new TranslationApiException(
                404,
                'TRANSLATION_GROUP_NOT_FOUND',
                'The requested translation group is not registered.'
            );
        }

        return $group;
    }

    private function verifiedSourceText(
        TranslationKey $key
    ): string {
        $text = $this->canonicalText(
            (string) $key->source_text
        );

        $actualHash = hash('sha256', $text);

        if (
            blank($key->source_sha256)
            || ! hash_equals(
                strtolower((string) $key->source_sha256),
                strtolower($actualHash)
            )
        ) {
            throw new TranslationApiException(
                503,
                'TRANSLATION_SOURCE_INTEGRITY_FAILED',
                'A translation source integrity check failed.'
            );
        }

        return $text;
    }

    private function isCurrentReviewedTranslation(
        TranslationValue $translation,
        TranslationKey $key
    ): bool {
        if (
            $translation->status
                !== TranslationValue::STATUS_REVIEWED
            || blank($translation->translated_text)
            || blank($translation->reviewed_at)
            || blank($translation->source_sha256_at_translation)
            || blank($translation->translation_sha256)
        ) {
            return false;
        }

        if (
            ! hash_equals(
                strtolower((string) $key->source_sha256),
                strtolower(
                    (string) $translation
                        ->source_sha256_at_translation
                )
            )
        ) {
            return false;
        }

        $translatedText = $this->canonicalText(
            (string) $translation->translated_text
        );

        return hash_equals(
            strtolower((string) $translation->translation_sha256),
            strtolower(hash('sha256', $translatedText))
        );
    }

    private function presentLanguage(
        LanguageProfile $profile
    ): array {
        return [
            'code' => $profile->code,
            'english_name' => $profile->english_name,
            'native_name' => $profile->native_name,
            'direction' => $profile->direction,
            'is_source' => (bool) $profile->is_source,
            'translation_program_enabled' =>
                (bool) $profile->is_translation_enabled,
            'registration_allowed' => in_array(
                $profile->code,
                $this->allowedLanguageCodes(),
                true
            ),
        ];
    }

    private function allowedLanguageCodes(): array
    {
        return array_values(
            array_map(
                static fn (mixed $code): string =>
                    strtolower(trim((string) $code)),
                (array) config(
                    'tradex_registration.allowed_languages',
                    []
                )
            )
        );
    }

    private function sourceLanguageCode(): string
    {
        return strtolower(
            trim(
                (string) config(
                    'tradex_languages.source_language',
                    'en'
                )
            )
        );
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
