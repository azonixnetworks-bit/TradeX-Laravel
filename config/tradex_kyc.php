<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KYC Storage
    |--------------------------------------------------------------------------
    |
    | KYC documents must remain on a non-public filesystem disk.
    | The existing TradeX "private" disk is the safe local default.
    |
    */

    'storage' => [
        'disk' => env(
            'TRADEX_KYC_STORAGE_DISK',
            'private'
        ),

        'directory' => env(
            'TRADEX_KYC_STORAGE_DIRECTORY',
            'kyc'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identity Number Hashing
    |--------------------------------------------------------------------------
    |
    | The raw identity number belongs only inside encrypted identity_data.
    | A normalized identity number may be HMAC-SHA256 hashed for duplicate
    | detection. Never store a plaintext identity number in the hash column.
    |
    */

    'identity_hash_key' => env(
        'TRADEX_KYC_IDENTITY_HASH_KEY',
        env('APP_KEY')
    ),

    /*
    |--------------------------------------------------------------------------
    | Persisted Statuses
    |--------------------------------------------------------------------------
    |
    | NOT_STARTED is a virtual API status when the user has no submission.
    |
    */

    'statuses' => [
        'not_started' => 'NOT_STARTED',
        'pending_review' => 'PENDING_REVIEW',
        'verified' => 'VERIFIED',
        'rejected' => 'REJECTED',
        'needs_resubmission' => 'NEEDS_RESUBMISSION',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Policy
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'max_kilobytes' => (int) env(
            'TRADEX_KYC_MAX_UPLOAD_KB',
            5120
        ),

        'identity_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ],

        'selfie_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Country Profiles
    |--------------------------------------------------------------------------
    |
    | These are TradeX product-level accepted document profiles, not a claim
    | that every listed document is sufficient for every legal/regulatory use.
    | Higher-risk services may require a separate compliance/provider review.
    |
    | India Aadhaar is intentionally not enabled in this v1 configuration.
    | Add it only after the required legal, privacy and operational controls
    | for the intended use case are confirmed.
    |
    */

    'countries' => [

        'LK' => [
            'name' => 'Sri Lanka',

            'required_identity_fields' => [
                'legal_name',
                'date_of_birth',
                'identity_number',
                'address',
            ],

            'document_types' => [
                'NATIONAL_ID' => [
                    'label' => 'National Identity Card',
                    'required_documents' => [
                        'IDENTITY_FRONT',
                        'IDENTITY_BACK',
                        'SELFIE',
                    ],
                ],

                'PASSPORT' => [
                    'label' => 'Passport',
                    'required_documents' => [
                        'IDENTITY_FRONT',
                        'SELFIE',
                    ],
                ],
            ],
        ],

        'IN' => [
            'name' => 'India',

            'required_identity_fields' => [
                'legal_name',
                'date_of_birth',
                'identity_number',
                'address',
            ],

            'document_types' => [
                'PASSPORT' => [
                    'label' => 'Passport',
                    'required_documents' => [
                        'IDENTITY_FRONT',
                        'SELFIE',
                    ],
                ],

                'DRIVING_LICENCE' => [
                    'label' => 'Driving Licence',
                    'required_documents' => [
                        'IDENTITY_FRONT',
                        'IDENTITY_BACK',
                        'SELFIE',
                    ],
                ],

                'PAN_CARD' => [
                    'label' => 'PAN Card',
                    'required_documents' => [
                        'IDENTITY_FRONT',
                        'SELFIE',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate-Limit Configuration
    |--------------------------------------------------------------------------
    |
    | AppServiceProvider wiring is added in the next API-13 step together
    | with the KYC API routes/controllers.
    |
    */

    'rate_limits' => [
        'read_per_minute' => 60,
        'submit_per_minute' => 3,
        'submit_per_hour' => 10,
        'document_per_minute' => 10,
    ],
];
