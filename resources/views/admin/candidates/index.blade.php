@extends('layouts.admin')

@section('title', 'Candidates')

@section('content')
<div class="page-head">
    <div><h1>Candidates</h1><div class="sub">{{ $election ? $election->name : 'No election selected' }}</div></div>
    @if ($election && $election->status->isStructureEditable())
        <a class="btn btn-primary" href="{{ route('admin.candidates.create', $election) }}">Add candidate</a>
    @endif
</div>

@if ($election && ! $election->status->isStructureEditable())
    <div class="alert alert-info">This election is {{ $election->status->label() }}; candidates are locked. Changes are only possible while the election is in draft.</div>
@endif

<form class="filters" method="GET">
    <div class="field"><label for="el">Election</label>
        <select class="input" id="el" name="election" data-autosubmit>
            @foreach ($elections as $e)<option value="{{ $e->id }}" @selected($election?->id === $e->id)>{{ $e->name }}</option>@endforeach
        </select></div>
    <div class="field grow"><label for="q">Search</label><input class="input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or Service Number"></div>
    <div class="field"><label for="pos">Position</label>
        <select class="input" id="pos" name="position"><option value="">All positions</option>
            @foreach ($positions as $p)<option value="{{ $p->id }}" @selected(($filters['position'] ?? '') === $p->id)>{{ $p->position->name }}</option>@endforeach
        </select></div>
    <div class="field"><label for="st">Status</label>
        <select class="input" id="st" name="status"><option value="">All</option>
            @foreach (\App\Enums\CandidateStatus::cases() as $cs)<option value="{{ $cs->value }}" @selected(($filters['status'] ?? '') === $cs->value)>{{ $cs->label() }}</option>@endforeach
        </select></div>
    <button class="btn btn-secondary" type="submit">Filter</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>No.</th><th>Candidate</th><th>Position</th><th>Rank / Command</th><th>Status</th><th class="actions"></th></tr></thead>
        <tbody>
        @forelse ($candidates as $candidate)
            <tr>
                <td class="mono">{{ $candidate->candidate_number }}</td>
                <td><div class="person"><x-candidate-avatar :candidate="$candidate" size="small" />
                    <div><strong>{{ $candidate->displayName() }}</strong>@if ($candidate->service_number)<div class="small muted mono">{{ $candidate->service_number }}</div>@endif</div></div></td>
                <td>{{ $candidate->electionPosition->position->name }}</td>
                <td class="small">{{ $candidate->rank }}<br><span class="muted">{{ $candidate->command }}</span></td>
                <td><x-status-badge :status="$candidate->status" /></td>
                <td class="actions">
                    @if ($candidate->election->status->isStructureEditable())
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.candidates.edit', $candidate) }}">Edit</a>
                        <button type="button" class="btn btn-ghost btn-sm" data-dialog-open="st-{{ $candidate->id }}">Status</button>
                        <dialog class="modal" id="st-{{ $candidate->id }}" aria-label="Change candidate status">
                            <form method="POST" action="{{ route('admin.candidates.status', $candidate) }}">
                                @csrf
                                <div class="modal-head"><h2>Candidate status</h2></div>
                                <div class="modal-body">
                                    <p><strong>{{ $candidate->displayName() }}</strong>, {{ $candidate->electionPosition->position->name }}</p>
                                    <div class="field"><label for="ns-{{ $candidate->id }}">Status</label>
                                        <select class="input" id="ns-{{ $candidate->id }}" name="status">
                                            @foreach (\App\Enums\CandidateStatus::cases() as $cs)<option value="{{ $cs->value }}" @selected($candidate->status === $cs)>{{ $cs->label() }}</option>@endforeach
                                        </select></div>
                                    <div class="field"><label for="nr-{{ $candidate->id }}">Reason</label>
                                        <input class="input" id="nr-{{ $candidate->id }}" name="reason" maxlength="500"></div>
                                    <p class="small muted">Only Active candidates appear on the ballot.</p>
                                </div>
                                <div class="modal-foot"><button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
                            </form>
                        </dialog>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="table-empty">No candidates found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $candidates->links() }}
@endsection
