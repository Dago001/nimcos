<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VoterOtpMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly int $ttlMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your NIMCOS E-Voting verification code');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.voter-otp');
    }
}
