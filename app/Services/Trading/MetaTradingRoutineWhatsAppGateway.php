<?php

namespace App\Services\Trading;

use App\Contracts\Trading\TradingRoutineWhatsAppGateway;
use App\Exceptions\TradingRoutineWhatsAppException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaTradingRoutineWhatsAppGateway implements TradingRoutineWhatsAppGateway
{
    public function sendPdf(
        string $recipient,
        string $pdfBytes,
        string $filename
    ): array {
        $driver = strtolower(trim((string) config('trading_routine.whatsapp.driver', 'disabled')));

        if ($driver !== 'meta') {
            throw new TradingRoutineWhatsAppException(
                'WHATSAPP_ROUTINE_NOT_CONFIGURED',
                'Trading routine WhatsApp delivery is not configured.'
            );
        }

        $graphVersion = trim((string) config('tradex_registration.delivery.meta.graph_version'));
        $phoneNumberId = trim((string) config('tradex_registration.delivery.meta.phone_number_id'));
        $accessToken = trim((string) config('tradex_registration.delivery.meta.access_token'));
        $templateName = trim((string) config('trading_routine.whatsapp.template_name'));
        $templateLanguage = trim((string) config('trading_routine.whatsapp.template_language', 'en_US'));
        $timeout = (int) config('tradex_registration.delivery.meta.timeout_seconds', 10);
        $connectTimeout = (int) config('tradex_registration.delivery.meta.connect_timeout_seconds', 5);

        if (
            preg_match('/^v\d+\.\d+$/', $graphVersion) !== 1
            || preg_match('/^\d+$/', $phoneNumberId) !== 1
            || $accessToken === ''
            || preg_match('/^[a-z0-9_]{1,512}$/', $templateName) !== 1
            || preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/', $templateLanguage) !== 1
        ) {
            throw new TradingRoutineWhatsAppException(
                'WHATSAPP_ROUTINE_NOT_CONFIGURED',
                'Trading routine WhatsApp provider credentials or template settings are incomplete.'
            );
        }

        $recipient = ltrim(trim($recipient), '+');
        if (preg_match('/^\d{7,20}$/', $recipient) !== 1) {
            throw new TradingRoutineWhatsAppException(
                'WHATSAPP_DESTINATION_INVALID',
                'The verified WhatsApp destination is invalid.'
            );
        }

        $base = 'https://graph.facebook.com/'.$graphVersion.'/'.$phoneNumberId;

        try {
            $mediaResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->attach('file', $pdfBytes, $filename, ['Content-Type' => 'application/pdf'])
                ->post($base.'/media', [
                    'messaging_product' => 'whatsapp',
                ]);

            if ($mediaResponse->failed()) {
                throw new TradingRoutineWhatsAppException(
                    'WHATSAPP_MEDIA_UPLOAD_FAILED',
                    'The daily trading routine PDF could not be uploaded to the WhatsApp provider.'
                );
            }

            $mediaId = trim((string) data_get($mediaResponse->json(), 'id'));
            if ($mediaId === '') {
                throw new TradingRoutineWhatsAppException(
                    'WHATSAPP_MEDIA_UPLOAD_FAILED',
                    'The WhatsApp provider did not return a media identifier.'
                );
            }

            /*
             * Do not automatically retry this POST inside the HTTP client. Meta
             * may have accepted it even when a network response is lost. Retry
             * decisions are handled by the persisted delivery state instead.
             */
            $messageResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->post($base.'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => [
                            'code' => $templateLanguage,
                        ],
                        'components' => [
                            [
                                'type' => 'header',
                                'parameters' => [
                                    [
                                        'type' => 'document',
                                        'document' => [
                                            'id' => $mediaId,
                                            'filename' => $filename,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($messageResponse->failed()) {
                throw new TradingRoutineWhatsAppException(
                    'WHATSAPP_MESSAGE_REJECTED',
                    'The WhatsApp provider rejected the daily trading routine message.'
                );
            }

            $messageId = trim((string) data_get($messageResponse->json(), 'messages.0.id'));
            if ($messageId === '') {
                throw new TradingRoutineWhatsAppException(
                    'WHATSAPP_MESSAGE_REJECTED',
                    'The WhatsApp provider did not return a message identifier.'
                );
            }

            return [
                'provider' => 'META_WHATSAPP_CLOUD_API',
                'message_id' => $messageId,
                'media_id' => $mediaId,
            ];
        } catch (TradingRoutineWhatsAppException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new TradingRoutineWhatsAppException(
                'WHATSAPP_PROVIDER_UNAVAILABLE',
                'The daily trading routine could not be submitted to the WhatsApp provider.'
            );
        }
    }
}
