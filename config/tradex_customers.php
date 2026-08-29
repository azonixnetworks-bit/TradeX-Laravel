<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin-created onboarding invitations
    |--------------------------------------------------------------------------
    |
    | An admin invitation is a pre-onboarding record. It never creates a
    | password, TOTP secret, premium entitlement, payment, or active login.
    | A cryptographically random activation token is delivered to the invited
    | customer; only the SHA-256 token fingerprint is stored by TradeX.
    |
    */
    'onboarding_invitation_days' =>
        (int) env('TRADEX_CUSTOMER_INVITATION_DAYS', 7),

    /*
     * Mobile/universal-link destination used by invitation email delivery.
     * The app/frontend reads invitation_id + token and exchanges them through
     * the secure invite-registration API. The raw token is never persisted.
     */
    'activation_url' =>
        env(
            'TRADEX_CUSTOMER_ACTIVATION_URL',
            rtrim(
                (string) env(
                    'APP_URL',
                    'http://localhost'
                ),
                '/'
            ).'/onboarding/activate'
        ),

    /*
     * Policies that an admin-invited customer must accept before the account
     * can be created and activated. Payment- or affiliate-specific policies
     * remain gated by their own modules and are intentionally excluded here.
     */
    'required_onboarding_legal_policies' => [
        'TERMS_OF_SERVICE',
        'PRIVACY_POLICY',
        'TRADING_RISK_DISCLOSURE',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recently joined segment
    |--------------------------------------------------------------------------
    */
    'recent_days' =>
        (int) env('TRADEX_CUSTOMER_RECENT_DAYS', 30),
];
