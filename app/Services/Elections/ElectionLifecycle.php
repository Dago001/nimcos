<?php

namespace App\Services\Elections;

use App\Enums\AuditResult;
use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Enums\VotingSessionStatus;
use App\Models\Election;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only code path that changes elections.status (spec §4, §28, §29).
 * The database trigger elections_status_guard enforces the same state machine.
 */
class ElectionLifecycle
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return list<string> problems preventing the election from being scheduled/opened */
    public function readinessProblems(Election $election): array
    {
        $problems = [];
        $positions = $election->electionPositions()->with('position')->withCount([
            'candidates as active_candidates_count' => fn ($q) => $q->where('status', CandidateStatus::ACTIVE->value),
        ])->get();

        if ($positions->isEmpty()) {
            $problems[] = 'No positions have been added to this election.';
        }
        foreach ($positions as $position) {
            if ($position->active_candidates_count === 0) {
                $problems[] = "{$position->position->name} has no active candidates.";
            }
        }
        if ($election->electionVoters()->where('eligibility_status', EligibilityStatus::ELIGIBLE->value)->doesntExist()) {
            $problems[] = 'No voters have been authorised for this election.';
        }
        if ($election->ends_at->lessThanOrEqualTo($election->starts_at)) {
            $problems[] = 'The voting end time must be after the start time.';
        }
        if ($election->ends_at->isPast()) {
            $problems[] = 'The voting end time is already in the past.';
        }

        return $problems;
    }

    public function schedule(Election $election, User $by): Election
    {
        return $this->transition($election, ElectionStatus::SCHEDULED, $by, function (Election $e) use ($by) {
            $problems = $this->readinessProblems($e);
            if ($e->starts_at->isPast()) {
                $problems[] = 'The voting start time is in the past. Update the schedule before publishing it.';
            }
            if ($problems !== []) {
                throw ValidationException::withMessages(['election' => $problems]);
            }
            $e->forceFill(['scheduled_at' => now(), 'scheduled_by' => $by->getKey()]);
        }, AuditAction::ELECTION_SCHEDULED);
    }

    public function unschedule(Election $election, User $by): Election
    {
        return $this->transition($election, ElectionStatus::DRAFT, $by, function (Election $e) {
            $e->forceFill(['scheduled_at' => null, 'scheduled_by' => null]);
        }, AuditAction::ELECTION_UNSCHEDULED);
    }

    /** Manual or automatic opening. Allowed from the start time until the end time. */
    public function open(Election $election, ?User $by): Election
    {
        return $this->transition($election, ElectionStatus::OPEN, $by, function (Election $e) use ($by) {
            $problems = $this->readinessProblems($e);
            if ($e->starts_at->isFuture() && $by === null) {
                $problems[] = 'The scheduled start time has not been reached.';
            }
            if ($problems !== []) {
                throw ValidationException::withMessages(['election' => $problems]);
            }
            if ($e->starts_at->isFuture()) {
                // An authorised official opened early: voting starts now.
                $e->starts_at = now();
            }
            $e->forceFill(['opened_at' => now(), 'opened_by' => $by?->getKey()]);
        }, AuditAction::ELECTION_OPENED);
    }

    /** Irreversible through the normal interface. In-flight ballots commit first (row lock). */
    public function close(Election $election, ?User $by): Election
    {
        $closed = $this->transition($election, ElectionStatus::CLOSED, $by, function (Election $e) use ($by) {
            $e->forceFill(['closed_at' => now(), 'closed_by' => $by?->getKey()]);
            // Record the actual end of voting when closed early. (If voting opened this very
            // second, keep the window as is: status CLOSED alone already stops ballots.)
            if ($e->ends_at->isFuture() && now()->timestamp > $e->starts_at->timestamp) {
                $e->ends_at = now();
            }
        }, AuditAction::ELECTION_CLOSED);

        DB::table('voting_sessions')
            ->whereIn('election_voter_id', fn ($q) => $q->select('id')->from('election_voters')->where('election_id', $closed->getKey()))
            ->where('status', VotingSessionStatus::ACTIVE->value)
            ->update(['status' => VotingSessionStatus::EXPIRED->value, 'ended_at' => now(), 'updated_at' => now()]);

        return $closed;
    }

    /** Extend voting time for an open or scheduled election. */
    public function extend(Election $election, CarbonInterface $newEndsAt, User $by): Election
    {
        if (! in_array($election->status, [ElectionStatus::OPEN, ElectionStatus::SCHEDULED], true)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Voting time can only be extended for open or scheduled elections.',
            ]);
        }

        if ($newEndsAt->lessThanOrEqualTo($election->ends_at)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The extended end time must be after the current closing time ('.display_time($election->ends_at, 'H:i, j M Y').' WAT).',
            ]);
        }

        if ($newEndsAt->isPast()) {
            throw ValidationException::withMessages([
                'ends_at' => 'The extended end time must be in the future.',
            ]);
        }

        $oldEndsAt = $election->ends_at;

        DB::transaction(function () use ($election, $newEndsAt, $oldEndsAt) {
            $election->ends_at = $newEndsAt;
            $election->save();

            // Give any active sessions that were capped at the previous ends_at the benefit of the extension
            $sessionTtl = (int) app(SettingsService::class)->get('voting_session_minutes');
            $maxExpiry = now()->addMinutes($sessionTtl);
            $cap = $maxExpiry->lessThan($newEndsAt) ? $maxExpiry : $newEndsAt;

            DB::table('voting_sessions')
                ->whereIn('election_voter_id', fn ($q) => $q->select('id')->from('election_voters')->where('election_id', $election->getKey()))
                ->where('status', VotingSessionStatus::ACTIVE->value)
                ->where('expires_at', '<=', $oldEndsAt)
                ->update([
                    'expires_at' => $cap,
                    'updated_at' => now(),
                ]);
        });

        $this->audit->log(AuditAction::ELECTION_EXTENDED, AuditResult::SUCCESS, $election, [
            'previous_ends_at' => $oldEndsAt->toIso8601String(),
            'new_ends_at' => $newEndsAt->toIso8601String(),
            'extended_by' => $by->name,
        ]);

        return $election;
    }

    public function archive(Election $election, User $by): Election
    {
        return $this->transition($election, ElectionStatus::ARCHIVED, $by, function (Election $e) {
            $e->forceFill(['archived_at' => now()]);
        }, AuditAction::ELECTION_ARCHIVED);
    }

    /**
     * Scheduler tick (every minute): auto-open due elections and auto-close expired ones.
     * Voting refuses ballots after ends_at even if this tick is late.
     *
     * @return array{opened: int, closed: int}
     */
    public function tick(): array
    {
        $opened = 0;
        $closed = 0;

        Election::query()
            ->where('status', ElectionStatus::OPEN->value)
            ->where('auto_close', true)
            ->where('ends_at', '<=', now())
            ->each(function (Election $e) use (&$closed) {
                $this->close($e, null);
                $closed++;
            });

        Election::query()
            ->where('status', ElectionStatus::SCHEDULED->value)
            ->where('auto_open', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->each(function (Election $e) use (&$opened) {
                try {
                    $this->open($e, null);
                    $opened++;
                } catch (ValidationException $ex) {
                    $this->audit->failure(AuditAction::ELECTION_OPENED, $e, ['problems' => $ex->errors()['election'] ?? []],
                        ['type' => 'SYSTEM', 'id' => null, 'label' => 'scheduler']);
                }
            });

        return ['opened' => $opened, 'closed' => $closed];
    }

    private function transition(Election $election, ElectionStatus $target, ?User $by, callable $mutate, string $action): Election
    {
        $result = DB::transaction(function () use ($election, $target, $mutate) {
            /** @var Election $locked */
            $locked = Election::query()->whereKey($election->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'election' => ["This election is {$locked->status->label()} and cannot be moved to {$target->label()}."],
                ]);
            }
            $from = $locked->status;
            $mutate($locked);
            $locked->status = $target;
            $locked->save();

            return [$locked, $from];
        });

        [$locked, $from] = $result;
        $this->audit->log($action, entity: $locked, metadata: [
            'from' => $from->value,
            'to' => $target->value,
            'automatic' => $by === null,
        ], actor: $by ? ['type' => 'ADMIN', 'id' => $by->getKey(), 'label' => $by->email] : ['type' => 'SYSTEM', 'id' => null, 'label' => 'scheduler']);

        return $locked;
    }
}
