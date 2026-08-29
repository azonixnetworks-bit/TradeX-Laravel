<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\CustomerOnboardingInvitation;
use App\Models\LegalPolicyVersion;
use App\Models\OnboardingLegalPolicyAcceptance;
use App\Models\User;
use App\Models\UserLegalPolicyAcceptance;
use App\Models\UserRegistrationSession;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class InviteRegistrationService
{
    public function __construct(
        private readonly RegistrationOtpService $otpService,
        private readonly TotpService $totpService
    ) {
    }

    public function activate(
        string $invitationUuid,
        string $plainActivationToken,
        Request $request
    ): array {
        $invitation = CustomerOnboardingInvitation::query()
            ->where('uuid', $invitationUuid)
            ->first();

        $this->assertValidActivationToken(
            $invitation,
            $plainActivationToken
        );

        $plainRegistrationToken = bin2hex(
            random_bytes(32)
        );

        $provisionalPassword = Hash::make(
            bin2hex(random_bytes(32))
        );

        $activationHash = hash(
            'sha256',
            $plainActivationToken
        );

        $consumedAt = now();

        $session = DB::transaction(
            function () use (
                $invitation,
                $activationHash,
                $plainRegistrationToken,
                $provisionalPassword,
                $consumedAt,
                $request
            ): UserRegistrationSession {
                $locked = CustomerOnboardingInvitation::query()
                    ->whereKey($invitation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertValidActivationToken(
                    $locked,
                    null,
                    $activationHash
                );

                $this->ensureIdentityAvailable(
                    $locked->email,
                    $locked->whatsapp_number
                );

                UserRegistrationSession::query()
                    ->where(
                        'status',
                        UserRegistrationSession::STATUS_PENDING
                    )
                    ->where(function ($query) use ($locked): void {
                        $query
                            ->where('email', $locked->email)
                            ->orWhere(
                                'whatsapp_number',
                                $locked->whatsapp_number
                            );
                    })
                    ->update([
                        'status' =>
                            UserRegistrationSession::STATUS_SUPERSEDED,
                    ]);

                $session = UserRegistrationSession::query()
                    ->create([
                        'uuid' => (string) Str::uuid(),
                        'registration_token_hash' => hash(
                            'sha256',
                            $plainRegistrationToken
                        ),
                        'name' => $locked->name,
                        'email' => $locked->email,
                        'whatsapp_number' =>
                            $locked->whatsapp_number,
                        'password_hash' =>
                            $provisionalPassword,
                        'referral_code_input' =>
                            $locked->referral_code_input,
                        'referred_by_user_id' =>
                            $locked->referred_by_user_id,
                        'country_code' =>
                            $locked->country_code,
                        'language_code' =>
                            $locked->language_code,
                        'source' =>
                            UserRegistrationSession::SOURCE_ADMIN_INVITE,
                        'customer_onboarding_invitation_id' =>
                            $locked->getKey(),
                        'status' =>
                            UserRegistrationSession::STATUS_PENDING,
                        'expires_at' => now()->addMinutes(
                            max(
                                15,
                                (int) config(
                                    'tradex_registration.session_minutes',
                                    60
                                )
                            )
                        ),
                        'start_ip' => $request->ip(),
                        'start_user_agent' =>
                            $request->userAgent(),
                    ]);

                $locked->forceFill([
                    'activation_token_hash' => null,
                    'activation_consumed_at' =>
                        $consumedAt,
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'CUSTOMER_ONBOARDING_INVITATION_ACTIVATED',
                    module: 'AUTH',
                    targetType: 'USER_REGISTRATION_SESSION',
                    targetId: $session->getKey(),
                    newValues: [
                        'source' =>
                            UserRegistrationSession::SOURCE_ADMIN_INVITE,
                        'invitation_uuid' => $locked->uuid,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $session;
            },
            3
        );

        try {
            $this->otpService->issueEmail(
                $session,
                $request
            );
        } catch (Throwable $exception) {
            $session->forceFill([
                'status' =>
                    UserRegistrationSession::STATUS_SUPERSEDED,
            ])->save();

            CustomerOnboardingInvitation::query()
                ->whereKey($invitation->getKey())
                ->where(
                    'status',
                    CustomerOnboardingInvitation::STATUS_PENDING
                )
                ->whereNull('activation_token_hash')
                ->where(
                    'activation_consumed_at',
                    $consumedAt
                )
                ->update([
                    'activation_token_hash' =>
                        $activationHash,
                    'activation_consumed_at' => null,
                    'onboarding_stage' =>
                        CustomerOnboardingInvitation::STAGE_INVITE_SENT,
                ]);

            throw $exception;
        }

        CustomerOnboardingInvitation::query()
            ->whereKey($invitation->getKey())
            ->where(
                'status',
                CustomerOnboardingInvitation::STATUS_PENDING
            )
            ->where(
                'activation_consumed_at',
                $consumedAt
            )
            ->update([
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_EMAIL_OTP_SENT,
            ]);

        return [
            'registration_id' => $session->uuid,
            'registration_token' =>
                $plainRegistrationToken,
            'source' =>
                UserRegistrationSession::SOURCE_ADMIN_INVITE,
            'expires_at' =>
                $session->expires_at->toIso8601String(),
            'next_action' => 'VERIFY_EMAIL_OTP',
        ];
    }

    public function setPassword(
        UserRegistrationSession $session,
        string $password,
        Request $request
    ): array {
        $this->assertAdminInvitePending($session);

        if (
            $session->email_verified_at === null
            || $session->whatsapp_verified_at === null
        ) {
            throw new AuthApiException(
                409,
                'CONTACT_VERIFICATION_REQUIRED',
                'Verify email and WhatsApp before setting the password.'
            );
        }

        if (filled($session->totp_secret)) {
            throw new AuthApiException(
                409,
                'PASSWORD_STAGE_CLOSED',
                'The password stage is closed after TOTP setup begins.'
            );
        }

        $session->forceFill([
            'password_hash' => Hash::make($password),
            'password_set_at' => now(),
        ])->save();

        $this->updateInvitationStage(
            $session,
            CustomerOnboardingInvitation::STAGE_PASSWORD_SET
        );

        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action: 'INVITE_REGISTRATION_PASSWORD_SET',
            module: 'AUTH',
            targetType: 'USER_REGISTRATION_SESSION',
            targetId: $session->getKey(),
            newValues: [
                'password_set' => true,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'password_set' => true,
            'next_action' => 'SETUP_TOTP',
        ];
    }

    public function confirmTotp(
        UserRegistrationSession $session,
        string $totpCode,
        Request $request
    ): array {
        $this->assertAdminInvitePending($session);

        if (
            $session->email_verified_at === null
            || $session->whatsapp_verified_at === null
            || $session->password_set_at === null
            || blank($session->totp_secret)
        ) {
            throw new AuthApiException(
                409,
                'INVITE_REGISTRATION_STEPS_INCOMPLETE',
                'Complete contact verification, password setup, and TOTP setup first.'
            );
        }

        if ($session->totp_confirmed_at !== null) {
            return [
                'totp_confirmed' => true,
                'next_action' => 'ACCEPT_LEGAL_TERMS',
            ];
        }

        $step = $this->totpService->matchingStep(
            (string) $session->totp_secret,
            $totpCode
        );

        if ($step === null) {
            throw new AuthApiException(
                422,
                'INVALID_TOTP',
                'The TOTP code is invalid or expired.'
            );
        }

        if (
            $session->totp_last_used_step !== null
            && $step <= $session->totp_last_used_step
        ) {
            throw new AuthApiException(
                409,
                'TOTP_REPLAY_BLOCKED',
                'This TOTP code has already been used.'
            );
        }

        $session->forceFill([
            'totp_confirmed_at' => now(),
            'totp_last_used_step' => $step,
        ])->save();

        $this->updateInvitationStage(
            $session,
            CustomerOnboardingInvitation::STAGE_TOTP_CONFIRMED
        );

        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action: 'INVITE_REGISTRATION_TOTP_CONFIRMED',
            module: 'AUTH',
            targetType: 'USER_REGISTRATION_SESSION',
            targetId: $session->getKey(),
            newValues: [
                'totp_confirmed' => true,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'totp_confirmed' => true,
            'next_action' => 'ACCEPT_LEGAL_TERMS',
        ];
    }

    public function requiredPolicies(
        UserRegistrationSession $session
    ): array {
        $this->assertAdminInvitePending($session);

        if ($session->totp_confirmed_at === null) {
            throw new AuthApiException(
                409,
                'TOTP_CONFIRMATION_REQUIRED',
                'Confirm TOTP before reviewing onboarding legal policies.'
            );
        }

        $policies = $this->currentRequiredPolicies();
        $acceptedIds = OnboardingLegalPolicyAcceptance::query()
            ->where(
                'user_registration_session_id',
                $session->getKey()
            )
            ->whereIn(
                'legal_policy_version_id',
                $policies->pluck('id')
            )
            ->pluck('legal_policy_version_id')
            ->all();

        return [
            'items' => $policies
                ->map(function (LegalPolicyVersion $policy) use ($acceptedIds): array {
                    $definition = (array) config(
                        'tradex_legal.policy_types.'.$policy->policy_type,
                        []
                    );

                    return [
                        'uuid' => $policy->uuid,
                        'policy_type' => $policy->policy_type,
                        'display_name' =>
                            $definition['display_name']
                            ?? $policy->policy_type,
                        'version_number' =>
                            $policy->version_number,
                        'title' => $policy->title,
                        'summary' => $policy->summary,
                        'content' => $policy->content,
                        'content_sha256' => strtolower(
                            (string) $policy->content_sha256
                        ),
                        'effective_at' =>
                            $policy->effective_at?->toIso8601String(),
                        'accepted' => in_array(
                            $policy->getKey(),
                            $acceptedIds,
                            true
                        ),
                    ];
                })
                ->values()
                ->all(),
            'next_action' => 'ACCEPT_LEGAL_TERMS',
        ];
    }

    public function acceptPolicies(
        UserRegistrationSession $session,
        array $acceptances,
        Request $request
    ): array {
        $this->assertAdminInvitePending($session);

        if ($session->totp_confirmed_at === null) {
            throw new AuthApiException(
                409,
                'TOTP_CONFIRMATION_REQUIRED',
                'Confirm TOTP before accepting onboarding legal policies.'
            );
        }

        $policies = $this->currentRequiredPolicies();
        $policiesByUuid = $policies->keyBy('uuid');
        $inputByUuid = collect($acceptances)
            ->keyBy(fn (array $item): string =>
                strtolower((string) $item['policy_uuid'])
            );

        foreach ($inputByUuid->keys() as $uuid) {
            if (! $policiesByUuid->has($uuid)) {
                throw new AuthApiException(
                    422,
                    'UNEXPECTED_ONBOARDING_POLICY',
                    'Only current required onboarding policies may be accepted.'
                );
            }
        }

        foreach ($policies as $policy) {
            $input = $inputByUuid->get(
                strtolower($policy->uuid)
            );

            if (! is_array($input)) {
                throw new AuthApiException(
                    422,
                    'ONBOARDING_POLICY_ACCEPTANCE_REQUIRED',
                    'Every current required onboarding policy must be accepted.'
                );
            }

            if (! hash_equals(
                strtolower((string) $policy->content_sha256),
                strtolower(
                    trim((string) $input['content_sha256'])
                )
            )) {
                throw new AuthApiException(
                    409,
                    'ONBOARDING_POLICY_CONTENT_CHANGED',
                    'A legal policy changed. Reload the current onboarding policies before accepting.'
                );
            }
        }

        DB::transaction(
            function () use (
                $session,
                $policies,
                $request
            ): void {
                $locked = UserRegistrationSession::query()
                    ->whereKey($session->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAdminInvitePending($locked);

                foreach ($policies as $policy) {
                    $existing = OnboardingLegalPolicyAcceptance::query()
                        ->where(
                            'user_registration_session_id',
                            $locked->getKey()
                        )
                        ->where(
                            'legal_policy_version_id',
                            $policy->getKey()
                        )
                        ->first();

                    if ($existing instanceof OnboardingLegalPolicyAcceptance) {
                        continue;
                    }

                    $userAgent = trim(
                        (string) $request->userAgent()
                    );

                    $acceptance = OnboardingLegalPolicyAcceptance::query()
                        ->create([
                            'user_registration_session_id' =>
                                $locked->getKey(),
                            'legal_policy_version_id' =>
                                $policy->getKey(),
                            'policy_type' =>
                                $policy->policy_type,
                            'policy_version_number' =>
                                $policy->version_number,
                            'content_sha256' => strtolower(
                                (string) $policy->content_sha256
                            ),
                            'accepted_at' => now(),
                            'accepted_ip' => $request->ip(),
                            'accepted_user_agent' =>
                                $userAgent !== ''
                                    ? mb_substr(
                                        $userAgent,
                                        0,
                                        1024
                                    )
                                    : null,
                        ]);

                    AuditLog::record(
                        actorType: 'SYSTEM',
                        actorId: null,
                        action: 'ONBOARDING_LEGAL_POLICY_ACCEPTED',
                        module: 'LEGAL',
                        targetType: 'ONBOARDING_LEGAL_POLICY_ACCEPTANCE',
                        targetId: $acceptance->getKey(),
                        newValues: [
                            'policy_uuid' => $policy->uuid,
                            'policy_type' => $policy->policy_type,
                            'policy_version_number' =>
                                $policy->version_number,
                            'content_sha256' =>
                                $policy->content_sha256,
                        ],
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );
                }
            },
            3
        );

        $this->updateInvitationStage(
            $session,
            CustomerOnboardingInvitation::STAGE_TERMS_ACCEPTED
        );

        return [
            'accepted_count' => $policies->count(),
            'required_count' => $policies->count(),
            'all_required_accepted' => true,
            'next_action' => 'COMPLETE_ONBOARDING',
        ];
    }

    public function complete(
        UserRegistrationSession $session,
        Request $request
    ): array {
        if ($session->isCompleted()) {
            return [
                'completed' => true,
                'user_uuid' =>
                    $session->completedUser?->uuid,
                'next_action' => 'LOGIN',
            ];
        }

        $this->assertAdminInvitePending($session);

        if (
            $session->email_verified_at === null
            || $session->whatsapp_verified_at === null
            || $session->password_set_at === null
            || blank($session->totp_secret)
            || $session->totp_confirmed_at === null
        ) {
            throw new AuthApiException(
                409,
                'INVITE_REGISTRATION_STEPS_INCOMPLETE',
                'Complete all secure onboarding steps before account activation.'
            );
        }

        $policies = $this->currentRequiredPolicies();
        $this->assertCurrentPoliciesAccepted(
            $session,
            $policies
        );

        return DB::transaction(
            function () use (
                $session,
                $policies,
                $request
            ): array {
                $locked = UserRegistrationSession::query()
                    ->whereKey($session->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->isCompleted()) {
                    return [
                        'completed' => true,
                        'user_uuid' =>
                            $locked->completedUser?->uuid,
                        'next_action' => 'LOGIN',
                    ];
                }

                $this->assertAdminInvitePending($locked);
                $this->assertCurrentPoliciesAccepted(
                    $locked,
                    $policies
                );

                $invitation = CustomerOnboardingInvitation::query()
                    ->whereKey(
                        $locked->customer_onboarding_invitation_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $invitation->isPending()) {
                    throw new AuthApiException(
                        409,
                        'CUSTOMER_INVITATION_NOT_ACTIVE',
                        'This customer invitation is no longer active.'
                    );
                }

                $this->ensureIdentityAvailable(
                    $locked->email,
                    $locked->whatsapp_number
                );

                $user = new User([
                    'uuid' => (string) Str::uuid(),
                    'name' => $locked->name,
                    'email' => $locked->email,
                    'email_verified_at' =>
                        $locked->email_verified_at,
                    'whatsapp_number' =>
                        $locked->whatsapp_number,
                    'whatsapp_verified_at' =>
                        $locked->whatsapp_verified_at,
                    'password' => $locked->password_hash,
                    'referral_code' =>
                        $this->generateReferralCode(),
                    'referred_by_user_id' =>
                        $locked->referred_by_user_id,
                    'country_code' =>
                        $locked->country_code,
                    'language_code' =>
                        $locked->language_code,
                    'account_type' => 'FREE',
                    'status' => 'ACTIVE',
                ]);

                $user->forceFill([
                    'totp_secret' => $locked->totp_secret,
                    'totp_confirmed_at' =>
                        $locked->totp_confirmed_at,
                    'totp_last_used_step' =>
                        $locked->totp_last_used_step,
                ]);

                $user->save();

                $pendingAcceptances = OnboardingLegalPolicyAcceptance::query()
                    ->where(
                        'user_registration_session_id',
                        $locked->getKey()
                    )
                    ->whereIn(
                        'legal_policy_version_id',
                        $policies->pluck('id')
                    )
                    ->get()
                    ->keyBy('legal_policy_version_id');

                foreach ($policies as $policy) {
                    $pending = $pendingAcceptances->get(
                        $policy->getKey()
                    );

                    if (! $pending instanceof OnboardingLegalPolicyAcceptance) {
                        throw new AuthApiException(
                            409,
                            'ONBOARDING_LEGAL_ACCEPTANCE_REQUIRED',
                            'Current legal policies must be accepted before activation.'
                        );
                    }

                    UserLegalPolicyAcceptance::query()
                        ->create([
                            'user_id' => $user->getKey(),
                            'legal_policy_version_id' =>
                                $policy->getKey(),
                            'policy_type' =>
                                $policy->policy_type,
                            'policy_version_number' =>
                                $policy->version_number,
                            'content_sha256' => strtolower(
                                (string) $policy->content_sha256
                            ),
                            'accepted_at' =>
                                $pending->accepted_at,
                            'accepted_ip' =>
                                $pending->accepted_ip,
                            'accepted_user_agent' =>
                                $pending->accepted_user_agent,
                            'device_uuid' => null,
                            'device_platform' => null,
                        ]);
                }

                $locked->forceFill([
                    'status' =>
                        UserRegistrationSession::STATUS_COMPLETED,
                    'completed_user_id' => $user->getKey(),
                    'completed_at' => now(),
                ])->save();

                $invitation->forceFill([
                    'status' =>
                        CustomerOnboardingInvitation::STATUS_MATCHED,
                    'onboarding_stage' =>
                        CustomerOnboardingInvitation::STAGE_COMPLETED,
                    'activation_token_hash' => null,
                    'active_email_hash' => null,
                    'active_whatsapp_hash' => null,
                    'matched_user_id' => $user->getKey(),
                    'matched_at' => now(),
                ])->save();

                AuditLog::record(
                    actorType: 'USER',
                    actorId: $user->getKey(),
                    action: 'REGISTRATION_COMPLETED',
                    module: 'AUTH',
                    targetType: 'USER',
                    targetId: $user->getKey(),
                    newValues: [
                        'account_type' => 'FREE',
                        'status' => 'ACTIVE',
                        'source' =>
                            UserRegistrationSession::SOURCE_ADMIN_INVITE,
                        'invitation_uuid' => $invitation->uuid,
                        'referred_by_user_id' =>
                            $user->referred_by_user_id,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return [
                    'completed' => true,
                    'user_uuid' => $user->uuid,
                    'referral_code' =>
                        $user->referral_code,
                    'account_type' => 'FREE',
                    'status' => 'ACTIVE',
                    'next_action' => 'LOGIN',
                ];
            },
            3
        );
    }

    private function assertValidActivationToken(
        ?CustomerOnboardingInvitation $invitation,
        ?string $plainToken = null,
        ?string $preHashedToken = null
    ): void {
        $providedHash = $preHashedToken
            ?? hash('sha256', (string) $plainToken);

        $storedHash = $invitation?->activation_token_hash;

        if (
            ! $invitation instanceof CustomerOnboardingInvitation
            || ! $invitation->isPending()
            || $invitation->revoked_at !== null
            || $invitation->activation_consumed_at !== null
            || $invitation->expires_at->isPast()
            || ! is_string($storedHash)
            || strlen($storedHash) !== 64
            || ! hash_equals($storedHash, $providedHash)
        ) {
            throw new AuthApiException(
                401,
                'INVALID_CUSTOMER_INVITATION',
                'The customer activation invitation is invalid, expired, revoked, or already used.'
            );
        }
    }

    private function assertAdminInvitePending(
        UserRegistrationSession $session
    ): void {
        if (! $session->isAdminInvite()) {
            throw new AuthApiException(
                409,
                'ADMIN_INVITE_REGISTRATION_REQUIRED',
                'This endpoint only accepts admin-invited registrations.'
            );
        }

        if (! $session->isPending()) {
            throw new AuthApiException(
                409,
                'REGISTRATION_NOT_PENDING',
                'This registration is no longer pending.'
            );
        }

        $invitation = $session->customerOnboardingInvitation;

        if (
            ! $invitation instanceof CustomerOnboardingInvitation
            || ! $invitation->isPending()
            || $invitation->revoked_at !== null
            || $invitation->expires_at->isPast()
        ) {
            throw new AuthApiException(
                409,
                'CUSTOMER_INVITATION_NOT_ACTIVE',
                'This customer invitation is no longer active.'
            );
        }
    }

    private function currentRequiredPolicies(): Collection
    {
        $types = array_values(
            array_unique(
                array_map(
                    fn ($type): string =>
                        strtoupper(trim((string) $type)),
                    (array) config(
                        'tradex_customers.required_onboarding_legal_policies',
                        []
                    )
                )
            )
        );

        if ($types === []) {
            throw new AuthApiException(
                503,
                'ONBOARDING_LEGAL_POLICIES_NOT_CONFIGURED',
                'Required onboarding legal policies are not configured.'
            );
        }

        $policies = collect();

        foreach ($types as $type) {
            $policy = LegalPolicyVersion::query()
                ->where('policy_type', $type)
                ->where(
                    'status',
                    LegalPolicyVersion::STATUS_PUBLISHED
                )
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereNotNull('effective_at')
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->orderByDesc('version_number')
                ->first();

            if (! $policy instanceof LegalPolicyVersion) {
                throw new AuthApiException(
                    503,
                    'ONBOARDING_LEGAL_POLICIES_NOT_READY',
                    'A required onboarding legal policy is not currently published and effective.'
                );
            }

            $policies->push($policy);
        }

        return $policies;
    }

    private function assertCurrentPoliciesAccepted(
        UserRegistrationSession $session,
        Collection $policies
    ): void {
        $accepted = OnboardingLegalPolicyAcceptance::query()
            ->where(
                'user_registration_session_id',
                $session->getKey()
            )
            ->whereIn(
                'legal_policy_version_id',
                $policies->pluck('id')
            )
            ->get()
            ->keyBy('legal_policy_version_id');

        foreach ($policies as $policy) {
            $evidence = $accepted->get(
                $policy->getKey()
            );

            if (
                ! $evidence instanceof OnboardingLegalPolicyAcceptance
                || ! hash_equals(
                    strtolower((string) $policy->content_sha256),
                    strtolower((string) $evidence->content_sha256)
                )
            ) {
                throw new AuthApiException(
                    409,
                    'ONBOARDING_LEGAL_ACCEPTANCE_REQUIRED',
                    'Accept every current required legal policy before activation.'
                );
            }
        }
    }

    private function updateInvitationStage(
        UserRegistrationSession $session,
        string $stage
    ): void {
        if (! $session->isAdminInvite()) {
            return;
        }

        CustomerOnboardingInvitation::query()
            ->whereKey(
                $session->customer_onboarding_invitation_id
            )
            ->where(
                'status',
                CustomerOnboardingInvitation::STATUS_PENDING
            )
            ->update([
                'onboarding_stage' => $stage,
            ]);
    }

    private function ensureIdentityAvailable(
        string $email,
        string $whatsapp
    ): void {
        if (
            User::withTrashed()
                ->where('email', $email)
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'EMAIL_ALREADY_REGISTERED',
                'This email address is already registered.'
            );
        }

        if (
            User::withTrashed()
                ->where('whatsapp_number', $whatsapp)
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'WHATSAPP_ALREADY_REGISTERED',
                'This WhatsApp number is already registered.'
            );
        }
    }

    private function generateReferralCode(): string
    {
        do {
            $code = 'TX'.Str::upper(
                Str::random(10)
            );
        } while (
            User::withTrashed()
                ->where('referral_code', $code)
                ->exists()
        );

        return $code;
    }
}
