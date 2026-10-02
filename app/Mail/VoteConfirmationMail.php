<?php

namespace App\Mail;

use App\Models\Election;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoteConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Election $election,
        public readonly string $voterName,
        public readonly string $reference,
        public readonly string $votedAtDisplay,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Voting Confirmation: '.$this->election->name);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.vote-confirmation');
    }
}
