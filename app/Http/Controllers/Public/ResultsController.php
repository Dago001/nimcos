<?php

namespace App\Http\Controllers\Public;

use App\Enums\ElectionStatus;
use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\Results\ResultsPresenter;
use Illuminate\View\View;

/** Officially published results only. */
class ResultsController extends Controller
{
    public function index(): View
    {
        $elections = Election::query()
            ->where('result_status', ResultStatus::PUBLISHED->value)
            ->whereIn('status', [ElectionStatus::RESULTS_PUBLISHED->value, ElectionStatus::ARCHIVED->value])
            ->orderByDesc('published_at')
            ->get();

        return view('public.results-index', ['elections' => $elections]);
    }

    public function show(string $code, ResultsPresenter $presenter): View
    {
        $election = Election::query()
            ->where('code', $code)
            ->where('result_status', ResultStatus::PUBLISHED->value)
            ->firstOrFail();

        return view('public.results', ['results' => $presenter->stored($election), 'election' => $election]);
    }
}
