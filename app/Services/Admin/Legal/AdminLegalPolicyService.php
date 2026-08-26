<?php

namespace App\Services\Admin\Legal;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\LegalPolicyVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLegalPolicyService
{
    public function index(): array
    {
        $definitions =
            (array) config(
                'tradex_legal.policy_types',
                []
            );

        $cards = [];

        foreach (
            $definitions
            as $policyType => $definition
        ) {
            $draft =
                LegalPolicyVersion::query()
                    ->where(
                        'policy_type',
                        $policyType
                    )
                    ->where(
                        'status',
                        LegalPolicyVersion::STATUS_DRAFT
                    )
                    ->orderByDesc(
                        'version_number'
                    )
                    ->first();

            $current =
                $this->currentPublished(
                    $policyType
                );

            $scheduled =
                LegalPolicyVersion::query()
                    ->where(
                        'policy_type',
                        $policyType
                    )
                    ->where(
                        'status',
                        LegalPolicyVersion::STATUS_PUBLISHED
                    )
                    ->where(
                        'effective_at',
                        '>',
                        now()
                    )
                    ->orderBy(
                        'effective_at'
                    )
                    ->orderByDesc(
                        'version_number'
                    )
                    ->first();

            $publishedCount =
                LegalPolicyVersion::query()
                    ->where(
                        'policy_type',
                        $policyType
                    )
                    ->where(
                        'status',
                        LegalPolicyVersion::STATUS_PUBLISHED
                    )
                    ->count();

            $cards[] = [
                'type' =>
                    $policyType,

                'display_name' =>
                    (string) (
                        $definition[
                            'display_name'
                        ]
                        ?? $policyType
                    ),

                'audience' =>
                    (string) (
                        $definition[
                            'audience'
                        ]
                        ?? 'UNSPECIFIED'
                    ),

                'draft' =>
                    $draft,

                'current' =>
                    $current,

                'scheduled' =>
                    $scheduled,

                'published_count' =>
                    $publishedCount,
            ];
        }

        return [
            'policyCards' =>
                $cards,

            'acceptanceRuntime' =>
                (string) config(
                    'tradex_legal.acceptance_runtime',
                    'NOT_WIRED'
                ),
        ];
    }

    public function createForm(
        string $policyType
    ): array {
        $definition =
            $this->definition(
                $policyType
            );

        $existingDraft =
            LegalPolicyVersion::query()
                ->where(
                    'policy_type',
                    $policyType
                )
                ->where(
                    'status',
                    LegalPolicyVersion::STATUS_DRAFT
                )
                ->orderByDesc(
                    'version_number'
                )
                ->first();

        if ($existingDraft) {
            abort(
                409,
                'This legal policy already has an editable draft.'
            );
        }

        return [
            'policyType' =>
                $policyType,

            'definition' =>
                $definition,

            'suggestedTitle' =>
                (string) $definition[
                    'display_name'
                ],
        ];
    }

    public function createDraft(
        AdminUser $admin,
        array $data,
        Request $request
    ): LegalPolicyVersion {
        $policyType =
            $this->normalizeType(
                $data['policy_type']
            );

        $this->definition(
            $policyType
        );

        return DB::transaction(
            function () use (
                $admin,
                $policyType,
                $data,
                $request
            ): LegalPolicyVersion {
                $existingDraft =
                    LegalPolicyVersion::query()
                        ->where(
                            'policy_type',
                            $policyType
                        )
                        ->where(
                            'status',
                            LegalPolicyVersion::STATUS_DRAFT
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existingDraft) {
                    abort(
                        409,
                        'This legal policy already has an editable draft.'
                    );
                }

                $nextVersion =
                    (
                        (int) LegalPolicyVersion::query()
                            ->where(
                                'policy_type',
                                $policyType
                            )
                            ->max(
                                'version_number'
                            )
                    ) + 1;

                $content =
                    $this->canonicalContent(
                        $data['content']
                    );

                $policy =
                    LegalPolicyVersion::query()
                        ->create([
                            'policy_type' =>
                                $policyType,

                            'version_number' =>
                                $nextVersion,

                            'title' =>
                                $data['title'],

                            'summary' =>
                                $data['summary']
                                ?? null,

                            'content' =>
                                $content,

                            'content_sha256' =>
                                hash(
                                    'sha256',
                                    $content
                                ),

                            'status' =>
                                LegalPolicyVersion::STATUS_DRAFT,

                            'effective_at' =>
                                null,

                            'published_at' =>
                                null,

                            'revision_version' =>
                                0,

                            'created_by_admin_id' =>
                                $admin->getKey(),

                            'updated_by_admin_id' =>
                                $admin->getKey(),
                        ]);

                $this->audit(
                    admin:
                        $admin,
                    request:
                        $request,
                    action:
                        'LEGAL_POLICY_DRAFT_CREATED',
                    policy:
                        $policy,
                    extra: [
                        'state' =>
                            'DRAFT',
                    ]
                );

                return $policy;
            },
            3
        );
    }

