<?php

namespace App\Http\Middleware;

use App\Enums\VotingSessionStatus;
use App\Services\Voting\VotingSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ballot pages require a live voting session bound to this browser and voter. */
class RequireVotingSession
{
    public function __construct(private readonly VotingSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessions->current($request);

        if (! $session) {
            return redirect()->route('voter.elections')->with('error', 'Please start your ballot from the election page.');
        }

        if ($session->status !== VotingSessionStatus::ACTIVE) {
            if ($session->electionVoter->hasVoted()) {
                return redirect()->route('voter.receipt');
            }
            $this->sessions->forget($request);

            return redirect()->route('voter.session-expired');
        }

        $request->attributes->set('voting_session', $session);

        return $next($request);
    }
}
