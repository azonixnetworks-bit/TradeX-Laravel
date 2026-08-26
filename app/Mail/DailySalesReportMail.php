<?php

namespace App\Mail;

use App\Services\Payments\PaymentMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySalesReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $report,
        public readonly string $reportDate,
        public readonly string $reportTimezone
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TradeX Daily Sales Report — '.$this->reportDate
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.finance.daily-sales-report',
            with: [
                'report' => $this->report,
                'reportDate' => $this->reportDate,
                'reportTimezone' => $this->reportTimezone,
                'money' => app(PaymentMoney::class),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
