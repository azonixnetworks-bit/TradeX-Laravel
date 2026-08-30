<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegenerateMfaRecoveryCodesRequest;
use App\Models\User;
use App\Services\Auth\MfaRecoveryCodeService;
use App\Services\Auth\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegenerateMfaRecoveryCodesController extends Controller
{
    public function __invoke(
        RegenerateMfaRecoveryCodesRequest $request,
        TotpService $totp,
        MfaRecoveryCodeService $recoveryCodes
    ): JsonResponse {
        $data = $request->validated();

        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthApiException(
                401,
                'UNAUTHENTICATED',
                'Authentication is required.'
            );
        }

        $codes = DB::transaction(
            function () use (
                $user,
                $data,
                $request,
                $totp,
                $recoveryCodes
            ): array {
                $locked = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! Hash::check(
                    (string) $data['password'],
                    (string) $locked->password
                )) {
                    throw new AuthApiException(
                        401,
                        'INVALID_CREDENTIALS',
                        'The password is incorrect.'
                    );
                }

                $totp->consume(
                    $locked,
                    (string) $data['totp_code']
                );

                return $recoveryCodes->regenerate(
                    $locked,
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
            ],
        ])->withHeaders([
            'Cache-Control' =>
                'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
