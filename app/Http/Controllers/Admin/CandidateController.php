<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\CandidateStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CandidateRequest;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Candidates\CandidatePhotoService;
use App\Support\ServiceNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CandidatePhotoService $photos,
    ) {}

    public function index(Request $request): View
    {
        $elections = Election::query()->orderByDesc('starts_at')->get();
        $electionId = $request->query('election') ?: $elections->first()?->getKey();
        $election = $elections->firstWhere('id', $electionId);

        $query = Candidate::query()->with(['electionPosition.position', 'election'])
            ->when($election, fn ($q) => $q->where('candidates.election_id', $election->getKey()));

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn ($q) => $q->where('candidates.surname', $likeOp, $like)->orWhere('candidates.first_name', $likeOp, $like)->orWhere('candidates.service_number', $likeOp, $like)->orWhere('candidates.membership_id', $likeOp, $like));
        }
        if ($position = $request->query('position')) {
            $query->where('candidates.election_position_id', $position);
        }
        if ($status = CandidateStatus::tryFrom((string) $request->query('status'))) {
            $query->where('candidates.status', $status->value);
        }

        $query->join('election_positions', 'election_positions.id', '=', 'candidates.election_position_id')
            ->orderBy('election_positions.display_order')->orderBy('candidates.display_order')->orderBy('candidates.candidate_number')
            ->select('candidates.*');

        return view('admin.candidates.index', [
            'elections' => $elections,
            'election' => $election,
            'positions' => $election?->electionPositions()->with('position')->get() ?? collect(),
            'candidates' => $query->paginate(40)->withQueryString(),
            'filters' => $request->only(['q', 'position', 'status']),
        ]);
    }

    public function lookupVoter(Request $request): JsonResponse
    {
        $rawSn = (string) $request->query('service_number');
        $rawMemberId = (string) $request->query('membership_id');
        $sn = ServiceNumber::normalise($rawSn);
        $memberId = trim($rawMemberId);

        if ($sn === '' && $memberId === '') {
            return response()->json([
                'found' => false,
                'message' => 'Service Number or Membership ID is required.',
            ], 400);
        }

        $voter = null;
        if ($sn !== '') {
            $voter = Voter::query()->where('service_number', $sn)->first();
        }
        if (! $voter && $memberId !== '') {
            $voter = Voter::query()->whereRaw('upper(trim(membership_id)) = ?', [mb_strtoupper($memberId)])->first();
        }

        if (! $voter) {
            $identifier = $sn !== '' ? "Service Number \"{$sn}\"" : "Membership ID \"{$memberId}\"";

            return response()->json([
                'found' => false,
                'message' => "No voter found with {$identifier} on the register.",
            ]);
        }

        $rankValue = null;
        if ($voter->rank) {
            $rankValue = NisRank::tryFrom($voter->rank)?->value ?? NisRank::fromText($voter->rank)?->value ?? null;
        }

        $commandValue = null;
        if ($voter->command) {
            $commandValue = NisCommand::tryFrom($voter->command)?->value ?? NisCommand::fromText($voter->command)?->value ?? null;
        }

        return response()->json([
            'found' => true,
            'voter' => [
                'service_number' => $voter->service_number,
                'membership_id' => $voter->membership_id ?? '',
                'surname' => $voter->surname,
                'first_name' => $voter->first_name,
                'other_names' => $voter->other_names ?? '',
                'rank' => $rankValue,
                'command' => $commandValue,
                'formation' => $voter->formation ?? '',
                'full_name' => $voter->fullName(),
                'rank_label' => $voter->rankLabel(),
                'command_label' => $voter->commandLabel(),
            ],
        ]);
    }

    public function create(Request $request, Election $election): View|RedirectResponse
    {
        $positions = $election->electionPositions()->with('position')->get();
        if ($positions->isEmpty()) {
            return redirect()->route('admin.election-positions.index', $election)
                ->with('error', 'Attach at least one position to this election before adding candidates.');
        }

        return view('admin.candidates.form', [
            'election' => $election,
            'candidate' => new Candidate(['election_position_id' => $request->query('position')]),
            'positions' => $positions,
        ]);
    }

    public function store(CandidateRequest $request, Election $election): RedirectResponse
    {
        $this->ensureEditable($election);
        $data = $request->safe()->except('photo');

        $candidate = DB::transaction(function () use ($data, $election, $request) {
            // Serialise numbering within the election.
            Election::query()->whereKey($election->getKey())->lockForUpdate()->first();
            $data['candidate_number'] ??= (int) Candidate::query()->where('election_id', $election->getKey())->max('candidate_number') + 1;
            $data['display_order'] ??= $data['candidate_number'];

            $candidate = new Candidate;
            $candidate->fill($data);
            $candidate->forceFill([
                'election_id' => $election->getKey(),
                'status' => CandidateStatus::ACTIVE,
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ])->save();

            return $candidate;
        });

        if ($request->hasFile('photo')) {
            $this->photos->store($candidate, $request->file('photo'), $request->user());
            $this->audit->log(AuditAction::CANDIDATE_PHOTO_UPLOADED, AuditResult::SUCCESS, $candidate);
        }

        $this->audit->log(AuditAction::CANDIDATE_CREATED, AuditResult::SUCCESS, $candidate, [
            'name' => $candidate->displayName(),
            'position' => $candidate->electionPosition->position->name,
        ]);

        return redirect()->route('admin.candidates.index', ['election' => $election->getKey()])
            ->with('success', "{$candidate->displayName()} added as candidate #{$candidate->candidate_number}.");
    }

    public function edit(Candidate $candidate): View
    {
        return view('admin.candidates.form', [
            'election' => $candidate->election,
            'candidate' => $candidate,
            'positions' => $candidate->election->electionPositions()->with('position')->get(),
        ]);
    }

    public function update(CandidateRequest $request, Candidate $candidate): RedirectResponse
    {
        $this->ensureEditable($candidate->election);
        $data = $request->safe()->except('photo');
        $data['candidate_number'] ??= $candidate->candidate_number;
        $data['display_order'] ??= $candidate->display_order;

        $candidate->fill($data);
        $changes = array_keys($candidate->getDirty());
        $candidate->updated_by = $request->user()->getKey();
        $candidate->save();

        if ($request->hasFile('photo')) {
            $this->photos->store($candidate, $request->file('photo'), $request->user());
            $this->audit->log(AuditAction::CANDIDATE_PHOTO_UPLOADED, AuditResult::SUCCESS, $candidate);
        }
        $this->audit->log(AuditAction::CANDIDATE_UPDATED, AuditResult::SUCCESS, $candidate, ['changed' => $changes]);

        return redirect()->route('admin.candidates.index', ['election' => $candidate->election_id])->with('success', 'Candidate details saved.');
    }

    public function status(Request $request, Candidate $candidate): RedirectResponse
    {
        $this->ensureEditable($candidate->election);
        $data = $request->validate([
            'status' => ['required', Rule::enum(CandidateStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $from = $candidate->status;
        $candidate->forceFill(['status' => $data['status'], 'updated_by' => $request->user()->getKey()])->save();

        $this->audit->log(AuditAction::CANDIDATE_STATUS_CHANGED, AuditResult::SUCCESS, $candidate, [
            'from' => $from->value, 'to' => $candidate->status->value, 'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', "{$candidate->displayName()} is now {$candidate->status->label()}.");
    }

    private function ensureEditable(Election $election): void
    {
        if (! $election->status->isStructureEditable()) {
            throw ValidationException::withMessages(['candidate' => 'Candidates can only be changed while the election is in draft. Return the election to draft first (only possible before it opens).']);
        }
    }
}
