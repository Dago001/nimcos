<?php

namespace App\Services\Voting;

use App\Enums\AccountStatus;
use App\Enums\EligibilityStatus;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Voter;
use App\Support\ServiceNumber;
use Illuminate\Database\Eloquent\Collection;

/**
 * Answers "may this Service Number proceed to OTP?" (spec §2).
 * A Service Number alone never grants access; it only allows an OTP to be sent
 * to the contact details already on the approved register.
 */
class VoterAccessService
{
    /** Returns the voter only if registered, active, and on the roll of at least one open election. */
    public function findVoterForAccess(string $rawServiceNumber): ?Voter
    {
        $serviceNumber = ServiceNumber::normalise($rawServiceNumber);
        if ($serviceNumber === '' || ! ServiceNumber::isValid($serviceNumber)) {
            return null;
        }

        $voter = Voter::query()->where('service_number', $serviceNumber)->first();
        if (! $voter || $voter->account_status !== AccountStatus::ACTIVE) {
            return null;
        }

        // VOTED is included so that "already voted" is only revealed after OTP.
        return $this->participations($voter)->isNotEmpty() ? $voter : null;
    }

    /**
     * Election rolls in open elections on which this voter appears as ELIGIBLE or VOTED.
     *
     * @return Collection<int, ElectionVoter>
     */
    public function participations(Voter $voter): Collection
    {
        return ElectionVoter::query()
            ->with('election')
            ->where('voter_id', $voter->getKey())
            ->whereIn('eligibility_status', [EligibilityStatus::ELIGIBLE->value, EligibilityStatus::VOTED->value])
            ->whereHas('election', fn ($q) => $q->acceptingVotes())
            ->get()
            ->sortBy(fn (ElectionVoter $ev) => $ev->election->ends_at)
            ->values();
    }

    public function participationFor(Voter $voter, string $electionCode): ?ElectionVoter
    {
        return ElectionVoter::query()
            ->with('election')
            ->where('voter_id', $voter->getKey())
            ->whereHas('election', fn ($q) => $q->where('code', $electionCode))
            ->first();
    }

    public function anyElectionAcceptingVotes(): bool
    {
        return Election::query()->acceptingVotes()->exists();
    }
}
