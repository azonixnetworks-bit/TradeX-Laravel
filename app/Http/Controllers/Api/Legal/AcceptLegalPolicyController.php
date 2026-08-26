<?php

namespace App\Http\Controllers\Api\Legal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Legal\AcceptLegalPolicyRequest;
use App\Models\User;
use App\Services\Legal\LegalPolicyApiService;
use Illuminate\Http\JsonResponse;

class AcceptLegalPolicyController extends Controller
{
    public function __invoke(
        AcceptLegalPolicyRequest $request,
        string $policyVersion,
        LegalPolicyApiService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $result = $service->accept(
            $user,
            $policyVersion,
            (string) $request->validated('content_sha256'),
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ], $result['created'] ? 201 : 200);
    }
}
