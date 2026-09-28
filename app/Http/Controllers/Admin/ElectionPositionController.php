<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\Position;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Positions contested in a specific election (data-driven, never hard-coded; spec §3). */
class ElectionPositionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Election $election): View
    {
        $attached = $election->electionPositions()->with('position')->withCount('candidates')->get();

        return view('admin.elections.positions', [
            'election' => $election,
            'attached' => $attached,
            'available' => Position::query()
                ->where('status', RecordStatus::ACTIVE->value)
                ->whereNotIn('id', $attached->pluck('position_id'))
                ->orderBy('display_order')->get(),
            'editable' => $election->status->isStructureEditable(),
        ]);
    }

    public function store(Request $request, Election $election): RedirectResponse
    {
        $this->ensureEditable($election);
        $data = $request->validate([
            'position_id' => ['required', 'uuid', Rule::exists('positions', 'id')->where('status', RecordStatus::ACTIVE->value),
                Rule::unique('election_positions', 'position_id')->where('election_id', $election->getKey())],
            'seats' => ['required', 'integer', 'min:1', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        $position = Position::query()->findOrFail($data['position_id']);

        $ep = new ElectionPosition;
        $ep->election()->associate($election);
        $ep->fill([
            'position_id' => $position->getKey(),
            'seats' => $data['seats'],
            'display_order' => $position->display_order,
            'is_required' => $request->boolean('is_required', true),
        ])->save();

        $this->audit->log(AuditAction::ELECTION_POSITIONS_CHANGED, AuditResult::SUCCESS, $election, ['added' => $position->name, 'seats' => $ep->seats]);

        return back()->with('success', "{$position->name} added to the ballot.");
    }

    public function attachAll(Election $election): RedirectResponse
    {
        $this->ensureEditable($election);
        $existing = $election->electionPositions()->pluck('position_id');
        $added = [];
        Position::query()->where('status', RecordStatus::ACTIVE->value)->whereNotIn('id', $existing)->orderBy('display_order')
            ->each(function (Position $position) use ($election, &$added) {
                $ep = new ElectionPosition;
                $ep->election()->associate($election);
                $ep->fill([
                    'position_id' => $position->getKey(),
                    'seats' => $position->default_seats,
                    'display_order' => $position->display_order,
                    'is_required' => true,
                ])->save();
                $added[] = $position->name;
            });

        $this->audit->log(AuditAction::ELECTION_POSITIONS_CHANGED, AuditResult::SUCCESS, $election, ['added' => $added]);

        return back()->with('success', count($added).' position(s) added to the ballot.');
    }

    public function update(Request $request, Election $election, ElectionPosition $electionPosition): RedirectResponse
    {
        $this->ensureEditable($election);
        abort_unless($electionPosition->election_id === $election->getKey(), 404);
        $data = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:50'],
            'display_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        $electionPosition->fill([
            'seats' => $data['seats'],
            'display_order' => $data['display_order'],
            'is_required' => $request->boolean('is_required'),
        ]);
        $changes = $electionPosition->getDirty();
        $electionPosition->save();

        $this->audit->log(AuditAction::ELECTION_POSITIONS_CHANGED, AuditResult::SUCCESS, $election, [
            'updated' => $electionPosition->position->name,
            'changes' => $changes,
        ]);

        return back()->with('success', 'Position settings saved.');
    }

    public function destroy(Election $election, ElectionPosition $electionPosition): RedirectResponse
    {
        $this->ensureEditable($election);
        abort_unless($electionPosition->election_id === $election->getKey(), 404);
        if ($electionPosition->candidates()->exists()) {
            throw ValidationException::withMessages(['position' => 'Remove or reassign this position\'s candidates before removing it from the ballot.']);
        }
        $name = $electionPosition->position->name;
        $electionPosition->delete();

        $this->audit->log(AuditAction::ELECTION_POSITIONS_CHANGED, AuditResult::SUCCESS, $election, ['removed' => $name]);

        return back()->with('success', "{$name} removed from the ballot.");
    }

    private function ensureEditable(Election $election): void
    {
        if (! $election->status->isStructureEditable()) {
            throw ValidationException::withMessages(['position' => 'The ballot can only be changed while the election is in draft.']);
        }
    }
}
