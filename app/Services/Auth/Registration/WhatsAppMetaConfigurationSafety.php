<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;

class WhatsAppMetaConfigurationSafety
{
    public function configured(): bool
    {
        return ! in_array(
            false,
            $this->checks(),
            true
        );
    }

    public function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new AuthApiException(
                503,
                'WHATSAPP_OTP_NOT_CONFIGURED',
                'WhatsApp OTP provider credentials are incomplete.'
            );
        }
    }

    public function checks(): array
    {
        $graphVersion =
            $this->graphVersion();

        $phoneNumberId =
            $this->phoneNumberId();

        $accessToken =
            $this->accessToken();

        $templateName =
            $this->templateName();

        $templateLanguage =
            $this->templateLanguage();

        $timeout =
            $this->timeoutSeconds();

        $connectTimeout =
            $this->connectTimeoutSeconds();

        return [
            'graph_version_valid' =>
                preg_match(
                    '/^v\d+\.\d+$/',
                    $graphVersion
                ) === 1,

            'phone_number_id_valid' =>
                preg_match(
                    '/^\d+$/',
                    $phoneNumberId
                ) === 1,

            'access_token_configured' =>
                $accessToken !== '',

            'template_name_valid' =>
                preg_match(
                    '/^[a-z0-9_]{1,512}$/',
                    $templateName
                ) === 1,

            'template_language_valid' =>
                preg_match(
                    '/^[a-z]{2,3}(?:_[A-Z]{2})?$/',
                    $templateLanguage
                ) === 1,

            'timeout_valid' =>
                $timeout >= 1
                && $timeout <= 30,

            'connect_timeout_valid' =>
                $connectTimeout >= 1
                && $connectTimeout <= 10
                && $connectTimeout <= $timeout,
        ];
    }

    public function endpoint(): string
    {
        return 'https://graph.facebook.com/'
            .$this->graphVersion()
            .'/'
            .$this->phoneNumberId()
            .'/messages';
    }

    public function graphVersion(): string
    {
        return trim(
            (string) config(
                'tradex_registration.delivery.meta.graph_version'
            )
        );
    }

    public function phoneNumberId(): string
    {
        return trim(
            (string) config(
                'tradex_registration.delivery.meta.phone_number_id'
            )
        );
    }

    public function accessToken(): string
    {
        return trim(
            (string) config(
                'tradex_registration.delivery.meta.access_token'
            )
        );
    }

    public function templateName(): string
    {
        return trim(
            (string) config(
                'tradex_registration.delivery.meta.otp_template_name'
            )
        );
    }

    public function templateLanguage(): string
    {
        return trim(
            (string) config(
                'tradex_registration.delivery.meta.otp_template_language',
                'en_US'
            )
        );
    }

    public function timeoutSeconds(): int
    {
        return (int) config(
            'tradex_registration.delivery.meta.timeout_seconds',
            10
        );
    }

    public function connectTimeoutSeconds(): int
    {
        return (int) config(
            'tradex_registration.delivery.meta.connect_timeout_seconds',
            5
        );
    }
}
