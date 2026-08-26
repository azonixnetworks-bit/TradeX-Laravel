<?php

namespace Tests\Feature\Api\Languages;

use App\Models\LanguageProfile;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RuntimeTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_registry_exposes_exact_frozen_registration_languages(): void
    {
        $response = $this
            ->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonPath(
                'data.runtime_state',
                'WIRED_REVIEWED_RUNTIME'
            )
            ->assertJsonPath(
                'data.source_language_code',
                'en'
            )
            ->assertJsonCount(7, 'data.items');

        $this->assertSame(
            ['en', 'ta', 'si', 'hi', 'ml', 'te', 'ur'],
            collect($response->json('data.items'))
                ->pluck('code')
                ->all()
        );

        $urdu = collect($response->json('data.items'))
            ->firstWhere('code', 'ur');

        $this->assertSame('RTL', $urdu['direction']);
        $this->assertTrue($urdu['registration_allowed']);

        $this->assertSame(
            'NOT_WIRED',
            config('tradex_languages.runtime_translation_api')
        );
    }

    public function test_english_bundle_uses_verified_source_strings(): void
    {
        $key = $this->sourceKey(
            'AUTH',
            'login.title',
            'Sign in to TradeX'
        );

        $response = $this
            ->getJson('/api/v1/translations/en')
            ->assertOk()
            ->assertJsonPath(
                'data.values.AUTH.login.title',
                'Sign in to TradeX'
            )
            ->assertJsonPath('data.key_count', 1)
            ->assertJsonPath(
                'data.reviewed_translation_count',
                1
            )
            ->assertJsonPath('data.fallback_count', 0)
            ->assertJsonPath('data.is_complete', true);

        $this->assertSame(
            $key->source_sha256,
            hash('sha256', 'Sign in to TradeX')
        );

        $this->assertNotEmpty(
            $response->headers->get('ETag')
        );
    }

    public function test_target_bundle_serves_only_reviewed_current_translation(): void
    {
        $key = $this->sourceKey(
            'GENERAL',
            'common.continue',
            'Continue'
        );

        $this->reviewedTranslation(
            $key,
            'ta',
            'தொடரவும்'
        );

        $this
            ->getJson('/api/v1/translations/ta')
            ->assertOk()
            ->assertJsonPath(
                'data.values.GENERAL.common.continue',
                'தொடரவும்'
            )
            ->assertJsonPath(
                'data.reviewed_translation_count',
                1
            )
            ->assertJsonPath('data.fallback_count', 0)
            ->assertJsonPath('data.is_complete', true);
    }

    public function test_draft_stale_and_integrity_invalid_values_fall_back_to_english_source(): void
    {
        $draftKey = $this->sourceKey(
            'APP_UI',
            'home.draft',
            'Draft fallback'
        );

        $staleKey = $this->sourceKey(
            'APP_UI',
            'home.stale',
            'Stale fallback'
        );

        $tamperedKey = $this->sourceKey(
            'APP_UI',
            'home.tampered',
            'Tampered fallback'
        );

        TranslationValue::query()->create([
            'uuid' => (string) Str::uuid(),
            'translation_key_id' => $draftKey->getKey(),
            'language_code' => 'ta',
            'translated_text' => 'DRAFT SHOULD NOT LEAK',
            'source_sha256_at_translation' =>
                $draftKey->source_sha256,
            'translation_sha256' =>
                hash('sha256', 'DRAFT SHOULD NOT LEAK'),
            'status' => TranslationValue::STATUS_DRAFT,
            'version' => 1,
        ]);

        TranslationValue::query()->create([
            'uuid' => (string) Str::uuid(),
            'translation_key_id' => $staleKey->getKey(),
            'language_code' => 'ta',
            'translated_text' => 'STALE SHOULD NOT LEAK',
            'source_sha256_at_translation' =>
                str_repeat('a', 64),
            'translation_sha256' =>
                hash('sha256', 'STALE SHOULD NOT LEAK'),
            'status' => TranslationValue::STATUS_REVIEWED,
            'version' => 2,
            'reviewed_at' => now(),
        ]);

        TranslationValue::query()->create([
            'uuid' => (string) Str::uuid(),
            'translation_key_id' => $tamperedKey->getKey(),
            'language_code' => 'ta',
            'translated_text' => 'TAMPERED SHOULD NOT LEAK',
            'source_sha256_at_translation' =>
                $tamperedKey->source_sha256,
            'translation_sha256' =>
                str_repeat('b', 64),
            'status' => TranslationValue::STATUS_REVIEWED,
            'version' => 2,
            'reviewed_at' => now(),
        ]);

        $response = $this
            ->getJson('/api/v1/translations/ta')
            ->assertOk()
            ->assertJsonPath(
                'data.values.APP_UI.home.draft',
                'Draft fallback'
            )
            ->assertJsonPath(
                'data.values.APP_UI.home.stale',
                'Stale fallback'
            )
            ->assertJsonPath(
                'data.values.APP_UI.home.tampered',
                'Tampered fallback'
            )
            ->assertJsonPath('data.fallback_count', 3)
            ->assertJsonPath('data.is_complete', false);

        $body = $response->getContent();

        $this->assertStringNotContainsString(
            'DRAFT SHOULD NOT LEAK',
            $body
        );
        $this->assertStringNotContainsString(
            'STALE SHOULD NOT LEAK',
            $body
        );
        $this->assertStringNotContainsString(
            'TAMPERED SHOULD NOT LEAK',
            $body
        );
    }

    public function test_group_bundle_returns_only_requested_registered_group(): void
    {
        $this->sourceKey(
            'AUTH',
            'login.button',
            'Login'
        );

        $this->sourceKey(
            'GENERAL',
            'common.ok',
            'OK'
        );

        $this
            ->getJson('/api/v1/translations/en/auth')
            ->assertOk()
            ->assertJsonPath('data.group', 'AUTH')
            ->assertJsonCount(1, 'data.values.AUTH')
            ->assertJsonMissingPath('data.values.GENERAL');

        $this
            ->getJson('/api/v1/translations/en/not_a_group')
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'TRANSLATION_GROUP_NOT_FOUND'
            );
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this
            ->getJson('/api/v1/translations/fr')
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'LANGUAGE_NOT_SUPPORTED'
            );
    }

    public function test_translation_program_flag_does_not_rewrite_runtime_or_registration_contract(): void
    {
        LanguageProfile::query()
            ->where('code', 'ta')
            ->update([
                'is_translation_enabled' => false,
            ]);

        $key = $this->sourceKey(
            'GENERAL',
            'common.save',
            'Save'
        );

        $this->reviewedTranslation(
            $key,
            'ta',
            'சேமிக்கவும்'
        );

        $this
            ->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'ta',
                'translation_program_enabled' => false,
                'registration_allowed' => true,
            ]);

        $this
            ->getJson('/api/v1/translations/ta')
            ->assertOk()
            ->assertJsonPath(
                'data.values.GENERAL.common.save',
                'சேமிக்கவும்'
            );
    }

    public function test_bundle_supports_etag_conditional_get(): void
    {
        $this->sourceKey(
            'GENERAL',
            'common.cancel',
            'Cancel'
        );

        $first = $this
            ->getJson('/api/v1/translations/en')
            ->assertOk();

        $etag = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);

        $this
            ->withHeader('If-None-Match', $etag)
            ->get('/api/v1/translations/en')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag);
    }

    public function test_source_hash_integrity_failure_is_fail_closed(): void
    {
        TranslationKey::query()->create([
            'uuid' => (string) Str::uuid(),
            'group_name' => 'GENERAL',
            'translation_key' => 'common.corrupt',
            'source_text' => 'Corrupt source',
            'source_sha256' => str_repeat('c', 64),
            'version' => 0,
        ]);

        $this
            ->getJson('/api/v1/translations/en')
            ->assertStatus(503)
            ->assertJsonPath(
                'error.code',
                'TRANSLATION_SOURCE_INTEGRITY_FAILED'
            );
    }

    private function sourceKey(
        string $group,
        string $stableKey,
        string $sourceText
    ): TranslationKey {
        return TranslationKey::query()->create([
            'uuid' => (string) Str::uuid(),
            'group_name' => $group,
            'translation_key' => $stableKey,
            'source_text' => $sourceText,
            'source_sha256' => hash('sha256', $sourceText),
            'version' => 0,
        ]);
    }

    private function reviewedTranslation(
        TranslationKey $key,
        string $languageCode,
        string $translatedText
    ): TranslationValue {
        return TranslationValue::query()->create([
            'uuid' => (string) Str::uuid(),
            'translation_key_id' => $key->getKey(),
            'language_code' => $languageCode,
            'translated_text' => $translatedText,
            'source_sha256_at_translation' =>
                $key->source_sha256,
            'translation_sha256' =>
                hash('sha256', $translatedText),
            'status' => TranslationValue::STATUS_REVIEWED,
            'version' => 2,
            'reviewed_at' => now(),
        ]);
    }
}
