<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ElectionRequest;
use App\Http\Requests\Admin\ExtendVotingTimeRequest;
use App\Http\Requests\Admin\ReauthenticatedRequest;
use App\Models\Ballot;
use App\Models\BallotToken;
use App\Models\Election;
use App\Models\ResultTally;
use App\Models\TieResolution;
use App\Models\Vote;
use App\Models\VotingSession;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Elections\ElectionLifecycle;
use App\Services\Monitoring\ElectionStatistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionLifecycle $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Election::query()->orderByDesc('starts_at');
        if ($status = $request->query('status')) {
            $query->where('status', ElectionStatus::tryFrom((string) $status)?->value ?? '__none__');
        }

        return view('admin.elections.index', [
            'elections' => $query->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('admin.elections.form', ['election' => new Election([
            'auto_open' => true,
            'auto_close' => true,
        ])]);
    }

    public function store(ElectionRequest $request): RedirectResponse
    {
        $data = $request->electionData();
        $election = new Election;
        $election->fill($data);
        $election->forceFill([
            'status' => ElectionStatus::DRAFT,
            'receipt_year' => display_time($data['starts_at'], 'Y'),
            'created_by' => $request->user()->getKey(),
        ])->save();

        $this->audit->log(AuditAction::ELECTION_CREATED, AuditResult::SUCCESS, $election, ['code' => $election->code]);

        return redirect()->route('admin.election-positions.index', $election)
            ->with('success', 'Election created. Next, add the positions to be contested.');
    }

    public function show(Election $election, ElectionStatistics $stats, Request $request): View
    {
        $election->loadCount([
            'electionPositions',
            'candidates as active_candidates_count' => fn ($q) => $q->where('status', CandidateStatus::ACTIVE->value),
            'electionVoters as eligible_count' => fn ($q) => $q->whereIn('eligibility_status', [EligibilityStatus::ELIGIBLE->value, EligibilityStatus::VOTED->value]),
        ]);

        return view('admin.elections.show', [
            'election' => $election,
            'problems' => in_array($election->status, [ElectionStatus::DRAFT, ElectionStatus::SCHEDULED], true)
                ? $this->lifecycle->readinessProblems($election) : [],
            'summary' => $stats->summary($election),
            'user' => $request->user(),
        ]);
    }

    public function edit(Election $election): View|RedirectResponse
    {
        if (! $election->status->isStructureEditable()) {
            return redirect()->route('admin.elections.show', $election)->with('error', 'Only draft elections can be edited. Return the election to draft first.');
        }

        return view('admin.elections.form', ['election' => $election]);
    }

    public function update(ElectionRequest $request, Election $election): RedirectResponse
    {
        if (! $election->status->isStructureEditable()) {
            throw ValidationException::withMessages(['election' => 'Only draft elections can be edited.']);
        }
        $data = $request->electionData();
        $election->fill($data);
        $election->receipt_year = display_time($data['starts_at'], 'Y');
        $changes = array_keys($election->getDirty());
        $election->save();

        $this->audit->log(AuditAction::ELECTION_UPDATED, AuditResult::SUCCESS, $election, ['changed' => $changes]);

        return redirect()->route('admin.elections.show', $election)->with('success', 'Election details saved.');
    }

    public function destroy(Request $request, Election $election): RedirectResponse
    {
        $this->audit->log(AuditAction::ELECTION_DELETED, AuditResult::SUCCESS, $election, [
            'code' => $election->code,
            'name' => $election->name,
            'status' => $election->status->value,
        ]);

        DB::transaction(function () use ($election) {
            $epIds = $election->electionPositions()->pluck('id');

            // Delete tie resolutions
            TieResolution::query()->whereIn('election_position_id', $epIds)->delete();

            // Unfreeze trigger on Postgres if result was published
            if ($election->result_status !== ResultStatus::NOT_CALCULATED) {
                DB::table('elections')->where('id', $election->id)->update([
                    'result_status' => ResultStatus::NOT_CALCULATED->value,
                ]);
            }
            ResultTally::query()->whereIn('election_position_id', $epIds)->delete();

            // Delete votes, ballots, and ballot tokens
            Vote::query()->where('election_id', $election->id)->delete();
            Ballot::query()->where('election_id', $election->id)->delete();
            BallotToken::query()->where('election_id', $election->id)->delete();

            // Delete voting sessions and election voters
            $evIds = $election->electionVoters()->pluck('id');
            VotingSession::query()->whereIn('election_voter_id', $evIds)->delete();
            $election->electionVoters()->delete();

            // Delete candidates and election positions
            $election->candidates()->delete();
            $election->electionPositions()->delete();

            // Delete the election itself
            $election->delete();
        });

        return redirect()->route('admin.elections.index')->with('success', 'Election "'.$election->name.'" has been permanently deleted.');
    }

    public function schedule(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $this->lifecycle->schedule($election, $request->user());

        return back()->with('success', 'Election scheduled. It will open automatically at the start time'.($election->auto_open ? '.' : ' only if opened manually.'));
    }

    public function unschedule(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $this->lifecycle->unschedule($election, $request->user());

        return back()->with('success', 'Election returned to draft. It can now be edited.');
    }

    public function open(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $this->lifecycle->open($election, $request->user());

        return redirect()->route('admin.monitor.show', $election)->with('success', 'The election is now OPEN. Voters can sign in and vote.');
    }

    public function extend(ExtendVotingTimeRequest $request, Election $election): RedirectResponse
    {
        $newEndsAt = $request->newEndsAt();
        $this->lifecycle->extend($election, $newEndsAt, $request->user());

        return back()->with('success', 'Voting time extended until '.display_time($newEndsAt, 'l j F Y, H:i').' WAT.');
    }

    public function close(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $this->lifecycle->close($election, $request->user());

        return redirect()->route('admin.elections.show', $election)->with('success', 'The election is CLOSED. No further ballots will be accepted.');
    }

    public function archive(ReauthenticatedRequest $request, Election $election): RedirectResponse
    {
        $this->lifecycle->archive($election, $request->user());

        return back()->with('success', 'Election archived.');
    }
}
