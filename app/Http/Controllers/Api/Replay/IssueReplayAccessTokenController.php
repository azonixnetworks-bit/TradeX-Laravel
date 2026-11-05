<?php

namespace App\Http\Controllers\Api\Replay;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;

/**
 * Issue a minimal RS256 access token for the *separate* TradeX Replay API.
 *
 * This is inside the existing auth:sanctum + tradex.mobile middleware group.
 * It does NOT issue broker permissions or replace the existing mobile session.
 */
final class IssueReplayAccessTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $request->attributes->get('tradex_user_device');
        $mobileSessionUuid = $request->attributes->get('tradex_session_uuid');

        abort_unless(
            $user instanceof User && $user->canLogin()
                && $device instanceof UserDevice && ! $device->isRevoked()
                && (int) $device->user_id === (int) $user->getKey()
                && $device->is_trusted,
            403, 'Replay access is not available for this device session.'
        );

        if (! Str::isUuid((string) $user->uuid)
            || ! Str::isUuid((string) $device->uuid)
            || ! Str::isUuid((string) $mobileSessionUuid)) {
            abort(403, 'Replay requires a valid authenticated device session.');
        }

        $privateKeyPath = (string) config('replay.jwt_private_key_path', '');
        if ($privateKeyPath === '' || ! is_file($privateKeyPath) || ! is_readable($privateKeyPath)) {
            abort(503, 'Replay token issuance is not configured.');
        }
        if (! extension_loaded('openssl')) {
            abort(503, 'Replay token signing is unavailable.');
        }
        $pem = @file_get_contents($privateKeyPath);
        if ($pem === false) {
            abort(503, 'Replay token signing is unavailable.');
        }
        $privateKey = openssl_pkey_get_private($pem);
        if ($privateKey === false) {
            abort(503, 'Replay token signing is unavailable.');
        }

        $now = time();
        $ttl = max(30, min(180, (int) config('replay.token_ttl_seconds', 120)));
        $claims = [
            'iss' => (string) config('replay.jwt_issuer', 'tradex-laravel'),
            'aud' => (string) config('replay.jwt_audience', 'tradex-replay'),
            'sub' => (string) $user->uuid,
            'device' => (string) $device->uuid,
            'session' => (string) $mobileSessionUuid,
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'scope' => 'replay:sessions',
        ];
        $base64url = static fn (string $bytes): string =>
            rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        try {
            $header = $base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $payload = $base64url(json_encode($claims, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            abort(503, 'Replay token encoding is unavailable.');
        }
        $unsigned = $header.'.'.$payload;
        $signature = '';
        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            abort(503, 'Replay token signing failed.');
        }
        $jwt = $unsigned.'.'.$base64url($signature);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $jwt,
                'token_type' => 'Bearer',
                'expires_in' => $ttl,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $now + $ttl),
                'scope' => 'replay:sessions',
                'execution_enabled' => false,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
