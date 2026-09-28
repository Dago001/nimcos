<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catalogue of NIMCOS offices reused across elections. */
class PositionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.positions.index', [
            'positions' => Position::query()->withCount('electionPositions')->orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $position = new Position($data);
        $position->status = RecordStatus::ACTIVE;
        $position->save();

        $this->audit->log(AuditAction::POSITION_CREATED, AuditResult::SUCCESS, $position, ['name' => $position->name]);

        return back()->with('success', "{$position->name} created.");
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $position->fill($this->validated($request, $position));
        $changes = $position->getDirty();
        $position->save();

        $this->audit->log(AuditAction::POSITION_UPDATED, AuditResult::SUCCESS, $position, ['changes' => $changes]);

        return back()->with('success', "{$position->name} updated.");
    }

    public function toggle(Position $position): RedirectResponse
    {
        $position->status = $position->status === RecordStatus::ACTIVE ? RecordStatus::INACTIVE : RecordStatus::ACTIVE;
        $position->save();

        $this->audit->log(AuditAction::POSITION_UPDATED, AuditResult::SUCCESS, $position, ['status' => $position->status->value]);

        return back()->with('success', "{$position->name} is now {$position->status->label()}.");
    }

    private function validated(Request $request, ?Position $position = null): array
    {
        $request->merge(['name' => mb_strtoupper(trim((string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('positions', 'name')->ignore($position?->getKey())],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'default_seats' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
    }
}
