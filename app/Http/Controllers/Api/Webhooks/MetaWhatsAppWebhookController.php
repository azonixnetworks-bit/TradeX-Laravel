<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\MetaWhatsAppWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWhatsAppWebhookController extends Controller
{
    public function verify(
        Request $request,
        MetaWhatsAppWebhookService $webhooks
    ): Response {
        $challenge =
            $webhooks->verificationChallenge(
                $request
            );

        if ($challenge === null) {
            return response(
                'Forbidden',
                403
            );
        }

        return response(
            $challenge,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );
    }

    public function receive(
        Request $request,
        MetaWhatsAppWebhookService $webhooks
    ): Response {
        if (! $webhooks->hasValidSignature($request)) {
            return response(
                'Forbidden',
                403
            );
        }

        $webhooks->recordEvent(
            $request
        );

        return response(
            'EVENT_RECEIVED',
            200
        );
    }
}
