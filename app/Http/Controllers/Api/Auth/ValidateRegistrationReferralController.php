<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\Registration\RegistrationReferralValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidateRegistrationReferralController extends Controller
{
    public function __invoke(
        Request $request,
        RegistrationReferralValidator $referrals
    ): JsonResponse {
        $validated = $request->validate([
            'referral_code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $referrals->findEligible(
                    $validated['referral_code']
                ) !== null,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
