<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Services\Education\EducationPlaybackService;
use App\Services\Education\HlsPlaybackGatewayService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlaybackAssetController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ?string $path,
        EducationPlaybackService $playback,
        HlsPlaybackGatewayService $gateway
    ): Response {
        $session =
            $playback->resolveToken(
                $token,
                $request
            );

        return $gateway->serve(
            $session,
            $token,
            $path
        );
    }
}
