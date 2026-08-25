<?php

namespace App\Services\Kyc;

use App\Exceptions\KycApiException;

class KycIdentityHasher
{
    public function hash(
        string $identityNumber,
        string $countryCode,
        string $documentType
    ): string {
        $normalizedIdentity =
            $this->normalize(
                $identityNumber
            );

        $normalizedCountry =
            strtoupper(
                trim(
                    $countryCode
                )
            );

        $normalizedDocumentType =
            strtoupper(
                trim(
                    $documentType
                )
            );

        if (
            $normalizedCountry === ''
            || $normalizedDocumentType === ''
        ) {
            throw new KycApiException(
                422,
                'KYC_IDENTITY_NAMESPACE_INVALID',
                'The identity namespace is invalid.'
            );
        }

        $configuredKey =
            (string) config(
                'tradex_kyc.identity_hash_key',
                ''
            );

        if (
            blank(
                $configuredKey
            )
        ) {
            throw new KycApiException(
                500,
                'KYC_HASH_KEY_NOT_CONFIGURED',
                'KYC identity protection is not configured.'
            );
        }

        /*
        | Domain-separate the KYC HMAC from other application uses and include
        | country + document type so identical document-number strings issued
        | by unrelated identity systems do not create false conflicts.
        */
        $key = hash_hmac(
            'sha256',
            'tradex:kyc:identity:v1',
            $configuredKey,
            true
        );

        $message =
            $normalizedCountry
            .'|'
            .$normalizedDocumentType
            .'|'
            .$normalizedIdentity;

        return hash_hmac(
            'sha256',
            $message,
            $key
        );
    }

    public function normalize(
        string $identityNumber
    ): string {
        $value = strtoupper(
            trim(
                $identityNumber
            )
        );

        $normalized =
            preg_replace(
                '/[^A-Z0-9]/',
                '',
                $value
            );

        if (
            ! is_string(
                $normalized
            )
            || strlen(
                $normalized
            ) < 3
        ) {
            throw new KycApiException(
                422,
                'KYC_IDENTITY_NUMBER_INVALID',
                'The identity number is invalid.'
            );
        }

        return $normalized;
    }
}
