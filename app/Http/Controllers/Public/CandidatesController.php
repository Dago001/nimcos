<?php

namespace App\Http\Controllers\Public;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public showcase of screened and cleared candidates.
 * Voters can view candidate photographs, names, contested positions,
 * NIS ranks, NIS commands, and biographies before and during elections.
 */
class CandidatesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $selectedCode = $request->query('election');
        $isAdmin = $request->user('web') !== null;

        // Retrieve elections, prioritizing those that have screened candidates
        $electionsQuery = Election::query()
            ->where('status', '!=', ElectionStatus::ARCHIVED->value)
            ->withCount(['candidates' => function ($q) {
                $q->where('status', CandidateStatus::ACTIVE->value);
            }]);

        // Non-admin visitors see active/scheduled elections, or draft elections with cleared candidates
        if (! $isAdmin) {
            $electionsQuery->where(function ($q) {
                $q->whereIn('status', [
                    ElectionStatus::SCHEDULED->value,
                    ElectionStatus::OPEN->value,
                    ElectionStatus::CLOSED->value,
                    ElectionStatus::RESULTS_PUBLISHED->value,
                ])->orWhere(function ($dq) {
                    $dq->where('status', ElectionStatus::DRAFT->value)
                        ->whereHas('candidates', function ($cq) {
                            $cq->where('status', CandidateStatus::ACTIVE->value);
                        });
                });
            });
        }

        $elections = $electionsQuery
            ->orderByRaw("CASE 
                WHEN status = '".ElectionStatus::OPEN->value."' THEN 1 
                WHEN status = '".ElectionStatus::SCHEDULED->value."' THEN 2 
                WHEN status = '".ElectionStatus::DRAFT->value."' THEN 3 
                WHEN status = '".ElectionStatus::CLOSED->value."' THEN 4 
                ELSE 5 END")
            ->orderBy('starts_at')
            ->get();

        $selectedElection = null;

        if ($selectedCode) {
            $selectedElection = $elections->firstWhere('code', $selectedCode);
        }

        // Default to the first election with screened candidates, or active/scheduled election
        if (! $selectedElection) {
            $selectedElection = $elections->firstWhere('candidates_count', '>', 0)
                ?? $elections->firstWhere('status', ElectionStatus::OPEN)
                ?? $elections->firstWhere('status', ElectionStatus::SCHEDULED)
                ?? $elections->first();
        }

        $totalClearedCandidates = 0;
        $totalPositions = 0;

        if ($selectedElection) {
            $selectedElection->load([
                'electionPositions' => function ($query) {
                    $query->orderBy('display_order')->with([
                        'position',
                        'activeCandidates' => function ($cq) {
                            $cq->orderBy('display_order')->orderBy('candidate_number');
                        },
                    ]);
                },
            ]);

            $totalPositions = $selectedElection->electionPositions->count();
            $totalClearedCandidates = $selectedElection->electionPositions->sum(fn ($ep) => $ep->activeCandidates->count());
        }

        return view('public.candidates', [
            'elections' => $elections,
            'selectedElection' => $selectedElection,
            'totalPositions' => $totalPositions,
            'totalClearedCandidates' => $totalClearedCandidates,
        ]);
    }
}
