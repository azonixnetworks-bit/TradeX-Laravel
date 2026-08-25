<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\PasswordRecoveryOtpChallenge;
use App\Models\PasswordRecoverySession;
use App\Models\User;
use App\Models\UserRefreshToken;
use App\Services\Auth\AccessTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordRecoveryResetService
{
    public function __construct(
        private readonly PasswordRecoverySessionService $sessions,
        private readonly PasswordRecoveryResetTokenService $resetTokens,
        private readonly AccessTokenService $accessTokens
    ) {
    }

    public function reset(
        string $recoveryId,
        string $recoveryToken,
        string $resetToken,
        string $newPassword,
        Request $request
    ): void {
        DB::transaction(function () use (
            $recoveryId,
            $recoveryToken,
            $resetToken,
            $newPassword,
            $request
        ): void {
            $session = $this->sessions->resolve(
                $recoveryId,
                $recoveryToken,
                true
            );

            if (
                ! $session->isReadyForResetLink()
                || $session->user_id === null
            ) {
                throw new AuthApiException(
                    409,
                    'PASSWORD_RECOVERY_NOT_READY',
                    'Complete all password recovery verification steps first.'
                );
            }

            $this->resetTokens->assertValid(
                $session,
                $resetToken
            );

            $user = User::query()
                ->whereKey($session->user_id)
                ->lockForUpdate()
                ->first();

            if (
                $user === null
                || ! $user->canLogin()
            ) {
                throw new AuthApiException(
                    422,
                    'PASSWORD_RECOVERY_SESSION_INVALID',
                    'The password recovery session is invalid or expired.'
                );
            }

            if (
                Hash::check(
                    $newPassword,
                    (string) $user->password
                )
            ) {
                throw new AuthApiException(
                    422,
                    'PASSWORD_RECOVERY_PASSWORD_REUSED',
                    'Choose a password different from your current password.'
                );
            }

            $user->forceFill([
                'password' => $newPassword,
                'remember_token' => null,
            ])->save();

            $refreshCount = UserRefreshToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'PASSWORD_RESET',
                ]);

            $accessCount = $this->accessTokens
                ->revokeAll($user);

            /*
             * Clear trust but do not permanently revoke device records.
             * The next strong login can safely re-trust the same installation.
             */
            $deviceCount = $user->devices()
                ->where('is_trusted', true)
                ->update([
                    'is_trusted' => false,
                    'trusted_at' => null,
                ]);

            PasswordRecoverySession::query()
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $session->getKey())
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => now(),
                ]);

            PasswordRecoveryOtpChallenge::query()
                ->where(
                    'password_recovery_session_id',
                    $session->getKey()
                )
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => now(),
                ]);

            $session->forceFill([
                'consumed_at' => now(),
                'reset_token_hash' => null,
                'reset_token_expires_at' => null,
            ])->save();

            AuditLog::record(
                'USER',
                $user->getKey(),
                'PASSWORD_RECOVERY_COMPLETED',
                'AUTH',
                'USER',
                $user->getKey(),
                null,
                [
                    'recovery_uuid' => $session->uuid,
                    'refresh_tokens_revoked' => $refreshCount,
                    'access_tokens_revoked' => $accessCount,
                    'trusted_devices_cleared' => $deviceCount,
                ],
                $request->ip(),
                $request->userAgent()
            );
        }, 3);
    }
}
