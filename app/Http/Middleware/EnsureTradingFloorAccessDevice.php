<?php

namespace App\Http\Middleware;

use App\Models\TradingFloorAccessDevice;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTradingFloorAccessDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceCode = strtoupper(trim((string) $request->header('X-TradeX-Device-Code', '')));
        $deviceToken = trim((string) $request->header('X-TradeX-Device-Token', ''));

        if ($deviceCode === '' || $deviceToken === '') {
            return $this->deny(401, 'DEVICE_AUTH_REQUIRED', 'Trading Floor device authentication is required.');
        }

        $device = TradingFloorAccessDevice::query()
            ->where('device_code', $deviceCode)
            ->where('status', TradingFloorAccessDevice::STATUS_ACTIVE)
            ->first();

        if ($device === null) {
            return $this->deny(401, 'DEVICE_NOT_AUTHORIZED', 'The Trading Floor device is not authorized.');
        }

        $providedHash = hash_hmac('sha256', $deviceToken, (string) config('app.key'));

        if (! hash_equals((string) $device->token_hash, $providedHash)) {
            return $this->deny(401, 'DEVICE_NOT_AUTHORIZED', 'The Trading Floor device is not authorized.');
        }

        $request->attributes->set('trading_floor_access_device', $device);

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill([
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
            ])->save();
        }

        return $next($request);
    }

    private function deny(int $status, string $code, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
