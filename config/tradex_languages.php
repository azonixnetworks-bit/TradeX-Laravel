<?php

return [
    'source_language' => 'en',

    'languages' => [
        'en' => [
            'english_name' => 'English',
            'native_name' => 'English',
            'direction' => 'LTR',
        ],

        'ta' => [
            'english_name' => 'Tamil',
            'native_name' => 'தமிழ்',
            'direction' => 'LTR',
        ],

        'si' => [
            'english_name' => 'Sinhala',
            'native_name' => 'සිංහල',
            'direction' => 'LTR',
        ],

        'hi' => [
            'english_name' => 'Hindi',
            'native_name' => 'हिन्दी',
            'direction' => 'LTR',
        ],

        'ml' => [
            'english_name' => 'Malayalam',
            'native_name' => 'മലയാളം',
            'direction' => 'LTR',
        ],

        'te' => [
            'english_name' => 'Telugu',
            'native_name' => 'తెలుగు',
            'direction' => 'LTR',
        ],

        'ur' => [
            'english_name' => 'Urdu',
            'native_name' => 'اردو',
            'direction' => 'RTL',
        ],
    ],

    'groups' => [
        'GENERAL' => 'General',
        'APP_UI' => 'App UI',
        'AUTH' => 'Authentication',
        'EDUCATION_UI' => 'Education UI',
        'PAYMENTS_UI' => 'Payments UI',
        'AFFILIATE_UI' => 'Affiliate UI',
        'NOTIFICATIONS_UI' => 'Notifications UI',
        'LEGAL_UI' => 'Legal UI',
    ],

    'runtime_translation_api' => 'NOT_WIRED',
    'education_content_localization' => 'WIRED_REVIEWED_RUNTIME',

    'source_text_max_characters' => 10000,
    'translation_text_max_characters' => 10000,
];
