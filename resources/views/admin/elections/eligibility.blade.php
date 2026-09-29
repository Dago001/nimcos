@extends('layouts.admin')

@section('title', 'Eligibility · '.$election->name)

@section('content')
@include('admin.elections.partials.tabs')

@php($eligible = (int) ($counts['ELIGIBLE'] ?? 0))
@php($voted = (int) ($counts['VOTED'] ?? 0))
<div class="stats">
    <div class="stat"><div class="stat-label">Eligible (not voted)</div><div class="stat-value">{{ number_format($eligible) }}</div></div>
    <div class="stat"><div class="stat-label">Voted</div><div class="stat-value green">{{ number_format($voted) }}</div></div>
    <div class="stat"><div class="stat-label">Suspended</div><div class="stat-value">{{ number_format((int) ($counts['SUSPENDED'] ?? 0)) }}</div></div>
    <div class="stat"><div class="stat-label">Ineligible</div><div class="stat-value">{{ number_format((int) ($counts['INELIGIBLE'] ?? 0)) }}</div></div>
</div>

@if ($canGrant)
    <div class="panel mb-3">
        <div class="panel-body grid-2 even">
            <div>
                <h3>Authorise all verified members</h3>
                <p class="muted small">{{ number_format($authorisable) }} verified, active, eligible member(s) on the register are not yet on this roll.</p>
                <form method="POST" action="{{ route('admin.eligibility.authorise-all', $election) }}" data-submit-once>
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($authorisable === 0)>Authorise {{ number_format($authorisable) }} voter(s)</button>
                </form>
            </div>
            <div>
                <h3>Authorise one voter</h3>
                <form method="POST" action="{{ route('admin.eligibility.store', $election) }}" class="btn-row">
                    @csrf
                    <label class="sr-only" for="add-sn">Service Number</label>
                    <input class="input w-auto" id="add-sn" name="service_number" placeholder="Service Number" required>
                    <button class="btn btn-secondary" type="submit">Authorise</button>
                </form>
                @error('service_number')<div class="error-text">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info">The roll is {{ $election->status === \App\Enums\ElectionStatus::OPEN ? 'locked while voting is open: voters can be suspended but not added.' : 'final.' }}</div>
@endif

<form class="filters" method="GET">
    <div class="field grow"><label for="q">Search</label><input class="input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Service Number or surname"></div>
    <div class="field"><label for="st">Status</label>
        <select class="input" id="st" name="status"><option value="">All</option>
            @foreach (\App\Enums\EligibilityStatus::cases() as $es)<option value="{{ $es->value }}" @selected(($filters['status'] ?? '') === $es->value)>{{ $es->label() }}</option>@endforeach
        </select></div>
    <div class="field"><label for="cm">Command</label>
        <select class="input" id="cm" name="command"><option value="">All</option>
            @foreach ($commands as $group => $groupCommands)<optgroup label="{{ $group }}">
                @foreach ($groupCommands as $c)<option value="{{ $c->value }}" @selected(($filters['command'] ?? '') === $c->value)>{{ $c->label() }}</option>@endforeach
            </optgroup>@endforeach
        </select></div>
    <button class="btn btn-secondary" type="submit">Filter</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Service No.</th><th>Name</th><th>Rank / Command</th><th>Status</th><th>Reason</th><th class="actions">Change</th></tr></thead>
        <tbody>
        @forelse ($rows as $ev)
            <tr>
                <td class="mono">{{ $ev->voter->service_number }}</td>
                <td><a href="{{ route('admin.voters.show', $ev->voter) }}">{{ $ev->voter->fullName() }}</a></td>
                <td class="small">{{ $ev->voter->rankLabel() }}<br><span class="muted">{{ $ev->voter->commandLabel() }}</span></td>
                <td><x-status-badge :status="$ev->eligibility_status" /></td>
                <td class="small muted">{{ $ev->eligibility_reason }}</td>
                <td class="actions">
                    @if (! $ev->hasVoted() && ! $election->status->hasClosed())
                        <form method="POST" action="{{ route('admin.eligibility.update', [$election, $ev]) }}" class="btn-row">
                            @csrf @method('PATCH')
                            <label class="sr-only" for="s-{{ $ev->id }}">New status</label>
                            <select class="input w-auto" id="s-{{ $ev->id }}" name="status">
                                @foreach (['ELIGIBLE', 'SUSPENDED', 'INELIGIBLE'] as $opt)
                                    @if ($opt !== $ev->eligibility_status->value && ! ($opt === 'ELIGIBLE' && ! $canGrant))
                                        <option value="{{ $opt }}">{{ ucfirst(strtolower($opt)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <label class="sr-only" for="r-{{ $ev->id }}">Reason</label>
                            <input class="input w-auto" id="r-{{ $ev->id }}" name="reason" placeholder="Reason (required)" required maxlength="500">
                            <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="table-empty">No voters on this roll match the filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
@endsection
