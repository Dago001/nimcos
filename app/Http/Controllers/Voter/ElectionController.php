<?php

namespace App\Http\Controllers\Voter;

use App\Http\Controllers\Controller;
use App\Models\ElectionVoter;
use App\Models\Voter;
use App\Services\Voting\BallotDefinition;
use App\Services\Voting\BallotSubmissionService;
use App\Services\Voting\Exceptions\VotingException;
use App\Services\Voting\VoterAccessService;
use App\Services\Voting\VotingSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class ElectionController extends Controller
{
    public function __construct(
        private readonly VoterAccessService $access,
        private readonly VotingSessionService $sessions,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $participations = $this->access->participations($this->voter($request));

        if ($participations->count() === 1) {
            return redirect()->route('voter.election', $participations->first()->election->code);
        }

        return view('voter.elections', ['participations' => $participations, 'voter' => $this->voter($request)]);
    }

    public function show(Request $request, string $code): View|RedirectResponse
    {
        $ev = $this->participation($request, $code);
        if ($ev->hasVoted()) {
            return redirect()->route('voter.already-voted', $code);
        }
        if (! $ev->canVote()) {
            return redirect()->route('voter.elections')->with('error', VotingException::notEligible()->getMessage());
        }

        return view('voter.election', [
            'election' => $ev->election,
            'voter' => $this->voter($request),
            'positionCount' => BallotDefinition::for($ev->election)->count(),
            'acceptingVotes' => $ev->election->isAcceptingVotes(),
        ]);
    }

    public function start(Request $request, string $code): RedirectResponse
    {
        $ev = $this->participation($request, $code);

        try {
            $started = $this->sessions->start($ev, $request);
        } catch (VotingException $e) {
            if ($e->reason === VotingException::ALREADY_VOTED) {
                return redirect()->route('voter.already-voted', $code);
            }

            return redirect()->route('voter.election', $code)->with('error', $e->getMessage());
        }

        $request->session()->put(VotingSessionService::SESSION_ID_KEY, $started['session']->getKey());
        $request->session()->put(VotingSessionService::SESSION_SECRET_KEY, $started['session_secret']);
        $request->session()->put('voting.election_code', $ev->election->code);

        // The ballot token lives only in the voter's browser (encrypted, HttpOnly, SameSite=Strict).
        $minutes = (int) max(60, now()->diffInMinutes($ev->election->ends_at) + 24 * 60);
        Cookie::queue(Cookie::make(
            config('nimcos.voting_session.ballot_cookie'),
            $started['ballot_token'],
            $minutes,
            '/',
            null,
            config('session.secure'),
            true,
            false,
            'strict',
        ));

        return redirect()->route('voter.ballot');
    }

    public function alreadyVoted(Request $request, string $code, BallotSubmissionService $submissions): View
    {
        $ev = $this->participation($request, $code);
        abort_unless($ev->hasVoted(), 404);

        return view('voter.already-voted', [
            'election' => $ev->election,
            'votedAt' => $ev->voted_at,
            'receipt' => $submissions->receiptFor($ev, $request->cookie(config('nimcos.voting_session.ballot_cookie'))),
        ]);
    }

    private function participation(Request $request, string $code): ElectionVoter
    {
        $ev = $this->access->participationFor($this->voter($request), $code);
        abort_if($ev === null, 404);

        return $ev;
    }

    private function voter(Request $request): Voter
    {
        return $request->user('voter');
    }
}
