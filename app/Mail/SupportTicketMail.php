<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $template, public readonly array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->template) {
            'ticket-received' => 'TradeX support ticket received',
            'admin-reply' => 'TradeX support replied to your ticket',
            'new-ticket-queue' => 'New TradeX support ticket',
            'customer-reply' => 'Customer replied to a TradeX support ticket',
            'ticket-assigned' => 'Your TradeX support ticket is assigned',
            'ticket-status' => 'TradeX support ticket status updated',
            'sla-breach' => 'TradeX support SLA requires attention',
            'daily-summary' => 'TradeX daily support summary',
            default => 'TradeX Support update',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support.ticket', text: 'mail.support.ticket-text');
    }
}
