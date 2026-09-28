<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlertStatus;
use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\SecurityAlert;
use App\Models\Voter;
use App\Services\Monitoring\ElectionStatistics;
use App\Services\Results\ResultsPresenter;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Answers at a glance (spec §51): Is the election open? Eligible? Voted? Turnout?
 * Security issues? Active sessions? Failed authentications? Closed? Results ready?
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ElectionStatistics $stats,
        private readonly ResultsPresenter $results,
    ) {}

    public function index(Request $request): View
    {
        $focus = $this->focusElection();

        return view('admin.dashboard', [
            'focus' => $focus,
            'summary' => $focus ? $this->stats->summary($focus) : null,
            'hourly' => $focus ? $this->stats->hourlyActivity($focus) : [],
            'tally' => $this->tally($request, $focus),
            'security' => $this->stats->securitySignals(),
            'elections' => Election::query()->where('status', '!=', ElectionStatus::ARCHIVED->value)->orderByDesc('starts_at')->limit(6)->get(),
            'registerTotal' => Voter::query()->count(),
            'alerts' => SecurityAlert::query()->where('status', AlertStatus::OPEN->value)->orderByDesc('last_seen_at')->limit(5)->get(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $focus = $this->focusElection();
        $tally = $this->tally($request, $focus);

        return response()->json([
            'summary' => $focus ? $this->stats->summary($focus) : null,
            'hourly' => $focus ? $this->stats->hourlyActivity($focus) : [],
            'tally' => $tally ? $this->results->liveTallyPayload($tally) : null,
            'security' => $this->stats->securitySignals(),
        ]);
    }

    /**
     * Running count per contestant, for officials who may view results, once voting has
     * started and only where the election allows live results (or after it closes).
     */
    private function tally(Request $request, ?Election $focus): ?array
    {
        if (! $focus || ! $request->user()->hasPermission(Permissions::VIEW_RESULTS) || ! $focus->resultsVisibleToAdmins()) {
            return null;
        }

        return $this->results->liveTally($focus);
    }

    /** The open election, else the nearest scheduled one, else the most recently closed. */
    private function focusElection(): ?Election
    {
        return Election::query()->where('status', ElectionStatus::OPEN->value)->orderBy('ends_at')->first()
            ?? Election::query()->where('status', ElectionStatus::SCHEDULED->value)->orderBy('starts_at')->first()
            ?? Election::query()->whereIn('status', [ElectionStatus::CLOSED->value, ElectionStatus::RESULTS_PUBLISHED->value])->orderByDesc('closed_at')->first()
            ?? Election::query()->where('status', ElectionStatus::DRAFT->value)->latest()->first();
    }
}
