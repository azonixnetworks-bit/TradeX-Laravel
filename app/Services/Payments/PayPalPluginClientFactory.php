<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalPluginClientFactory
{
    public function authenticated(): PayPalClient
    {
        try {
            $provider = new PayPalClient(
                (array) config('paypal')
            );

            $token = $provider->getAccessToken();
        } catch (\Throwable $exception) {
            throw new AuthApiException(
                502,
                'PAYPAL_AUTH_FAILED',
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        if (
            ! is_array($token)
            || blank($token['access_token'] ?? null)
        ) {
            throw new AuthApiException(
                502,
                'PAYPAL_AUTH_FAILED',
                'The PayPal payment service is temporarily unavailable.'
            );
        }

        return $provider;
    }
}
