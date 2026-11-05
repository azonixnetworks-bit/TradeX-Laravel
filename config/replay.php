<?php

return [
    // Prefer a private file *outside* Laravel's web/public directory.
    // The matching public key is loaded by the independent Python Replay API.
    'jwt_private_key_path' => env('REPLAY_JWT_PRIVATE_KEY_PATH', ''),
    'jwt_issuer' => env('REPLAY_JWT_ISSUER', 'tradex-laravel'),
    'jwt_audience' => env('REPLAY_JWT_AUDIENCE', 'tradex-replay'),
    'token_ttl_seconds' => (int) env('REPLAY_JWT_TTL_SECONDS', 120),
];
