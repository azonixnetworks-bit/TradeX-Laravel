<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppWebhookService
{
    public function verificationChallenge(
        Request $request
    ): ?string {
        $configuredToken = trim(
            (string) config(
                'tradex_registration.delivery.meta.webhook_verify_token'
            )
        );

        if ($configuredToken === '') {
            Log::error(
                'Meta WhatsApp webhook verification token is not configured.'
            );

            return null;
        }

        $mode = trim(
            (string) (
                $request->query('hub_mode')
                ?? $request->query('hub.mode')
                ?? ''
            )
        );

        $providedToken = trim(
            (string) (
                $request->query('hub_verify_token')
                ?? $request->query('hub.verify_token')
                ?? ''
            )
        );

        $challenge = (string) (
            $request->query('hub_challenge')
            ?? $request->query('hub.challenge')
            ?? ''
        );

        if (
            $mode !== 'subscribe'
            || $providedToken === ''
            || ! hash_equals(
                $configuredToken,
                $providedToken
            )
            || $challenge === ''
        ) {
            return null;
        }

        return $challenge;
    }

    public function hasValidSignature(
        Request $request
    ): bool {
        $appSecret = trim(
            (string) config(
                'tradex_registration.delivery.meta.app_secret'
            )
        );

        if ($appSecret === '') {
            Log::error(
                'Meta WhatsApp app secret is not configured for webhook signature validation.'
            );

            return false;
        }

        $providedSignature = trim(
            (string) $request->header(
                'X-Hub-Signature-256',
                ''
            )
        );

        if (! str_starts_with(
            $providedSignature,
            'sha256='
        )) {
            return false;
        }

        $expectedSignature =
            'sha256='.
            hash_hmac(
                'sha256',
                $request->getContent(),
                $appSecret
            );

        return hash_equals(
            $expectedSignature,
            $providedSignature
        );
    }

    public function recordEvent(
        Request $request
    ): void {
        $payload = $request->json()->all();
        $entries = $payload['entry'] ?? [];

        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $changes = $entry['changes'] ?? [];

            if (! is_array($changes)) {
                continue;
            }

            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $field = trim(
                    (string) ($change['field'] ?? '')
                );

                if ($field !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                if (! is_array($value)) {
                    continue;
                }

                $phoneNumberId = trim(
                    (string) data_get(
                        $value,
                        'metadata.phone_number_id',
                        ''
                    )
                );

                $displayPhoneNumber =
                    $this->maskPhone(
                        (string) data_get(
                            $value,
                            'metadata.display_phone_number',
                            ''
                        )
                    );

                $this->recordInboundMessages(
                    $value['messages'] ?? [],
                    $phoneNumberId,
                    $displayPhoneNumber
                );

                $this->recordStatuses(
                    $value['statuses'] ?? [],
                    $phoneNumberId,
                    $displayPhoneNumber
                );
            }
        }
    }

    private function recordInboundMessages(
        mixed $messages,
        string $phoneNumberId,
        string $displayPhoneNumber
    ): void {
        if (! is_array($messages)) {
            return;
        }

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            Log::info(
                'TradeX Meta WhatsApp inbound message received.',
                [
                    'message_id' => trim(
                        (string) ($message['id'] ?? '')
                    ),
                    'from' => $this->maskPhone(
                        (string) ($message['from'] ?? '')
                    ),
                    'type' => trim(
                        (string) ($message['type'] ?? '')
                    ),
                    'timestamp' => trim(
                        (string) ($message['timestamp'] ?? '')
                    ),
                    'phone_number_id' => $phoneNumberId,
                    'display_phone_number' =>
                        $displayPhoneNumber,
                ]
            );
        }
    }

    private function recordStatuses(
        mixed $statuses,
        string $phoneNumberId,
        string $displayPhoneNumber
    ): void {
        if (! is_array($statuses)) {
            return;
        }

        foreach ($statuses as $status) {
            if (! is_array($status)) {
                continue;
            }

            $errorCodes = [];
            $errors = $status['errors'] ?? [];

            if (is_array($errors)) {
                foreach ($errors as $error) {
                    if (! is_array($error)) {
                        continue;
                    }

                    $code = $error['code'] ?? null;

                    if (
                        is_int($code)
                        || is_string($code)
                    ) {
                        $errorCodes[] = $code;
                    }
                }
            }

            Log::info(
                'TradeX Meta WhatsApp delivery status received.',
                [
                    'message_id' => trim(
                        (string) ($status['id'] ?? '')
                    ),
                    'status' => trim(
                        (string) ($status['status'] ?? '')
                    ),
                    'recipient_id' =>
                        $this->maskPhone(
                            (string) (
                                $status['recipient_id']
                                ?? ''
                            )
                        ),
                    'timestamp' => trim(
                        (string) ($status['timestamp'] ?? '')
                    ),
                    'phone_number_id' => $phoneNumberId,
                    'display_phone_number' =>
                        $displayPhoneNumber,
                    'error_codes' => $errorCodes,
                ]
            );
        }
    }

    private function maskPhone(
        string $phone
    ): string {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        if (mb_strlen($phone) <= 7) {
            return '***';
        }

        return mb_substr(
            $phone,
            0,
            4
        )
            .'***'
            .mb_substr(
                $phone,
                -3
            );
    }
}
