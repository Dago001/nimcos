<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\AuditResult;
use App\Enums\EligibilityStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Enums\VerificationStatus;
use App\Enums\VoterEligibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoterRequest;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoterController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return $this->listing($request, false);
    }

    public function suspended(Request $request): View
    {
        return $this->listing($request, true);
    }

    private function listing(Request $request, bool $suspendedOnly): View
    {
        $elections = Election::query()->orderByDesc('starts_at')->get(['id', 'name', 'code', 'starts_at']);
        $query = Voter::query();

        if ($suspendedOnly) {
            $query->where('account_status', '!=', AccountStatus::ACTIVE->value);
        }
        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn (Builder $q) => $q->where('service_number', $likeOp, $like)->orWhere('surname', $likeOp, $like)->orWhere('first_name', $likeOp, $like));
        }
        foreach (['rank', 'command', 'formation'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }
        if ($eligibility = VoterEligibility::tryFrom((string) $request->query('eligibility'))) {
            $query->where('eligibility_status', $eligibility->value);
        }
        if ($verification = VerificationStatus::tryFrom((string) $request->query('verification'))) {
            $query->where('verification_status', $verification->value);
        }

        // Voting status is election-specific.
        $election = $elections->firstWhere('id', $request->query('election'));
        if ($election && ($votingStatus = $request->query('voting'))) {
            $ids = ElectionVoter::query()->where('election_id', $election->id)->select('voter_id');
            match ($votingStatus) {
                'voted' => $query->whereIn('id', (clone $ids)->where('eligibility_status', EligibilityStatus::VOTED->value)),
                'not_voted' => $query->whereIn('id', (clone $ids)->where('eligibility_status', EligibilityStatus::ELIGIBLE->value)),
                'not_on_roll' => $query->whereNotIn('id', $ids),
                default => null,
            };
        }

        $voters = $query->orderBy('surname')->orderBy('first_name')->paginate(50)->withQueryString();
        $roll = $election
            ? ElectionVoter::query()->where('election_id', $election->id)->whereIn('voter_id', $voters->pluck('id'))->get()->keyBy('voter_id')
            : collect();

        $distinct = fn (string $column) => Voter::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return view('admin.voters.index', [
            'voters' => $voters,
            'roll' => $roll,
            'elections' => $elections,
            'election' => $election,
            'ranks' => NisRank::cases(),
            'commands' => NisCommand::grouped(),
            'formations' => $distinct('formation'),
            'filters' => $request->query(),
            'suspendedOnly' => $suspendedOnly,
        ]);
    }

    public function create(): View
    {
        return view('admin.voters.form', ['voter' => new Voter(['membership_status' => 'ACTIVE'])]);
    }

    public function store(VoterRequest $request): RedirectResponse
    {
        $voter = new Voter;
        $voter->fill($request->voterData());
        $voter->forceFill([
            'created_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
            'registered_at' => now(),
        ])->save();

        $this->audit->log(AuditAction::VOTER_CREATED, AuditResult::SUCCESS, $voter, ['service_number' => $voter->service_number]);

        return redirect()->route('admin.voters.show', $voter)->with('success', 'Voter added to the register. Verify the record before authorising them for an election.');
    }

    public function show(Voter $voter): View
    {
        return view('admin.voters.show', [
            'voter' => $voter,
            'participations' => $voter->electionVoters()->with('election')->latest()->get(),
            'history' => AuditLog::query()->where('entity_type', 'Voter')->where('entity_id', $voter->getKey())->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function edit(Voter $voter): View
    {
        return view('admin.voters.form', ['voter' => $voter]);
    }

    public function update(VoterRequest $request, Voter $voter): RedirectResponse
    {
        $voter->fill($request->voterData());
        $changes = array_keys($voter->getDirty());
        // Changing identity or contact details requires re-verification.
        if (array_intersect($changes, ['service_number', 'surname', 'first_name', 'phone', 'email']) !== []
            && $voter->verification_status === VerificationStatus::VERIFIED) {
            $voter->forceFill(['verification_status' => VerificationStatus::UNVERIFIED, 'verified_at' => null, 'verified_by' => null]);
            $changes[] = 'verification_reset';
        }
        $voter->updated_by = $request->user()->getKey();
        $voter->save();

        $this->audit->log(AuditAction::VOTER_UPDATED, AuditResult::SUCCESS, $voter, ['changed' => $changes]);

        return redirect()->route('admin.voters.show', $voter)->with('success', 'Voter record saved.');
    }

    public function verify(Request $request, Voter $voter): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['VERIFIED', 'REJECTED'])], 'reason' => ['nullable', 'string', 'max:500']]);
        $voter->forceFill([
            'verification_status' => $data['decision'],
            'verified_at' => now(),
            'verified_by' => $request->user()->getKey(),
            'status_reason' => $data['reason'] ?? $voter->status_reason,
        ])->save();

        $this->audit->log(AuditAction::VOTER_VERIFIED, AuditResult::SUCCESS, $voter, ['decision' => $data['decision']]);

        return back()->with('success', "Record marked {$voter->verification_status->label()}.");
    }

    public function verifyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['voter_ids' => ['required', 'array', 'max:500'], 'voter_ids.*' => ['uuid']]);
        $count = DB::transaction(function () use ($data, $request) {
            return Voter::query()->whereIn('id', $data['voter_ids'])
                ->where('verification_status', VerificationStatus::UNVERIFIED->value)
                ->update([
                    'verification_status' => VerificationStatus::VERIFIED->value,
                    'verified_at' => now(),
                    'verified_by' => $request->user()->getKey(),
                    'updated_at' => now(),
                ]);
        });

        $this->audit->log(AuditAction::VOTER_VERIFIED, AuditResult::SUCCESS, null, ['bulk' => true, 'count' => $count]);

        return back()->with('success', "{$count} voter record(s) verified.");
    }

    public function suspend(Request $request, Voter $voter): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($voter, $data) {
            $voter->forceFill(['account_status' => AccountStatus::SUSPENDED, 'status_reason' => $data['reason']])->save();
            // Suspend on every roll where the voter has not yet voted.
            ElectionVoter::query()->where('voter_id', $voter->getKey())
                ->where('eligibility_status', EligibilityStatus::ELIGIBLE->value)
                ->update(['eligibility_status' => EligibilityStatus::SUSPENDED->value, 'eligibility_reason' => $data['reason'], 'updated_at' => now()]);
        });

        $this->audit->log(AuditAction::VOTER_SUSPENDED, AuditResult::SUCCESS, $voter, ['reason' => $data['reason']]);

        return back()->with('success', 'Voter suspended. They cannot sign in or vote until reinstated.');
    }

    public function reinstate(Request $request, Voter $voter): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $voter->forceFill(['account_status' => AccountStatus::ACTIVE, 'status_reason' => $data['reason']])->save();

        $this->audit->log(AuditAction::VOTER_REINSTATED, AuditResult::SUCCESS, $voter, ['reason' => $data['reason']]);

        return back()->with('success', 'Voter account reinstated. Election eligibility must be restored separately on each election roll.');
    }
}
