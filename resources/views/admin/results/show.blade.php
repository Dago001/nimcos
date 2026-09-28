@extends('layouts.admin')

@section('title', 'Results · '.$election->name)

@section('content')
@include('admin.elections.partials.tabs')

@php
    $rs = $election->result_status->value;
    $steps = ['CLOSE' => $election->status->hasClosed(), 'CALCULATE' => $rs !== 'NOT_CALCULATED', 'VERIFY' => in_array($rs, ['VERIFIED', 'PUBLISHED'], true), 'PUBLISH' => $rs === 'PUBLISHED'];
    $currentStep = collect($steps)->search(false);
@endphp

<ol class="workflow" aria-label="Results workflow">
    <li class="{{ $steps['CLOSE'] ? 'done' : 'current' }}">Close election</li>
    <li class="{{ $steps['CALCULATE'] ? 'done' : ($currentStep === 'CALCULATE' ? 'current' : '') }}">Calculate results</li>
    <li class="{{ $steps['VERIFY'] ? 'done' : ($currentStep === 'VERIFY' ? 'current' : '') }}">Verify results</li>
    <li class="{{ $steps['PUBLISH'] ? 'done' : ($currentStep === 'PUBLISH' ? 'current' : '') }}">Publish results</li>
</ol>

@if (! $visible)
    <div class="panel"><div class="panel-body">
        <h2>Results are sealed</h2>
        <p class="muted mb-0">Per-candidate results are not available until the election has officially closed{{ $election->status->value === 'OPEN' ? '. Live vote counts are switched off for this election.' : '.' }} Turnout can be followed on the live monitor.</p>
    </div></div>
@else
    @if ($canPublish && $election->status->hasClosed() && $rs !== 'PUBLISHED')
        <div class="panel mb-3"><div class="panel-body">
            <h2>Returning Officer actions</h2>
            <p class="muted">Results are computed only from recorded ballots. No figure can be typed in or edited.</p>
            <div class="btn-row">
                <x-reauth-dialog id="dlg-calc" :action="route('admin.results.calculate', $election)" :title="$rs === 'NOT_CALCULATED' ? 'Calculate results' : 'Recalculate results'"
                                 :button="$rs === 'NOT_CALCULATED' ? 'Calculate results' : 'Recalculate'" :trigger-class="$rs === 'NOT_CALCULATED' ? 'btn btn-primary' : 'btn btn-secondary'">
                    <p>Counts every recorded vote for this election. Recalculating discards the current tallies and any tie resolutions, and requires verification again.</p>
                </x-reauth-dialog>
                @if ($rs === 'CALCULATED')
                    <x-reauth-dialog id="dlg-verify" :action="route('admin.results.verify', $election)" title="Verify results" button="Run verification">
                        <p>Performs an independent recount and checks: stored tallies match the recount; ballots equal voters marked as voted; consumed ballot tokens equal ballots; no ballot exceeds a position's seats.</p>
                    </x-reauth-dialog>
                @endif
                @if ($rs === 'VERIFIED')
                    @if ($blockers)
                        <div class="alert alert-warning mb-0"><strong>Cannot publish yet:</strong><ul>@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
                    @else
                        <x-reauth-dialog id="dlg-publish" :action="route('admin.results.publish', $election)" title="PUBLISH RESULTS" button="Publish official results">
                            <div class="alert alert-warning mb-2"><strong>Publication is final.</strong> Results become public and are frozen.</div>
                            <div class="modal-summary">{{ $election->name }}<br>{{ number_format($results['ballots'] ?? 0) }} ballots · turnout {{ number_format($results['turnout'] ?? 0, 2) }}%</div>
                        </x-reauth-dialog>
                    @endif
                @endif
            </div>
        </div></div>
    @endif

    @if (! $results)
        <div class="panel"><div class="panel-body"><p class="muted mb-0">Results have not been calculated yet.</p></div></div>
    @else
        @if ($live)
            <div class="alert alert-warning"><strong>INTERIM — NOT FINAL.</strong> Live count while voting is open, shown because interim results are enabled for this election.</div>
        @elseif ($rs !== 'PUBLISHED')
            <div class="alert alert-info"><strong>PROVISIONAL — not yet published.</strong> Calculated {{ display_time($results['calculated_at']) }}.</div>
        @endif

        <div class="stats">
            <div class="stat"><div class="stat-label">Eligible voters</div><div class="stat-value">{{ number_format($results['eligible']) }}</div></div>
            <div class="stat"><div class="stat-label">Ballots cast</div><div class="stat-value">{{ number_format($results['ballots']) }}</div></div>
            <div class="stat"><div class="stat-label">Turnout</div><div class="stat-value green">{{ number_format($results['turnout'], 2) }}%</div></div>
            <div class="stat"><div class="stat-label">Ties detected</div><div class="stat-value">{{ collect($results['positions'])->where('has_tie', true)->count() }}</div></div>
        </div>

        @foreach ($results['positions'] as $p)
            @include('partials.position-result', ['p' => $p])
            @if ($p['has_tie'] && $canPublish && ! $live && $rs !== 'PUBLISHED')
                <div class="panel mb-3"><div class="panel-body">
                    <h3>Resolve tie — {{ $p['name'] }}</h3>
                    <p class="small muted">Record the outcome decided under the approved NIMCOS election rules. Vote counts are not changed.</p>
                    <form method="POST" action="{{ route('admin.results.tie', [$election, $p['election_position']]) }}" data-tie-form class="form-grid two">
                        @csrf
                        <div class="field"><label for="m-{{ $loop->index }}">Method</label>
                            <select class="input" id="m-{{ $loop->index }}" name="method">
                                @foreach ($methods as $m)<option value="{{ $m->value }}" @selected($p['resolution']?->method === $m)>{{ $m->label() }}</option>@endforeach
                            </select></div>
                        <div class="field" data-winner-field><label for="w-{{ $loop->index }}">Declared winner</label>
                            <select class="input" id="w-{{ $loop->index }}" name="winning_candidate_id"><option value="">—</option>
                                @foreach ($p['candidates'] as $row)@if ($row['is_tied'])<option value="{{ $row['candidate']->id }}" @selected($p['resolution']?->winning_candidate_id === $row['candidate']->id)>{{ $row['candidate']->displayName() }}</option>@endif @endforeach
                            </select></div>
                        <div class="field span-2"><label for="n-{{ $loop->index }}">Notes / authority (e.g. minutes reference)</label>
                            <textarea class="input" id="n-{{ $loop->index }}" name="notes" required minlength="10" maxlength="2000">{{ $p['resolution']?->notes }}</textarea></div>
                        <div class="field"><label for="pw-{{ $loop->index }}">Your password</label><input class="input" type="password" id="pw-{{ $loop->index }}" name="confirm_password" required autocomplete="current-password"></div>
                        @if (auth()->user()->hasMfa())
                            <div class="field"><label for="mf-{{ $loop->index }}">Authenticator code</label><input class="input" id="mf-{{ $loop->index }}" name="confirm_mfa" inputmode="numeric" maxlength="6" required></div>
                        @endif
                        <div class="span-2"><button class="btn btn-primary" type="submit">Record tie resolution</button></div>
                    </form>
                </div></div>
            @endif
        @endforeach

        <p class="small muted">Result hash (SHA-256 of all tallies): <span class="mono">{{ $results['hash'] }}</span></p>
    @endif
@endif
@endsection
