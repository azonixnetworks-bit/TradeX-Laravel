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
     * The app/frontend must read invitation_id + token from this URL and use
     * them with the invite-registration API introduced in the next slice.
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
    |--------------------------------------------------------------------------
    | Recently joined segment
    |--------------------------------------------------------------------------
    */
    'recent_days' =>
        (int) env('TRADEX_CUSTOMER_RECENT_DAYS', 30),
];
