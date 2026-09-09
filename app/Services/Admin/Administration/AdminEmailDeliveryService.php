<?php

namespace App\Services\Admin\Administration;

use App\Mail\AdminSecurityMail;
use App\Models\AdminEmailDelivery;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AdminEmailDeliveryService
{
    /**
     * Dispatches one security email and records application-level delivery state.
     * SENT means the configured Laravel mail transport accepted the message.
     * It does not prove final inbox delivery unless a provider webhook updates
     * delivery_status in a later integration.
     */
    public function send(
        AdminUser $target,
        string $eventType,
        array $context = []
    ): AdminEmailDelivery {
        $eventType = strtoupper(trim($eventType));
        $mailer = trim((string) config(
            'tradex_admin_email.mailer',
            config('mail.default', 'log')
        ));

        if ($mailer === '') {
            $mailer = (string) config('mail.default', 'log');
        }

        $subject = $this->subjectFor($eventType);

        $delivery = AdminEmailDelivery::query()->create([
            'uuid' => Str::uuid()->toString(),
            'admin_user_id' => $target->getKey(),
            'event_type' => $eventType,
            'recipient_email' => strtolower(trim((string) $target->email)),
            'recipient_name' => trim((string) $target->name),
            'subject' => $subject,
            'mailer' => $mailer,
            'dispatch_status' => 'PENDING',
            'delivery_status' => 'UNCONFIRMED',
            'attempt_count' => 0,
            'context' => $this->safeContext($context),
        ]);

        if (! (bool) config('tradex_admin_email.enabled', true)) {
            $delivery->forceFill([
                'dispatch_status' => 'SKIPPED',
                'last_error_code' => 'ADMIN_EMAIL_DISABLED',
                'last_error_message' => 'Admin email delivery is disabled by configuration.',
            ])->save();

            return $delivery->fresh();
        }

        try {
            $delivery->increment('attempt_count');

            Mail::mailer($mailer)
                ->to($target->email, $target->name)
                ->send(new AdminSecurityMail(
                    subjectLine: $subject,
                    eventType: $eventType,
                    adminName: (string) $target->name,
                    context: $this->safeContext($context)
                ));

            $delivery->forceFill([
                'dispatch_status' => 'SENT',
                'delivery_status' => 'UNCONFIRMED',
                'sent_at' => now('UTC'),
                'failed_at' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $delivery->forceFill([
                'dispatch_status' => 'FAILED',
                'delivery_status' => 'UNCONFIRMED',
                'failed_at' => now('UTC'),
                'last_error_code' => class_basename($exception),
                'last_error_message' => 'Configured mail transport could not send this message.',
            ])->save();
        }

        return $delivery->fresh();
    }

    private function subjectFor(string $eventType): string
    {
        return match ($eventType) {
            'ADMIN_INVITATION' => 'Your TradeX Admin account is ready',
            'ROLES_UPDATED' => 'Your TradeX Admin roles were updated',
            'ACCOUNT_DISABLED' => 'Your TradeX Admin account was disabled',
            'ACCOUNT_ENABLED' => 'Your TradeX Admin account was enabled',
            'ACCOUNT_DELETED' => 'Your TradeX Admin account was removed',
            'PROFILE_UPDATED' => 'Your TradeX Admin profile was updated',
            default => 'TradeX Admin security notification',
        };
    }

    /** @return array<string, mixed> */
    private function safeContext(array $context): array
    {
        $roles = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            (array) ($context['roles'] ?? [])
        )));

        $reason = trim((string) ($context['reason'] ?? ''));

        return [
            'roles' => array_slice($roles, 0, 25),
            'reason' => $reason !== ''
                ? Str::limit($reason, 500, '')
                : null,
        ];
    }
}
