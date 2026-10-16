<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegistrationSessionRequest;
use App\Models\User;
use App\Models\UserRegistrationSession;
use App\Services\Auth\MfaRecoveryCodeService;
use App\Services\Auth\Registration\RegistrationOtpDeliveryService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BootstrapRegistrationRecoveryCodesController extends Controller
{
    public function __invoke(
        RegistrationSessionRequest $request,
        RegistrationSessionService $registrations,
        MfaRecoveryCodeService $recoveryCodes,
        RegistrationOtpDeliveryService $delivery
    ): JsonResponse {
        $data = $request->validated();

        $session = $registrations->resolveAny(
            $data['registration_id'],
            $data['registration_token']
        );

        [$codes, $email] = DB::transaction(
            function () use ($session, $request, $recoveryCodes): array {
                $locked = UserRegistrationSession::query()
                    ->whereKey($session->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $locked->source !== UserRegistrationSession::SOURCE_SELF_REGISTER
                    || ! $locked->isCompleted()
                    || $locked->completed_user_id === null
                ) {
                    throw new AuthApiException(
                        409,
                        'REGISTRATION_NOT_COMPLETED',
                        'Complete the TradeX registration security checks before recovery codes are issued.'
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

                return [
                    $recoveryCodes->issueBootstrap($user, $request),
                    $user->email,
                ];
            },
            3
        );

        // This confirms that codes were issued without ever including them in
        // the mailbox. Email is not a safe place to store a second factor.
        $delivery->sendRecoveryCodesReadyNotification($email);

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
