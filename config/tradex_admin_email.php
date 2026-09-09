<?php

return [
    'enabled' => env('TRADEX_ADMIN_EMAIL_ENABLED', true),
    'mailer' => env('TRADEX_ADMIN_EMAIL_MAILER', env('MAIL_MAILER', 'log')),
    'login_url' => env(
        'TRADEX_ADMIN_LOGIN_URL',
        rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/admin/login'
    ),
];
