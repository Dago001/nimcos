<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * Runtime-editable settings. Each key falls back to its config default, so a
 * fresh installation behaves sensibly before anything is saved.
 */
class SettingsService
{
    /**
     * key => [config path for default, type, label, help]
     *
     * @var array<string, array{0:string,1:string,2:string,3:string}>
     */
    public const DEFINITIONS = [
        'otp_ttl_minutes' => ['nimcos.otp.ttl_minutes', 'int', 'OTP validity (minutes)', 'How long a one-time password remains valid. 3–10 minutes recommended.'],
        'otp_max_attempts' => ['nimcos.otp.max_attempts', 'int', 'OTP attempts per code', 'Wrong entries allowed before a code is invalidated.'],
        'voting_session_minutes' => ['nimcos.voting_session.idle_minutes', 'int', 'Ballot session idle timeout (minutes)', 'A voter is signed out of the ballot after this much inactivity.'],
        'require_admin_mfa' => ['nimcos.admin.require_mfa', 'bool', 'Require MFA for all administrators', 'Administrators without an authenticator app will be required to enrol at next sign-in.'],
        'support_contact' => ['', 'string', 'Election support contact', 'Shown to voters who cannot sign in (phone or email of the election administrator).'],
    ];

    /** @var array<string, mixed> */
    private array $cache = [];

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $def = self::DEFINITIONS[$key] ?? null;
        $default = $def && $def[0] !== '' ? config($def[0]) : null;

        $stored = SystemSetting::query()->find($key);
        $value = $stored ? $stored->value : $default;

        return $this->cache[$key] = $this->cast($value, $def[1] ?? 'string');
    }

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $type = self::DEFINITIONS[$key][1] ?? 'string';
        $value = $this->cast($value, $type);

        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->getKey()]);
        $this->cache[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFINITIONS) as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value === null ? null : (string) $value,
        };
    }
}
