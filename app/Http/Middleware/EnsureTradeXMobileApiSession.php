<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\UserDevice;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTradeXMobileApiSession
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->deny(
                status: 401,
                code: 'UNAUTHENTICATED',
                message: 'Authentication is required.'
            );
        }

        if (! $user->canLogin()) {
            return $this->deny(
                status: 403,
                code: 'ACCOUNT_NOT_ACTIVE',
                message: 'This account is not allowed to access the API.'
            );
        }

        $accessToken = $user->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return $this->deny(
                status: 401,
                code: 'INVALID_ACCESS_TOKEN',
                message: 'A valid TradeX access token is required.'
            );
        }

        if (! $accessToken->isTradeXBound()) {
            return $this->deny(
                status: 401,
                code: 'UNBOUND_ACCESS_TOKEN',
                message: 'This access token is not bound to a TradeX device session.'
            );
        }

        $device = $accessToken->device;

        if (! $device instanceof UserDevice) {
            return $this->deny(
                status: 401,
                code: 'DEVICE_NOT_FOUND',
                message: 'The device linked to this session is unavailable.'
            );
        }

        if ((int) $device->user_id !== (int) $user->getKey()) {
            return $this->deny(
                status: 401,
                code: 'DEVICE_USER_MISMATCH',
                message: 'The authenticated session does not match the registered device.'
            );
        }

        if ($device->isRevoked()) {
            return $this->deny(
                status: 401,
                code: 'DEVICE_REVOKED',
                message: 'This device session has been revoked.'
            );
        }

        if (! $device->is_trusted) {
            return $this->deny(
                status: 403,
                code: 'DEVICE_NOT_TRUSTED',
                message: 'This device is not trusted.'
            );
        }

        $request->attributes->set(
            'tradex_user_device',
            $device
        );

        $request->attributes->set(
            'tradex_session_uuid',
            $accessToken->session_uuid
        );

        return $next($request);
    }

    private function deny(
        int $status,
        string $code,
        string $message
    ): JsonResponse {
        return response()->json(
            [
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
            $status
        );
    }
}
