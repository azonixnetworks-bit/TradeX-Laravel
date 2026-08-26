<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API-17 — Legal Policy Runtime
    |--------------------------------------------------------------------------
    |
    | API-17 deliberately provides an immutable customer acceptance evidence
    | layer. Cross-module enforcement remains false until a later explicit
    | integration chooses which policy audiences gate which business actions.
    |
    */

    'runtime_state' => 'WIRED_EVIDENCE_ONLY',
    'cross_module_enforcement' => false,

    'max_user_agent_length' => 1024,

    'rate_limits' => [
        'public_read_per_minute' => 120,
        'auth_read_per_minute' => 120,
        'accept_per_minute' => 10,
    ],
];
