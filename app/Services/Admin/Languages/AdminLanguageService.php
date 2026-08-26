<?php

namespace App\Services\Admin\Languages;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\LanguageProfile;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLanguageService
{
    public function index(): array
    {
        $languages =
            LanguageProfile::query()
                ->orderByDesc('is_source')
                ->orderBy('id')
                ->get();

        $totalKeys =
            TranslationKey::query()
                ->count();

        $cards =
            $languages->map(
                function (
                    LanguageProfile $language
                ) use (
                    $totalKeys
                ): array {
                    if ($language->is_source) {
                        return [
                            'language' =>
                                $language,

                            'total_keys' =>
                                $totalKeys,

                            'translated' =>
                                $totalKeys,

                            'reviewed_current' =>
                                $totalKeys,

                            'draft' =>
                                0,

                            'stale' =>
                                0,

                            'missing' =>
                                0,

                            'completion_percent' =>
                                $totalKeys > 0
                                    ? 100
                                    : 0,

                            'registration_allowed' =>
                                $this
                                    ->isRegistrationAllowed(
                                        $language->code
                                    ),
                        ];
                    }

                    $translations =
                        TranslationValue::query()
                            ->where(
                                'language_code',
                                $language->code
                            )
                            ->with(
                                'translationKey:id,source_sha256'
                            )
                            ->get();

                    $translated = 0;
                    $reviewedCurrent = 0;
                    $draft = 0;
                    $stale = 0;

                    foreach (
                        $translations
                        as $translation
                    ) {
                        $status =
                            $this->translationStatus(
                                $translation,
                                $translation
                                    ->translationKey
                            );

                        if ($status !== 'MISSING') {
                            $translated++;
                        }

                        if ($status === 'REVIEWED') {
                            $reviewedCurrent++;
                        } elseif ($status === 'STALE') {
                            $stale++;
                        } elseif ($status === 'DRAFT') {
                            $draft++;
                        }
                    }

                    $missing =
                        max(
                            0,
                            $totalKeys
                            - $translated
                        );

                    return [
                        'language' =>
                            $language,

                        'total_keys' =>
                            $totalKeys,

                        'translated' =>
                            $translated,

                        'reviewed_current' =>
                            $reviewedCurrent,

                        'draft' =>
                            $draft,

                        'stale' =>
                            $stale,

                        'missing' =>
                            $missing,

                        'completion_percent' =>
                            $totalKeys > 0
                                ? (int) floor(
                                    (
                                        $reviewedCurrent
                                        / $totalKeys
                                    ) * 100
                                )
                                : 0,

                        'registration_allowed' =>
                            $this
                                ->isRegistrationAllowed(
                                    $language->code
                                ),
                    ];
                }
            );

        return [
            'languageCards' =>
                $cards,

            'totalKeys' =>
                $totalKeys,

            'runtimeTranslationApi' =>
                (string) config(
                    'tradex_languages.runtime_translation_api',
                    'NOT_WIRED'
                ),

            'educationContentLocalization' =>
                (string) config(
                    'tradex_languages.education_content_localization',
                    'NOT_WIRED'
                ),
        ];
    }

    public function updateLanguage(
        AdminUser $admin,
        LanguageProfile $language,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $language,
                $data,
                $request
            ): void {
                $locked =
                    LanguageProfile::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $language->getKey()
                        );

                if (
                    $locked->version
                    !== (int) $data[
                        'version'
                    ]
                ) {
                    abort(
                        409,
                        'Language state changed in another session. Refresh and try again.'
                    );
                }

                $enabled =
                    (bool) $data[
                        'is_translation_enabled'
                    ];

                if (
                    $locked->is_source
                    && ! $enabled
                ) {
                    abort(
                        422,
                        'The source language cannot be disabled.'
                    );
                }

                $old = [
                    'language_code' =>
                        $locked->code,

                    'is_translation_enabled' =>
                        (bool) $locked
                            ->is_translation_enabled,

                    'version' =>
                        (int) $locked
                            ->version,
                ];

