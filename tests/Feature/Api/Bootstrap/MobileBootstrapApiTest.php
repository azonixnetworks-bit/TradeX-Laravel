<?php

namespace Tests\Feature\Api\Bootstrap;

use App\Models\LegalPolicyVersion;
use App\Models\SystemSetting;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_bootstrap_returns_safe_defaults_languages_and_legal_metadata(): void
    {
        $policy = $this->publishedPolicy();
        $this->sourceKey();

        $response = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath(
                'data.runtime_state',
                'WIRED_SAFE_BOOTSTRAP'
            )
            ->assertJsonPath('data.schema_version', 1)
            ->assertJsonPath(
                'data.settings.platform_name',
                'TradeX OS'
            )
            ->assertJsonPath(
                'data.settings.support_email',
                null
            )
            ->assertJsonPath(
                'data.settings.maintenance_notice_enabled',
                false
            )
            ->assertJsonPath(
                'data.languages.runtime_state',
                'WIRED_REVIEWED_RUNTIME'
            )
            ->assertJsonCount(7, 'data.languages.items')
            ->assertJsonPath(
                'data.legal.runtime_state',
                'WIRED_EVIDENCE_ONLY'
            )
            ->assertJsonPath(
                'data.legal.items.0.uuid',
                $policy->uuid
            );

        $this->assertNotEmpty(
            $response->json('data.bootstrap_sha256')
        );

        $etag = $response->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertMatchesRegularExpression(
            '/^"[a-f0-9]{64}"$/',
            (string) $etag
        );
    }

    public function test_bootstrap_reads_only_explicit_admin14_safe_settings(): void
    {
        $this->setting(
            'general.platform_name',
            'GENERAL',
            'TradeX Global',
            'STRING'
        );
        $this->setting(
            'general.support_email',
            'GENERAL',
            'support@example.com',
            'STRING'
        );
        $this->setting(
            'general.support_whatsapp',
            'GENERAL',
            '+94770000000',
            'STRING'
        );
        $this->setting(
            'general.maintenance_notice_enabled',
            'GENERAL',
            '1',
            'BOOL'
        );
        $this->setting(
            'general.maintenance_notice',
            'GENERAL',
            'Planned maintenance tonight.',
            'STRING'
        );

        $this->setting(
            'private.payment_secret',
            'PRIVATE',
            'DO_NOT_EXPOSE_ME',
            'STRING'
        );

        $response = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath(
                'data.settings.platform_name',
                'TradeX Global'
            )
            ->assertJsonPath(
                'data.settings.support_email',
                'support@example.com'
            )
            ->assertJsonPath(
                'data.settings.support_whatsapp',
                '+94770000000'
            )
            ->assertJsonPath(
                'data.settings.maintenance_notice_enabled',
                true
            )
            ->assertJsonPath(
                'data.settings.maintenance_notice',
                'Planned maintenance tonight.'
            );

        $this->assertStringNotContainsString(
            'DO_NOT_EXPOSE_ME',
            $response->getContent()
        );
        $this->assertStringNotContainsString(
            'private.payment_secret',
            $response->getContent()
        );
    }

    public function test_disabled_maintenance_does_not_expose_stored_notice_text(): void
    {
        $this->setting(
            'general.maintenance_notice_enabled',
            'GENERAL',
            '0',
            'BOOL'
        );
        $this->setting(
            'general.maintenance_notice',
            'GENERAL',
            'Hidden old maintenance notice',
            'STRING'
        );

        $response = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath(
                'data.settings.maintenance_notice_enabled',
                false
            )
            ->assertJsonPath(
                'data.settings.maintenance_notice',
                null
            );

        $this->assertStringNotContainsString(
            'Hidden old maintenance notice',
            $response->getContent()
        );
    }

    public function test_legal_bootstrap_metadata_never_contains_full_policy_content(): void
    {
        $this->publishedPolicy(
            'FULL LEGAL CONTENT MUST NOT BE IN BOOTSTRAP'
        );

        $response = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk();

        $this->assertStringNotContainsString(
            'FULL LEGAL CONTENT MUST NOT BE IN BOOTSTRAP',
            $response->getContent()
        );
    }

    public function test_corrupt_allowlisted_setting_fails_closed(): void
    {
        $this->setting(
            'general.platform_name',
            'GENERAL',
            'TradeX OS',
            'BOOL'
        );

        $this
            ->getJson('/api/v1/bootstrap')
            ->assertStatus(503)
            ->assertJsonPath(
                'error.code',
                'BOOTSTRAP_SAFE_SETTING_INTEGRITY_FAILED'
            );
    }

    public function test_bootstrap_supports_etag_conditional_get(): void
    {
        $first = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk();

        $etag = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);

        $this
            ->withHeader('If-None-Match', $etag)
            ->get('/api/v1/bootstrap')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag);
    }

    public function test_api19_does_not_mutate_frozen_admin_runtime_markers(): void
    {
        $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk();

        $this->assertSame(
            'NOT_WIRED',
            config('tradex_languages.runtime_translation_api')
        );

        $this->assertSame(
            'NOT_WIRED',
            config('tradex_legal.acceptance_runtime')
        );
    }

    private function setting(
        string $key,
        string $group,
        ?string $value,
        string $type
    ): SystemSetting {
        return SystemSetting::query()->create([
            'setting_key' => $key,
            'group_name' => $group,
            'value' => $value,
            'value_type' => $type,
            'version' => 0,
        ]);
    }

    private function publishedPolicy(
        string $content = 'Current terms content'
    ): LegalPolicyVersion {
        return LegalPolicyVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'policy_type' => 'TERMS_OF_SERVICE',
            'version_number' => 1,
            'title' => 'Current Terms',
            'summary' => 'Current terms summary',
            'content' => $content,
            'content_sha256' => hash('sha256', $content),
            'status' => LegalPolicyVersion::STATUS_PUBLISHED,
            'effective_at' => now()->subMinute(),
            'published_at' => now()->subMinute(),
            'revision_version' => 0,
        ]);
    }

    private function sourceKey(): TranslationKey
    {
        $text = 'Continue';

        return TranslationKey::query()->create([
            'uuid' => (string) Str::uuid(),
            'group_name' => 'GENERAL',
            'translation_key' => 'common.continue',
            'source_text' => $text,
            'source_sha256' => hash('sha256', $text),
            'version' => 0,
        ]);
    }
}
