<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\SubmitPreparedCryptoEvidenceRequest;
use App\Models\User;
use App\Services\Payments\PreparedManualPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class SubmitPreparedCryptoEvidenceController
    extends Controller
{
    public function __invoke(
        SubmitPreparedCryptoEvidenceRequest $request,
        string $paymentOrder,
        PreparedManualPaymentService $service
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $proof =
            $request->file(
                'proof_file'
            );

        return response()->json([
            'success' =>
                true,
            'data' =>
                $service
                    ->submitCryptoEvidence(
                        $user,
                        $paymentOrder,
                        $request->validated(),
                        $proof
                            instanceof UploadedFile
                                ? $proof
                                : null,
                        $request
                    ),
        ]);
    }
}
