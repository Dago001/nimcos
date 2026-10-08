<?php

namespace App\Jobs;

use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers a voter OTP. The payload contains the plain code, so the job is
 * encrypted at rest in the jobs table (ShouldBeEncrypted) and is short-lived.
 */
class SendVoterOtp implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public function __construct(
        public readonly string $otpId,
        public readonly string $voterId,
        public readonly string $channel,
        public readonly string $destination,
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {
        $this->onQueue('otp');
    }

    public function handle(NotificationService $notifications, \App\Services\Notifications\TermiiSmsService $termii): void
    {
        $notifications->sendOtp($this->destination, $this->code, $this->ttlMinutes);

        // Dual delivery: also dispatch SMS if voter has registered phone number
        if ($termii->isEnabled()) {
            $voter = \App\Models\Voter::query()->find($this->voterId);
            if ($voter && $voter->phone) {
                $msg = "Your NIMCOS E-Voting verification code is: {$this->code}. It expires in {$this->ttlMinutes} minutes. Do not share this code.";
                $termii->send($voter->phone, $msg);
            }
        }
    }

    /** An OTP past its lifetime is useless; do not keep retrying. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes($this->ttlMinutes);
    }

    public function failed(?Throwable $e): void
    {
        app(AuditLogger::class)->failure(AuditAction::OTP_DELIVERY_FAILED, ['OtpVerification', $this->otpId], [
            'channel' => $this->channel,
            'error' => $e ? mb_substr($e->getMessage(), 0, 250) : null,
        ], ['type' => 'SYSTEM', 'id' => null, 'label' => 'queue']);
    }
}
