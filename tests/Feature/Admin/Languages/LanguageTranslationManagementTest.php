<?php

namespace Tests\Feature\Admin\Languages;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\LanguageProfile;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Admin\Languages\AdminLanguageService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class LanguageTranslationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            AdminRoleSeeder::class
        );

        $this->seed(
            AdminPermissionSeeder::class
        );
    }

    public function test_guest_is_redirected_from_language_management(): void
    {
        $this->get(
            route(
                'admin.languages.index'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_languages_view_permission_is_required(): void
    {
        $admin =
            $this->adminWithPermissions(
                []
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.index'
                )
            )
            ->assertForbidden();
    }

    public function test_migration_creates_exactly_seven_language_profiles(): void
    {
        $this->assertSame(
            [
                'en',
                'hi',
                'ml',
                'si',
                'ta',
                'te',
                'ur',
            ],
            LanguageProfile::query()
                ->orderBy('code')
                ->pluck('code')
                ->all()
        );

        $this->assertSame(
            7,
            LanguageProfile::query()
                ->count()
        );
    }

    public function test_language_registry_matches_frozen_registration_allowlist(): void
    {
        $registryCodes =
            array_keys(
                (array) config(
                    'tradex_languages.languages'
                )
            );

        $registrationCodes =
            (array) config(
                'tradex_registration.allowed_languages'
            );

        sort(
            $registryCodes
        );

        sort(
            $registrationCodes
        );

        $this->assertSame(
            $registrationCodes,
            $registryCodes
        );
    }

    public function test_english_is_source_and_urdu_is_rtl(): void
    {
        $english =
            $this->language(
                'en'
            );

        $urdu =
            $this->language(
                'ur'
            );

        $this->assertTrue(
            $english->is_source
        );

        $this->assertSame(
            'LTR',
            $english->direction
        );

        $this->assertFalse(
            $urdu->is_source
        );

        $this->assertSame(
            'RTL',
            $urdu->direction
        );
    }

    public function test_language_index_lists_all_supported_names_and_runtime_boundary(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.index'
                )
            )
            ->assertOk()
            ->assertSee('English')
            ->assertSee('Tamil')
            ->assertSee('Sinhala')
            ->assertSee('Hindi')
            ->assertSee('Malayalam')
            ->assertSee('Telugu')
            ->assertSee('Urdu')
            ->assertSee('NOT_WIRED');
    }

    public function test_language_state_update_requires_manage_permission(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $tamil =
            $this->language(
                'ta'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.update',
                    $tamil
                ),
                [
                    'is_translation_enabled' =>
                        false,

                    'version' =>
                        0,
                ]
            )
            ->assertForbidden();
    }

    public function test_source_language_cannot_be_disabled(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $english =
            $this->language(
                'en'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.update',
                    $english
                ),
                [
                    'is_translation_enabled' =>
                        false,

                    'version' =>
                        0,
                ]
            )
            ->assertStatus(422);

        $this->assertTrue(
            $english
                ->fresh()
                ->is_translation_enabled
        );
    }

    public function test_target_translation_program_can_be_disabled(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $tamil =
            $this->language(
                'ta'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.update',
                    $tamil
                ),
                [
                    'is_translation_enabled' =>
                        false,

                    'version' =>
                        0,
                ]
            )
            ->assertRedirect();

        $tamil->refresh();

        $this->assertFalse(
            $tamil->is_translation_enabled
        );

        $this->assertSame(
            1,
            $tamil->version
        );
    }

    public function test_stale_language_profile_version_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $tamil =
            $this->language(
                'ta'
            );

        $tamil->forceFill([
            'version' => 2,
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.update',
                    $tamil
                ),
                [
                    'is_translation_enabled' =>
                        false,

                    'version' =>
                        1,
                ]
            )
            ->assertStatus(409);

        $this->assertTrue(
            $tamil
                ->fresh()
                ->is_translation_enabled
        );
    }

    public function test_translation_key_catalog_requires_view_permission(): void
    {
        $admin =
            $this->adminWithPermissions(
                []
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.keys.index'
                )
            )
            ->assertForbidden();
    }

    public function test_translation_key_creation_requires_manage_permission(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.keys.store'
                ),
                $this->keyPayload()
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_can_create_source_key_with_canonical_hash(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $submitted =
            "Welcome\r\n"
            ."to TradeX";

        $expected =
            "Welcome\n"
            ."to TradeX";

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.keys.store'
                ),
                $this->keyPayload([
                    'source_text' =>
                        $submitted,
                ])
            )
            ->assertRedirect();

        $key =
            TranslationKey::query()
                ->firstOrFail();

        $this->assertSame(
            'APP_UI',
            $key->group_name
        );

        $this->assertSame(
            'dashboard.welcome',
            $key->translation_key
        );

        $this->assertSame(
            $expected,
            $key->source_text
        );

        $this->assertSame(
            hash(
                'sha256',
                $expected
            ),
            $key->source_sha256
        );
    }

    public function test_duplicate_source_key_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $this->sourceKey(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.keys.store'
                ),
                $this->keyPayload()
            )
            ->assertStatus(409);

        $this->assertSame(
            1,
            TranslationKey::query()
                ->count()
        );
    }

    public function test_invalid_translation_key_format_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.keys.store'
                ),
                $this->keyPayload([
                    'translation_key' =>
                        'Dashboard Welcome!',
                ])
            )
            ->assertSessionHasErrors(
                'translation_key'
            );
    }

    public function test_translation_key_identity_is_immutable_at_model_layer(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $this->expectException(
            LogicException::class
        );

        $key->forceFill([
            'translation_key' =>
                'changed.identity',
        ])->save();
    }

    public function test_translation_key_delete_is_rejected_at_model_layer(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $this->expectException(
            LogicException::class
        );

        $key->delete();
    }

    public function test_source_text_update_changes_hash_and_version(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $newSource =
            'Updated TradeX dashboard welcome text.';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.keys.update',
                    $key
                ),
                [
                    'source_text' =>
                        $newSource,

                    'version' =>
                        0,
                ]
            )
            ->assertRedirect();

        $key->refresh();

        $this->assertSame(
            1,
            $key->version
        );

        $this->assertSame(
            hash(
                'sha256',
                $newSource
            ),
            $key->source_sha256
        );
    }

    public function test_stale_source_key_version_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin,
                [
                    'version' => 2,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.keys.update',
                    $key
                ),
                [
                    'source_text' =>
                        'A new source text.',

                    'version' =>
                        1,
                ]
            )
            ->assertStatus(409);

        $this->assertSame(
            2,
            $key->fresh()->version
        );
    }

    public function test_source_language_cannot_open_target_translation_editor(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $english =
            $this->language(
                'en'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.translations.edit',
                    [
                        $english,
                        $key,
                    ]
                )
            )
            ->assertStatus(422);
    }

    public function test_disabled_target_language_cannot_be_edited(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $tamil->forceFill([
            'is_translation_enabled' =>
                false,
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.translations.edit',
                    [
                        $tamil,
                        $key,
                    ]
                )
            )
            ->assertStatus(409);
    }

    public function test_first_target_translation_save_creates_draft_with_hashes(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $text =
            'டிரேட்எக்ஸுக்கு வரவேற்கிறோம்';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.translations.update',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'translated_text' =>
                        $text,

                    'version' =>
                        0,
                ]
            )
            ->assertRedirect();

        $translation =
            TranslationValue::query()
                ->firstOrFail();

        $this->assertSame(
            TranslationValue::STATUS_DRAFT,
            $translation->status
        );

        $this->assertSame(
            $key->source_sha256,
            $translation
                ->source_sha256_at_translation
        );

        $this->assertSame(
            hash(
                'sha256',
                $text
            ),
            $translation
                ->translation_sha256
        );

        $this->assertSame(
            1,
            $translation->version
        );
    }

    public function test_translation_update_resets_review_state_to_draft(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $translation =
            $this->translation(
                $key,
                $tamil,
                [
                    'status' =>
                        TranslationValue::STATUS_REVIEWED,

                    'version' =>
                        4,

                    'reviewed_by_admin_id' =>
                        $admin->id,

                    'reviewed_at' =>
                        now(),
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.translations.update',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'translated_text' =>
                        'புதிய மொழிபெயர்ப்பு',

                    'version' =>
                        4,
                ]
            )
            ->assertRedirect();

        $translation->refresh();

        $this->assertSame(
            TranslationValue::STATUS_DRAFT,
            $translation->status
        );

        $this->assertNull(
            $translation->reviewed_at
        );

        $this->assertNull(
            $translation
                ->reviewed_by_admin_id
        );

        $this->assertSame(
            5,
            $translation->version
        );
    }

    public function test_translation_becomes_stale_when_source_hash_changes(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $translation =
            $this->translation(
                $key,
                $tamil,
                [
                    'status' =>
                        TranslationValue::STATUS_REVIEWED,
                ]
            );

        $key->forceFill([
            'source_text' =>
                'Changed source text.',

            'source_sha256' =>
                hash(
                    'sha256',
                    'Changed source text.'
                ),

            'version' =>
                1,
        ])->save();

        $status =
            app(
                AdminLanguageService::class
            )->translationStatus(
                $translation->fresh(),
                $key->fresh()
            );

        $this->assertSame(
            'STALE',
            $status
        );
    }

    public function test_review_requires_existing_translation_draft(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.translations.review',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'version' =>
                        0,

                    'review_confirmation' =>
                        true,
                ]
            )
            ->assertStatus(409);
    }

    public function test_review_requires_explicit_confirmation(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $translation =
            $this->translation(
                $key,
                $tamil
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.translations.review',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'version' =>
                        $translation->version,

                    'review_confirmation' =>
                        false,
                ]
            )
            ->assertSessionHasErrors(
                'review_confirmation'
            );

        $this->assertSame(
            TranslationValue::STATUS_DRAFT,
            $translation
                ->fresh()
                ->status
        );
    }

    public function test_current_translation_can_be_marked_reviewed(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $translation =
            $this->translation(
                $key,
                $tamil,
                [
                    'version' => 3,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.translations.review',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'version' =>
                        3,

                    'review_confirmation' =>
                        true,
                ]
            )
            ->assertRedirect();

        $translation->refresh();

        $this->assertSame(
            TranslationValue::STATUS_REVIEWED,
            $translation->status
        );

        $this->assertSame(
            4,
            $translation->version
        );

        $this->assertSame(
            $admin->id,
            $translation
                ->reviewed_by_admin_id
        );

        $this->assertNotNull(
            $translation->reviewed_at
        );
    }

    public function test_stale_translation_cannot_be_reviewed(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $tamil =
            $this->language(
                'ta'
            );

        $translation =
            $this->translation(
                $key,
                $tamil,
                [
                    'source_sha256_at_translation' =>
                        str_repeat(
                            'a',
                            64
                        ),

                    'version' =>
                        2,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.translations.review',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'version' =>
                        2,

                    'review_confirmation' =>
                        true,
                ]
            )
            ->assertStatus(409);

        $this->assertSame(
            TranslationValue::STATUS_DRAFT,
            $translation
                ->fresh()
                ->status
        );
    }

    public function test_translation_status_filters_apply_across_query(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $tamil =
            $this->language(
                'ta'
            );

        $reviewedKey =
            $this->sourceKey(
                $admin,
                [
                    'translation_key' =>
                        'status.reviewed',
                ]
            );

        $missingKey =
            $this->sourceKey(
                $admin,
                [
                    'translation_key' =>
                        'status.missing',
                ]
            );

        $this->translation(
            $reviewedKey,
            $tamil,
            [
                'status' =>
                    TranslationValue::STATUS_REVIEWED,
            ]
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.translations.index',
                    $tamil
                )
                .'?status=REVIEWED'
            )
            ->assertOk()
            ->assertSee(
                'status.reviewed'
            )
            ->assertDontSee(
                'status.missing'
            );

        $this->assertNotNull(
            $missingKey
        );
    }

    public function test_language_index_completion_detects_reviewed_and_stale_counts(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $tamil =
            $this->language(
                'ta'
            );

        $reviewedKey =
            $this->sourceKey(
                $admin,
                [
                    'translation_key' =>
                        'stats.reviewed',
                ]
            );

        $staleKey =
            $this->sourceKey(
                $admin,
                [
                    'translation_key' =>
                        'stats.stale',
                ]
            );

        $this->translation(
            $reviewedKey,
            $tamil,
            [
                'status' =>
                    TranslationValue::STATUS_REVIEWED,
            ]
        );

        $this->translation(
            $staleKey,
            $tamil,
            [
                'status' =>
                    TranslationValue::STATUS_REVIEWED,

                'source_sha256_at_translation' =>
                    str_repeat(
                        'b',
                        64
                    ),
            ]
        );

        $data =
            app(
                AdminLanguageService::class
            )->index();

        $card =
            $data[
                'languageCards'
            ]
                ->first(
                    fn (array $item): bool =>
                        $item[
                            'language'
                        ]->code === 'ta'
                );

        $this->assertSame(
            1,
            $card[
                'reviewed_current'
            ]
        );

        $this->assertSame(
            1,
            $card['stale']
        );
    }

    public function test_audit_does_not_copy_source_or_translation_text(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $sourceCanary =
            'SOURCE-CONTENT-CANARY-ADMIN18';

        $translationCanary =
            'TRANSLATION-CONTENT-CANARY-ADMIN18';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.languages.keys.store'
                ),
                $this->keyPayload([
                    'source_text' =>
                        $sourceCanary,
                ])
            )
            ->assertRedirect();

        $key =
            TranslationKey::query()
                ->firstOrFail();

        $tamil =
            $this->language(
                'ta'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.languages.translations.update',
                    [
                        $tamil,
                        $key,
                    ]
                ),
                [
                    'translated_text' =>
                        $translationCanary,

                    'version' =>
                        0,
                ]
            )
            ->assertRedirect();

        $audits =
            AuditLog::query()
                ->where(
                    'module',
                    'LANGUAGES'
                )
                ->get();

        $json =
            json_encode(
                $audits
                    ->pluck(
                        'new_values'
                    )
                    ->all(),
                JSON_THROW_ON_ERROR
            );

        $this->assertStringNotContainsString(
            $sourceCanary,
            $json
        );

        $this->assertStringNotContainsString(
            $translationCanary,
            $json
        );

        $this->assertStringContainsString(
            'dashboard.welcome',
            $json
        );
    }

    public function test_source_text_is_rendered_escaped_not_executed(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $source =
            '<script>alert("ADMIN18")</script>';

        $this->sourceKey(
            $admin,
            [
                'source_text' =>
                    $source,

                'source_sha256' =>
                    hash(
                        'sha256',
                        $source
                    ),
            ]
        );

        $english =
            $this->language(
                'en'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.translations.index',
                    $english
                )
            )
            ->assertOk()
            ->assertSee(
                $source,
                true
            )
            ->assertDontSee(
                $source,
                false
            );
    }

    public function test_urdu_translation_editor_renders_rtl_direction(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
                'languages.manage',
            ]);

        $key =
            $this->sourceKey(
                $admin
            );

        $urdu =
            $this->language(
                'ur'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.languages.translations.edit',
                    [
                        $urdu,
                        $key,
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'dir="rtl"',
                false
            );
    }

    public function test_language_pages_send_no_store_headers(): void
    {
        $admin =
            $this->adminWithPermissions([
                'languages.view',
            ]);

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.languages.index'
                    )
                )
                ->assertOk()
                ->assertHeader(
                    'Referrer-Policy',
                    'no-referrer'
                )
                ->assertHeader(
                    'X-Content-Type-Options',
                    'nosniff'
                );

        $this->assertStringContainsString(
            'no-store',
            (string) $response
                ->headers
                ->get(
                    'Cache-Control'
                )
        );
    }

    public function test_admin_18_has_exactly_eleven_routes_and_no_delete_route(): void
    {
        $routes =
            collect(
                app('router')
                    ->getRoutes()
                    ->getRoutes()
            )
                ->filter(
                    fn ($route): bool =>
                        str_starts_with(
                            (string) $route->uri(),
                            'admin/languages'
                        )
                );

        $this->assertCount(
            11,
            $routes
        );

        foreach (
            $routes
            as $route
        ) {
            $this->assertNotContains(
                'DELETE',
                $route->methods()
            );
        }
    }

    private function language(
        string $code
    ): LanguageProfile {
        return LanguageProfile::query()
            ->where(
                'code',
                $code
            )
            ->firstOrFail();
    }

    private function keyPayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'group_name' =>
                    'APP_UI',

                'translation_key' =>
                    'dashboard.welcome',

                'source_text' =>
                    'Welcome to TradeX',
            ],
            $overrides
        );
    }

    private function sourceKey(
        AdminUser $admin,
        array $overrides = []
    ): TranslationKey {
        $source =
            (string) (
                $overrides[
                    'source_text'
                ]
                ?? 'Welcome to TradeX'
            );

        return TranslationKey::query()
            ->create(
                array_merge(
                    [
                        'group_name' =>
                            'APP_UI',

                        'translation_key' =>
                            'dashboard.welcome',

                        'source_text' =>
                            $source,

                        'source_sha256' =>
                            hash(
                                'sha256',
                                $source
                            ),

                        'version' =>
                            0,

                        'created_by_admin_id' =>
                            $admin->id,

                        'updated_by_admin_id' =>
                            $admin->id,
                    ],
                    $overrides
                )
            );
    }

    private function translation(
        TranslationKey $key,
        LanguageProfile $language,
        array $overrides = []
    ): TranslationValue {
        $text =
            (string) (
                $overrides[
                    'translated_text'
                ]
                ?? 'TradeX translation test text'
            );

        return TranslationValue::query()
            ->create(
                array_merge(
                    [
                        'translation_key_id' =>
                            $key->id,

                        'language_code' =>
                            $language->code,

                        'translated_text' =>
                            $text,

                        'source_sha256_at_translation' =>
                            $key->source_sha256,

                        'translation_sha256' =>
                            hash(
                                'sha256',
                                $text
                            ),

                        'status' =>
                            TranslationValue::STATUS_DRAFT,

                        'version' =>
                            1,

                        'updated_by_admin_id' =>
                            null,

                        'reviewed_by_admin_id' =>
                            null,

                        'reviewed_at' =>
                            null,
                    ],
                    $overrides
                )
            );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'ADMIN-18 Test Actor',

                    'email' =>
                        'admin18-'
                        .Str::lower(
                            Str::random(10)
                        )
                        .'@example.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'Testing-Admin-Pass-123!',

                    'is_active' =>
                        true,

                    'failed_login_attempts' =>
                        0,
                ]);

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'ADMIN18_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'name' =>
                        'ADMIN-18 Test Role',

                    'description' =>
                        null,

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        $permissionIds = [];

        foreach (
            $codes
            as $code
        ) {
            $permission =
                AdminPermission::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->firstOrFail();

            $permissionIds[] =
                $permission->id;
        }

        $role->permissions()->sync(
            $permissionIds
        );

        $admin->roles()->sync([
            $role->id,
        ]);

        return $admin->fresh(
            'roles'
        );
    }
}
