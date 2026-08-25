<?php

namespace App\Services\Kyc;

use App\Exceptions\KycApiException;

class KycCountryPolicy
{
    public function country(
        string $countryCode
    ): array {
        $code = strtoupper(
            trim($countryCode)
        );

        $profile = config(
            'tradex_kyc.countries.'.$code
        );

        if (! is_array($profile)) {
            throw new KycApiException(
                422,
                'KYC_COUNTRY_NOT_SUPPORTED',
                'KYC is not currently available for this account country.'
            );
        }

        return $profile;
    }

    public function document(
        string $countryCode,
        string $documentType
    ): array {
        $country = $this->country(
            $countryCode
        );

        $type = strtoupper(
            trim($documentType)
        );

        $profile = data_get(
            $country,
            'document_types.'.$type
        );

        if (! is_array($profile)) {
            throw new KycApiException(
                422,
                'KYC_DOCUMENT_TYPE_NOT_SUPPORTED',
                'The selected identity document type is not supported for this account country.'
            );
        }

        return $profile;
    }

    public function requiredIdentityFields(
        string $countryCode
    ): array {
        return array_values(
            array_filter(
                (array) data_get(
                    $this->country(
                        $countryCode
                    ),
                    'required_identity_fields',
                    []
                ),
                'is_string'
            )
        );
    }

    public function requiredDocuments(
        string $countryCode,
        string $documentType
    ): array {
        return array_values(
            array_filter(
                (array) data_get(
                    $this->document(
                        $countryCode,
                        $documentType
                    ),
                    'required_documents',
                    []
                ),
                'is_string'
            )
        );
    }

    public function identityMimeTypes(): array
    {
        return array_values(
            array_filter(
                (array) config(
                    'tradex_kyc.uploads.identity_mime_types',
                    []
                ),
                'is_string'
            )
        );
    }

    public function selfieMimeTypes(): array
    {
        return array_values(
            array_filter(
                (array) config(
                    'tradex_kyc.uploads.selfie_mime_types',
                    []
                ),
                'is_string'
            )
        );
    }

    public function maxUploadKilobytes(): int
    {
        return max(
            1,
            (int) config(
                'tradex_kyc.uploads.max_kilobytes',
                5120
            )
        );
    }
}
