<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureTradeXMobileApiSession;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureTradeXMobileApiSessionTest extends TestCase
{
    public function test_valid_active_trusted_device_session_is_allowed(): void
    {
        $user = $this->activeUser();

        $device = $this->device(
            userId: 10,
            trusted: true,
            active: true
        );

        $token = $this->token(
            device: $device,
            deviceId: 50,
            sessionUuid: '11111111-1111-4111-8111-111111111111'
        );

        $user->setAttribute('id', 10);
        $user->withAccessToken($token);

        $request = Request::create(
            '/api/v1/test',
            'GET'
        );

        $request->setUserResolver(
            fn () => $user
        );

        $response = app(
            EnsureTradeXMobileApiSession::class
        )->handle(
            $request,
            fn (Request $request): Response => response()->json([
                'success' => true,
                'device_id' => $request->attributes
                    ->get('tradex_user_device')
                    ?->getKey(),
                'session_uuid' => $request->attributes
                    ->get('tradex_session_uuid'),
            ])
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            true,
            $response->getData(true)['success']
        );

        $this->assertSame(
            50,
            $response->getData(true)['device_id']
        );
    }

    public function test_unbound_access_token_is_rejected(): void
    {
        $user = $this->activeUser();

        $token = new PersonalAccessToken();
        $token->forceFill([
            'user_device_id' => null,
            'session_uuid' => null,
        ]);

        $user->withAccessToken($token);

        $response = $this->runMiddleware($user);

        $this->assertSame(
            401,
            $response->getStatusCode()
        );

        $this->assertSame(
            'UNBOUND_ACCESS_TOKEN',
            $response->getData(true)['error']['code']
        );
    }

    public function test_revoked_device_is_rejected(): void
    {
        $user = $this->activeUser();

        $device = $this->device(
            userId: 10,
            trusted: true,
            active: false
        );

        $token = $this->token(
            device: $device,
            deviceId: 50
        );

        $user->setAttribute('id', 10);
        $user->withAccessToken($token);

        $response = $this->runMiddleware($user);

        $this->assertSame(
            401,
            $response->getStatusCode()
        );

        $this->assertSame(
            'DEVICE_REVOKED',
            $response->getData(true)['error']['code']
        );
    }

    public function test_untrusted_device_is_rejected(): void
    {
        $user = $this->activeUser();

        $device = $this->device(
            userId: 10,
            trusted: false,
            active: true
        );

        $token = $this->token(
            device: $device,
            deviceId: 50
        );

        $user->setAttribute('id', 10);
        $user->withAccessToken($token);

        $response = $this->runMiddleware($user);

        $this->assertSame(
            403,
            $response->getStatusCode()
        );

        $this->assertSame(
            'DEVICE_NOT_TRUSTED',
            $response->getData(true)['error']['code']
        );
    }

    public function test_device_belonging_to_another_user_is_rejected(): void
    {
        $user = $this->activeUser();

        $device = $this->device(
            userId: 999,
            trusted: true,
            active: true
        );

        $token = $this->token(
            device: $device,
            deviceId: 50
        );

        $user->setAttribute('id', 10);
        $user->withAccessToken($token);

        $response = $this->runMiddleware($user);

        $this->assertSame(
            401,
            $response->getStatusCode()
        );

        $this->assertSame(
            'DEVICE_USER_MISMATCH',
            $response->getData(true)['error']['code']
        );
    }

    public function test_inactive_user_is_rejected_even_with_valid_device(): void
    {
        $user = new User([
            'status' => 'SUSPENDED',
            'account_type' => 'PREMIUM',
        ]);

        $user->setAttribute('id', 10);

        $device = $this->device(
            userId: 10,
            trusted: true,
            active: true
        );

        $token = $this->token(
            device: $device,
            deviceId: 50
        );

        $user->withAccessToken($token);

        $response = $this->runMiddleware($user);

        $this->assertSame(
            403,
            $response->getStatusCode()
        );

        $this->assertSame(
            'ACCOUNT_NOT_ACTIVE',
            $response->getData(true)['error']['code']
        );
    }

    private function runMiddleware(
        User $user
    ): Response {
        $request = Request::create(
            '/api/v1/test',
            'GET'
        );

        $request->setUserResolver(
            fn () => $user
        );

        return app(
            EnsureTradeXMobileApiSession::class
        )->handle(
            $request,
            fn (): Response => response()->json([
                'success' => true,
            ])
        );
    }

    private function activeUser(): User
    {
        return new User([
            'status' => 'ACTIVE',
            'account_type' => 'PREMIUM',
        ]);
    }

    private function device(
        int $userId,
        bool $trusted,
        bool $active
    ): UserDevice {
        $device = new UserDevice();

        $device->forceFill([
            'user_id' => $userId,
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'is_trusted' => $trusted,
            'is_active' => $active,
            'revoked_at' => null,
        ]);

        return $device;
    }

    private function token(
        UserDevice $device,
        int $deviceId,
        string $sessionUuid = '11111111-1111-4111-8111-111111111111'
    ): PersonalAccessToken {
        $device->setAttribute(
            'id',
            $deviceId
        );

        $token = new PersonalAccessToken();

        $token->forceFill([
            'user_device_id' => $deviceId,
            'session_uuid' => $sessionUuid,
        ]);

        $token->setRelation(
            'device',
            $device
        );

        return $token;
    }
}
