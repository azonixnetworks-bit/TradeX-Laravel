<?php

return [
    'enabled' => (bool) env('TRADEX_ADMIN_DEVICE_SECURITY_ENABLED', true),
    // The configured Super Admin account may use any mix of five browsers/devices.
    'super_admin_total_limit' => max(1, (int) env('TRADEX_SUPER_ADMIN_DEVICE_LIMIT', 5)),
    // Delegated admins receive two desktop slots and one mobile slot.
    'admin_desktop_limit' => max(0, (int) env('TRADEX_ADMIN_DESKTOP_DEVICE_LIMIT', 2)),
    'admin_mobile_limit' => max(0, (int) env('TRADEX_ADMIN_MOBILE_DEVICE_LIMIT', 1)),
    // Security alerts use a separate approved utility template, never the OTP template.
    'whatsapp_recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRADEX_ADMIN_SECURITY_WHATSAPP_RECIPIENTS', ''))))),
    'whatsapp_template' => env('TRADEX_ADMIN_SECURITY_WHATSAPP_TEMPLATE', ''),
    'whatsapp_language' => env('TRADEX_ADMIN_SECURITY_WHATSAPP_LANGUAGE', 'en_US'),
];
