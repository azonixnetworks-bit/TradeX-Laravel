<?php

namespace App\Console\Commands;

use App\Services\Auth\Registration\WhatsAppMetaConfigurationSafety;
use Illuminate\Console\Command;

class WhatsAppOtpCheckCommand extends Command
{
    protected $signature = 'tradex:whatsapp-otp-check';

    protected $description = 'Check WhatsApp OTP configuration without sending messages or exposing credentials';

    public function handle(WhatsAppMetaConfigurationSafety $configuration): int
    {
        $checks = [
            'meta_driver_selected' => config('tradex_registration.delivery.whatsapp_driver') === 'meta',
            ...$configuration->checks(),
        ];

        $this->table(['Check', 'Result'], collect($checks)->map(
            fn (bool $valid, string $name) => [$name, $valid ? 'PASS' : 'MISSING / INVALID']
        )->values()->all());

        if (in_array(false, $checks, true)) {
            $this->error('WhatsApp OTP is not ready. Follow docs/WHATSAPP-OTP-SETUP.md, then run php artisan config:cache.');

            return self::FAILURE;
        }

        $this->info('Local configuration checks passed. No message was sent. Token validity, template approval and delivery still require a real onboarding verification.');

        return self::SUCCESS;
    }
}
