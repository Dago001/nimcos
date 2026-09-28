<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Temporary password for a new or reset administrator account. The payload
 * contains the password, so the queued job is encrypted at rest.
 */
class AdminCredentialsMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $temporaryPassword,
        public readonly bool $isNewAccount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isNewAccount
            ? 'Your NIMCOS E-Voting administrator account'
            : 'Your NIMCOS E-Voting password has been reset');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.admin-credentials', with: ['loginUrl' => route('admin.login')]);
    }
}
