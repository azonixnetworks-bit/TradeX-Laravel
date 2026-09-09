<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminSecurityMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $eventType,
        public readonly string $adminName,
        public readonly array $context = []
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('mail.admin.security')
            ->with([
                'subjectLine' => $this->subjectLine,
                'eventType' => $this->eventType,
                'adminName' => $this->adminName,
                'context' => $this->context,
                'loginUrl' => (string) config(
                    'tradex_admin_email.login_url'
                ),
            ]);
    }
}
