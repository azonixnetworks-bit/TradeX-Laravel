<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegistrationSessionRequest;
use App\Models\User;
use App\Models\UserRegistrationSession;
use App\Services\Auth\MfaRecoveryCodeService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BootstrapInvitationRecoveryCodesController extends Controller
{
    public function __invoke(
        RegistrationSessionRequest $request,
        RegistrationSessionService $registrations,
        MfaRecoveryCodeService $recoveryCodes
    ): JsonResponse {
        $data = $request->validated();

        $session = $registrations->resolveAny(
            $data['registration_id'],
            $data['registration_token']
        );

        $codes = DB::transaction(
            function () use (
                $session,
                $request,
                $recoveryCodes
            ): array {
                $locked = UserRegistrationSession::query()
                    ->whereKey($session->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! $locked->isAdminInvite()
                    || ! $locked->isCompleted()
                    || $locked->completed_user_id === null
                ) {
                    throw new AuthApiException(
                        409,
                        'INVITE_ONBOARDING_NOT_COMPLETED',
                        'Complete the secure customer onboarding flow before recovery codes are issued.'
                    );
                }

                if ($locked->expires_at->isPast()) {
                    throw new AuthApiException(
                        410,
                        'RECOVERY_CODE_BOOTSTRAP_EXPIRED',
                        'The one-time recovery-code setup window has expired. Sign in with TOTP and regenerate recovery codes from an authenticated session.'
                    );
                }

                $user = User::query()
                    ->whereKey($locked->completed_user_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $user instanceof User
                    || ! $user->canLogin()
                    || ! $user->hasConfirmedTotp()
                ) {
                    throw new AuthApiException(
                        409,
                        'RECOVERY_CODE_USER_NOT_READY',
                        'The activated customer account is not ready for MFA recovery codes.'
                    );
                }

                return $recoveryCodes->issueBootstrap(
                    $user,
                    $request
                );
            },
            3
        );

        return response()->json([
            'success' => true,
            'data' => [
                'recovery_codes' => $codes,
                'recovery_codes_count' => count($codes),
                'displayed_once' => true,
                'next_action' => 'LOGIN',
            ],
        ])->withHeaders([
            'Cache-Control' =>
                'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
