<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ConfirmProfileContactChangeRequest;
use App\Models\User;
use App\Services\Profile\ProfileContactChangeService;
use Illuminate\Http\JsonResponse;

class ConfirmWhatsAppChangeController extends Controller
{
    public function __invoke(
        ConfirmProfileContactChangeRequest $request,
        ProfileContactChangeService $contacts
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $contacts->confirmWhatsApp(
            $user,
            $request->validated(),
            $request
        );

        return response()->json([
            'success' => true,
            'message' =>
                'WhatsApp number updated.',
            'data' => [
                'whatsapp_number' =>
                    $user->fresh()
                        ->whatsapp_number,

                'whatsapp_verified' =>
                    $user->fresh()
                        ->whatsapp_verified_at
                    !== null,
            ],
        ]);
    }
}
