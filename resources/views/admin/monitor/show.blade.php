@extends('layouts.admin')

@section('title', 'Live monitor · '.$election->name)

@section('content')
@include('admin.elections.partials.tabs')

<div data-poll-url="{{ route('admin.monitor.stats', $election) }}" data-poll-seconds="15">
    <div class="btn-row spread mb-2">
        <div class="btn-row">
            @if ($summary['accepting_votes'])
                <span class="badge badge-live">LIVE</span>
            @else
                <x-status-badge :status="$election->status" />
            @endif
            <span class="small muted">Refreshes every 15 s · updated <span data-poll-stamp>{{ display_time(now(), 'H:i:s') }}</span> · server time <strong data-stat="summary.server_time">{{ $summary['server_time'] }}</strong> WAT</span>
        </div>
        <span class="small muted">Figures show participation only. Candidate totals are never shown here.</span>
    </div>

    <div class="stats">
        <div class="stat"><div class="stat-label">Eligible voters</div><div class="stat-value" data-stat="summary.eligible">{{ number_format($summary['eligible']) }}</div></div>
        <div class="stat"><div class="stat-label">Votes cast</div><div class="stat-value green" data-stat="summary.voted">{{ number_format($summary['voted']) }}</div></div>
        <div class="stat"><div class="stat-label">Not yet voted</div><div class="stat-value" data-stat="summary.not_voted">{{ number_format($summary['not_voted']) }}</div></div>
        <div class="stat"><div class="stat-label">Turnout</div><div class="stat-value green" data-stat="summary.turnout" data-format="percent">{{ number_format($summary['turnout'], 2) }}%</div>
            <div class="meter mt-1"><span data-width="{{ $summary['turnout'] }}" data-stat-width="summary.turnout"></span></div></div>
    </div>

    <div class="stats">
        <div class="stat"><div class="stat-label">Last vote</div><div class="stat-value" data-stat="summary.last_vote_at">{{ $summary['last_vote_at'] ?? '-' }}</div></div>
        <div class="stat"><div class="stat-label">Active sessions</div><div class="stat-value" data-stat="summary.sessions.active">{{ $summary['sessions']['active'] }}</div></div>
        <div class="stat"><div class="stat-label">Completed sessions</div><div class="stat-value" data-stat="summary.sessions.completed">{{ $summary['sessions']['completed'] }}</div></div>
        <div class="stat"><div class="stat-label">Expired sessions</div><div class="stat-value" data-stat="summary.sessions.expired">{{ $summary['sessions']['expired'] }}</div><div class="stat-note">Revoked: <span data-stat="summary.sessions.revoked">{{ $summary['sessions']['revoked'] }}</span></div></div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h3>Voting activity by hour (WAT)</h3></div>
            <div class="panel-body">
                <div class="chart" data-bar-chart="{{ json_encode($hourly) }}" data-chart-source="hourly" data-label="Votes cast per hour" data-empty="No votes have been cast yet."></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3>Security signals (last hour)</h3></div>
            <div class="panel-body">
                <ul class="checklist">
                    <li><span>Failed OTP entries</span><strong data-stat="security.failed_otp_last_hour">{{ $security['failed_otp_last_hour'] }}</strong></li>
                    <li><span>Unrecognised Service Numbers</span><strong data-stat="security.failed_lookups_last_hour">{{ $security['failed_lookups_last_hour'] }}</strong></li>
                    <li><span>Failed admin sign-ins</span><strong data-stat="security.failed_admin_logins_last_hour">{{ $security['failed_admin_logins_last_hour'] }}</strong></li>
                    <li><span>Open alerts (high/critical)</span><strong><span data-stat="security.open_alerts">{{ $security['open_alerts'] }}</span> (<span data-stat="security.high_alerts">{{ $security['high_alerts'] }}</span>)</strong></li>
                </ul>
                @if (auth()->user()->hasPermission('view_audit_logs'))
                    <a class="btn btn-secondary btn-sm mt-2" href="{{ route('admin.alerts.index') }}">Review alerts</a>
                @endif
            </div>
        </div>
    </div>

    <div class="panel mt-3">
        <div class="panel-head"><h3>Turnout by command</h3><span class="small muted">Commands with fewer than 5 eligible voters are grouped to protect privacy.</span></div>
        <div class="panel-body">
            <ul class="hbar-list">
                @forelse ($byCommand as $row)
                    <li>
                        <div class="hbar-row"><span>{{ $row['label'] }}</span><span class="muted">{{ number_format($row['voted']) }} / {{ number_format($row['eligible']) }} · <strong>{{ number_format($row['turnout'], 1) }}%</strong></span></div>
                        <div class="meter"><span data-width="{{ $row['turnout'] }}"></span></div>
                    </li>
                @empty
                    <li class="muted">No voters on the roll.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
