<?php

namespace App\Services\Voting;

use App\Enums\AlertSeverity;
use App\Enums\AuditResult;
use App\Enums\EligibilityStatus;
use App\Enums\VotingSessionStatus;
use App\Models\BallotToken;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\VotingSession;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SecurityAlertService;
use App\Services\Settings\SettingsService;
use App\Services\Voting\Exceptions\VotingException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ballot sessions. Starting one issues two independent secrets:
 *  - a session secret (kept in the server-side Laravel session), bound to the voter;
 *  - a ballot token (sent ONLY to the voter's browser in an encrypted cookie), bound to
 *    nothing but the election. Only its SHA-256 hash is stored in ballot_tokens.
 */
class VotingSessionService
{
    public const SESSION_ID_KEY = 'voting.session_id';

    public const SESSION_SECRET_KEY = 'voting.session_secret';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{session: VotingSession, session_secret: string, ballot_token: string}
     *
     * @throws VotingException
     */
    public function start(ElectionVoter $electionVoter, Request $request): array
    {
        $result = DB::transaction(function () use ($electionVoter, $request) {
            $election = Election::query()->whereKey($electionVoter->election_id)->sharedLock()->firstOrFail();
            if (! $election->isAcceptingVotes()) {
                throw VotingException::electionNotOpen();
            }

            /** @var ElectionVoter $ev */
            $ev = ElectionVoter::query()->whereKey($electionVoter->getKey())->lockForUpdate()->firstOrFail();
            if ($ev->eligibility_status === EligibilityStatus::VOTED) {
                throw VotingException::alreadyVoted();
            }
            if ($ev->eligibility_status !== EligibilityStatus::ELIGIBLE) {
                throw VotingException::notEligible();
            }

            $revoked = VotingSession::query()
                ->where('election_voter_id', $ev->getKey())
                ->where('status', VotingSessionStatus::ACTIVE->value)
                ->update(['status' => VotingSessionStatus::REVOKED->value, 'ended_at' => now(), 'updated_at' => now()]);

            $sessionSecret = bin2hex(random_bytes(32));
            $ballotToken = bin2hex(random_bytes(32));
            $minutes = max(5, (int) $this->settings->get('voting_session_minutes'));

            $session = new VotingSession;
            $session->forceFill([
                'election_voter_id' => $ev->getKey(),
                'token_hash' => hash('sha256', $sessionSecret),
                'status' => VotingSessionStatus::ACTIVE,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'started_at' => now(),
                'last_activity_at' => now(),
                'expires_at' => $this->expiry($minutes, $election),
            ])->save();

            $token = new BallotToken;
            $token->forceFill([
                'election_id' => $election->getKey(),
                'token_hash' => hash('sha256', $ballotToken),
                'is_consumed' => false,
            ])->save();

            return compact('session', 'sessionSecret', 'ballotToken', 'revoked', 'ev');
        });

        if ($result['revoked'] > 0) {
            $this->audit->log(AuditAction::VOTING_SESSION_REVOKED, AuditResult::SUCCESS, $result['ev'], ['revoked' => $result['revoked']]);
            $this->alerts->raise(
                SecurityAlertService::MULTIPLE_SESSIONS,
                AlertSeverity::LOW,
                'A voter started a new ballot session while another was active; the earlier session was revoked.',
                $request->ip(),
                SecurityAlertService::maskServiceNumber($result['ev']->voter->service_number),
                ['revoked_sessions' => $result['revoked']],
                'ev:'.$result['ev']->getKey(),
            );
        }

        $this->audit->log(AuditAction::VOTING_SESSION_STARTED, AuditResult::SUCCESS, $result['session']);

        return [
            'session' => $result['session'],
            'session_secret' => $result['sessionSecret'],
            'ballot_token' => $result['ballotToken'],
        ];
    }

    /** Resolve and refresh the voting session bound to this browser session, or null. */
    public function current(Request $request, bool $touch = true): ?VotingSession
    {
        $id = $request->session()->get(self::SESSION_ID_KEY);
        $secret = $request->session()->get(self::SESSION_SECRET_KEY);
        if (! is_string($id) || ! is_string($secret)) {
            return null;
        }

        $session = VotingSession::query()->with('electionVoter.election')->find($id);
        if (! $session || ! hash_equals($session->token_hash, hash('sha256', $secret))) {
            return null;
        }

        $voterId = $request->user('voter')?->getKey();
        if ($voterId === null || $session->electionVoter->voter_id !== $voterId) {
            return null;
        }

        if ($session->status === VotingSessionStatus::ACTIVE && $session->expires_at->isPast()) {
            $session->forceFill(['status' => VotingSessionStatus::EXPIRED, 'ended_at' => now()])->save();
        }

        if ($touch && $session->status === VotingSessionStatus::ACTIVE) {
            $minutes = max(5, (int) $this->settings->get('voting_session_minutes'));
            $session->forceFill([
                'last_activity_at' => now(),
                'expires_at' => $this->expiry($minutes, $session->electionVoter->election),
            ])->save();
        }

        return $session;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget([self::SESSION_ID_KEY, self::SESSION_SECRET_KEY]);
    }

    /** Scheduler: mark idle sessions as expired. */
    public function expireStale(): int
    {
        return VotingSession::query()
            ->where('status', VotingSessionStatus::ACTIVE->value)
            ->where('expires_at', '<', now())
            ->update(['status' => VotingSessionStatus::EXPIRED->value, 'ended_at' => now(), 'updated_at' => now()]);
    }

    private function expiry(int $minutes, Election $election): \DateTimeInterface
    {
        $idle = now()->addMinutes($minutes);

        return $idle->lessThan($election->ends_at) ? $idle : $election->ends_at;
    }
}
