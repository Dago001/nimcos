<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Enums\NisCommand;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Support\ServiceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Election-specific voter roll (spec §7). A voter may be ELIGIBLE, INELIGIBLE,
 * SUSPENDED or VOTED per election; VOTED is set only by the voting engine.
 */
class EligibilityController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Election $election): View
    {
        $query = ElectionVoter::query()->where('election_voters.election_id', $election->getKey())
            ->join('voters', 'voters.id', '=', 'election_voters.voter_id')
            ->select('election_voters.*')
            ->with('voter');

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn ($q) => $q->where('voters.service_number', $likeOp, $like)->orWhere('voters.surname', $likeOp, $like));
        }
        if ($status = EligibilityStatus::tryFrom((string) $request->query('status'))) {
            $query->where('election_voters.eligibility_status', $status->value);
        }
        if ($command = $request->query('command')) {
            $query->where('voters.command', $command);
        }

        $counts = ElectionVoter::query()->where('election_id', $election->getKey())
            ->selectRaw('eligibility_status, COUNT(*) AS n')->groupBy('eligibility_status')->pluck('n', 'eligibility_status');

        return view('admin.elections.eligibility', [
            'election' => $election,
            'rows' => $query->orderBy('voters.surname')->paginate(50)->withQueryString(),
            'counts' => $counts,
            'authorisable' => Voter::query()->authorisable()
                ->whereNotIn('id', ElectionVoter::query()->where('election_id', $election->getKey())->select('voter_id'))->count(),
            'commands' => NisCommand::grouped(),
            'filters' => $request->query(),
            'canGrant' => $election->status->isEligibilityEditable(),
        ]);
    }

    /** Add every verified, active member who is not yet on this election's roll. */
    public function authoriseAll(Request $request, Election $election): RedirectResponse
    {
        $this->ensureGrantable($election);
        $userId = $request->user()->getKey();

        $added = DB::transaction(function () use ($election, $userId) {
            $added = 0;
            Voter::query()->authorisable()
                ->whereNotIn('id', ElectionVoter::query()->where('election_id', $election->getKey())->select('voter_id'))
                ->select('id')
                ->chunkById(1000, function ($voters) use ($election, $userId, &$added) {
                    $now = now();
                    $rows = $voters->map(fn ($v) => [
                        'id' => (string) Str::orderedUuid(),
                        'election_id' => $election->getKey(),
                        'voter_id' => $v->id,
                        'eligibility_status' => EligibilityStatus::ELIGIBLE->value,
                        'authorized_at' => $now,
                        'authorized_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();
                    $added += DB::table('election_voters')->insertOrIgnore($rows);
                });

            return $added;
        });

        $this->audit->log(AuditAction::ELIGIBILITY_GRANTED, AuditResult::SUCCESS, $election, ['bulk' => true, 'added' => $added]);

        return back()->with('success', "{$added} verified member(s) authorised to vote in this election.");
    }

    /** Add a single voter by Service Number. */
    public function store(Request $request, Election $election): RedirectResponse
    {
        $this->ensureGrantable($election);
        $data = $request->validate(['service_number' => ['required', 'string', 'max:30']]);
        $voter = Voter::query()->where('service_number', ServiceNumber::normalise($data['service_number']))->first();

        if (! $voter) {
            throw ValidationException::withMessages(['service_number' => 'No voter with that Service Number is on the register.']);
        }
        if (! $voter->isEligibleForAuthorisation()) {
            throw ValidationException::withMessages(['service_number' => 'This voter cannot be authorised: the record must be verified, active and marked eligible on the register.']);
        }

        $ev = ElectionVoter::query()->where('election_id', $election->getKey())->where('voter_id', $voter->getKey())->first() ?? new ElectionVoter;
        if ($ev->exists && $ev->eligibility_status === EligibilityStatus::VOTED) {
            throw ValidationException::withMessages(['service_number' => 'This voter has already voted.']);
        }
        $ev->forceFill([
            'election_id' => $election->getKey(),
            'voter_id' => $voter->getKey(),
            'eligibility_status' => EligibilityStatus::ELIGIBLE,
            'eligibility_reason' => null,
            'authorized_at' => now(),
            'authorized_by' => $request->user()->getKey(),
        ])->save();

        $this->audit->log(AuditAction::ELIGIBILITY_GRANTED, AuditResult::SUCCESS, $ev, ['service_number' => $voter->service_number]);

        return back()->with('success', "{$voter->fullName()} is authorised to vote in this election.");
    }

    public function update(Request $request, Election $election, ElectionVoter $electionVoter): RedirectResponse
    {
        abort_unless($electionVoter->election_id === $election->getKey(), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in([EligibilityStatus::ELIGIBLE->value, EligibilityStatus::INELIGIBLE->value, EligibilityStatus::SUSPENDED->value])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $target = EligibilityStatus::from($data['status']);

        if ($electionVoter->hasVoted()) {
            throw ValidationException::withMessages(['status' => 'This voter has already voted; their participation record is final.']);
        }
        if ($election->status->hasClosed()) {
            throw ValidationException::withMessages(['status' => 'The election has closed; the roll is final.']);
        }
        // While voting is open, eligibility can only be withdrawn (suspension), never newly granted.
        if ($election->status === ElectionStatus::OPEN && $target === EligibilityStatus::ELIGIBLE) {
            throw ValidationException::withMessages(['status' => 'Eligibility cannot be granted or restored while voting is open.']);
        }
        if ($target === EligibilityStatus::ELIGIBLE && ! $electionVoter->voter->isEligibleForAuthorisation()) {
            throw ValidationException::withMessages(['status' => 'The register record must be verified, active and eligible first.']);
        }

        $from = $electionVoter->eligibility_status;
        $electionVoter->forceFill(['eligibility_status' => $target, 'eligibility_reason' => $data['reason']])->save();

        $this->audit->log(AuditAction::ELIGIBILITY_CHANGED, AuditResult::SUCCESS, $electionVoter, [
            'service_number' => $electionVoter->voter->service_number,
            'from' => $from->value,
            'to' => $target->value,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Eligibility updated.');
    }

    private function ensureGrantable(Election $election): void
    {
        if (! $election->status->isEligibilityEditable()) {
            throw ValidationException::withMessages(['election' => 'Voters can only be authorised before the election opens.']);
        }
    }
}
