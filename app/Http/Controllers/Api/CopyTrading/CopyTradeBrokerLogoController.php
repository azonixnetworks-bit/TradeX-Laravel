<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\CopyTradeBroker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CopyTradeBrokerLogoController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeBroker $copyTradeBroker
    ): StreamedResponse {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );

        abort_unless($copyTradeBroker->hasLogo(), 404);

        abort_unless(
            in_array(
                $copyTradeBroker->logo_mime_type,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
                true
            ),
            404
        );

        return Storage::disk(
            $copyTradeBroker->logo_disk
        )->response(
            $copyTradeBroker->logo_path,
            'broker-logo',
            [
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => $copyTradeBroker->logo_mime_type,
            ],
            'inline'
        );
    }
}
