<?php

namespace App\Services\Reports;

use App\Enums\EligibilityStatus;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Voter;
use App\Services\Monitoring\ElectionStatistics;
use App\Services\Results\ResultsPresenter;
use Illuminate\Validation\ValidationException;

/**
 * Builds report data sets (spec §35). Every report respects ballot secrecy: the
 * voter register shows WHETHER someone voted, results show totals only, and no
 * report ever joins the two.
 */
class ReportService
{
    public const TYPES = [
        'voter-register' => 'Voter Register',
        'turnout' => 'Election Turnout',
        'results' => 'Election Results',
        'audit' => 'Audit Report',
    ];

    public function __construct(
        private readonly ElectionStatistics $stats,
        private readonly ResultsPresenter $results,
    ) {}

    /**
     * @return array{title:string, subtitle:string, headings:list<string>, rows:iterable<list<scalar|null>>, summary: array<string,string>}
     */
    public function build(string $type, ?Election $election, array $filters = []): array
    {
        return match ($type) {
            'voter-register' => $this->voterRegister($election),
            'turnout' => $this->turnout($this->require($election)),
            'results' => $this->resultsReport($this->require($election)),
            'audit' => $this->audit($filters),
            default => abort(404),
        };
    }

    private function require(?Election $election): Election
    {
        if (! $election) {
            throw ValidationException::withMessages(['election' => 'Select an election for this report.']);
        }

        return $election;
    }

    private function voterRegister(?Election $election): array
    {
        if ($election) {
            $rows = (function () use ($election) {
                $query = ElectionVoter::query()->where('election_id', $election->getKey())
                    ->join('voters', 'voters.id', '=', 'election_voters.voter_id')
                    ->orderBy('voters.surname')->orderBy('voters.first_name')
                    ->select('election_voters.*');
                foreach ($query->with('voter')->lazy(1000) as $ev) {
                    $v = $ev->voter;
                    yield [$v->service_number, $v->fullName(), $v->rank, $v->command, $ev->eligibility_status->label(), $ev->hasVoted() ? 'Voted' : 'Not voted'];
                }
            })();

            return [
                'title' => 'Voter Register',
                'subtitle' => $election->name,
                'headings' => ['Service Number', 'Name', 'Rank', 'Command', 'Eligibility', 'Voting Status'],
                'rows' => $rows,
                'summary' => [],
            ];
        }

        $rows = (function () {
            foreach (Voter::query()->orderBy('surname')->orderBy('first_name')->lazy(1000) as $v) {
                yield [$v->service_number, $v->fullName(), $v->rank, $v->command, $v->eligibility_status->label(), $v->verification_status->label()];
            }
        })();

        return [
            'title' => 'Voter Register',
            'subtitle' => 'Full NIMCOS register',
            'headings' => ['Service Number', 'Name', 'Rank', 'Command', 'Eligibility', 'Verification'],
            'rows' => $rows,
            'summary' => [],
        ];
    }

    private function turnout(Election $election): array
    {
        $s = $this->stats->summary($election);
        $rows = [];
        foreach ($this->stats->turnoutByCommand($election) as $r) {
            $rows[] = [$r['label'], $r['eligible'], $r['voted'], $r['eligible'] - $r['voted'], number_format($r['turnout'], 1).'%'];
        }

        return [
            'title' => 'Election Turnout',
            'subtitle' => $election->name,
            'headings' => ['Command', 'Eligible Voters', 'Votes Cast', 'Not Voted', 'Turnout %'],
            'rows' => $rows,
            'summary' => [
                'Eligible Voters' => number_format($s['eligible']),
                'Votes Cast' => number_format($s['voted']),
                'Not Voted' => number_format($s['not_voted']),
                'Turnout' => number_format($s['turnout'], 2).'%',
                'Status' => $election->status->label(),
            ],
        ];
    }

    private function resultsReport(Election $election): array
    {
        if (! $election->resultsVisibleToAdmins()) {
            throw ValidationException::withMessages(['election' => 'Results are not available until the election has closed.']);
        }
        $data = $this->results->stored($election) ?? $this->results->live($election);
        $provisional = $election->result_status->value !== 'PUBLISHED';

        $rows = [];
        foreach ($data['positions'] as $p) {
            foreach ($p['candidates'] as $c) {
                $rows[] = [
                    $p['name'],
                    $c['candidate']?->displayName(),
                    $c['votes'],
                    number_format($c['percentage'], 2).'%',
                    $c['is_tied'] && ! $p['resolution'] ? 'TIE' : ($c['is_winner'] ? 'Elected' : ''),
                ];
            }
            $rows[] = [$p['name'].': total valid votes', '', $p['total_valid_votes'], '', ''];
        }

        return [
            'title' => 'Election Results'.($provisional ? ' (PROVISIONAL, NOT PUBLISHED)' : ''),
            'subtitle' => $election->name,
            'headings' => ['Position', 'Candidate', 'Votes', 'Percentage', 'Outcome'],
            'rows' => $rows,
            'summary' => [
                'Ballots Cast' => number_format($data['ballots']),
                'Eligible Voters' => number_format($data['eligible']),
                'Turnout' => number_format($data['turnout'], 2).'%',
                'Result Status' => $election->result_status->label(),
                'Verification Hash' => $data['hash'],
            ],
        ];
    }

    private function audit(array $filters): array
    {
        $query = AuditLog::query()->orderBy('id');
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', to_utc_from_display($filters['from'].' 00:00'));
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', to_utc_from_display($filters['to'].' 00:00')->addDay());
        }

        $rows = (function () use ($query) {
            foreach ($query->lazyById(1000) as $log) {
                yield [display_time($log->created_at, 'Y-m-d H:i:s'), $log->actor_label ?? $log->actor_type, $log->action,
                    trim(($log->entity_type ?? '').' '.($log->entity_id ?? '')), $log->result->value, $log->ip];
            }
        })();

        return [
            'title' => 'Audit Report',
            'subtitle' => trim(($filters['from'] ?? 'Start').' to '.($filters['to'] ?? 'now')),
            'headings' => ['Timestamp (WAT)', 'Actor', 'Action', 'Entity', 'Result', 'IP'],
            'rows' => $rows,
            'summary' => [],
        ];
    }

    public static function eligibleStatuses(): array
    {
        return [EligibilityStatus::ELIGIBLE->value, EligibilityStatus::VOTED->value];
    }
}
