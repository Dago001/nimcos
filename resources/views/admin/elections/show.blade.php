@extends('layouts.admin')

@section('title', $election->name)

@section('content')
@php
    $s = $election->status;
    $order = ['DRAFT', 'SCHEDULED', 'OPEN', 'CLOSED', 'RESULTS_PUBLISHED', 'ARCHIVED'];
    $pos = array_search($s->value, $order, true);
@endphp

@include('admin.elections.partials.tabs')

<ol class="workflow" aria-label="Election lifecycle">
    @foreach ($order as $i => $step)
        <li class="{{ $i < $pos ? 'done' : ($i === $pos ? 'current' : '') }}">{{ \App\Enums\ElectionStatus::from($step)->label() }}</li>
    @endforeach
</ol>

<div class="grid-2">
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h2>Election details</h2>
                @if ($s->isStructureEditable() && $user->hasPermission('manage_elections'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('admin.elections.edit', $election) }}">Edit details</a>
                @endif
            </div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>Code</dt><dd class="mono">{{ $election->code }}</dd>
                    <dt>Type</dt><dd>{{ $election->election_type->label() }}</dd>
                    <dt>Voting opens</dt><dd>{{ display_time($election->starts_at, 'l j F Y, H:i') }} WAT</dd>
                    <dt>Voting closes</dt><dd>{{ display_time($election->ends_at, 'l j F Y, H:i') }} WAT</dd>
                    <dt>Automation</dt><dd>{{ $election->auto_open ? 'Opens automatically' : 'Manual opening' }} · {{ $election->auto_close ? 'closes automatically' : 'manual closing' }}</dd>
                    <dt>Live vote count</dt><dd>{{ $election->interim_results_enabled ? 'Shown on the dashboard while voting is open' : 'Sealed until the election closes' }}</dd>
                    <dt>Positions</dt><dd>{{ $election->election_positions_count }}</dd>
                    <dt>Active candidates</dt><dd>{{ $election->active_candidates_count }}</dd>
                    <dt>Eligible voters</dt><dd>{{ number_format($election->eligible_count) }}</dd>
                    <dt>Result status</dt><dd><x-status-badge :status="$election->result_status" /></dd>
                    @if ($election->opened_at)<dt>Opened</dt><dd>{{ display_time($election->opened_at) }} {{ $election->opened_by ? '' : '(automatic)' }}</dd>@endif
                    @if ($election->closed_at)<dt>Closed</dt><dd>{{ display_time($election->closed_at) }} {{ $election->closed_by ? '' : '(automatic)' }}</dd>@endif
                    @if ($election->published_at)<dt>Results published</dt><dd>{{ display_time($election->published_at) }}</dd>@endif
                </dl>
                @if ($election->description)
                    <p class="mt-2 mb-0 muted">{{ $election->description }}</p>
                @endif
            </div>
        </div>

        @if ($problems)
            <div class="alert alert-warning">
                <strong>Not ready to {{ $s === \App\Enums\ElectionStatus::DRAFT ? 'schedule' : 'open' }}:</strong>
                <ul>@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
            </div>
        @endif
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Election control</h2></div>
        <div class="panel-body">
            @if ($s === \App\Enums\ElectionStatus::DRAFT)
                <p class="muted">Draft elections are invisible to voters. When positions, candidates and the voter roll are complete, schedule the election. Scheduling locks the ballot.</p>
                @if ($user->hasPermission('manage_elections'))
                    <div class="btn-row">
                        <x-reauth-dialog id="dlg-schedule" :action="route('admin.elections.schedule', $election)" title="Schedule election" button="Schedule election">
                            <div class="modal-summary">
                                <strong>{{ $election->name }}</strong><br>
                                Opens {{ display_time($election->starts_at, 'j M Y H:i') }} · closes {{ display_time($election->ends_at, 'j M Y H:i') }} WAT<br>
                                {{ number_format($election->eligible_count) }} eligible voters · {{ $election->active_candidates_count }} candidates
                            </div>
                            <p class="small">The ballot (positions and candidates) is locked once scheduled. You can return it to draft before voting opens.</p>
                        </x-reauth-dialog>
                        <x-reauth-dialog id="dlg-delete" :action="route('admin.elections.destroy', $election)" method="DELETE" title="Delete draft election" button="Delete election" variant="danger" trigger-class="btn btn-secondary">
                            <p>This permanently removes the draft election, its ballot and its voter roll. The voter register itself is not affected.</p>
                        </x-reauth-dialog>
                    </div>
                @endif

            @elseif ($s === \App\Enums\ElectionStatus::SCHEDULED)
                <p>Scheduled. {{ $election->auto_open ? 'Voting will open automatically at '.display_time($election->starts_at, 'H:i, j M Y').' WAT.' : 'An authorised official must open voting.' }}</p>
                <div class="btn-row">
                    @if ($user->hasPermission('open_election'))
                        <x-reauth-dialog id="dlg-open" :action="route('admin.elections.open', $election)" title="OPEN ELECTION" button="Confirm and open election">
                            <dl class="dl modal-summary">
                                <dt>Election</dt><dd>{{ $election->name }}</dd>
                                <dt>Start</dt><dd>{{ $election->starts_at->isFuture() ? 'Now ('.display_time(now(), 'j M Y H:i').')' : display_time($election->starts_at, 'j M Y H:i') }}</dd>
                                <dt>End</dt><dd>{{ display_time($election->ends_at, 'j M Y H:i') }}</dd>
                                <dt>Eligible voters</dt><dd>{{ number_format($election->eligible_count) }}</dd>
                                <dt>Candidates</dt><dd>{{ $election->active_candidates_count }}</dd>
                            </dl>
                            <p class="small">Voters will be able to sign in and vote immediately.</p>
                        </x-reauth-dialog>
                    @endif
                    @if ($user->hasPermission('manage_elections'))
                        <x-reauth-dialog id="dlg-extend-sched" :action="route('admin.elections.extend', $election)" title="Extend voting time" button="Confirm extension" trigger="Extend voting time" trigger-class="btn btn-secondary">
                            <div class="modal-summary mb-2">
                                <strong>{{ $election->name }}</strong><br>
                                Current closing time: <strong>{{ display_time($election->ends_at, 'l j F Y, H:i') }} WAT</strong>
                            </div>
                            <div class="field">
                                <label for="ends_at_sched">New closing date and time (WAT) <span class="req">*</span></label>
                                <input class="input" type="datetime-local" id="ends_at_sched" name="ends_at"
                                       value="{{ old('ends_at', display_time($election->ends_at->addHour(), 'Y-m-d\TH:i')) }}"
                                       min="{{ display_time(now(), 'Y-m-d\TH:i') }}" required>
                                <div class="help">Enter the extended date and time when voting should close (WAT). Must be after current closing time.</div>
                            </div>
                        </x-reauth-dialog>
                        <x-reauth-dialog id="dlg-unschedule" :action="route('admin.elections.unschedule', $election)" title="Return to draft" button="Return to draft" trigger-class="btn btn-secondary">
                            <p>The election returns to draft so the ballot can be corrected. It must be scheduled again before it can open.</p>
                        </x-reauth-dialog>
                    @endif
                </div>

            @elseif ($s === \App\Enums\ElectionStatus::OPEN)
                <p><span class="badge badge-live">LIVE</span> Voting is in progress. Turnout: <strong>{{ number_format($summary['turnout'], 2) }}%</strong> ({{ number_format($summary['voted']) }} of {{ number_format($summary['eligible']) }}).</p>
                <div class="btn-row">
                    @if ($user->hasPermission('view_live_statistics'))
                        <a class="btn btn-secondary" href="{{ route('admin.monitor.show', $election) }}">Live monitor</a>
                    @endif
                    @if ($user->hasPermission('manage_elections'))
                        <x-reauth-dialog id="dlg-extend-open" :action="route('admin.elections.extend', $election)" title="Extend voting time" button="Confirm extension" trigger="Extend voting time" trigger-class="btn btn-secondary">
                            <div class="modal-summary mb-2">
                                <strong>{{ $election->name }}</strong><br>
                                Current closing time: <strong>{{ display_time($election->ends_at, 'l j F Y, H:i') }} WAT</strong>
                            </div>
                            <div class="field">
                                <label for="ends_at_open">New closing date and time (WAT) <span class="req">*</span></label>
                                <input class="input" type="datetime-local" id="ends_at_open" name="ends_at"
                                       value="{{ old('ends_at', display_time($election->ends_at->addHour(), 'Y-m-d\TH:i')) }}"
                                       min="{{ display_time(now(), 'Y-m-d\TH:i') }}" required>
                                <div class="help">Enter the extended date and time when voting should close (WAT). Must be after current closing time.</div>
                            </div>
                        </x-reauth-dialog>
                    @endif
                    @if ($user->hasPermission('close_election'))
                        <x-reauth-dialog id="dlg-close" :action="route('admin.elections.close', $election)" title="CLOSE ELECTION" button="Close election" variant="danger" trigger-class="btn btn-danger">
                            <div class="alert alert-warning mb-2"><strong>Once closed, voters will no longer be able to submit ballots.</strong> This cannot be undone through the normal interface.</div>
                            <p class="small">Scheduled close: {{ display_time($election->ends_at, 'j M Y H:i') }} WAT. Ballots already being submitted will complete first.</p>
                        </x-reauth-dialog>
                    @endif
                </div>

            @elseif ($s === \App\Enums\ElectionStatus::CLOSED)
                <p>Voting has closed. {{ number_format($summary['voted']) }} ballots were cast ({{ number_format($summary['turnout'], 2) }}% turnout).</p>
                <p class="muted small">Next: the Returning Officer calculates, verifies and publishes the results.</p>
                @if ($user->hasPermission('view_results'))
                    <a class="btn btn-primary" href="{{ route('admin.results.show', $election) }}">Go to results</a>
                @endif

            @elseif ($s === \App\Enums\ElectionStatus::RESULTS_PUBLISHED)
                <p>Results were officially published on {{ display_time($election->published_at) }}.</p>
                <div class="btn-row">
                    <a class="btn btn-secondary" href="{{ route('public.results.show', $election->code) }}">Public results page</a>
                    @if ($user->hasPermission('manage_elections'))
                        <x-reauth-dialog id="dlg-archive" :action="route('admin.elections.archive', $election)" title="Archive election" button="Archive" trigger-class="btn btn-secondary">
                            <p>Archiving marks the election as historical. Records, ballots and results are retained unchanged.</p>
                        </x-reauth-dialog>
                    @endif
                </div>
            @else
                <p class="muted">Archived on {{ display_time($election->archived_at) }}. All records are retained read-only.</p>
            @endif
        </div>
    </div>
</div>
@endsection
