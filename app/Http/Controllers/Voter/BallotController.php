<?php

namespace App\Http\Controllers\Voter;

use App\Http\Controllers\Controller;
use App\Models\ElectionVoter;
use App\Models\VotingSession;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Voting\BallotDefinition;
use App\Services\Voting\BallotSubmissionService;
use App\Services\Voting\BallotValidator;
use App\Services\Voting\Exceptions\VotingException;
use App\Services\Voting\VotingSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ballot → Review → Submit → Receipt.
 * Selections travel only in the voter's own form posts; they are never stored
 * server-side before the final, anonymous submission.
 */
class BallotController extends Controller
{
    public function __construct(
        private readonly BallotValidator $validator,
        private readonly VotingSessionService $sessions,
        private readonly BallotSubmissionService $submissions,
    ) {}

    public function show(Request $request): View
    {
        return $this->ballotView($this->session($request), []);
    }

    public function review(Request $request): View
    {
        $session = $this->session($request);
        $ballot = BallotDefinition::for($session->electionVoter->election);
        $raw = $request->input('selections', []);

        try {
            $selections = $this->validator->validate($ballot, $raw, true);
        } catch (VotingException $e) {
            return $this->ballotView($session, is_array($raw) ? $raw : [], $e->fieldErrors, $e->getMessage());
        }

        return view('voter.review', [
            'election' => $ballot->election,
            'ballot' => $ballot,
            'selections' => $selections,
            'expiresAt' => $session->expires_at,
        ]);
    }

    public function edit(Request $request): View
    {
        $raw = $request->input('selections', []);

        return $this->ballotView($this->session($request), is_array($raw) ? $raw : []);
    }

    public function submit(Request $request): View|RedirectResponse|Response
    {
        $session = $this->sessions->current($request, false);
        if (! $session) {
            return redirect()->route('voter.session-expired');
        }

        try {
            $this->submissions->submit(
                $session,
                $request->cookie(config('nimcos.voting_session.ballot_cookie')),
                $request->input('selections', []),
                $request,
            );
        } catch (VotingException $e) {
            return match ($e->reason) {
                VotingException::INVALID_BALLOT => $this->ballotView($session, (array) $request->input('selections', []), $e->fieldErrors, $e->getMessage()),
                VotingException::ALREADY_VOTED => redirect()->route('voter.already-voted', $session->electionVoter->election->code),
                VotingException::ELECTION_NOT_OPEN, VotingException::NOT_ELIGIBLE => redirect()
                    ->route('voter.elections')->with('error', $e->getMessage()),
                default => redirect()->route('voter.session-expired'),
            };
        }

        // 303: the browser must not re-POST the ballot when the receipt is refreshed.
        return redirect()->route('voter.receipt', status: 303);
    }

    public function receipt(Request $request): View|RedirectResponse
    {
        $code = $request->session()->get('voting.election_code');
        $ev = $code ? ElectionVoter::query()->with('election')
            ->where('voter_id', $request->user('voter')->getKey())
            ->whereHas('election', fn ($q) => $q->where('code', $code))
            ->first() : null;

        if (! $ev || ! $ev->hasVoted()) {
            return redirect()->route('voter.elections');
        }

        return view('voter.receipt', [
            'election' => $ev->election,
            'votedAt' => $ev->voted_at,
            'receipt' => $this->submissions->receiptFor($ev, $request->cookie(config('nimcos.voting_session.ballot_cookie'))),
        ]);
    }

    public function finish(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->log(AuditAction::VOTER_LOGOUT);
        $this->sessions->forget($request);
        Auth::guard('voter')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(config('nimcos.voting_session.ballot_cookie')));

        return redirect()->route('home')->with('status', 'Thank you for voting. You have been signed out.');
    }

    private function session(Request $request): VotingSession
    {
        return $request->attributes->get('voting_session');
    }

    /**
     * @param  array<string, mixed>  $raw  previously chosen values (re-displayed only if they are valid options)
     * @param  array<string, string>  $errors
     */
    private function ballotView(VotingSession $session, array $raw, array $errors = [], ?string $message = null): View
    {
        $ballot = BallotDefinition::for($session->electionVoter->election);

        $selected = [];
        foreach ($ballot->positions as $position) {
            $value = $raw[$position->id] ?? [];
            foreach ((array) $value as $candidateId) {
                if (is_string($candidateId) && $ballot->candidate($position->id, $candidateId)) {
                    $selected[$position->id][] = $candidateId;
                }
            }
        }

        return view('voter.ballot', [
            'election' => $ballot->election,
            'ballot' => $ballot,
            'selected' => $selected,
            'fieldErrors' => $errors,
            'message' => $message,
            'expiresAt' => $session->expires_at,
        ]);
    }
}
