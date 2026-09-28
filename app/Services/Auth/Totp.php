<?php

namespace App\Services\Auth;

/**
 * RFC 6238 time-based one-time passwords (HMAC-SHA1, 30 s step, 6 digits),
 * compatible with Google Authenticator, Microsoft Authenticator, Authy, etc.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $digits = 6,
        private readonly int $period = 30,
    ) {}

    public function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), $this->period);

        return $this->hotp(self::base32Decode($secret), $counter);
    }

    /** Accepts the current step and ±$window steps to tolerate clock drift. */
    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.$this->digits.'}$/', $code)) {
            return false;
        }

        $key = self::base32Decode($secret);
        $counter = intdiv($timestamp ?? time(), $this->period);
        $ok = false;
        for ($i = -$window; $i <= $window; $i++) {
            // Evaluate every step to keep timing independent of which one matches.
            $ok = hash_equals($this->hotp($key, $counter + $i), $code) || $ok;
        }

        return $ok;
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => $this->digits,
            'period' => $this->period,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function hotp(string $key, int $counter): string
    {
        $binCounter = pack('N*', 0, $counter); // 64-bit big-endian (high word 0 until year 2106+)
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper(rtrim(preg_replace('/\s+/', '', $data) ?? '', '='));
        $bits = '';
        foreach (str_split($data) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 secret.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
