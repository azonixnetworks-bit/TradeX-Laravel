<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expire = (int) config('auth.passwords.admins.expire', 30);

        return (new MailMessage)
            ->subject('Reset your TradeX Admin password')
            ->greeting('TradeX Administration')
            ->line('A password reset was requested for your TradeX admin account.')
            ->action('RESET ADMIN PASSWORD', $url)
            ->line("This secure reset link expires in {$expire} minutes.")
            ->line('If you did not request this reset, no action is required. Your existing password and two-factor authentication remain unchanged.');
    }
}
