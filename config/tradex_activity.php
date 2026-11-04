<?php

return [
    // Enable only after the user_activity_events migration is applied.
    // Keep off by default on existing production installations.
    'enabled' => (bool) env('TRADEX_ACTIVITY_ENABLED', false),
    'retention_days' => 45,
    'max_batch_size' => 25,
];
