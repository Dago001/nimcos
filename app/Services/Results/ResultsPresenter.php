<?php

namespace App\Services\Results;

use App\Enums\TieResolutionMethod;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ResultTally;
use App\Models\TieResolution;

/**
 * Turns computed or stored tallies into display rows (votes, percentages, winners, ties).
 * Read-only: it never writes and never accepts figures from input.
 */
class ResultsPresenter
{
    public function __construct(private readonly ResultsCalculator $calculator) {}

    /** Stored (calculated/verified/published) results. */
    public function stored(Election $election): ?array
    {
        $tallies = ResultTally::query()->where('election_id', $election->getKey())->get();
        if ($tallies->isEmpty()) {
            return null;
        }

        $positions = [];
        foreach ($tallies->groupBy('election_position_id') as $positionId => $rows) {
            $positions[$positionId] = [
                'seats' => 0,
                'has_tie' => $rows->contains('is_tied', true),
                'candidates' => $rows->sortBy('rank')->map(fn (ResultTally $t) => [
                    'candidate_id' => $t->candidate_id,
                    'votes' => $t->votes,
                    'rank' => $t->rank,
                    'is_tied' => $t->is_tied,
                    'is_provisional_winner' => $t->is_provisional_winner,
                ])->values()->all(),
            ];
        }

        return $this->build($election, $positions, Ballot::query()->where('election_id', $election->getKey())->count(), $tallies->first()->calculation_hash, $tallies->first()->calculated_at);
    }

    /** Live recount straight from votes (interim results, or preview before calculation). */
    public function live(Election $election): array
    {
        $computed = $this->calculator->compute($election);

        return $this->build($election, $computed['positions'], $computed['ballots'], $computed['hash'], now());
    }

    /**
     * Compact running count for the dashboard: every contestant with their current
     * votes, straight from the votes table. Only aggregate counts per candidate are
     * exposed; nothing identifies a ballot or a voter.
     *
     * @return array{ballots:int, positions: list<array{id:string, name:string, seats:int, total:int, candidates: list<array{id:string, candidate:Candidate, votes:int, percentage:float, leading:bool}>}>}
     */
    public function liveTally(Election $election): array
    {
        $results = $this->live($election);

        $positions = [];
        foreach ($results['positions'] as $p) {
            $positions[] = [
                'id' => $p['election_position']->id,
                'name' => $p['name'],
                'seats' => $p['seats'],
                'total' => $p['total_valid_votes'],
                'candidates' => array_map(fn ($row) => [
                    'id' => $row['candidate']->id,
                    'candidate' => $row['candidate'],
                    'votes' => $row['votes'],
                    'percentage' => $row['percentage'],
                    // Ahead for a seat right now (includes a level position at the seat boundary).
                    'leading' => $row['votes'] > 0 && ($row['is_winner'] || $row['is_tied']),
                ], $p['candidates']),
            ];
        }

        return ['ballots' => $results['ballots'], 'positions' => $positions];
    }

    /** JSON form of liveTally() for polling. @return array{ballots:int, candidates: array<string, array{votes:int, percentage:float, leading:bool}>, totals: array<string,int>} */
    public function liveTallyPayload(array $tally): array
    {
        $candidates = [];
        $totals = [];
        foreach ($tally['positions'] as $p) {
            $totals[$p['id']] = $p['total'];
            foreach ($p['candidates'] as $c) {
                $candidates[$c['id']] = ['votes' => $c['votes'], 'percentage' => $c['percentage'], 'leading' => $c['leading']];
            }
        }

        return ['ballots' => $tally['ballots'], 'candidates' => $candidates, 'totals' => $totals];
    }

    private function build(Election $election, array $computedPositions, int $ballots, string $hash, $calculatedAt): array
    {
        $electionPositions = ElectionPosition::query()->where('election_id', $election->getKey())
            ->with('position')->orderBy('display_order')->get();
        $candidates = Candidate::query()->where('election_id', $election->getKey())->get()->keyBy('id');
        $resolutions = TieResolution::query()->where('election_id', $election->getKey())->get()->keyBy('election_position_id');
        $eligible = $election->eligibleCount();

        $positions = [];
        foreach ($electionPositions as $ep) {
            $data = $computedPositions[$ep->id] ?? ['candidates' => [], 'has_tie' => false];
            $total = array_sum(array_column($data['candidates'], 'votes'));
            /** @var TieResolution|null $resolution */
            $resolution = $resolutions->get($ep->id);

            $rows = [];
            foreach ($data['candidates'] as $c) {
                $isWinner = $c['is_provisional_winner'];
                if ($c['is_tied'] && $resolution && $resolution->method !== TieResolutionMethod::RUNOFF_PENDING) {
                    $isWinner = $resolution->winning_candidate_id === $c['candidate_id'];
                }
                $rows[] = [
                    'candidate' => $candidates->get($c['candidate_id']),
                    'votes' => $c['votes'],
                    'percentage' => $total > 0 ? round($c['votes'] * 100 / $total, 2) : 0.0,
                    'rank' => $c['rank'],
                    'is_tied' => $c['is_tied'],
                    'is_winner' => $isWinner,
                ];
            }

            $positions[] = [
                'election_position' => $ep,
                'name' => $ep->position->name,
                'seats' => $ep->seats,
                'total_valid_votes' => $total,
                // Electronic ballots are validated before acceptance, so none are invalid.
                'invalid_votes' => 0,
                'abstentions' => $ep->seats === 1 ? max(0, $ballots - $total) : null,
                'has_tie' => $data['has_tie'],
                'resolution' => $resolution,
                'candidates' => $rows,
            ];
        }

        return [
            'election' => $election,
            'ballots' => $ballots,
            'eligible' => $eligible,
            'turnout' => $eligible > 0 ? round($ballots * 100 / $eligible, 2) : 0.0,
            'positions' => $positions,
            'hash' => $hash,
            'calculated_at' => $calculatedAt,
        ];
    }
}
