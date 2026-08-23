<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\RequestWhatsAppChangeRequest;
use App\Models\User;
use App\Services\Profile\ProfileContactChangeService;
use Illuminate\Http\JsonResponse;

class RequestWhatsAppChangeController extends Controller
{
    public function __invoke(
        RequestWhatsAppChangeRequest $request,
        ProfileContactChangeService $contacts
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' =>
                $contacts->requestWhatsApp(
                    $user,
                    $data['new_whatsapp_number'],
                    $data['totp_code'],
                    $request
                ),
        ]);
    }
}
