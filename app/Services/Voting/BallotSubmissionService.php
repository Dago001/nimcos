<?php

namespace App\Services\Voting;

use App\Enums\AlertSeverity;
use App\Enums\AuditResult;
use App\Enums\EligibilityStatus;
use App\Enums\VotingSessionStatus;
use App\Jobs\SendVoteConfirmationEmail;
use App\Models\Ballot;
use App\Models\BallotToken;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Vote;
use App\Models\VotingSession;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SecurityAlertService;
use App\Services\Voting\Exceptions\VotingException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records a ballot exactly once (spec §13, §15, §16, §45).
 *
 * Lock order (always the same, so concurrent submissions cannot deadlock):
 *   elections (FOR SHARE) -> election_voters (FOR UPDATE) -> voting_sessions -> ballot_tokens
 *
 * The election row is share-locked so a concurrent CLOSE waits until in-flight
 * ballots commit, and no ballot can be written after the close commits.
 */
class BallotSubmissionService
{
    public function __construct(
        private readonly BallotValidator $validator,
        private readonly ReceiptReferenceGenerator $references,
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
    ) {}

    /**
     * @throws VotingException
     */
    public function submit(VotingSession $session, ?string $rawBallotToken, mixed $rawSelections, Request $request): BallotReceipt
    {
        $electionVoterId = $session->election_voter_id;
        $tokenHash = is_string($rawBallotToken) && $rawBallotToken !== '' ? hash('sha256', $rawBallotToken) : null;

        try {
            $receipt = DB::transaction(function () use ($session, $electionVoterId, $tokenHash, $rawSelections) {
                $election = Election::query()
                    ->whereKey($session->electionVoter->election_id)
                    ->sharedLock()
                    ->firstOrFail();

                /** @var ElectionVoter $ev */
                $ev = ElectionVoter::query()->whereKey($electionVoterId)->lockForUpdate()->firstOrFail();

                // Idempotent replay: the same browser retrying after a timeout gets the original receipt.
                if ($ev->eligibility_status === EligibilityStatus::VOTED) {
                    $replay = $this->findReceiptByToken($election, $ev, $tokenHash);
                    if ($replay) {
                        return $replay;
                    }
                    throw VotingException::alreadyVoted();
                }

                if (! $election->isAcceptingVotes()) {
                    throw VotingException::electionNotOpen();
                }
                if ($ev->eligibility_status !== EligibilityStatus::ELIGIBLE) {
                    throw VotingException::notEligible();
                }

                /** @var VotingSession $lockedSession */
                $lockedSession = VotingSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                if ($lockedSession->status !== VotingSessionStatus::ACTIVE || $lockedSession->expires_at->isPast()) {
                    throw VotingException::sessionInvalid();
                }

                $selections = $this->validator->validate(BallotDefinition::for($election), $rawSelections, true);

                if ($tokenHash === null) {
                    throw VotingException::tokenInvalid();
                }
                /** @var BallotToken|null $token */
                $token = BallotToken::query()
                    ->where('token_hash', $tokenHash)
                    ->where('election_id', $election->getKey())
                    ->lockForUpdate()
                    ->first();
                if (! $token || $token->is_consumed) {
                    throw VotingException::tokenInvalid();
                }

                // Compare-and-set: exactly one row must move from ELIGIBLE to VOTED.
                $votedAt = now();
                $updated = DB::table('election_voters')
                    ->where('id', $ev->getKey())
                    ->where('eligibility_status', EligibilityStatus::ELIGIBLE->value)
                    ->update([
                        'eligibility_status' => EligibilityStatus::VOTED->value,
                        'voted_at' => $votedAt,
                        'updated_at' => $votedAt,
                    ]);
                if ($updated !== 1) {
                    throw VotingException::alreadyVoted();
                }

                $token->forceFill(['is_consumed' => true])->save();

                $ballot = new Ballot;
                $ballot->forceFill([
                    'election_id' => $election->getKey(),
                    'ballot_token_id' => $token->getKey(),
                    'reference' => $this->references->generate($election),
                ])->save();

                $rows = [];
                foreach ($selections as $positionId => $candidateIds) {
                    foreach ($candidateIds as $candidateId) {
                        $rows[] = [
                            'id' => (new Vote)->newUniqueId(),
                            'ballot_id' => $ballot->getKey(),
                            'election_id' => $election->getKey(),
                            'election_position_id' => $positionId,
                            'candidate_id' => $candidateId,
                        ];
                    }
                }
                // Shuffle so physical insert order within a ballot carries no information either.
                shuffle($rows);
                if ($rows !== []) {
                    Vote::query()->insert($rows);
                }

                $lockedSession->forceFill([
                    'status' => VotingSessionStatus::COMPLETED,
                    'ended_at' => $votedAt,
                ])->save();

                // Secrecy boundary: voter receives congratulations email with their ballot reference
                $voter = $ev->voter;
                if ($voter && $voter->email) {
                    $voterId = $voter->getKey();
                    $electionId = $election->getKey();
                    $reference = $ballot->reference;
                    $displayTime = display_time($votedAt, 'j F Y, H:i');

                    DB::afterCommit(fn () => SendVoteConfirmationEmail::dispatch(
                        $voterId,
                        $electionId,
                        $reference,
                        $displayTime,
                    ));
                }

                return new BallotReceipt($election, $ballot->reference, $votedAt);
            });
        } catch (TamperedBallotException $e) {
            $this->audit->denied(AuditAction::VOTE_REJECTED, $session->electionVoter, ['reason' => 'tampered_ballot']);
            $this->alerts->raise(
                SecurityAlertService::TAMPERED_BALLOT,
                AlertSeverity::HIGH,
                'A ballot was submitted containing positions or candidates that were not offered on the ballot paper.',
                $request->ip(),
                SecurityAlertService::maskServiceNumber($session->electionVoter->voter->service_number),
                [],
                'ev:'.$electionVoterId,
            );
            throw $e;
        } catch (VotingException $e) {
            if ($e->reason === VotingException::ALREADY_VOTED) {
                $this->audit->denied(AuditAction::DUPLICATE_VOTE_ATTEMPT, $session->electionVoter);
                $this->alerts->raise(
                    SecurityAlertService::DUPLICATE_BALLOT,
                    AlertSeverity::LOW,
                    'A voter who has already voted attempted to submit another ballot.',
                    $request->ip(),
                    SecurityAlertService::maskServiceNumber($session->electionVoter->voter->service_number),
                    [],
                    'ev:'.$electionVoterId,
                );
            } elseif ($e->reason !== VotingException::INVALID_BALLOT) {
                $this->audit->denied(AuditAction::VOTE_REJECTED, $session->electionVoter, ['reason' => $e->reason]);
            }
            throw $e;
        }

        // Identity side only: who voted in which election. Never the reference or selections.
        $this->audit->log(
            $receipt->replayed ? AuditAction::VOTE_REPLAYED : AuditAction::VOTE_CAST,
            AuditResult::SUCCESS,
            $session->electionVoter,
            ['election' => $receipt->election->code],
        );

        return $receipt;
    }

    /** Receipt lookup for a voter who has voted, using the ballot token held by their own browser. */
    public function receiptFor(ElectionVoter $ev, ?string $rawBallotToken): ?BallotReceipt
    {
        if (! $ev->hasVoted()) {
            return null;
        }
        $tokenHash = is_string($rawBallotToken) && $rawBallotToken !== '' ? hash('sha256', $rawBallotToken) : null;

        return $this->findReceiptByToken($ev->election, $ev, $tokenHash, false);
    }

    private function findReceiptByToken(Election $election, ElectionVoter $ev, ?string $tokenHash, bool $replayed = true): ?BallotReceipt
    {
        if ($tokenHash === null) {
            return null;
        }
        $token = BallotToken::query()
            ->where('token_hash', $tokenHash)
            ->where('election_id', $election->getKey())
            ->where('is_consumed', true)
            ->first();
        $ballot = $token ? Ballot::query()->where('ballot_token_id', $token->getKey())->first() : null;

        return $ballot ? new BallotReceipt($election, $ballot->reference, $ev->voted_at, $replayed) : null;
    }
}
