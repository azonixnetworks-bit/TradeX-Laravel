<?php

namespace Tests\Feature\Api\Legal;

use App\Models\LegalPolicyVersion;
use App\Models\UserLegalPolicyAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class LegalPolicyAcceptanceApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_public_policy_list_exposes_only_current_published_effective_versions(): void
    {
        $current = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Current terms'
        );

        $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            2,
            'Future terms',
            now()->addDay()
        );

        LegalPolicyVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'policy_type' => 'PRIVACY_POLICY',
            'version_number' => 1,
            'title' => 'Draft privacy',
            'content' => 'Draft privacy content that is not published.',
            'content_sha256' => hash('sha256', 'Draft privacy content that is not published.'),
            'status' => LegalPolicyVersion::STATUS_DRAFT,
            'revision_version' => 0,
        ]);

        $this
            ->getJson('/api/v1/legal/policies')
            ->assertOk()
            ->assertJsonPath('data.runtime_state', 'WIRED_EVIDENCE_ONLY')
            ->assertJsonPath('data.cross_module_enforcement', false)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $current->uuid)
            ->assertJsonPath('data.items.0.version_number', 1);
    }

    public function test_current_policy_selection_matches_frozen_admin17_effective_order(): void
    {
        $laterEffective = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            2,
            'Scheduled version that became effective later',
            now()->subMinute()
        );

        $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            3,
            'Higher version published for an earlier effective time',
            now()->subMinutes(2)
        );

        $this
            ->getJson('/api/v1/legal/policies/TERMS_OF_SERVICE')
            ->assertOk()
            ->assertJsonPath('data.uuid', $laterEffective->uuid)
            ->assertJsonPath('data.version_number', 2);
    }

    public function test_public_policy_show_returns_current_content_and_hash(): void
    {
        $policy = $this->publishedPolicy(
            'PRIVACY_POLICY',
            1,
            'Privacy policy body'
        );

        $this
            ->getJson('/api/v1/legal/policies/privacy_policy')
            ->assertOk()
            ->assertJsonPath('data.uuid', $policy->uuid)
            ->assertJsonPath('data.policy_type', 'PRIVACY_POLICY')
            ->assertJsonPath('data.content', 'Privacy policy body')
            ->assertJsonPath('data.content_sha256', $policy->content_sha256);
    }

    public function test_acceptance_status_and_accept_routes_require_authentication(): void
    {
        $policy = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Terms body for authentication test'
        );

        $this
            ->getJson('/api/v1/legal/acceptance-status')
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/legal/policies/'.$policy->uuid.'/accept',
                [
                    'content_sha256' => $policy->content_sha256,
                    'acceptance_confirmation' => true,
                ]
            )
            ->assertUnauthorized();
    }

    public function test_user_can_accept_current_policy_and_acceptance_is_audited_and_idempotent(): void
    {
        $policy = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Terms body accepted by user'
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $payload = [
            'content_sha256' => $policy->content_sha256,
            'acceptance_confirmation' => true,
        ];

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/legal/policies/'.$policy->uuid.'/accept',
                $payload
            )
            ->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.already_accepted', false)
            ->assertJsonPath('data.policy.uuid', $policy->uuid)
            ->assertJsonPath('data.acceptance.content_sha256', $policy->content_sha256);

        $this->assertDatabaseCount('user_legal_policy_acceptances', 1);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'action' => 'LEGAL_POLICY_ACCEPTED',
            'module' => 'LEGAL',
        ]);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/legal/policies/'.$policy->uuid.'/accept',
                $payload
            )
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.already_accepted', true);

        $this->assertDatabaseCount('user_legal_policy_acceptances', 1);

        $this
            ->withToken($token)
            ->getJson('/api/v1/legal/acceptance-status')
            ->assertOk()
            ->assertJsonPath('data.accepted_count', 1)
            ->assertJsonPath('data.unaccepted_count', 0)
            ->assertJsonPath('data.items.0.accepted', true)
            ->assertJsonPath('data.items.0.acceptance_required_by_api17', false);
    }

    public function test_acceptance_rejects_stale_content_hash(): void
    {
        $policy = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Current terms for hash validation'
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/legal/policies/'.$policy->uuid.'/accept',
                [
                    'content_sha256' => str_repeat('a', 64),
                    'acceptance_confirmation' => true,
                ]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'LEGAL_POLICY_CONTENT_CHANGED'
            );

        $this->assertDatabaseCount('user_legal_policy_acceptances', 0);
    }

    public function test_old_policy_version_cannot_be_accepted_after_new_version_becomes_current(): void
    {
        $old = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Old terms content',
            now()->subMinutes(2)
        );

        $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            2,
            'New terms content',
            now()->subMinute()
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/legal/policies/'.$old->uuid.'/accept',
                [
                    'content_sha256' => $old->content_sha256,
                    'acceptance_confirmation' => true,
                ]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'LEGAL_POLICY_VERSION_NOT_CURRENT'
            );
    }

    public function test_acceptance_confirmation_is_explicitly_required(): void
    {
        $policy = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Terms requiring explicit confirmation'
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/legal/policies/'.$policy->uuid.'/accept',
                [
                    'content_sha256' => $policy->content_sha256,
                    'acceptance_confirmation' => false,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acceptance_confirmation');
    }

    public function test_acceptance_evidence_is_immutable(): void
    {
        $policy = $this->publishedPolicy(
            'TERMS_OF_SERVICE',
            1,
            'Immutable acceptance terms'
        );

        $user = $this->createApiUser();

        $acceptance = UserLegalPolicyAcceptance::query()->create([
            'user_id' => $user->getKey(),
            'legal_policy_version_id' => $policy->getKey(),
            'policy_type' => $policy->policy_type,
            'policy_version_number' => $policy->version_number,
            'content_sha256' => $policy->content_sha256,
            'accepted_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $acceptance->update([
            'content_sha256' => str_repeat('b', 64),
        ]);
    }

    private function publishedPolicy(
        string $type,
        int $version,
        string $content,
        $effectiveAt = null
    ): LegalPolicyVersion {
        return LegalPolicyVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'policy_type' => $type,
            'version_number' => $version,
            'title' => 'Policy '.$version,
            'summary' => 'Summary '.$version,
            'content' => $content,
            'content_sha256' => hash('sha256', $content),
            'status' => LegalPolicyVersion::STATUS_PUBLISHED,
            'effective_at' => $effectiveAt ?? now()->subMinute(),
            'published_at' => now()->subMinute(),
            'revision_version' => 0,
        ]);
    }
}
