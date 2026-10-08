<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ResultStatus;
use App\Enums\TieResolutionMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReauthenticatedRequest;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Services\Auth\Reauthenticator;
use App\Services\Results\ResultsCalculator;
use App\Services\Results\ResultsPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CLOSE → CALCULATE → VERIFY → (RESOLVE TIES) → PUBLISH. There is no route that
 * accepts a vote count (spec §21).
 */
class ResultController extends Controller
{
    public function __construct(
        private readonly ResultsCalculator $calculator,
        private readonly ResultsPresenter $presenter,
    ) {}

    public function show(Request $request, Election $election): View
    {
        $visible = $election->resultsVisibleToAdmins();
        $results = null;
        $live = false;

        if ($visible) {
            $results = $election->result_status === ResultStatus::NOT_CALCULATED ? null : $this->presenter->stored($election);
            if (! $results && $election->interim_results_enabled) {
                $results = $this->presenter->live($election);
                $live = true;
            }
        }

        return view('admin.results.show', [
            'election' => $election,
            'visible' => $visible,
            'results' => $results,
            'live' => $live,
            'blockers' => $election->status->hasClosed() ? $this->calculator->publicationBlockers($election) : [],
            'canPublish' => $request->user()->hasPermission('publish_results'),
            'methods' => TieResolutionMethod::cases(),
        ]);
    }

    public function calculate(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $computed = $this->calculator->calculate($election, $request->user());
        $ties = count(array_filter($computed['positions'], fn ($p) => $p['has_tie']));

        return back()->with('success', 'Results calculated from '.number_format($computed['ballots']).' ballot(s).'
            .($ties ? " {$ties} position(s) are tied and need resolution." : '').' Verify the results next.');
    }

    public function verify(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $problems = $this->calculator->verify($election, $request->user());

        return $problems === []
            ? back()->with('success', 'Verification passed: an independent recount matches the stored tallies and every integrity check succeeded.')
            : back()->with('error', 'Verification FAILED: '.implode(' ', $problems));
    }

    public function resolveTie(Request $request, Election $election, ElectionPosition $electionPosition): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(TieResolutionMethod::class)],
            'winning_candidate_id' => ['nullable', 'uuid'],
            'notes' => ['required', 'string', 'min:10', 'max:2000'],
            'confirm_password' => ['required', 'string'],
            'confirm_mfa' => [$request->user()->hasMfa() ? 'required' : 'nullable', 'string'],
        ], ['notes.required' => 'Record how the tie was resolved under the NIMCOS election rules (e.g. minutes reference).']);

        app(Reauthenticator::class)->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, 'resolve_tie');

        $this->calculator->resolveTie($election, $electionPosition, TieResolutionMethod::from($data['method']),
            $data['winning_candidate_id'] ?? null, $data['notes'], $request->user());

        return back()->with('success', 'Tie resolution recorded for '.$electionPosition->position->name.'.');
    }

    public function publish(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $request->validate([
            'signature' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'returning_officer_name' => ['nullable', 'string', 'max:150'],
        ]);

        $signaturePath = null;
        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            $filename = 'signature_'.$election->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $signaturePath = $file->storeAs('signatures', $filename, 'public');
        }

        $this->calculator->publish(
            $election,
            $request->user(),
            $signaturePath,
            $request->input('returning_officer_name')
        );

        return back()->with('success', 'Results have been officially PUBLISHED and are now frozen.');
    }
}
