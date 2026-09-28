<?php

namespace App\Services\Voting;

use App\Services\Voting\Exceptions\VotingException;

/**
 * A ballot referencing positions/candidates that the ballot paper never offered.
 * Normal use of the interface cannot produce this; it is flagged for review.
 */
class TamperedBallotException extends VotingException
{
    public function __construct(VotingException $inner)
    {
        parent::__construct($inner->getMessage(), self::INVALID_BALLOT, $inner->fieldErrors);
    }
}
