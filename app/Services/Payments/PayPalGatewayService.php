<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalGatewayService
{
    public function __construct(
        private readonly PayPalPluginClientFactory
            $pluginFactory,
        private readonly PayPalConfigurationSafety
            $configuration
    ) {
    }

    public function createOrder(
        PaymentOrder $order
    ): array {
        $this->configuration
            ->assertConfigured();

        if ($this->usesPlugin()) {
            return $this->createOrderWithPlugin(
                $order
            );
        }

        return $this->createOrderWithHttp(
            $order
        );
    }

    public function captureOrder(
        PaymentOrder $order
    ): array {
        $this->configuration
            ->assertWebhookConfigured();

        if ($this->usesPlugin()) {
            return $this->captureOrderWithPlugin(
                $order
            );
        }

        return $this->captureOrderWithHttp(
            $order
        );
    }

    public function verifyWebhook(
        Request $request
    ): bool {
        $this->configuration
            ->assertWebhookConfigured();

        /*
         * Always use PayPal's official verification endpoint with the exact
         * raw webhook event bytes. This keeps verification behavior identical
         * for the HTTP and srmklive checkout drivers.
         */
        return $this->verifyWebhookWithHttp(
            $request
        );
    }

    private function createOrderWithPlugin(
        PaymentOrder $order
    ): array {
        $provider = $this->pluginFactory
            ->authenticated();

        try {
            $provider
                ->withIdempotencyKey(
                    'tradex-'.$order->uuid
                )
                ->setRequestHeader(
                    'Prefer',
                    'return=representation'
                );

            $data = $provider->createOrder(
                $this->orderPayload(
                    $order
                )
            );
        } catch (\Throwable $exception) {
            throw new AuthApiException(
                502,
                'PAYPAL_ORDER_CREATE_FAILED',
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        $data = $this->pluginArrayOrFail(
            $data,
            'PAYPAL_ORDER_CREATE_FAILED'
        );

        return $this->createdOrderResult(
            $data
        );
    }

    private function createOrderWithHttp(
        PaymentOrder $order
    ): array {
        $response = $this->api()
            ->withHeaders([
                'PayPal-Request-Id' =>
                    'tradex-'.$order->uuid,
                'Prefer' =>
                    'return=representation',
            ])
            ->post(
                '/v2/checkout/orders',
                $this->orderPayload(
                    $order
                )
            );

        $data = $this->jsonOrFail(
            $response,
            'PAYPAL_ORDER_CREATE_FAILED'
        );

        return $this->createdOrderResult(
            $data
        );
    }

    private function createdOrderResult(
        array $data
    ): array {
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

    private function captureOrderWithPlugin(
        PaymentOrder $order
    ): array {
        $providerReference = $this
            ->providerReference(
                $order
            );

        $provider = $this->pluginFactory
            ->authenticated();

        try {
            $provider
                ->withIdempotencyKey(
                    'tradex-capture-'
                    .$order->uuid
                )
                ->setRequestHeader(
                    'Prefer',
                    'return=representation'
                );

            $data = $provider
                ->capturePaymentOrder(
                    $providerReference
                );
        } catch (\Throwable $exception) {
            throw new AuthApiException(
                502,
                'PAYPAL_CAPTURE_FAILED',
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        return $this->pluginArrayOrFail(
            $data,
            'PAYPAL_CAPTURE_FAILED'
        );
    }

    private function captureOrderWithHttp(
        PaymentOrder $order
    ): array {
        $providerReference = $this
            ->providerReference(
                $order
            );

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

    private function providerReference(
        PaymentOrder $order
    ): string {
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

        return $providerReference;
    }

    private function verifyWebhookWithPlugin(
        Request $request
    ): bool {
        $webhookId = $this->webhookId();
        $headers = $this->webhookHeaders(
            $request
        );

        if ($headers === null) {
            return false;
        }

        $event = $request->json()->all();

        if ($event === []) {
            return false;
        }

        $provider = $this->pluginFactory
            ->authenticated();

        try {
            $result = $provider->verifyWebHook([
                ...$headers,
                'webhook_id' =>
                    $webhookId,
                'webhook_event' =>
                    $event,
            ]);
        } catch (\Throwable $exception) {
            throw new AuthApiException(
                502,
                'PAYPAL_WEBHOOK_VERIFY_FAILED',
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        $result = $this->pluginArrayOrFail(
            $result,
            'PAYPAL_WEBHOOK_VERIFY_FAILED'
        );

        return strtoupper(
            (string) (
                $result[
                    'verification_status'
                ]
                ?? ''
            )
        ) === 'SUCCESS';
    }

    private function verifyWebhookWithHttp(
        Request $request
    ): bool {
        $webhookId = $this->webhookId();
        $headers = $this->webhookHeaders(
            $request
        );

        if ($headers === null) {
            return false;
        }

        $rawEvent = (string) $request
            ->getContent();

        $decoded = json_decode(
            $rawEvent,
            true
        );

        if (
            ! is_array($decoded)
            || $decoded === []
        ) {
            return false;
        }

        /*
         * PayPal requires webhook_event to be posted back exactly as received.
         * Do not parse and re-encode the event. Only the wrapper fields are
         * encoded here; the original raw JSON bytes are appended untouched.
         */
        $metadataJson = json_encode(
            [
                ...$headers,
                'webhook_id' =>
                    $webhookId,
            ],
            JSON_THROW_ON_ERROR
        );

        $verificationBody =
            substr(
                $metadataJson,
                0,
                -1
            )
            .',"webhook_event":'
            .$rawEvent
            .'}';

        $response = $this->api()
            ->withBody(
                $verificationBody,
                'application/json'
            )
            ->post(
                '/v1/notifications/verify-webhook-signature'
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

    private function webhookId(): string
    {
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

        return $webhookId;
    }

    private function webhookHeaders(
        Request $request
    ): ?array {
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
            return null;
        }

        return $headers;
    }

    private function orderPayload(
        PaymentOrder $order
    ): array {
        $value = $this->minorToDecimal(
            (int) $order->amount_minor,
            2
        );

        $currency = strtoupper(
            (string) $order->currency
        );

        $productName = (string) config(
            'tradex_payments.product.education_premium.name',
            'TradeX Education Premium'
        );

        $productCode = (string) config(
            'tradex_payments.product.education_premium.code',
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM
        );

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' =>
                        $order->uuid,
                    'custom_id' =>
                        $order->uuid,
                    'description' =>
                        $productName,
                    'amount' => [
                        'currency_code' =>
                            $currency,
                        'value' =>
                            $value,
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' =>
                                    $currency,
                                'value' =>
                                    $value,
                            ],
                        ],
                    ],
                    'items' => [
                        [
                            'name' =>
                                $productName,
                            'sku' =>
                                $productCode,
                            'description' =>
                                'TradeX OS Premium Trading Education Course',
                            'quantity' =>
                                '1',
                            'category' =>
                                'DIGITAL_GOODS',
                            'unit_amount' => [
                                'currency_code' =>
                                    $currency,
                                'value' =>
                                    $value,
                            ],
                        ],
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' =>
                    'TradeX OS',
                'shipping_preference' =>
                    'NO_SHIPPING',
                'user_action' =>
                    'PAY_NOW',
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

        if ($returnUrl !== '') {
            $payload[
                'application_context'
            ][
                'return_url'
            ] = $returnUrl;
        }

        if ($cancelUrl !== '') {
            $payload[
                'application_context'
            ][
                'cancel_url'
            ] = $cancelUrl;
        }

        return $payload;
    }

    private function usesPlugin(): bool
    {
        return strtolower(
            trim(
                (string) config(
                    'tradex_payments.paypal.driver',
                    'http'
                )
            )
        ) === 'srmklive';
    }

    private function pluginArrayOrFail(
        mixed $payload,
        string $code
    ): array {
        if (
            ! is_array($payload)
            || isset($payload['error'])
        ) {
            throw new AuthApiException(
                502,
                $code,
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        return $payload;
    }

    private function api(): PendingRequest
    {
        $this->configuration
            ->assertApiConfigured();

        [
            $clientId,
            $clientSecret,
        ] = $this->credentials();

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

    private function credentials(): array
    {
        $driver = strtolower(
            trim(
                (string) config(
                    'tradex_payments.paypal.driver',
                    'http'
                )
            )
        );

        $environment = strtolower(
            trim(
                (string) config(
                    'tradex_payments.paypal.environment',
                    'sandbox'
                )
            )
        );

        if ($driver === 'srmklive') {
            return [
                trim((string) config(
                    'paypal.'
                    .$environment
                    .'.client_id'
                )),
                trim((string) config(
                    'paypal.'
                    .$environment
                    .'.client_secret'
                )),
            ];
        }

        return [
            trim((string) config(
                'tradex_payments.paypal.client_id'
            )),
            trim((string) config(
                'tradex_payments.paypal.client_secret'
            )),
        ];
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
