<?php

namespace App\Services\Kyc;

use App\Exceptions\KycApiException;
use App\Models\KycSubmission;
use App\Models\User;

class KycQueryService
{
    public function __construct(
        private readonly KycCountryPolicy
            $policy
    ) {
    }

    public function latestSubmission(
        User $user
    ): ?KycSubmission {
        return KycSubmission::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->with([
                'documents',
            ])
            ->latest(
                'id'
            )
            ->first();
    }

    public function resolveOwnedSubmission(
        User $user,
        string $submissionUuid
    ): KycSubmission {
        $submission =
            KycSubmission::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'uuid',
                    $submissionUuid
                )
                ->with([
                    'documents',
                ])
                ->first();

        if (
            ! $submission
            instanceof KycSubmission
        ) {
            /*
            | Deliberately return the same response for "does not exist"
            | and "belongs to another user" to avoid IDOR information leaks.
            */
            throw new KycApiException(
                404,
                'KYC_SUBMISSION_NOT_FOUND',
                'The KYC submission could not be found.'
            );
        }

        return $submission;
    }

    public function requirements(
        User $user
    ): array {
        $countryCode = strtoupper(
            trim(
                (string) $user
                    ->country_code
            )
        );

        $country =
            $this->policy
                ->country(
                    $countryCode
                );

        $documentTypes = [];

        foreach (
            (array) data_get(
                $country,
                'document_types',
                []
            )
            as $code => $profile
        ) {
            if (! is_array($profile)) {
                continue;
            }

            $documentTypes[] = [
                'code' =>
                    strtoupper(
                        (string) $code
                    ),

                'label' =>
                    (string) data_get(
                        $profile,
                        'label',
                        $code
                    ),

                'required_documents' =>
                    array_values(
                        array_filter(
                            (array) data_get(
                                $profile,
                                'required_documents',
                                []
                            ),
                            'is_string'
                        )
                    ),
            ];
        }

        return [
            'country_code' =>
                $countryCode,

            'country_name' =>
                (string) data_get(
                    $country,
                    'name',
                    $countryCode
                ),

            'required_identity_fields' =>
                $this->policy
                    ->requiredIdentityFields(
                        $countryCode
                    ),

            'document_types' =>
                $documentTypes,

            'upload_policy' => [
                'max_kilobytes' =>
                    $this->policy
                        ->maxUploadKilobytes(),

                'identity_mime_types' =>
                    $this->policy
                        ->identityMimeTypes(),

                'selfie_mime_types' =>
                    $this->policy
                        ->selfieMimeTypes(),
            ],
        ];
    }
}
