@extends('layouts.admin')

@section('title', 'Security alerts')

@section('content')
<div class="page-head"><div><h1>Security alerts</h1><div class="sub">Suspicious activity flagged for authorised review. An alert is an observation, not an accusation — confirm the facts before acting.</div></div></div>

<form class="filters" method="GET">
    <div class="field"><label for="status">Status</label><select class="input" id="status" name="status" data-autosubmit>
        @foreach (\App\Enums\AlertStatus::cases() as $s)<option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>@endforeach
    </select></div>
    <div class="field"><label for="severity">Severity</label><select class="input" id="severity" name="severity" data-autosubmit><option value="">All</option>
        @foreach (\App\Enums\AlertSeverity::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['severity'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
    </select></div>
    <noscript><button class="btn btn-secondary" type="submit">Filter</button></noscript>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Severity</th><th>Alert</th><th>Source</th><th class="num">Count</th><th>First / last seen</th><th class="actions"></th></tr></thead>
        <tbody>
        @forelse ($alerts as $alert)
            <tr>
                <td><x-status-badge :status="$alert->severity" /></td>
                <td><strong class="small mono">{{ $alert->type }}</strong><br>{{ $alert->description }}
                    @if ($alert->review_notes)<div class="small muted mt-1">Review: {{ $alert->review_notes }} — {{ $alert->reviewer?->name }}, {{ display_time($alert->reviewed_at) }}</div>@endif</td>
                <td class="small">{{ $alert->subject }}<br><span class="mono muted">{{ $alert->ip }}</span></td>
                <td class="num">{{ $alert->occurrences }}</td>
                <td class="small nowrap">{{ display_time($alert->first_seen_at, 'j M H:i') }}<br>{{ display_time($alert->last_seen_at, 'j M H:i') }}</td>
                <td class="actions">
                    @if ($alert->status->value === 'OPEN')
                        <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="rv-{{ $alert->id }}">Review</button>
                        <dialog class="modal" id="rv-{{ $alert->id }}" aria-label="Review alert">
                            <form method="POST" action="{{ route('admin.alerts.review', $alert) }}">
                                @csrf
                                <div class="modal-head"><h2>Review alert</h2></div>
                                <div class="modal-body">
                                    <p class="small">{{ $alert->description }}</p>
                                    <div class="field"><label for="st-{{ $alert->id }}">Outcome</label>
                                        <select class="input" id="st-{{ $alert->id }}" name="status"><option value="REVIEWED">Reviewed — action taken / noted</option><option value="DISMISSED">Dismissed — benign</option></select></div>
                                    <div class="field"><label for="nt-{{ $alert->id }}">Notes</label><textarea class="input" id="nt-{{ $alert->id }}" name="review_notes" required maxlength="2000"></textarea></div>
                                </div>
                                <div class="modal-foot"><button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button><button class="btn btn-primary" type="submit">Save review</button></div>
                            </form>
                        </dialog>
                    @else
                        <x-status-badge :status="$alert->status" />
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="table-empty">No alerts.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $alerts->links() }}
@endsection
