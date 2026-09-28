<?php

namespace App\Services\Voting\Exceptions;

use RuntimeException;

/** User-safe voting failures. Messages are shown to voters verbatim. */
class VotingException extends RuntimeException
{
    public const ELECTION_NOT_OPEN = 'election_not_open';

    public const NOT_ELIGIBLE = 'not_eligible';

    public const ALREADY_VOTED = 'already_voted';

    public const SESSION_INVALID = 'session_invalid';

    public const INVALID_BALLOT = 'invalid_ballot';

    public const TOKEN_INVALID = 'token_invalid';

    /** @param array<string, string> $fieldErrors */
    public function __construct(string $message, public readonly string $reason, public readonly array $fieldErrors = [])
    {
        parent::__construct($message);
    }

    public static function electionNotOpen(): self
    {
        return new self('Voting is not open for this election. Ballots can only be submitted during the official voting period.', self::ELECTION_NOT_OPEN);
    }

    public static function notEligible(): self
    {
        return new self('You are not authorised to vote in this election. If you believe this is an error, contact the election administrator.', self::NOT_ELIGIBLE);
    }

    public static function alreadyVoted(): self
    {
        return new self('Our records show that you have already voted in this election. Each member may vote only once.', self::ALREADY_VOTED);
    }

    public static function sessionInvalid(): self
    {
        return new self('Your ballot session has expired or is no longer valid. Please sign in again to continue.', self::SESSION_INVALID);
    }

    public static function tokenInvalid(): self
    {
        return new self('Your ballot could not be verified. Please sign in again to continue.', self::TOKEN_INVALID);
    }

    /** @param array<string, string> $fieldErrors */
    public static function invalidBallot(array $fieldErrors): self
    {
        return new self('Please correct the highlighted positions before submitting.', self::INVALID_BALLOT, $fieldErrors);
    }
}
