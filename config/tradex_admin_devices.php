<?php

return [
    'enabled' => (bool) env('TRADEX_ADMIN_DEVICE_SECURITY_ENABLED', true),
    // Security alerts use a separate approved utility template, never the OTP template.
    'whatsapp_recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRADEX_ADMIN_SECURITY_WHATSAPP_RECIPIENTS', ''))))),
    'whatsapp_template' => env('TRADEX_ADMIN_SECURITY_WHATSAPP_TEMPLATE', ''),
    'whatsapp_language' => env('TRADEX_ADMIN_SECURITY_WHATSAPP_LANGUAGE', 'en_US'),
];
