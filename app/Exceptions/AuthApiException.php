<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AuthApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $apiCode,
        string $message
    ) {
        parent::__construct($message);
    }

    public function render(
        Request $request
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->apiCode,
                'message' => $this->getMessage(),
            ],
        ], $this->status);
    }
}
