<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WhatsAppSecurityAlertCheckCommand extends Command
{
    protected $signature = 'tradex:whatsapp-security-alert-check';

    protected $description = 'Check admin WhatsApp alert settings offline without exposing credentials or sending messages';

    public function handle(): int
    {
        $meta = config('tradex_registration.delivery.meta', []);
        $recipients = config('tradex_admin_devices.whatsapp_recipients', []);
        $checks = [
            'recipients_configured' => count($recipients) > 0,
            'recipient_numbers_valid' => count($recipients) > 0 && collect($recipients)->every(fn ($number) => preg_match('/^\+?[1-9][0-9]{6,14}$/D', $number) === 1),
            'graph_version_valid' => preg_match('/^v\d+\.\d+$/D', (string) ($meta['graph_version'] ?? '')) === 1,
            'sender_phone_number_id_valid' => preg_match('/^\d+$/D', (string) ($meta['phone_number_id'] ?? '')) === 1,
            'access_token_configured' => ! empty($meta['access_token']),
            'security_template_name_valid' => preg_match('/^[a-z0-9_]{1,512}$/D', (string) config('tradex_admin_devices.whatsapp_template')) === 1,
            'template_language_valid' => preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', (string) config('tradex_admin_devices.whatsapp_language')) === 1,
        ];
        $this->table(['Check', 'Result'], collect($checks)->map(fn ($valid, $name) => [$name, $valid ? 'PASS' : 'MISSING / INVALID'])->values()->all());
        if (in_array(false, $checks, true)) {
            $this->error('Admin WhatsApp alerts are not configured. See docs/ADMIN-DEVICE-SECURITY.md.');

            return self::FAILURE;
        }
        $this->info('Configuration format checks passed. No message was sent. Meta token authorization, template approval and recipient delivery still need verification.');

        return self::SUCCESS;
    }
}
