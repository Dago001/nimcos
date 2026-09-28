@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="sub">{{ display_time(now(), 'l, j F Y · H:i') }} WAT · {{ number_format($registerTotal) }} members on the register</div>
    </div>
    @if ($focus)
        <div class="btn-row">
            @if (auth()->user()->hasPermission('view_live_statistics'))
                <a class="btn btn-secondary" href="{{ route('admin.monitor.show', $focus) }}">Live monitor</a>
            @endif
            <a class="btn btn-primary" href="{{ route('admin.elections.show', $focus) }}">Manage election</a>
        </div>
    @endif
</div>

@if (! $focus)
    <div class="panel"><div class="panel-body">
        <h2>No elections yet</h2>
        <p class="muted">Create an election, attach the positions, add candidates and authorise voters.</p>
        @if (auth()->user()->hasPermission('manage_elections'))
            <a class="btn btn-primary" href="{{ route('admin.elections.create') }}">Create election</a>
        @endif
    </div></div>
@else
    <div data-poll-url="{{ route('admin.dashboard.stats') }}" data-poll-seconds="10">
        <div class="btn-row mb-2">
            <h2 class="mb-0">{{ $focus->name }}</h2>
            <x-status-badge :status="$focus->status" />
            <span class="small muted">Updated <span data-poll-stamp>{{ display_time(now(), 'H:i:s') }}</span></span>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-label">Election status</div>
                <div class="stat-value {{ $summary['accepting_votes'] ? 'green' : '' }}" data-stat="summary.status_label">{{ $summary['status_label'] }}</div>
                <div class="stat-note">{{ display_time($focus->starts_at, 'H:i') }} to {{ display_time($focus->ends_at, 'H:i, j M') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Eligible voters</div>
                <div class="stat-value" data-stat="summary.eligible">{{ number_format($summary['eligible']) }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Votes cast</div>
                <div class="stat-value" data-stat="summary.voted">{{ number_format($summary['voted']) }}</div>
                <div class="stat-note">Not yet voted: <span data-stat="summary.not_voted">{{ number_format($summary['not_voted']) }}</span></div>
            </div>
            <div class="stat">
                <div class="stat-label">Turnout</div>
                <div class="stat-value green" data-stat="summary.turnout" data-format="percent">{{ number_format($summary['turnout'], 2) }}%</div>
                <div class="meter mt-1"><span data-width="{{ $summary['turnout'] }}" data-stat-width="summary.turnout"></span></div>
            </div>
        </div>

        @if ($tally)
            <section class="panel mb-3" aria-labelledby="live-tally-title">
                <div class="panel-head">
                    <h2 id="live-tally-title" class="h3">
                        {{ $focus->status->hasClosed() ? 'Vote count' : 'Live vote count' }}
                        @if ($summary['accepting_votes'])<span class="badge badge-live">LIVE</span>@endif
                    </h2>
                    <span class="small muted"><strong data-stat="tally.ballots">{{ number_format($tally['ballots']) }}</strong> ballots counted · refreshes every 10 seconds</span>
                </div>
                <div class="panel-body">
                    <p class="small muted mt-0">Counted directly from recorded votes. These figures are unofficial until the Returning Officer verifies and publishes the results.</p>
                    <div class="tally-grid">
                        @foreach ($tally['positions'] as $p)
                            <div class="tally-position">
                                <div class="tally-head">
                                    <h3 class="mb-0">{{ $p['name'] }}@if ($p['seats'] > 1) <span class="muted small">({{ $p['seats'] }} seats)</span>@endif</h3>
                                    <span class="small muted"><span data-tally-total="{{ $p['id'] }}">{{ number_format($p['total']) }}</span> votes</span>
                                </div>
                                <ol class="tally-list" data-tally-list>
                                    @foreach ($p['candidates'] as $i => $c)
                                        <li class="tally-row {{ $c['leading'] ? 'is-leading' : '' }}" data-tally-row="{{ $c['id'] }}" data-order="{{ $i }}">
                                            <x-candidate-avatar :candidate="$c['candidate']" size="small" />
                                            <div class="tally-body">
                                                <div class="tally-line">
                                                    <span class="tally-name">{{ $c['candidate']->displayName() }}
                                                        <span class="badge badge-success" data-tally-lead @unless ($c['leading']) hidden @endunless>Leading</span></span>
                                                    <span class="tally-votes"><strong data-tally-votes>{{ number_format($c['votes']) }}</strong>
                                                        <span class="small muted" data-tally-pct>{{ number_format($c['percentage'], 1) }}%</span></span>
                                                </div>
                                                <div class="meter"><span data-width="{{ $c['percentage'] }}" data-tally-bar></span></div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div class="grid-2">
            <div class="panel">
                <div class="panel-head"><h3>Votes cast by hour (WAT)</h3><span class="small muted">Last vote: <strong data-stat="summary.last_vote_at">{{ $summary['last_vote_at'] ?? '-' }}</strong></span></div>
                <div class="panel-body">
                    <div class="chart" data-bar-chart="{{ json_encode($hourly) }}" data-chart-source="hourly" data-label="Votes cast per hour" data-empty="No votes have been cast yet."></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3>At a glance</h3></div>
                <div class="panel-body">
                    <ul class="checklist">
                        <li><span>Is the election open?</span><strong class="{{ $summary['accepting_votes'] ? 'ok' : '' }}">{{ $summary['accepting_votes'] ? 'Yes, voting in progress' : 'No' }}</strong></li>
                        <li><span>Active voting sessions</span><strong data-stat="summary.sessions.active">{{ $summary['sessions']['active'] }}</strong></li>
                        <li><span>Completed sessions</span><strong data-stat="summary.sessions.completed">{{ $summary['sessions']['completed'] }}</strong></li>
                        <li><span>Expired / revoked sessions</span><strong><span data-stat="summary.sessions.expired">{{ $summary['sessions']['expired'] }}</span> / <span data-stat="summary.sessions.revoked">{{ $summary['sessions']['revoked'] }}</span></strong></li>
                        <li><span>Failed OTP entries (last hour)</span><strong class="{{ $security['failed_otp_last_hour'] ? 'bad' : '' }}" data-stat="security.failed_otp_last_hour">{{ $security['failed_otp_last_hour'] }}</strong></li>
                        <li><span>Unrecognised Service Numbers (last hour)</span><strong data-stat="security.failed_lookups_last_hour">{{ $security['failed_lookups_last_hour'] }}</strong></li>
                        <li><span>Failed admin sign-ins (last hour)</span><strong data-stat="security.failed_admin_logins_last_hour">{{ $security['failed_admin_logins_last_hour'] }}</strong></li>
                        <li><span>Open security alerts</span><strong class="{{ $security['open_alerts'] ? 'bad' : 'ok' }}" data-stat="security.open_alerts">{{ $security['open_alerts'] }}</strong></li>
                        <li><span>Has the election closed?</span><strong>{{ $focus->status->hasClosed() ? 'Yes, '.display_time($focus->closed_at, 'H:i j M') : 'No' }}</strong></li>
                        <li><span>Are results ready?</span><strong class="{{ $focus->result_status->value === 'PUBLISHED' ? 'ok' : '' }}">{{ $focus->result_status->label() }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if ($alerts->isNotEmpty())
        <div class="panel mt-3">
            <div class="panel-head"><h3>Recent security alerts</h3><a href="{{ route('admin.alerts.index') }}" class="small">All alerts</a></div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    @foreach ($alerts as $alert)
                        <tr>
                            <td><x-status-badge :status="$alert->severity" /></td>
                            <td>{{ $alert->description }}</td>
                            <td class="num small muted">×{{ $alert->occurrences }}</td>
                            <td class="nowrap small muted">{{ display_time($alert->last_seen_at, 'H:i j M') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif

@if ($elections->isNotEmpty())
    <h2 class="mt-4">Elections</h2>
    @include('admin.elections.partials.table', ['elections' => $elections])
@endif
@endsection
