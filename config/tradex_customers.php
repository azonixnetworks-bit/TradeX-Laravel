<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin-created onboarding invitations
    |--------------------------------------------------------------------------
    |
    | An admin invitation is only a pre-onboarding record. It never creates a
    | password, TOTP secret, premium entitlement, payment, or active login.
    | The customer must still finish the normal TradeX registration flow.
    |
    */
    'onboarding_invitation_days' =>
        (int) env('TRADEX_CUSTOMER_INVITATION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Recently joined segment
    |--------------------------------------------------------------------------
    */
    'recent_days' =>
        (int) env('TRADEX_CUSTOMER_RECENT_DAYS', 30),
];
