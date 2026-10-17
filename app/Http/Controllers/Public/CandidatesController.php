<?php

namespace App\Http\Controllers\Public;

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
        $isAdmin = $request->user('web') !== null;

        $electionsQuery = Election::query();
        if (! $isAdmin) {
            $electionsQuery->whereIn('status', [
                ElectionStatus::SCHEDULED->value,
                ElectionStatus::OPEN->value,
                ElectionStatus::CLOSED->value,
                ElectionStatus::RESULTS_PUBLISHED->value,
            ]);
        }

        $elections = $electionsQuery
            ->orderByRaw("CASE 
                WHEN status = '".ElectionStatus::OPEN->value."' THEN 1 
                WHEN status = '".ElectionStatus::SCHEDULED->value."' THEN 2 
                WHEN status = '".ElectionStatus::DRAFT->value."' THEN 3 
                WHEN status = '".ElectionStatus::CLOSED->value."' THEN 4 
                WHEN status = '".ElectionStatus::RESULTS_PUBLISHED->value."' THEN 5 
                ELSE 6 END")
            ->orderBy('starts_at')
            ->get();

        $selectedCode = $request->query('election');
        $selectedElection = null;

        if ($selectedCode) {
            $selectedElection = $elections->firstWhere('code', $selectedCode);
        }

        if (! $selectedElection) {
            $selectedElection = $elections->first();
        }

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
        }

        return view('public.candidates', [
            'elections' => $elections,
            'selectedElection' => $selectedElection,
        ]);
    }
}
