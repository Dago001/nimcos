<?php

namespace App\Services\Voting\Exceptions;

use RuntimeException;

/** User-safe OTP failures. Messages are shown to voters verbatim. */
class OtpException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly bool $restart = false)
    {
        parent::__construct($message);
    }

    public static function cooldown(int $seconds): self
    {
        return new self("Please wait {$seconds} seconds before requesting another code.", 'cooldown');
    }

    public static function tooManyRequests(int $minutes): self
    {
        return new self("Too many codes have been requested. Please try again in {$minutes} minutes.", 'rate_limited', true);
    }

    public static function noContact(): self
    {
        return new self('No email address is registered for you, so a code cannot be sent. Please contact the election administrator.', 'no_contact', true);
    }

    public static function expired(): self
    {
        return new self('This code has expired. Request a new code.', 'expired');
    }

    public static function attemptsExhausted(): self
    {
        return new self('Too many incorrect attempts. This code is no longer valid. Request a new code.', 'locked');
    }

    public static function incorrect(int $remaining): self
    {
        return new self('The code you entered is incorrect. '.max(0, $remaining).' attempt(s) remaining.', 'wrong');
    }

    public static function invalid(): self
    {
        return new self('This code is no longer valid. Request a new code.', 'invalid');
    }
}
