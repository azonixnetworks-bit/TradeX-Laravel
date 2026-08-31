<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\PreparedManualPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualPaymentQrController extends Controller
{
    public function __invoke(
        Request $request,
        string $paymentOrder,
        PreparedManualPaymentService $service
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $file = $service->manualQr(
            $user,
            $paymentOrder
        );

        return response()->stream(
            static function () use ($file): void {
                $stream = Storage::disk($file['disk'])
                    ->readStream($file['path']);

                if ($stream === false) {
                    return;
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => match (
                    strtolower(
                        (string) pathinfo(
                            $file['path'],
                            PATHINFO_EXTENSION
                        )
                    )
                ) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    default => 'application/octet-stream',
                },
                'Content-Disposition' =>
                    'inline; filename="'.$file['name'].'"',
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ]
        );
    }
}
