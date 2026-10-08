<?php

namespace App\Jobs;

use App\Mail\VoteConfirmationMail;
use App\Models\Election;
use App\Models\Voter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendVoteConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $voterId,
        public readonly string $electionId,
        public readonly string $reference,
        public readonly string $votedAtDisplay,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $voter = Voter::query()->find($this->voterId);
        if (! $voter || ! $voter->email) {
            return;
        }

        $election = Election::query()->find($this->electionId);
        if (! $election) {
            return;
        }

        $voterName = trim($voter->first_name.' '.$voter->surname);

        $mailable = new VoteConfirmationMail(
            election: $election,
            voterName: $voterName,
            reference: $this->reference,
            votedAtDisplay: $this->votedAtDisplay,
        );

        try {
            Mail::to($voter->email)->send($mailable);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Primary mailer failed to send Vote Confirmation email to {$voter->email}: {$e->getMessage()}. Attempting Resend fallback.");

            if (config('services.resend.key') || env('RESEND_API_KEY')) {
                Mail::mailer('resend_smtp')->to($voter->email)->send($mailable);
                \Illuminate\Support\Facades\Log::info("Successfully sent Vote Confirmation email to {$voter->email} via Resend fallback.");
                return;
            }

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        // Failures logged silently in queue failure logs without exposing secrets
    }
}
