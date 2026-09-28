<?php

namespace App\Services\Results;

use App\Enums\AlertSeverity;
use App\Enums\AuditResult;
use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ResultStatus;
use App\Enums\TieResolutionMethod;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ResultTally;
use App\Models\TieResolution;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SecurityAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Results are computed from the votes table and nothing else (spec §20–22).
 * There is no code path that accepts a vote count from a user.
 *
 * Workflow: CLOSE -> calculate() -> verify() -> resolveTie() as needed -> publish()
 */
class ResultsCalculator
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
    ) {}

    /**
     * Pure computation from the database (no writes). Candidates with zero votes are included.
     *
     * @return array{ballots:int, voted:int, positions: array<string, array{seats:int, candidates: list<array{candidate_id:string, votes:int, rank:int, is_tied:bool, is_provisional_winner:bool}>, has_tie: bool}>, hash: string}
     */
    public function compute(Election $election): array
    {
        $counts = DB::table('votes')
            ->where('election_id', $election->getKey())
            ->groupBy('election_position_id', 'candidate_id')
            ->select('election_position_id', 'candidate_id', DB::raw('COUNT(*) AS votes'))
            ->get()
            ->mapWithKeys(fn ($r) => [$r->election_position_id.'|'.$r->candidate_id => (int) $r->votes]);

        $positions = ElectionPosition::query()
            ->where('election_id', $election->getKey())
            ->with('candidates')
            ->orderBy('display_order')
            ->get();

        $out = [];
        foreach ($positions as $position) {
            $rows = [];
            foreach ($position->candidates as $candidate) {
                $votes = $counts[$position->id.'|'.$candidate->id] ?? 0;
                // Inactive candidates appear only if they somehow received votes (they cannot, but never hide data).
                if ($candidate->status !== CandidateStatus::ACTIVE && $votes === 0) {
                    continue;
                }
                $rows[] = ['candidate_id' => $candidate->id, 'votes' => $votes, 'sort' => $candidate->display_order.'|'.$candidate->candidate_number];
            }

            usort($rows, fn ($a, $b) => [$b['votes'], $a['sort']] <=> [$a['votes'], $b['sort']]);
            $out[$position->id] = $this->rank($rows, $position->seats);
        }

        $ballots = DB::table('ballots')->where('election_id', $election->getKey())->count();
        $voted = DB::table('election_voters')->where('election_id', $election->getKey())
            ->where('eligibility_status', EligibilityStatus::VOTED->value)->count();

        return [
            'ballots' => $ballots,
            'voted' => $voted,
            'positions' => $out,
            'hash' => $this->hash($election, $ballots, $out),
        ];
    }

    /**
     * Competition ranking (1,2,2,4) with tie detection at the seat boundary:
     * a tie matters only when candidates with equal votes compete for the last seat(s).
     */
    private function rank(array $rows, int $seats): array
    {
        $ranked = [];
        $rank = 0;
        $prevVotes = null;
        foreach ($rows as $i => $row) {
            if ($row['votes'] !== $prevVotes) {
                $rank = $i + 1;
                $prevVotes = $row['votes'];
            }
            $ranked[] = ['candidate_id' => $row['candidate_id'], 'votes' => $row['votes'], 'rank' => $rank, 'is_tied' => false, 'is_provisional_winner' => false];
        }

        $hasTie = false;
        if (count($ranked) <= $seats) {
            foreach ($ranked as &$r) {
                $r['is_provisional_winner'] = true;
            }
            unset($r);
        } else {
            $threshold = $ranked[$seats - 1]['votes'];
            $above = count(array_filter($ranked, fn ($r) => $r['votes'] > $threshold));
            $atThreshold = count(array_filter($ranked, fn ($r) => $r['votes'] === $threshold));
            $remaining = $seats - $above;
            $hasTie = $atThreshold > $remaining;

            foreach ($ranked as &$r) {
                if ($r['votes'] > $threshold) {
                    $r['is_provisional_winner'] = true;
                } elseif ($r['votes'] === $threshold) {
                    $r['is_tied'] = $hasTie;
                    $r['is_provisional_winner'] = ! $hasTie;
                }
            }
            unset($r);
        }

        return ['seats' => $seats, 'candidates' => $ranked, 'has_tie' => $hasTie];
    }

    private function hash(Election $election, int $ballots, array $positions): string
    {
        $parts = [$election->getKey(), (string) $ballots];
        ksort($positions);
        foreach ($positions as $positionId => $p) {
            $cands = $p['candidates'];
            usort($cands, fn ($a, $b) => strcmp($a['candidate_id'], $b['candidate_id']));
            foreach ($cands as $c) {
                $parts[] = $positionId.':'.$c['candidate_id'].':'.$c['votes'];
            }
        }

        return hash('sha256', implode('|', $parts));
    }

    public function calculate(Election $election, User $by): array
    {
        $computed = DB::transaction(function () use ($election, $by) {
            $locked = $this->lockClosed($election);
            if ($locked->result_status === ResultStatus::PUBLISHED) {
                throw ValidationException::withMessages(['results' => 'Results have already been published and are frozen.']);
            }

            $computed = $this->compute($locked);

            ResultTally::query()->where('election_id', $locked->getKey())->delete();
            TieResolution::query()->where('election_id', $locked->getKey())->delete();

            $now = now();
            $rows = [];
            foreach ($computed['positions'] as $positionId => $p) {
                foreach ($p['candidates'] as $c) {
                    $rows[] = [
                        'id' => (string) Str::orderedUuid(),
                        'election_id' => $locked->getKey(),
                        'election_position_id' => $positionId,
                        'candidate_id' => $c['candidate_id'],
                        'votes' => $c['votes'],
                        'rank' => $c['rank'],
                        'is_tied' => $c['is_tied'],
                        'is_provisional_winner' => $c['is_provisional_winner'],
                        'calculation_hash' => $computed['hash'],
                        'calculated_at' => $now,
                        'calculated_by' => $by->getKey(),
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                ResultTally::query()->insert($chunk);
            }

            $locked->result_status = ResultStatus::CALCULATED;
            $locked->save();

            return $computed;
        });

        $this->audit->log(AuditAction::RESULTS_CALCULATED, AuditResult::SUCCESS, $election, [
            'ballots' => $computed['ballots'],
            'hash' => $computed['hash'],
            'ties' => count(array_filter($computed['positions'], fn ($p) => $p['has_tie'])),
        ]);

        return $computed;
    }

    /**
     * Independent re-count compared with the stored tallies, plus integrity invariants.
     *
     * @return list<string> problems (empty = verified)
     */
    public function verify(Election $election, User $by): array
    {
        $problems = DB::transaction(function () use ($election) {
            $locked = $this->lockClosed($election);
            if (! in_array($locked->result_status, [ResultStatus::CALCULATED, ResultStatus::VERIFIED], true)) {
                throw ValidationException::withMessages(['results' => 'Calculate the results before verifying them.']);
            }

            $problems = $this->integrityProblems($locked);

            if ($problems === []) {
                $locked->result_status = ResultStatus::VERIFIED;
                $locked->save();
            }

            return $problems;
        });

        if ($problems === []) {
            $this->audit->log(AuditAction::RESULTS_VERIFIED, AuditResult::SUCCESS, $election);
        } else {
            $this->audit->failure(AuditAction::RESULTS_VERIFICATION_FAILED, $election, ['problems' => $problems]);
            $this->alerts->raise('RESULTS_VERIFICATION_FAILED', AlertSeverity::CRITICAL,
                'Result verification failed: '.implode(' ', $problems), null, $election->code, [], 'results:'.$election->getKey());
        }

        return $problems;
    }

    /** @return list<string> */
    public function integrityProblems(Election $election): array
    {
        $problems = [];
        $computed = $this->compute($election);

        $stored = ResultTally::query()->where('election_id', $election->getKey())->get();
        $storedHash = $stored->pluck('calculation_hash')->unique();
        if ($stored->isEmpty() || $storedHash->count() !== 1 || $storedHash->first() !== $computed['hash']) {
            $problems[] = 'Stored tallies do not match an independent recount of the votes.';
        }
        foreach ($stored as $row) {
            $recount = collect($computed['positions'][$row->election_position_id]['candidates'] ?? [])
                ->firstWhere('candidate_id', $row->candidate_id);
            if (! $recount || $recount['votes'] !== $row->votes) {
                $problems[] = 'A stored tally differs from the recount.';
                break;
            }
        }

        if ($computed['ballots'] !== $computed['voted']) {
            $problems[] = "Ballots recorded ({$computed['ballots']}) do not equal voters marked as voted ({$computed['voted']}).";
        }

        $consumed = DB::table('ballot_tokens')->where('election_id', $election->getKey())->where('is_consumed', true)->count();
        if ($consumed !== $computed['ballots']) {
            $problems[] = "Consumed ballot tokens ({$consumed}) do not equal ballots recorded ({$computed['ballots']}).";
        }

        $overvotes = DB::table('votes')
            ->join('election_positions', 'election_positions.id', '=', 'votes.election_position_id')
            ->where('votes.election_id', $election->getKey())
            ->groupBy('votes.ballot_id', 'votes.election_position_id', 'election_positions.seats')
            ->havingRaw('COUNT(*) > election_positions.seats')
            ->select('votes.ballot_id')
            ->get()
            ->count();
        if ($overvotes > 0) {
            $problems[] = "{$overvotes} ballot position(s) contain more selections than seats.";
        }

        return $problems;
    }

    public function resolveTie(Election $election, ElectionPosition $position, TieResolutionMethod $method, ?string $winnerId, string $notes, User $by): TieResolution
    {
        $resolution = DB::transaction(function () use ($election, $position, $method, $winnerId, $notes, $by) {
            $locked = $this->lockClosed($election);
            if (! in_array($locked->result_status, [ResultStatus::CALCULATED, ResultStatus::VERIFIED], true)) {
                throw ValidationException::withMessages(['results' => 'Results must be calculated before a tie can be resolved.']);
            }
            if ($position->election_id !== $locked->getKey()) {
                abort(404);
            }

            $tied = ResultTally::query()
                ->where('election_id', $locked->getKey())
                ->where('election_position_id', $position->getKey())
                ->where('is_tied', true)
                ->pluck('candidate_id');
            if ($tied->isEmpty()) {
                throw ValidationException::withMessages(['tie' => 'There is no tie to resolve for this position.']);
            }
            if ($method === TieResolutionMethod::RUNOFF_PENDING) {
                $winnerId = null;
            } elseif (! $winnerId || ! $tied->contains($winnerId)) {
                throw ValidationException::withMessages(['winning_candidate_id' => 'Select one of the tied candidates as the declared winner.']);
            }

            $resolution = TieResolution::query()
                ->where('election_id', $locked->getKey())
                ->where('election_position_id', $position->getKey())
                ->first() ?? new TieResolution;
            $resolution->forceFill([
                'election_id' => $locked->getKey(),
                'election_position_id' => $position->getKey(),
                'method' => $method,
                'winning_candidate_id' => $winnerId,
                'notes' => $notes,
                'resolved_by' => $by->getKey(),
                'resolved_at' => now(),
            ])->save();

            return $resolution;
        });

        $this->audit->log(AuditAction::TIE_RESOLVED, AuditResult::SUCCESS, $resolution, [
            'position' => $position->position->name,
            'method' => $method->value,
            'winner' => $winnerId,
        ]);

        return $resolution;
    }

    /** @return list<string> reasons publication is blocked */
    public function publicationBlockers(Election $election): array
    {
        $blockers = [];
        if ($election->status !== ElectionStatus::CLOSED) {
            $blockers[] = 'The election must be closed.';
        }
        if ($election->result_status !== ResultStatus::VERIFIED) {
            $blockers[] = 'Results must be calculated and verified.';
        }
        $tiedPositions = ResultTally::query()->where('election_id', $election->getKey())->where('is_tied', true)
            ->distinct()->pluck('election_position_id');
        $resolved = TieResolution::query()->where('election_id', $election->getKey())->pluck('election_position_id');
        $unresolved = $tiedPositions->diff($resolved)->count();
        if ($unresolved > 0) {
            $blockers[] = "{$unresolved} tied position(s) have not been resolved under the NIMCOS election rules.";
        }

        return $blockers;
    }

    public function publish(Election $election, User $by): Election
    {
        $published = DB::transaction(function () use ($election, $by) {
            $locked = $this->lockClosed($election);
            $blockers = $this->publicationBlockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['results' => $blockers]);
            }
            // Final recount immediately before publication.
            $problems = $this->integrityProblems($locked);
            if ($problems !== []) {
                throw ValidationException::withMessages(['results' => $problems]);
            }

            $locked->forceFill([
                'status' => ElectionStatus::RESULTS_PUBLISHED,
                'result_status' => ResultStatus::PUBLISHED,
                'published_at' => now(),
                'published_by' => $by->getKey(),
            ]);
            $locked->save();

            return $locked;
        });

        $this->audit->log(AuditAction::RESULTS_PUBLISHED, AuditResult::SUCCESS, $published, [
            'hash' => ResultTally::query()->where('election_id', $published->getKey())->value('calculation_hash'),
        ], ['type' => 'ADMIN', 'id' => $by->getKey(), 'label' => $by->email]);

        return $published;
    }

    private function lockClosed(Election $election): Election
    {
        /** @var Election $locked */
        $locked = Election::query()->whereKey($election->getKey())->lockForUpdate()->firstOrFail();
        if (! in_array($locked->status, [ElectionStatus::CLOSED, ElectionStatus::RESULTS_PUBLISHED], true)) {
            throw ValidationException::withMessages(['results' => 'Results can only be processed after the election has closed.']);
        }

        return $locked;
    }
}
