<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeIdeaChartController extends Controller
{
    public function __invoke(
        Request $request,
        string $tradeIdea,
        TradeIdeaApiService $service
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $resolved = $service->resolve(
            $user,
            $tradeIdea
        );

        $asset = $service->chartAsset(
            $user,
            $resolved,
            $request
        );

        return response()->stream(
            static function () use (
                $asset
            ): void {
                try {
                    fpassthru(
                        $asset['stream']
                    );
                } finally {
                    if (
                        is_resource(
                            $asset['stream']
                        )
                    ) {
                        fclose(
                            $asset['stream']
                        );
                    }
                }
            },
            200,
            [
                'Content-Type' =>
                    $asset['mime_type'],

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' =>
                    'no-cache',

                'Expires' =>
                    '0',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Content-Disposition' =>
                    'inline',

                'X-TradeX-Content-Protection' =>
                    'premium-private',
            ]
        );
    }
}
