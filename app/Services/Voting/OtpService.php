<?php

namespace App\Services\Voting;

use App\Enums\AlertSeverity;
use App\Enums\AuditResult;
use App\Jobs\SendVoterOtp;
use App\Models\OtpVerification;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Security\SecurityAlertService;
use App\Services\Settings\SettingsService;
use App\Services\Voting\Exceptions\OtpException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Voter one-time passwords (spec §8).
 *  - 6 digits from random_int() (CSPRNG)
 *  - stored only as HMAC-SHA256(key = APP_KEY, id|code)
 *  - short expiry, attempt limit, single use
 *  - issuing a new code invalidates earlier ones
 */
class OtpService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{otp: OtpVerification, code: string}
     *
     * @throws OtpException
     */
    public function issue(Voter $voter, Request $request): array
    {
        [$channel, $destination, $masked] = $this->resolveChannel($voter);

        $cfg = config('nimcos.otp');
        $ttl = max(1, (int) $this->settings->get('otp_ttl_minutes'));
        $maxAttempts = max(1, (int) $this->settings->get('otp_max_attempts'));

        return DB::transaction(function () use ($voter, $request, $channel, $destination, $masked, $cfg, $ttl, $maxAttempts) {
            // Serialise OTP issuance per voter.
            Voter::query()->whereKey($voter->getKey())->lockForUpdate()->first();

            $latest = OtpVerification::query()->where('voter_id', $voter->getKey())->latest('created_at')->first();
            if ($latest && $latest->created_at->diffInSeconds(now()) < $cfg['resend_cooldown_seconds']) {
                $wait = (int) ceil($cfg['resend_cooldown_seconds'] - $latest->created_at->diffInSeconds(now()));
                throw OtpException::cooldown(max(1, $wait));
            }

            $recent = OtpVerification::query()
                ->where('voter_id', $voter->getKey())
                ->where('created_at', '>=', now()->subMinutes($cfg['request_window_minutes']))
                ->count();
            if ($recent >= $cfg['max_requests_per_window']) {
                $this->audit->denied(AuditAction::OTP_RATE_LIMITED, $voter, ['recent_requests' => $recent]);
                throw OtpException::tooManyRequests($cfg['request_window_minutes']);
            }

            OtpVerification::query()
                ->where('voter_id', $voter->getKey())
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $code = str_pad((string) random_int(0, 10 ** $cfg['length'] - 1), $cfg['length'], '0', STR_PAD_LEFT);

            $otp = new OtpVerification;
            $otp->forceFill([
                'id' => $otp->newUniqueId(),
                'voter_id' => $voter->getKey(),
                'channel' => $channel,
                'destination_masked' => $masked,
                'code_hash' => '',
                'expires_at' => now()->addMinutes($ttl),
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
            $otp->code_hash = self::hashCode($otp->id, $code);
            $otp->save();

            $this->audit->log(AuditAction::OTP_ISSUED, AuditResult::SUCCESS, $otp, ['channel' => $channel], $this->voterActor($voter));

            DB::afterCommit(fn () => SendVoterOtp::dispatch($otp->id, $voter->getKey(), $channel, $destination, $code, $ttl));

            return ['otp' => $otp, 'code' => $code];
        });
    }

    /**
     * Verify a code against a specific OTP record. Returns true exactly once per valid code.
     *
     * @throws OtpException
     */
    public function verify(string $otpId, string $code, Request $request): OtpVerification
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';

        $result = DB::transaction(function () use ($otpId, $code) {
            /** @var OtpVerification|null $otp */
            $otp = OtpVerification::query()->whereKey($otpId)->lockForUpdate()->first();

            if (! $otp || $otp->consumed_at || $otp->invalidated_at) {
                return ['status' => 'invalid', 'otp' => $otp];
            }
            if ($otp->expires_at->isPast()) {
                return ['status' => 'expired', 'otp' => $otp];
            }
            if ($otp->attempts >= $otp->max_attempts) {
                return ['status' => 'locked', 'otp' => $otp];
            }

            $otp->attempts++;
            $matches = hash_equals($otp->code_hash, self::hashCode($otp->id, $code));

            if ($matches) {
                $otp->consumed_at = now();
                $otp->save();

                return ['status' => 'ok', 'otp' => $otp];
            }

            if ($otp->attempts >= $otp->max_attempts) {
                $otp->invalidated_at = now();
            }
            $otp->save();

            return ['status' => $otp->invalidated_at ? 'locked' : 'wrong', 'otp' => $otp];
        });

        /** @var OtpVerification|null $otp */
        $otp = $result['otp'];
        $voter = $otp?->voter;

        if ($result['status'] === 'ok') {
            $this->audit->log(AuditAction::OTP_VERIFIED, AuditResult::SUCCESS, $otp, [], $this->voterActor($voter));

            return $otp;
        }

        $this->audit->failure(AuditAction::OTP_FAILED, $otp ?? ['OtpVerification', $otpId], [
            'reason' => $result['status'],
            'attempts' => $otp?->attempts,
        ], $voter ? $this->voterActor($voter) : null);

        if ($voter) {
            $this->flagRepeatedFailures($voter, $request);
        }

        throw match ($result['status']) {
            'expired' => OtpException::expired(),
            'locked' => OtpException::attemptsExhausted(),
            'wrong' => OtpException::incorrect($otp->max_attempts - $otp->attempts),
            default => OtpException::invalid(),
        };
    }

    public static function hashCode(string $otpId, string $code): string
    {
        return hash_hmac('sha256', $otpId.'|'.$code, (string) config('app.key'));
    }

    /**
     * Codes are delivered by email only.
     *
     * @return array{0:string,1:string,2:string} channel, destination, masked destination
     */
    private function resolveChannel(Voter $voter): array
    {
        if ($voter->email) {
            return [NotificationService::CHANNEL_EMAIL, $voter->email, 'email '.$voter->maskedEmail()];
        }

        throw OtpException::noContact();
    }

    private function flagRepeatedFailures(Voter $voter, Request $request): void
    {
        $window = now()->subMinutes((int) config('nimcos.alerts.window_minutes'));
        $failedAttempts = (int) OtpVerification::query()
            ->where('voter_id', $voter->getKey())
            ->where('created_at', '>=', $window)
            ->whereNull('consumed_at')
            ->sum('attempts');

        if ($failedAttempts >= (int) config('nimcos.alerts.failed_otp_per_voter')) {
            $this->alerts->raise(
                SecurityAlertService::MULTIPLE_FAILED_OTP,
                AlertSeverity::MEDIUM,
                "{$failedAttempts} failed OTP entries for one voter within the monitoring window.",
                $request->ip(),
                SecurityAlertService::maskServiceNumber($voter->service_number),
                ['failed_attempts' => $failedAttempts],
                'voter:'.$voter->getKey(),
            );
        }
    }

    /** @return array{type:string,id:string,label:string} */
    private function voterActor(Voter $voter): array
    {
        return ['type' => 'VOTER', 'id' => $voter->getKey(), 'label' => $voter->service_number];
    }
}
