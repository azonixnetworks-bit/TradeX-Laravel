<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserMfaRecoveryCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class MfaRecoveryCodeService
{
    public function issueBootstrap(
        User $user,
        Request $request
    ): array {
        if (
            UserMfaRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'MFA_RECOVERY_CODES_ALREADY_ISSUED',
                'Recovery codes were already issued. Sign in with TOTP and regenerate them from an authenticated session if needed.'
            );
        }

        return $this->createCodes(
            $user,
            $request,
            'MFA_RECOVERY_CODES_ISSUED'
        );
    }

    public function regenerate(
        User $user,
        Request $request
    ): array {
        UserMfaRecoveryCode::query()
            ->where('user_id', $user->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        return $this->createCodes(
            $user,
            $request,
            'MFA_RECOVERY_CODES_REGENERATED'
        );
    }

    public function consume(
        User $user,
        string $plainCode,
        Request $request
    ): void {
        $hash = $this->hashCode($plainCode);

        $code = UserMfaRecoveryCode::query()
            ->where('user_id', $user->getKey())
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->first();

        if (! $code instanceof UserMfaRecoveryCode) {
            throw new AuthApiException(
                422,
                'INVALID_MFA_RECOVERY_CODE',
                'The MFA recovery code is invalid, used, or revoked.'
            );
        }

        $userAgent = trim((string) $request->userAgent());

        $code->forceFill([
            'used_at' => now(),
            'used_ip' => $request->ip(),
            'used_user_agent' => $userAgent !== ''
                ? mb_substr($userAgent, 0, 1024)
                : null,
        ])->save();

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'MFA_RECOVERY_CODE_USED',
            module: 'AUTH',
            targetType: 'USER',
            targetId: $user->getKey(),
            newValues: [
                'remaining_codes' => $this->remainingCount($user),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    public function remainingCount(User $user): int
    {
        return UserMfaRecoveryCode::query()
            ->where('user_id', $user->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->count();
    }

    private function createCodes(
        User $user,
        Request $request,
        string $auditAction
    ): array {
        $count = max(
            5,
            min(
                20,
                (int) config(
                    'tradex_auth.recovery_codes.count',
                    10
                )
            )
        );

        $plainCodes = [];

        for ($index = 0; $index < $count; $index++) {
            $plainCode = $this->generateCode();

            UserMfaRecoveryCode::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'code_hash' => $this->hashCode($plainCode),
                'issued_at' => now(),
            ]);

            $plainCodes[] = $plainCode;
        }

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: $auditAction,
            module: 'AUTH',
            targetType: 'USER',
            targetId: $user->getKey(),
            newValues: [
                'code_count' => $count,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $plainCodes;
    }

    private function generateCode(): string
    {
        $hex = strtoupper(
            bin2hex(random_bytes(16))
        );

        return 'TXR-'.implode(
            '-',
            str_split($hex, 4)
        );
    }

    private function hashCode(string $plainCode): string
    {
        $normalized = strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim($plainCode)
            ) ?? ''
        );

        if ($normalized === '') {
            throw new AuthApiException(
                422,
                'INVALID_MFA_RECOVERY_CODE',
                'The MFA recovery code is invalid, used, or revoked.'
            );
        }

        $pepper = trim(
            (string) config(
                'tradex_auth.recovery_codes.pepper',
                ''
            )
        );

        if ($pepper === '') {
            $pepper = (string) config('app.key');
        }

        if ($pepper === '') {
            throw new RuntimeException(
                'MFA recovery code pepper is not configured.'
            );
        }

        return hash_hmac(
            'sha256',
            $normalized,
            $pepper
        );
    }
}
