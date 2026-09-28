<?php

namespace App\Services\Monitoring;

use App\Enums\AlertStatus;
use App\Enums\EligibilityStatus;
use App\Models\Election;
use App\Services\Audit\AuditAction;
use Illuminate\Support\Facades\DB;

/**
 * Turnout and operational statistics. Every figure comes from the identity side
 * (election_voters, voting_sessions, audit_logs), never from ballots or votes,
 * so nothing here can reveal how anyone voted (spec §19).
 */
class ElectionStatistics
{
    public function summary(Election $election): array
    {
        $counts = DB::table('election_voters')
            ->where('election_id', $election->getKey())
            ->selectRaw('eligibility_status, COUNT(*) AS n, MAX(voted_at) AS last_vote')
            ->groupBy('eligibility_status')
            ->get()
            ->keyBy('eligibility_status');

        $voted = (int) ($counts[EligibilityStatus::VOTED->value]->n ?? 0);
        $eligibleRemaining = (int) ($counts[EligibilityStatus::ELIGIBLE->value]->n ?? 0);
        $eligible = $voted + $eligibleRemaining;
        $lastVote = $counts[EligibilityStatus::VOTED->value]->last_vote ?? null;

        $sessions = DB::table('voting_sessions')
            ->join('election_voters', 'election_voters.id', '=', 'voting_sessions.election_voter_id')
            ->where('election_voters.election_id', $election->getKey())
            ->selectRaw("
                SUM(CASE WHEN voting_sessions.status = 'ACTIVE' AND voting_sessions.expires_at > NOW() THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN voting_sessions.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN voting_sessions.status = 'EXPIRED' OR (voting_sessions.status = 'ACTIVE' AND voting_sessions.expires_at <= NOW()) THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN voting_sessions.status = 'REVOKED' THEN 1 ELSE 0 END) AS revoked
            ")
            ->first();

        return [
            'status' => $election->status->value,
            'status_label' => $election->status->label(),
            'accepting_votes' => $election->isAcceptingVotes(),
            'eligible' => $eligible,
            'voted' => $voted,
            'not_voted' => $eligibleRemaining,
            'suspended' => (int) ($counts[EligibilityStatus::SUSPENDED->value]->n ?? 0),
            'turnout' => $eligible > 0 ? round($voted * 100 / $eligible, 2) : 0.0,
            'last_vote_at' => $lastVote ? display_time($lastVote, 'H:i:s') : null,
            'sessions' => [
                'active' => (int) ($sessions->active ?? 0),
                'completed' => (int) ($sessions->completed ?? 0),
                'expired' => (int) ($sessions->expired ?? 0),
                'revoked' => (int) ($sessions->revoked ?? 0),
            ],
            'server_time' => display_time(now(), 'H:i:s'),
        ];
    }

    /** Votes per hour in the operational timezone. @return list<array{label:string, value:int}> */
    public function hourlyActivity(Election $election): array
    {
        $tz = config('nimcos.display_timezone');

        return DB::table('election_voters')
            ->where('election_id', $election->getKey())
            ->whereNotNull('voted_at')
            ->selectRaw("date_trunc('hour', voted_at AT TIME ZONE ?) AS bucket, COUNT(*) AS n", [$tz])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($r) => ['label' => substr((string) $r->bucket, 11, 2).':00', 'value' => (int) $r->n])
            ->all();
    }

    /**
     * Turnout by command. Groups with fewer than $minGroup eligible voters are merged
     * into "Other commands" so small groups cannot be singled out.
     *
     * @return list<array{label:string, eligible:int, voted:int, turnout:float}>
     */
    public function turnoutByCommand(Election $election, int $minGroup = 5): array
    {
        $rows = DB::table('election_voters')
            ->join('voters', 'voters.id', '=', 'election_voters.voter_id')
            ->where('election_voters.election_id', $election->getKey())
            ->whereIn('election_voters.eligibility_status', [EligibilityStatus::ELIGIBLE->value, EligibilityStatus::VOTED->value])
            ->selectRaw("COALESCE(voters.command, 'UNSPECIFIED') AS label, COUNT(*) AS eligible, SUM(CASE WHEN election_voters.eligibility_status = 'VOTED' THEN 1 ELSE 0 END) AS voted")
            ->groupBy('label')
            ->orderByDesc('eligible')
            ->get();

        $out = [];
        $other = ['label' => 'Other commands', 'eligible' => 0, 'voted' => 0];
        foreach ($rows as $r) {
            if ((int) $r->eligible < $minGroup) {
                $other['eligible'] += (int) $r->eligible;
                $other['voted'] += (int) $r->voted;

                continue;
            }
            $out[] = ['label' => $r->label, 'eligible' => (int) $r->eligible, 'voted' => (int) $r->voted];
        }
        if ($other['eligible'] > 0) {
            $out[] = $other;
        }

        return array_map(fn ($r) => $r + ['turnout' => $r['eligible'] > 0 ? round($r['voted'] * 100 / $r['eligible'], 1) : 0.0], $out);
    }

    /** Security signals for the last hour across the platform. */
    public function securitySignals(): array
    {
        $since = now()->subHour();

        return [
            'failed_otp_last_hour' => DB::table('audit_logs')->where('action', AuditAction::OTP_FAILED)->where('created_at', '>=', $since)->count(),
            'failed_lookups_last_hour' => DB::table('audit_logs')->where('action', AuditAction::VOTER_LOOKUP_FAILED)->where('created_at', '>=', $since)->count(),
            'failed_admin_logins_last_hour' => DB::table('audit_logs')->where('action', AuditAction::ADMIN_LOGIN_FAILED)->where('created_at', '>=', $since)->count(),
            'open_alerts' => DB::table('security_alerts')->where('status', AlertStatus::OPEN->value)->count(),
            'high_alerts' => DB::table('security_alerts')->where('status', AlertStatus::OPEN->value)->whereIn('severity', ['HIGH', 'CRITICAL'])->count(),
        ];
    }
}