                $locked->forceFill([
                    'is_translation_enabled' =>
                        $enabled,

                    'version' =>
                        $locked->version + 1,

                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $admin->getKey(),
                    action:
                        'LANGUAGE_TRANSLATION_STATE_UPDATED',
                    module:
                        'LANGUAGES',
                    targetType:
                        'LANGUAGE_PROFILE',
                    targetId:
                        $locked->getKey(),
                    oldValues:
                        $old,
                    newValues: [
                        'language_code' =>
                            $locked->code,

                        'is_translation_enabled' =>
                            (bool) $locked
                                ->is_translation_enabled,

                        'version' =>
                            (int) $locked
                                ->version,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function keyIndex(
        array $filters = []
    ): array {
        $query =
            TranslationKey::query()
                ->withCount(
                    'translations'
                );

        $search =
            trim(
                (string) (
                    $filters['search']
                    ?? ''
                )
            );

        if ($search !== '') {
            $escaped =
                addcslashes(
                    $search,
                    '%_\\'
                );

            $query->where(
                function (
                    $builder
                ) use (
                    $escaped
                ): void {
                    $builder
                        ->where(
                            'translation_key',
                            'like',
                            '%'.$escaped.'%'
                        )
                        ->orWhere(
                            'source_text',
                            'like',
                            '%'.$escaped.'%'
                        );
                }
            );
        }

        $group =
            strtoupper(
                trim(
                    (string) (
                        $filters['group']
                        ?? ''
                    )
                )
            );

        if (
            $group !== ''
            && array_key_exists(
                $group,
                (array) config(
                    'tradex_languages.groups',
                    []
                )
            )
        ) {
            $query->where(
                'group_name',
                $group
            );
        }

        $perPage =
            min(
                100,
                max(
                    10,
                    (int) (
                        $filters['per_page']
                        ?? 25
                    )
                )
            );

        return [
            'keys' =>
                $query
                    ->orderBy(
                        'group_name'
                    )
                    ->orderBy(
                        'translation_key'
                    )
                    ->paginate(
                        $perPage
                    )
                    ->withQueryString(),

            'groups' =>
                (array) config(
                    'tradex_languages.groups',
                    []
                ),

            'filters' => [
                'search' =>
                    $search,

                'group' =>
                    $group,
            ],
        ];
    }

    public function createKey(
        AdminUser $admin,
        array $data,
        Request $request
    ): TranslationKey {
        return DB::transaction(
            function () use (
                $admin,
                $data,
                $request
            ): TranslationKey {
                $exists =
                    TranslationKey::query()
                        ->where(
                            'group_name',
                            $data[
                                'group_name'
                            ]
                        )
                        ->where(
                            'translation_key',
                            $data[
                                'translation_key'
                            ]
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($exists) {
                    abort(
                        409,
                        'This translation key already exists in the selected group.'
                    );
                }

                $source =
                    $this->canonicalText(
                        $data[
                            'source_text'
                        ]
                    );

                $key =
                    TranslationKey::query()
                        ->create([
                            'group_name' =>
                                $data[
                                    'group_name'
                                ],

                            'translation_key' =>
                                $data[
                                    'translation_key'
                                ],

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
                                $admin->getKey(),

                            'updated_by_admin_id' =>
                                $admin->getKey(),
                        ]);

                $this->audit(
                    $admin,
                    $request,
                    'TRANSLATION_KEY_CREATED',
                    $key,
                    [
                        'state' =>
                            'SOURCE_CREATED',
                    ]
                );

                return $key;
            },
            3
        );
    }

    public function updateKey(
        AdminUser $admin,
        TranslationKey $key,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $key,
                $data,
                $request
            ): void {
                $locked =
                    TranslationKey::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $key->getKey()
                        );

                if (
                    $locked->version
                    !== (int) $data[
                        'version'
                    ]
                ) {
                    abort(
                        409,
                        'Source text changed in another session. Refresh and try again.'
                    );
                }

                $source =
                    $this->canonicalText(
                        $data[
                            'source_text'
                        ]
                    );

                $oldHash =
                    $locked
                        ->source_sha256;

                $newHash =
                    hash(
                        'sha256',
                        $source
                    );

                $locked->forceFill([
                    'source_text' =>
                        $source,

                    'source_sha256' =>
                        $newHash,

                    'version' =>
                        $locked->version + 1,

                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'TRANSLATION_SOURCE_UPDATED',
                    $locked,
                    [
                        'source_changed' =>
                            ! hash_equals(
                                $oldHash,
                                $newHash
                            ),

                        'previous_source_sha256' =>
                            $oldHash,
                    ]
                );
            },
            3
        );
    }

    public function languageTranslations(
        LanguageProfile $language,
        array $filters = []
    ): array {
        $query =
            TranslationKey::query()
                ->with([
                    'translations' =>
                        function (
                            $builder
                        ) use (
                            $language
                        ): void {
                            $builder->where(
                                'language_code',
                                $language->code
                            );
                        },
                ]);

        $search =
            trim(
                (string) (
                    $filters['search']
                    ?? ''
                )
            );

        if ($search !== '') {
            $escaped =
                addcslashes(
                    $search,
                    '%_\\'
                );

            $query->where(
                function (
                    $builder
                ) use (
                    $escaped
                ): void {
                    $builder
                        ->where(
                            'translation_key',
                            'like',
                            '%'.$escaped.'%'
                        )
                        ->orWhere(
                            'source_text',
                            'like',
                            '%'.$escaped.'%'
                        );
                }
            );
        }

        $group =
            strtoupper(
                trim(
                    (string) (
                        $filters['group']
                        ?? ''
                    )
                )
            );

        if (
            $group !== ''
            && array_key_exists(
                $group,
                (array) config(
                    'tradex_languages.groups',
                    []
                )
            )
        ) {
            $query->where(
                'group_name',
                $group
            );
        }

        $statusFilter =
            strtoupper(
                trim(
                    (string) (
                        $filters['status']
                        ?? ''
                    )
                )
            );

        if (
            ! $language->is_source
            && in_array(
                $statusFilter,
                [
                    'MISSING',
                    'DRAFT',
                    'REVIEWED',
                    'STALE',
                ],
                true
            )
        ) {
            if ($statusFilter === 'MISSING') {
                $query->whereDoesntHave(
                    'translations',
                    function (
                        $builder
                    ) use (
                        $language
                    ): void {
                        $builder->where(
                            'language_code',
                            $language->code
                        );
                    }
                );
            } else {
                $query->whereHas(
                    'translations',
                    function (
                        $builder
                    ) use (
                        $language,
                        $statusFilter
                    ): void {
                        $builder->where(
                            'language_code',
                            $language->code
                        );

                        if (
                            $statusFilter
                            === 'DRAFT'
                        ) {
                            $builder->where(
                                'status',
                                TranslationValue::STATUS_DRAFT
                            );
                        }

                        if (
                            $statusFilter
                            === 'REVIEWED'
                        ) {
                            $builder->where(
                                'status',
                                TranslationValue::STATUS_REVIEWED
                            );
                        }

                        if (
                            $statusFilter
                            === 'STALE'
                        ) {
                            $builder->whereColumn(
                                'translation_values.source_sha256_at_translation',
                                '<>',
                                'translation_keys.source_sha256'
                            );

                            return;
                        }

                        $builder->whereColumn(
                            'translation_values.source_sha256_at_translation',
                            'translation_keys.source_sha256'
                        );
                    }
                );
            }
        }

        $keys =
            $query
                ->orderBy(
                    'group_name'
                )
                ->orderBy(
                    'translation_key'
                )
                ->paginate(25)
                ->withQueryString();

        return [
            'language' =>
                $language,

            'keys' =>
                $keys,

            'groups' =>
                (array) config(
                    'tradex_languages.groups',
                    []
                ),

            'filters' => [
                'search' =>
                    $search,

                'group' =>
                    $group,

                'status' =>
                    $statusFilter,
            ],

            'registrationAllowed' =>
                $this->isRegistrationAllowed(
                    $language->code
                ),

            'runtimeTranslationApi' =>
                (string) config(
                    'tradex_languages.runtime_translation_api',
                    'NOT_WIRED'
                ),
        ];
    }

    public function translationEditor(
        LanguageProfile $language,
        TranslationKey $key
    ): array {
        $this->assertTargetLanguage(
            $language
        );

        $translation =
            TranslationValue::query()
                ->where(
                    'translation_key_id',
                    $key->getKey()
                )
                ->where(
                    'language_code',
                    $language->code
                )
                ->first();

        return [
            'language' =>
                $language,

            'key' =>
                $key,

            'translation' =>
                $translation,

            'derivedStatus' =>
                $this->translationStatus(
                    $translation,
                    $key
                ),
        ];
    }

    public function updateTranslation(
        AdminUser $admin,
        LanguageProfile $language,
        TranslationKey $key,
        array $data,
        Request $request
    ): TranslationValue {
        $this->assertTargetLanguage(
            $language
        );

        return DB::transaction(
            function () use (
                $admin,
                $language,
                $key,
                $data,
                $request
            ): TranslationValue {
                $lockedKey =
                    TranslationKey::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $key->getKey()
                        );

                $translation =
                    TranslationValue::query()
                        ->where(
                            'translation_key_id',
                            $lockedKey->getKey()
                        )
                        ->where(
                            'language_code',
                            $language->code
                        )
                        ->lockForUpdate()
                        ->first();

                $submittedVersion =
                    (int) $data[
                        'version'
                    ];

                if (
                    $translation
                    && $translation->version
                    !== $submittedVersion
                ) {
                    abort(
                        409,
                        'Translation changed in another session. Refresh and try again.'
                    );
                }

                if (
                    ! $translation
                    && $submittedVersion !== 0
                ) {
                    abort(
                        409,
                        'Translation state is stale. Refresh and try again.'
                    );
                }

                $text =
                    $this->canonicalText(
                        $data[
                            'translated_text'
                        ]
                    );

                $payload = [
                    'translated_text' =>
                        $text,

                    'source_sha256_at_translation' =>
                        $lockedKey
                            ->source_sha256,

                    'translation_sha256' =>
                        hash(
                            'sha256',
                            $text
                        ),

                    'status' =>
                        TranslationValue::STATUS_DRAFT,

                    'version' =>
                        $translation
                            ? $translation
                                ->version + 1
                            : 1,

                    'updated_by_admin_id' =>
                        $admin->getKey(),

                    'reviewed_by_admin_id' =>
                        null,

                    'reviewed_at' =>
                        null,
                ];

                if ($translation) {
                    $translation
                        ->forceFill(
                            $payload
                        )
                        ->save();
                } else {
                    $translation =
                        TranslationValue::query()
                            ->create([
                                'translation_key_id' =>
                                    $lockedKey
                                        ->getKey(),

                                'language_code' =>
                                    $language->code,

                                ...$payload,
                            ]);
                }

                $this->audit(
                    $admin,
                    $request,
                    'TRANSLATION_DRAFT_UPDATED',
                    $lockedKey,
                    [
                        'language_code' =>
                            $language->code,

                        'translation_status' =>
                            'DRAFT',

                        'translation_sha256' =>
                            $translation
                                ->translation_sha256,

                        'translation_version' =>
                            (int) $translation
                                ->version,
                    ]
                );

                return $translation;
            },
            3
        );
    }

    public function reviewTranslation(
        AdminUser $admin,
        LanguageProfile $language,
        TranslationKey $key,
        array $data,
        Request $request
    ): void {
        $this->assertTargetLanguage(
            $language
        );

        DB::transaction(
            function () use (
                $admin,
                $language,
                $key,
                $data,
                $request
            ): void {
                $lockedKey =
                    TranslationKey::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $key->getKey()
                        );

                $translation =
                    TranslationValue::query()
                        ->where(
                            'translation_key_id',
                            $lockedKey->getKey()
                        )
                        ->where(
                            'language_code',
                            $language->code
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $translation
                    || blank(
                        $translation
                            ->translated_text
                    )
                ) {
                    abort(
                        409,
                        'A translation draft is required before review.'
                    );
                }

                if (
                    $translation->version
                    !== (int) $data[
                        'version'
                    ]
                ) {
                    abort(
                        409,
                        'Translation changed in another session. Refresh and review again.'
                    );
                }

                if (
                    ! hash_equals(
                        $lockedKey
                            ->source_sha256,
                        (string) $translation
                            ->source_sha256_at_translation
                    )
                ) {
                    abort(
                        409,
                        'The source text changed after this translation was drafted. Update the translation before review.'
                    );
                }

                $translation->forceFill([
                    'status' =>
                        TranslationValue::STATUS_REVIEWED,

                    'version' =>
                        $translation
                            ->version + 1,

                    'reviewed_by_admin_id' =>
                        $admin->getKey(),

                    'reviewed_at' =>
                        now(),

                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'TRANSLATION_REVIEWED',
                    $lockedKey,
                    [
                        'language_code' =>
                            $language->code,

                        'translation_status' =>
                            'REVIEWED',

                        'translation_sha256' =>
                            $translation
                                ->translation_sha256,

                        'translation_version' =>
                            (int) $translation
                                ->version,
                    ]
                );
            },
            3
        );
    }

    public function translationStatus(
        ?TranslationValue $translation,
        TranslationKey $key
    ): string {
        if (
            ! $translation
            || blank(
                $translation->translated_text
            )
        ) {
            return 'MISSING';
        }

        if (
            blank(
                $translation
                    ->source_sha256_at_translation
            )
            || ! hash_equals(
                $key->source_sha256,
                (string) $translation
                    ->source_sha256_at_translation
            )
        ) {
            return 'STALE';
        }

        if (
            $translation->status
            === TranslationValue::STATUS_REVIEWED
        ) {
            return 'REVIEWED';
        }

        return 'DRAFT';
    }

    private function assertTargetLanguage(
        LanguageProfile $language
    ): void {
        if ($language->is_source) {
            abort(
                422,
                'The source language is edited through Translation Keys, not target translations.'
            );
        }

        if (! $language->is_translation_enabled) {
            abort(
                409,
                'This language translation program is disabled.'
            );
        }
    }

    private function canonicalText(
        string $text
    ): string {
        return trim(
            str_replace(
                ["\r\n", "\r"],
                "\n",
                $text
            )
        );
    }

    private function isRegistrationAllowed(
        string $code
    ): bool {
        return in_array(
            $code,
            (array) config(
                'tradex_registration.allowed_languages',
                []
            ),
            true
        );
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        TranslationKey $key,
        array $extra = []
    ): void {
        /*
         * Source and translated text are deliberately excluded from audit.
         * Hashes provide change evidence without duplicating content.
         */
        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin->getKey(),
            action:
                $action,
            module:
                'LANGUAGES',
            targetType:
                'TRANSLATION_KEY',
            targetId:
                $key->getKey(),
            newValues:
                array_merge(
                    [
                        'translation_key_uuid' =>
                            $key->uuid,

                        'group_name' =>
                            $key->group_name,

                        'translation_key' =>
                            $key
                                ->translation_key,

                        'source_sha256' =>
                            $key
                                ->source_sha256,

                        'source_version' =>
                            (int) $key
                                ->version,
                    ],
                    $extra
                ),
            ipAddress:
                $request->ip(),
            userAgent:
                $request->userAgent()
        );
    }
}