    public function updateDraft(
        AdminUser $admin,
        LegalPolicyVersion $policy,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $policy,
                $data,
                $request
            ): void {
                $locked =
                    LegalPolicyVersion::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $policy->getKey()
                        );

                $this->assertDraft(
                    $locked
                );

                if (
                    (int) $data[
                        'revision_version'
                    ]
                    !== $locked
                        ->revision_version
                ) {
                    abort(
                        409,
                        'This legal draft changed in another session. Refresh and try again.'
                    );
                }

                $old =
                    $this->safeMetadata(
                        $locked
                    );

                $content =
                    $this->canonicalContent(
                        $data['content']
                    );

                $locked->forceFill([
                    'title' =>
                        $data['title'],

                    'summary' =>
                        $data['summary']
                        ?? null,

                    'content' =>
                        $content,

                    'content_sha256' =>
                        hash(
                            'sha256',
                            $content
                        ),

                    'revision_version' =>
                        $locked
                            ->revision_version
                        + 1,

                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $admin->getKey(),
                    action:
                        'LEGAL_POLICY_DRAFT_UPDATED',
                    module:
                        'LEGAL',
                    targetType:
                        'LEGAL_POLICY_VERSION',
                    targetId:
                        $locked->getKey(),
                    oldValues:
                        $old,
                    newValues:
                        $this->safeMetadata(
                            $locked
                        ),
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function publish(
        AdminUser $admin,
        LegalPolicyVersion $policy,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $policy,
                $data,
                $request
            ): void {
                $locked =
                    LegalPolicyVersion::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $policy->getKey()
                        );

                $this->assertDraft(
                    $locked
                );

                if (
                    (int) $data[
                        'revision_version'
                    ]
                    !== $locked
                        ->revision_version
                ) {
                    abort(
                        409,
                        'This legal draft changed in another session. Refresh and review it before publishing.'
                    );
                }

                $effectiveAt =
                    $this->resolveEffectiveAt(
                        $data[
                            'effective_at'
                        ]
                        ?? null
                    );

                $now =
                    CarbonImmutable::now();

                $locked->forceFill([
                    'status' =>
                        LegalPolicyVersion::STATUS_PUBLISHED,

                    'published_at' =>
                        $now,

                    'effective_at' =>
                        $effectiveAt,

                    'published_by_admin_id' =>
                        $admin->getKey(),

                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                $this->audit(
                    admin:
                        $admin,
                    request:
                        $request,
                    action:
                        $effectiveAt
                            ->isFuture()
                            ? 'LEGAL_POLICY_PUBLISHED_SCHEDULED'
                            : 'LEGAL_POLICY_PUBLISHED',
                    policy:
                        $locked,
                    extra: [
                        'state' =>
                            $effectiveAt
                                ->isFuture()
                                ? 'SCHEDULED'
                                : 'EFFECTIVE',

                        'published_at' =>
                            $locked
                                ->published_at
                                ?->toISOString(),

                        'effective_at' =>
                            $locked
                                ->effective_at
                                ?->toISOString(),
                    ]
                );
            },
            3
        );
    }

    public function show(
        LegalPolicyVersion $policy
    ): array {
        $policy->loadMissing([
            'createdByAdmin:id,name,email',
            'updatedByAdmin:id,name,email',
            'publishedByAdmin:id,name,email',
        ]);

        $history =
            LegalPolicyVersion::query()
                ->where(
                    'policy_type',
                    $policy->policy_type
                )
                ->orderByDesc(
                    'version_number'
                )
                ->get();

        $current =
            $this->currentPublished(
                $policy->policy_type
            );

        return [
            'policy' =>
                $policy,

            'definition' =>
                $this->definition(
                    $policy->policy_type
                ),

            'derivedStatus' =>
                $this->derivedStatus(
                    $policy,
                    $current
                ),

            'history' =>
                $history,

            'currentPolicy' =>
                $current,

            'isDraft' =>
                $policy->isDraft(),

            'isCurrent' =>
                $current?->is(
                    $policy
                )
                ?? false,

            'acceptanceRuntime' =>
                (string) config(
                    'tradex_legal.acceptance_runtime',
                    'NOT_WIRED'
                ),
        ];
    }

    public function assertDraft(
        LegalPolicyVersion $policy
    ): void {
        if (
            ! $policy->isDraft()
            || $policy->published_at
            !== null
        ) {
            abort(
                409,
                'Published legal policy versions are immutable.'
            );
        }
    }

    public function currentPublished(
        string $policyType
    ): ?LegalPolicyVersion {
        return LegalPolicyVersion::query()
            ->where(
                'policy_type',
                $policyType
            )
            ->where(
                'status',
                LegalPolicyVersion::STATUS_PUBLISHED
            )
            ->whereNotNull(
                'published_at'
            )
            ->where(
                'published_at',
                '<=',
                now()
            )
            ->whereNotNull(
                'effective_at'
            )
            ->where(
                'effective_at',
                '<=',
                now()
            )
            ->orderByDesc(
                'effective_at'
            )
            ->orderByDesc(
                'version_number'
            )
            ->first();
    }

    private function definition(
        string $policyType
    ): array {
        $policyType =
            $this->normalizeType(
                $policyType
            );

        $definition =
            config(
                'tradex_legal.policy_types.'
                .$policyType
            );

        abort_unless(
            is_array(
                $definition
            ),
            404
        );

        return $definition;
    }

    private function normalizeType(
        string $policyType
    ): string {
        return strtoupper(
            trim(
                $policyType
            )
        );
    }

    private function canonicalContent(
        string $content
    ): string {
        $content =
            str_replace(
                ["\r\n", "\r"],
                "\n",
                trim(
                    $content
                )
            );

        return $content;
    }

    private function resolveEffectiveAt(
        mixed $value
    ): CarbonImmutable {
        $now =
            CarbonImmutable::now();

        if (
            $value === null
            || $value === ''
        ) {
            return $now;
        }

        $effectiveAt =
            CarbonImmutable::parse(
                (string) $value
            );

        if (
            $effectiveAt->lessThan(
                $now->subMinute()
            )
        ) {
            abort(
                422,
                'Legal policy effective time cannot be backdated.'
            );
        }

        return $effectiveAt
            ->lessThan($now)
            ? $now
            : $effectiveAt;
    }

    private function derivedStatus(
        LegalPolicyVersion $policy,
        ?LegalPolicyVersion $current
    ): string {
        if ($policy->isDraft()) {
            return 'DRAFT';
        }

        if (
            $policy->effective_at
            && $policy
                ->effective_at
                ->isFuture()
        ) {
            return 'SCHEDULED';
        }

        if (
            $current
            && $current->is(
                $policy
            )
        ) {
            return 'CURRENT';
        }

        return 'HISTORICAL';
    }

    private function safeMetadata(
        LegalPolicyVersion $policy
    ): array {
        return [
            'policy_uuid' =>
                $policy->uuid,

            'policy_type' =>
                $policy->policy_type,

            'version_number' =>
                (int) $policy
                    ->version_number,

            'status' =>
                $policy->status,

            'content_sha256' =>
                $policy
                    ->content_sha256,

            'revision_version' =>
                (int) $policy
                    ->revision_version,
        ];
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        LegalPolicyVersion $policy,
        array $extra = []
    ): void {
        /*
         * Deliberately excludes legal title, summary and content.
         */
        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin->getKey(),
            action:
                $action,
            module:
                'LEGAL',
            targetType:
                'LEGAL_POLICY_VERSION',
            targetId:
                $policy->getKey(),
            newValues:
                array_merge(
                    $this->safeMetadata(
                        $policy
                    ),
                    $extra
                ),
            ipAddress:
                $request->ip(),
            userAgent:
                $request->userAgent()
        );
    }
}
