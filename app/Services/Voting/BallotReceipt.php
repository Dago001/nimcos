<?php

namespace App\Services\Voting;

use App\Models\Election;
use Carbon\CarbonInterface;

/** What the voter is shown after voting. Contains no selections (spec §18). */
final class BallotReceipt
{
    public function __construct(
        public readonly Election $election,
        public readonly string $reference,
        public readonly CarbonInterface $votedAt,
        public readonly bool $replayed = false,
    ) {}
}
