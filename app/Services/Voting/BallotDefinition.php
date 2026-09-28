<?php

namespace App\Services\Voting;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use Illuminate\Database\Eloquent\Collection;

/**
 * The ballot paper for an election: ordered positions with their ACTIVE candidates.
 * Always loaded from the database, never from anything the browser sends.
 */
class BallotDefinition
{
    /** @param Collection<int, ElectionPosition> $positions */
    private function __construct(public readonly Election $election, public readonly Collection $positions) {}

    public static function for(Election $election): self
    {
        $positions = ElectionPosition::query()
            ->where('election_id', $election->getKey())
            ->with([
                'position',
                'candidates' => fn ($q) => $q->where('status', CandidateStatus::ACTIVE->value),
            ])
            ->orderBy('display_order')
            ->get();

        return new self($election, $positions);
    }

    public function position(string $electionPositionId): ?ElectionPosition
    {
        return $this->positions->firstWhere('id', $electionPositionId);
    }

    public function candidate(string $electionPositionId, string $candidateId): ?Candidate
    {
        return $this->position($electionPositionId)?->candidates->firstWhere('id', $candidateId);
    }

    public function count(): int
    {
        return $this->positions->count();
    }
}
