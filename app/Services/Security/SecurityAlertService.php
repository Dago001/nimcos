<?php

namespace App\Services\Security;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AuditResult;
use App\Models\SecurityAlert;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records suspicious activity for authorised review (spec §53).
 * Alerts are observations, never accusations: nothing here blocks a voter by itself.
 */
class SecurityAlertService
{
    public const MULTIPLE_FAILED_OTP = 'MULTIPLE_FAILED_OTP';

    public const REPEATED_LOOKUPS = 'REPEATED_SERVICE_NUMBER_LOOKUPS';

    public const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';

    public const MULTIPLE_SESSIONS = 'MULTIPLE_VOTING_SESSIONS';

    public const DUPLICATE_BALLOT = 'DUPLICATE_BALLOT_ATTEMPT';

    public const UNAUTHORIZED_ADMIN = 'UNAUTHORIZED_ADMIN_ACTION';

    public const ADMIN_LOCKOUT = 'ADMIN_ACCOUNT_LOCKED';

    public const TAMPERED_BALLOT = 'TAMPERED_BALLOT_SUBMISSION';

    public const AUDIT_CHAIN_BROKEN = 'AUDIT_CHAIN_BROKEN';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Open a new alert, or increment the open alert with the same fingerprint
     * seen within the configured window.
     */
    public function raise(
        string $type,
        AlertSeverity $severity,
        string $description,
        ?string $ip = null,
        ?string $subject = null,
        array $metadata = [],
        ?string $dedupeKey = null,
    ): SecurityAlert {
        $fingerprint = hash('sha256', $type.'|'.($dedupeKey ?? ($ip.'|'.$subject)));
        $window = now()->subMinutes((int) config('nimcos.alerts.window_minutes', 30));

        $alert = DB::transaction(function () use ($type, $severity, $description, $ip, $subject, $metadata, $fingerprint, $window) {
            $existing = SecurityAlert::query()
                ->where('fingerprint', $fingerprint)
                ->where('status', AlertStatus::OPEN->value)
                ->where('last_seen_at', '>=', $window)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->occurrences++;
                $existing->last_seen_at = now();
                $existing->save();

                return $existing;
            }

            $alert = new SecurityAlert;
            $alert->forceFill([
                'type' => $type,
                'severity' => $severity,
                'fingerprint' => $fingerprint,
                'ip' => $ip,
                'subject' => $subject,
                'description' => $description,
                'metadata' => $metadata,
                'occurrences' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'status' => AlertStatus::OPEN,
            ])->save();

            return $alert;
        });

        if ($alert->occurrences === 1) {
            $this->audit->log(AuditAction::SECURITY_ALERT, AuditResult::FAILURE, $alert, [
                'type' => $type,
                'severity' => $severity->value,
            ]);
            Log::channel(config('logging.default'))->warning('NIMCOS security alert', [
                'type' => $type, 'severity' => $severity->value, 'ip' => $ip,
            ]);
            if (in_array($severity, [AlertSeverity::HIGH, AlertSeverity::CRITICAL], true)) {
                try {
                    $this->notifications->sendSecurityAlert($alert);
                } catch (Throwable $e) {
                    // Email is best-effort; the alert itself is already recorded.
                    Log::error('Security alert email could not be queued', ['error' => $e->getMessage()]);
                }
            }
        }

        return $alert;
    }

    public static function maskServiceNumber(string $serviceNumber): string
    {
        $len = mb_strlen($serviceNumber);

        // Service Numbers are short (4–5 digits), so reveal only the last two.
        return $len <= 3 ? str_repeat('•', $len) : str_repeat('•', $len - 2).mb_substr($serviceNumber, -2);
    }
}
