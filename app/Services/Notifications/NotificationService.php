<?php

namespace App\Services\Notifications;

use App\Enums\UserStatus;
use App\Mail\AdminCredentialsMail;
use App\Mail\SecurityAlertMail;
use App\Mail\VoterOtpMail;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Mail;

/**
 * All outbound notifications go by email (MAIL_MAILER). Voters receive their
 * one-time codes; administrators receive account credentials and urgent
 * security alerts.
 */
class NotificationService
{
    public const CHANNEL_EMAIL = 'EMAIL';

    /** Sent synchronously from the encrypted SendVoterOtp job with automatic fallback to Resend. */
    public function sendOtp(string $destination, string $code, int $ttlMinutes): void
    {
        try {
            Mail::to($destination)->send(new VoterOtpMail($code, $ttlMinutes));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Primary mailer failed to send OTP to {$destination}: {$e->getMessage()}. Attempting Resend fallback.");

            // If Resend is configured, immediately failover to Resend
            if (config('services.resend.key') || env('RESEND_API_KEY')) {
                Mail::mailer('resend')->to($destination)->send(new VoterOtpMail($code, $ttlMinutes));
                \Illuminate\Support\Facades\Log::info("Successfully sent OTP to {$destination} via Resend fallback.");
                return;
            }

            throw $e;
        }
    }

    /** Temporary password for a new account or after a reset; queued and encrypted at rest. */
    public function sendAdminCredentials(User $user, string $temporaryPassword, bool $isNewAccount): void
    {
        Mail::to($user->email)->queue(new AdminCredentialsMail($user->name, $user->email, $temporaryPassword, $isNewAccount));
    }

    /** Notify every active administrator who can review security alerts. */
    public function sendSecurityAlert(SecurityAlert $alert): void
    {
        $recipients = User::query()->where('status', UserStatus::ACTIVE->value)->get()
            ->filter(fn (User $u) => $u->hasPermission(Permissions::VIEW_AUDIT_LOGS));

        foreach ($recipients as $user) {
            Mail::to($user->email)->queue(new SecurityAlertMail(
                $alert->type,
                $alert->severity->value,
                $alert->description,
                display_time($alert->last_seen_at ?? now(), 'j M Y, H:i'),
            ));
        }
    }
}
