<?php

namespace App\Http\Requests\Api\Kyc;

use App\Models\KycDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' =>
                strtoupper(
                    trim(
                        (string) $this->input(
                            'document_type',
                            ''
                        )
                    )
                ),

            'legal_name' =>
                trim(
                    (string) $this->input(
                        'legal_name',
                        ''
                    )
                ),

            'identity_number' =>
                strtoupper(
                    trim(
                        (string) $this->input(
                            'identity_number',
                            ''
                        )
                    )
                ),

            'address' =>
                trim(
                    (string) $this->input(
                        'address',
                        ''
                    )
                ),
        ]);
    }

    public function rules(): array
    {
        $maxKilobytes = max(
            1,
            (int) config(
                'tradex_kyc.uploads.max_kilobytes',
                5120
            )
        );

        $identityMimeTypes =
            implode(
                ',',
                (array) config(
                    'tradex_kyc.uploads.identity_mime_types',
                    [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ]
                )
            );

        $selfieMimeTypes =
            implode(
                ',',
                (array) config(
                    'tradex_kyc.uploads.selfie_mime_types',
                    [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ]
                )
            );

        return [
            'document_type' => [
                'required',
                'string',
                'max:40',
            ],

            'legal_name' => [
                'required',
                'string',
                'min:2',
                'max:191',
            ],

            'date_of_birth' => [
                'required',
                'date_format:Y-m-d',
                'before:today',
            ],

            'identity_number' => [
                'required',
                'string',
                'min:3',
                'max:100',
            ],

            'address' => [
                'required',
                'string',
                'min:3',
                'max:500',
            ],

            'identity_front' => [
                'nullable',
                'file',
                'mimetypes:'.$identityMimeTypes,
                'max:'.$maxKilobytes,
            ],

            'identity_back' => [
                'nullable',
                'file',
                'mimetypes:'.$identityMimeTypes,
                'max:'.$maxKilobytes,
            ],

            'selfie' => [
                'nullable',
                'file',
                'mimetypes:'.$selfieMimeTypes,
                'max:'.$maxKilobytes,
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $user = $this->user();

                $countryCode = strtoupper(
                    trim(
                        (string) (
                            $user?->country_code
                            ?? ''
                        )
                    )
                );

                $country = config(
                    'tradex_kyc.countries.'
                    .$countryCode
                );

                if (! is_array($country)) {
                    $validator
                        ->errors()
                        ->add(
                            'country_code',
                            'KYC is not currently available for this account country.'
                        );

                    return;
                }

                $documentType = strtoupper(
                    trim(
                        (string) $this->input(
                            'document_type',
                            ''
                        )
                    )
                );

                $documentProfile = data_get(
                    $country,
                    'document_types.'
                    .$documentType
                );

                if (
                    ! is_array(
                        $documentProfile
                    )
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'document_type',
                            'The selected identity document type is not supported for this account country.'
                        );

                    return;
                }

                $requiredDocuments =
                    (array) data_get(
                        $documentProfile,
                        'required_documents',
                        []
                    );

                foreach (
                    $requiredDocuments
                    as $kind
                ) {
                    $inputName =
                        $this->inputNameForKind(
                            (string) $kind
                        );

                    if (
                        $inputName === null
                    ) {
                        continue;
                    }

                    if (
                        ! $this->file(
                            $inputName
                        ) instanceof UploadedFile
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                $inputName,
                                'This KYC document is required.'
                            );
                    }
                }
            }
        );
    }

    public function identityData(): array
    {
        $validated = $this->validated();

        return [
            'legal_name' =>
                (string) $validated[
                    'legal_name'
                ],

            'date_of_birth' =>
                (string) $validated[
                    'date_of_birth'
                ],

            'identity_number' =>
                (string) $validated[
                    'identity_number'
                ],

            'address' =>
                (string) $validated[
                    'address'
                ],
        ];
    }

    public function documentType(): string
    {
        return strtoupper(
            trim(
                (string) $this->validated(
                    'document_type'
                )
            )
        );
    }

    public function documentFiles(): array
    {
        $files = [];

        $map = [
            'identity_front' =>
                KycDocument::KIND_IDENTITY_FRONT,

            'identity_back' =>
                KycDocument::KIND_IDENTITY_BACK,

            'selfie' =>
                KycDocument::KIND_SELFIE,
        ];

        foreach (
            $map as $inputName => $kind
        ) {
            $file = $this->file(
                $inputName
            );

            if (
                $file
                instanceof UploadedFile
            ) {
                $files[$kind] = $file;
            }
        }

        return $files;
    }

    private function inputNameForKind(
        string $kind
    ): ?string {
        return match (
            strtoupper(
                trim($kind)
            )
        ) {
            KycDocument::KIND_IDENTITY_FRONT =>
                'identity_front',

            KycDocument::KIND_IDENTITY_BACK =>
                'identity_back',

            KycDocument::KIND_SELFIE =>
                'selfie',

            default => null,
        };
    }
}
