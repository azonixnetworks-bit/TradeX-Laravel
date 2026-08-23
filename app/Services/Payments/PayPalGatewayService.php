<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayPalGatewayService
{
    public function createOrder(
        PaymentOrder $order
    ): array {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' =>
                        $order->uuid,
                    'custom_id' =>
                        $order->uuid,
                    'description' =>
                        (string) config(
                            'tradex_payments.product.education_premium.name',
                            'TradeX Education Premium'
                        ),
                    'amount' => [
                        'currency_code' =>
                            $order->currency,
                        'value' =>
                            $this->minorToDecimal(
                                (int) $order->amount_minor,
                                2
                            ),
                    ],
                ],
            ],
        ];

        $returnUrl = trim(
            (string) config(
                'tradex_payments.paypal.return_url'
            )
        );

        $cancelUrl = trim(
            (string) config(
                'tradex_payments.paypal.cancel_url'
            )
        );

        if (
            $returnUrl !== ''
            || $cancelUrl !== ''
        ) {
            $payload[
                'application_context'
            ] = array_filter([
                'return_url' =>
                    $returnUrl !== ''
                        ? $returnUrl
                        : null,
                'cancel_url' =>
                    $cancelUrl !== ''
                        ? $cancelUrl
                        : null,
                'user_action' =>
                    'PAY_NOW',
            ]);
        }

        $response = $this->api()
            ->withHeaders([
                'PayPal-Request-Id' =>
                    'tradex-'.$order->uuid,
                'Prefer' =>
                    'return=representation',
            ])
            ->post(
                '/v2/checkout/orders',
                $payload
            );

        $data = $this->jsonOrFail(
            $response,
            'PAYPAL_ORDER_CREATE_FAILED'
        );

        $providerId = trim(
            (string) (
                $data['id']
                ?? ''
            )
        );

        $approvalUrl = collect(
            $data['links']
            ?? []
        )->first(
            fn (
                mixed $link
            ): bool =>
                is_array($link)
                && (
                    $link['rel']
                    ?? null
                ) === 'approve'
        );

        $approvalHref =
            is_array($approvalUrl)
                ? trim(
                    (string) (
                        $approvalUrl['href']
                        ?? ''
                    )
                )
                : '';

        if (
            $providerId === ''
            || $approvalHref === ''
        ) {
            throw new AuthApiException(
                502,
                'PAYPAL_ORDER_RESPONSE_INVALID',
                'PayPal did not return a usable checkout order.'
            );
        }

        return [
            'provider_order_id' =>
                $providerId,
            'approval_url' =>
                $approvalHref,
            'provider_status' =>
                (string) (
                    $data['status']
                    ?? ''
                ),
        ];
    }

    public function captureOrder(
        PaymentOrder $order
    ): array {
        $providerReference = trim(
            (string) $order
                ->provider_reference
        );

        if ($providerReference === '') {
            throw new AuthApiException(
                409,
                'PAYPAL_ORDER_REFERENCE_MISSING',
                'The PayPal order reference is unavailable.'
            );
        }

        $response = $this->api()
            ->withHeaders([
                'PayPal-Request-Id' =>
                    'tradex-capture-'
                    .$order->uuid,
                'Prefer' =>
                    'return=representation',
            ])
            ->post(
                '/v2/checkout/orders/'
                .rawurlencode(
                    $providerReference
                )
                .'/capture'
            );

        return $this->jsonOrFail(
            $response,
            'PAYPAL_CAPTURE_FAILED'
        );
    }

    public function verifyWebhook(
        Request $request
    ): bool {
        $webhookId = trim(
            (string) config(
                'tradex_payments.paypal.webhook_id'
            )
        );

        if ($webhookId === '') {
            throw new AuthApiException(
                503,
                'PAYPAL_WEBHOOK_NOT_CONFIGURED',
                'PayPal webhook verification is not configured.'
            );
        }

        $headers = [
            'auth_algo' =>
                $request->header(
                    'PAYPAL-AUTH-ALGO'
                ),
            'cert_url' =>
                $request->header(
                    'PAYPAL-CERT-URL'
                ),
            'transmission_id' =>
                $request->header(
                    'PAYPAL-TRANSMISSION-ID'
                ),
            'transmission_sig' =>
                $request->header(
                    'PAYPAL-TRANSMISSION-SIG'
                ),
            'transmission_time' =>
                $request->header(
                    'PAYPAL-TRANSMISSION-TIME'
                ),
        ];

        if (
            collect(
                $headers
            )->contains(
                fn (
                    mixed $value
                ): bool =>
                    blank($value)
            )
        ) {
            return false;
        }

        $event = $request->json()->all();

        if ($event === []) {
            return false;
        }

        $response = $this->api()
            ->post(
                '/v1/notifications/verify-webhook-signature',
                [
                    ...$headers,
                    'webhook_id' =>
                        $webhookId,
                    'webhook_event' =>
                        $event,
                ]
            );

        $data = $this->jsonOrFail(
            $response,
            'PAYPAL_WEBHOOK_VERIFY_FAILED'
        );

        return strtoupper(
            (string) (
                $data[
                    'verification_status'
                ]
                ?? ''
            )
        ) === 'SUCCESS';
    }

    private function api(): PendingRequest
    {
        $clientId = trim(
            (string) config(
                'tradex_payments.paypal.client_id'
            )
        );

        $clientSecret = trim(
            (string) config(
                'tradex_payments.paypal.client_secret'
            )
        );

        if (
            $clientId === ''
            || $clientSecret === ''
        ) {
            throw new AuthApiException(
                503,
                'PAYPAL_NOT_CONFIGURED',
                'PayPal checkout is not configured.'
            );
        }

        $tokenResponse = Http::baseUrl(
            $this->baseUrl()
        )
            ->timeout(
                $this->timeout()
            )
            ->withBasicAuth(
                $clientId,
                $clientSecret
            )
            ->asForm()
            ->post(
                '/v1/oauth2/token',
                [
                    'grant_type' =>
                        'client_credentials',
                ]
            );

        $tokenData = $this->jsonOrFail(
            $tokenResponse,
            'PAYPAL_AUTH_FAILED'
        );

        $accessToken = trim(
            (string) (
                $tokenData[
                    'access_token'
                ]
                ?? ''
            )
        );

        if ($accessToken === '') {
            throw new AuthApiException(
                503,
                'PAYPAL_AUTH_FAILED',
                'PayPal authentication failed.'
            );
        }

        return Http::baseUrl(
            $this->baseUrl()
        )
            ->timeout(
                $this->timeout()
            )
            ->acceptJson()
            ->asJson()
            ->withToken(
                $accessToken
            );
    }

    private function baseUrl(): string
    {
        $environment = strtolower(
            trim(
                (string) config(
                    'tradex_payments.paypal.environment',
                    'sandbox'
                )
            )
        );

        return rtrim(
            (string) (
                $environment === 'live'
                    ? config(
                        'tradex_payments.paypal.live_base_url'
                    )
                    : config(
                        'tradex_payments.paypal.sandbox_base_url'
                    )
            ),
            '/'
        );
    }

    private function timeout(): int
    {
        return max(
            3,
            (int) config(
                'tradex_payments.paypal.timeout_seconds',
                15
            )
        );
    }

    private function jsonOrFail(
        Response $response,
        string $code
    ): array {
        if (! $response->successful()) {
            throw new AuthApiException(
                502,
                $code,
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new AuthApiException(
                502,
                $code,
                'PayPal returned an invalid response.'
            );
        }

        return $json;
    }

    private function minorToDecimal(
        int $minor,
        int $scale
    ): string {
        $factor = 10 ** $scale;

        return intdiv(
            $minor,
            $factor
        )
            .'.'
            .str_pad(
                (string) (
                    $minor
                    % $factor
                ),
                $scale,
                '0',
                STR_PAD_LEFT
            );
    }
}
