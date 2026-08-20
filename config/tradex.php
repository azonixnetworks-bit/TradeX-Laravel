<?php

return [

    'super_admin' => [

        'name' => env(
            'SUPER_ADMIN_NAME',
            'TradeX Super Admin'
        ),

        'email' => env(
            'SUPER_ADMIN_EMAIL',
            'admin@tradex.local'
        ),

        'password' => env(
            'SUPER_ADMIN_PASSWORD'
        ),

    ],

];