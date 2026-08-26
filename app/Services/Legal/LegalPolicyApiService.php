<?php

namespace App\Services\Legal;

use App\Exceptions\LegalApiException;
use App\Models\AuditLog;
use App\Models\LegalPolicyVersion;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserLegalPolicyAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegalPolicyApiService
{
    public function currentPolicies(): array
    {
        return [
            'runtime_state' => 'WIRED_EVIDENCE_ONLY',
            'cross_module_enforcement' => false,
            'items' => $this->currentPolicyModels()
                ->map(fn (LegalPolicyVersion $policy): array =>
                    $this->presentPolicy($policy, false)
                )
                ->values()
                ->all(),
        ];
    }

    public function currentPolicy(string $policyType): array
    {
        return $this->presentPolicy(
            $this->currentPolicyModel($policyType),
            true
        );
    }

    public function acceptanceStatus(User $user): array
    {
        $policies = $this->currentPolicyModels();

        $acceptances = UserLegalPolicyAcceptance::query()
            ->where('user_id', $user->getKey())
            ->whereIn(
                'legal_policy_version_id',
                $policies->pluck('id')
            )
            ->get()
            ->keyBy('legal_policy_version_id');

        $items = $policies
            ->map(function (LegalPolicyVersion $policy) use ($acceptances): array {
                $acceptance = $acceptances->get($policy->getKey());

                return array_merge(
                    $this->presentPolicy($policy, false),
                    [
                        'accepted' =>
                            $acceptance instanceof UserLegalPolicyAcceptance,
                        'acceptance_required_by_api17' => false,
                        'acceptance' =>
                            $acceptance instanceof UserLegalPolicyAcceptance
                                ? $this->presentAcceptance($acceptance)
                                : null,
                    ]
                );
            })
            ->values();

        return [
            'runtime_state' => 'WIRED_EVIDENCE_ONLY',
            'cross_module_enforcement' => false,
            'accepted_count' => $items->where('accepted', true)->count(),
            'unaccepted_count' => $items->where('accepted', false)->count(),
            'items' => $items->all(),
        ];
    }

    public function accept(
        User $user,
        string $policyVersionUuid,
        string $contentSha256,
        Request $request
    ): array {
        $policy = LegalPolicyVersion::query()
            ->where('uuid', $policyVersionUuid)
            ->first();

        if (! $policy instanceof LegalPolicyVersion) {
            throw new LegalApiException(
                404,
                'LEGAL_POLICY_VERSION_NOT_FOUND',
                'The requested legal policy version does not exist.'
            );
        }

        $current = $this->currentPolicyModel($policy->policy_type);

        if ((int) $current->getKey() !== (int) $policy->getKey()) {
            throw new LegalApiException(
                409,
                'LEGAL_POLICY_VERSION_NOT_CURRENT',
                'This legal policy version is no longer the current effective version.'
            );
        }

        if (! hash_equals(
            strtolower((string) $policy->content_sha256),
            strtolower(trim($contentSha256))
        )) {
            throw new LegalApiException(
                409,
                'LEGAL_POLICY_CONTENT_CHANGED',
                'The legal policy content changed. Reload the current policy before accepting it.'
            );
        }

        $device = $request->attributes->get('tradex_user_device');

        if (
            ! $device instanceof UserDevice
            || (int) $device->user_id !== (int) $user->getKey()
            || $device->isRevoked()
        ) {
            throw new LegalApiException(
                401,
                'TRUSTED_DEVICE_REQUIRED',
                'A valid TradeX trusted device session is required.'
            );
        }

        [$acceptance, $created] = DB::transaction(
            function () use ($user, $policy, $request, $device): array {
                User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = UserLegalPolicyAcceptance::query()
                    ->where('user_id', $user->getKey())
                    ->where('legal_policy_version_id', $policy->getKey())
                    ->first();

                if ($existing instanceof UserLegalPolicyAcceptance) {
                    return [$existing, false];
                }

                $userAgent = trim((string) $request->userAgent());

                $acceptance = UserLegalPolicyAcceptance::query()->create([
                    'user_id' => $user->getKey(),
                    'legal_policy_version_id' => $policy->getKey(),
                    'policy_type' => $policy->policy_type,
                    'policy_version_number' => $policy->version_number,
                    'content_sha256' => strtolower((string) $policy->content_sha256),
                    'accepted_at' => now(),
                    'accepted_ip' => $request->ip(),
                    'accepted_user_agent' => $userAgent !== ''
                        ? mb_substr(
                            $userAgent,
                            0,
                            (int) config('tradex_legal_api.max_user_agent_length', 1024)
                        )
                        : null,
                    'device_uuid' => $device->uuid,
                    'device_platform' => $device->platform,
                ]);

                AuditLog::record(
                    actorType: 'USER',
                    actorId: $user->getKey(),
                    action: 'LEGAL_POLICY_ACCEPTED',
                    module: 'LEGAL',
                    targetType: 'USER_LEGAL_POLICY_ACCEPTANCE',
                    targetId: $acceptance->getKey(),
                    newValues: [
                        'acceptance_uuid' => $acceptance->uuid,
                        'policy_uuid' => $policy->uuid,
                        'policy_type' => $policy->policy_type,
                        'policy_version_number' => $policy->version_number,
                        'content_sha256' => $policy->content_sha256,
                        'device_uuid' => $device->uuid,
                        'device_platform' => $device->platform,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return [$acceptance, true];
            },
            3
        );

        return [
            'created' => $created,
            'already_accepted' => ! $created,
            'acceptance' => $this->presentAcceptance($acceptance),
            'policy' => $this->presentPolicy($policy, false),
        ];
    }

    private function currentPolicyModels(): Collection
    {
        return LegalPolicyVersion::query()
            ->where('status', LegalPolicyVersion::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('effective_at')
            ->where('effective_at', '<=', now())
            ->orderBy('policy_type')
            ->orderByDesc('version_number')
            ->get()
            ->unique('policy_type')
            ->values();
    }

    private function currentPolicyModel(string $policyType): LegalPolicyVersion
    {
        $normalized = strtoupper(trim($policyType));
        $definitions = (array) config('tradex_legal.policy_types', []);

        if (! array_key_exists($normalized, $definitions)) {
            throw new LegalApiException(
                404,
                'LEGAL_POLICY_TYPE_NOT_FOUND',
                'The requested legal policy type does not exist.'
            );
        }

        $policy = $this->currentPolicyModels()
            ->firstWhere('policy_type', $normalized);

        if (! $policy instanceof LegalPolicyVersion) {
            throw new LegalApiException(
                404,
                'LEGAL_POLICY_NOT_PUBLISHED',
                'No current published legal policy is available for this type.'
            );
        }

        return $policy;
    }

    private function presentPolicy(
        LegalPolicyVersion $policy,
        bool $includeContent
    ): array {
        $definition = (array) config(
            'tradex_legal.policy_types.'.$policy->policy_type,
            []
        );

        $data = [
            'uuid' => $policy->uuid,
            'policy_type' => $policy->policy_type,
            'display_name' => $definition['display_name'] ?? $policy->policy_type,
            'audience' => $definition['audience'] ?? null,
            'version_number' => $policy->version_number,
            'title' => $policy->title,
            'summary' => $policy->summary,
            'content_sha256' => strtolower((string) $policy->content_sha256),
            'published_at' => $policy->published_at?->toISOString(),
            'effective_at' => $policy->effective_at?->toISOString(),
            'acceptance_endpoint' => '/api/v1/legal/policies/'.$policy->uuid.'/accept',
        ];

        if ($includeContent) {
            $data['content'] = $policy->content;
        }

        return $data;
    }

    private function presentAcceptance(
        UserLegalPolicyAcceptance $acceptance
    ): array {
        return [
            'uuid' => $acceptance->uuid,
            'policy_type' => $acceptance->policy_type,
            'policy_version_number' => $acceptance->policy_version_number,
            'content_sha256' => strtolower((string) $acceptance->content_sha256),
            'accepted_at' => $acceptance->accepted_at?->toISOString(),
            'device_uuid' => $acceptance->device_uuid,
            'device_platform' => $acceptance->device_platform,
        ];
    }
}
