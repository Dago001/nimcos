<?php

namespace App\Http\Controllers\Public;

use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\View\View;

/** Public landing page: what is happening, how to vote, and where to get help. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $open = Election::query()->acceptingVotes()->withCount('electionPositions')->orderBy('ends_at')->get();

        return view('public.home', [
            'openElections' => $open,
            'nextElection' => $open->isEmpty()
                ? Election::query()->where('status', ElectionStatus::SCHEDULED->value)->where('starts_at', '>', now())->orderBy('starts_at')->first()
                : null,
            'latestResults' => Election::query()->where('status', ElectionStatus::RESULTS_PUBLISHED->value)->orderByDesc('published_at')->first(),
        ]);
    }
}
