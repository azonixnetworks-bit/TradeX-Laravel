<?php

return [
    'rate_limits' => [
        'read_per_minute' =>
            (int) env(
                'TRADEX_DASHBOARD_READ_PER_MINUTE',
                120
            ),
    ],
];
