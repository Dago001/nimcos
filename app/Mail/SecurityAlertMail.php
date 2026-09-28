<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $description,
        public readonly string $seenAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->severity}] NIMCOS E-Voting security alert");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.security-alert', with: ['alertsUrl' => route('admin.alerts.index')]);
    }
}
