<?php

namespace App\Http\Controllers\Api\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\FundedAccountPartner;
use App\Services\Trading\FundedAdvisorCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundedAdvisorCatalogController extends Controller
{
    public function version(
        Request $request,
        FundedAdvisorCatalogService $catalog
    ): JsonResponse|Response {
        $snapshot = $catalog->snapshot();

        return $this->versionedResponse($request, [
            'version' => $snapshot['version'],
            'generated_at' => $snapshot['generated_at'],
        ], $snapshot['version']);
    }

    public function catalog(
        Request $request,
        FundedAdvisorCatalogService $catalog
    ): JsonResponse|Response {
        $snapshot = $catalog->snapshot();

        return $this->versionedResponse($request, $snapshot, $snapshot['version']);
    }

    public function logo(FundedAccountPartner $fundedAccountPartner): StreamedResponse
    {
        abort_unless($fundedAccountPartner->is_active && $fundedAccountPartner->hasLogo(), 404);
        abort_unless(
            in_array(
                $fundedAccountPartner->logo_mime_type,
                ['image/jpeg', 'image/png', 'image/webp'],
                true
            ),
            404
        );

        $disk = Storage::disk((string) $fundedAccountPartner->logo_disk);
        abort_unless($disk->exists((string) $fundedAccountPartner->logo_path), 404);

        return $disk->response(
            (string) $fundedAccountPartner->logo_path,
            'funded-partner-logo',
            [
                'Cache-Control' => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => (string) $fundedAccountPartner->logo_mime_type,
            ],
            'inline'
        );
    }

    /** @param array<string, mixed> $data */
    private function versionedResponse(
        Request $request,
        array $data,
        string $version
    ): JsonResponse|Response {
        $etag = '"'.$version.'"';
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200, $headers);
    }
}
