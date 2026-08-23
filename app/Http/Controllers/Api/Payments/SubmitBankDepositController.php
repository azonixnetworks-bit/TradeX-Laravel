<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\CreateBankDepositRequest;
use App\Models\User;
use App\Services\Payments\ManualDepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class SubmitBankDepositController extends Controller
{
    public function __invoke(
        CreateBankDepositRequest $request,
        ManualDepositService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $proof = $request->file(
            'proof_file'
        );

        abort_unless(
            $proof instanceof UploadedFile,
            422,
            'A valid payment proof file is required.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->submitBankDeposit(
                $user,
                $request->validated(),
                $proof,
                $request
            ),
        ], 201);
    }
}
