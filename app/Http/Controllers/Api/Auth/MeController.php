<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $device = $request->attributes->get(
            'tradex_user_device'
        );

        abort_unless(
            $device instanceof UserDevice,
            401,
            'TradeX device session is unavailable.'
        );

        return response()->json([
            'success' => true,

            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' =>
                        $user->email_verified_at !== null,
                    'whatsapp_number' =>
                        $user->whatsapp_number,
                    'whatsapp_verified' =>
                        $user->whatsapp_verified_at !== null,
                    'referral_code' =>
                        $user->referral_code,
                    'country_code' =>
                        $user->country_code,
                    'language_code' =>
                        $user->language_code,
                    'account_type' =>
                        $user->account_type,
                    'status' =>
                        $user->status,
                ],

                'device' => [
                    'uuid' => $device->uuid,
                    'device_slot' =>
                        $device->device_slot,
                    'platform' =>
                        $device->platform,
                    'device_name' =>
                        $device->device_name,
                    'device_model' =>
                        $device->device_model,
                    'os_version' =>
                        $device->os_version,
                    'app_version' =>
                        $device->app_version,
                    'is_trusted' =>
                        (bool) $device->is_trusted,
                ],

                'session' => [
                    'uuid' =>
                        $request->attributes->get(
                            'tradex_session_uuid'
                        ),
                ],
            ],
        ]);
    }
}