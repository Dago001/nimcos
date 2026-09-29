@extends('layouts.admin')

@section('title', $voter->service_number)

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.voters.index') }}">Voter register</a> / {{ $voter->service_number }}</div>
<div class="page-head">
    <div><h1>{{ $voter->fullName() }}</h1><div class="sub mono">{{ $voter->service_number }}</div></div>
    <a class="btn btn-secondary" href="{{ route('admin.voters.edit', $voter) }}">Edit</a>
</div>

<div class="grid-2">
    <div class="stack">
        <div class="panel"><div class="panel-body">
            <dl class="dl">
                <dt>Rank</dt><dd>{{ $voter->rankLabel() ?? '-' }}</dd>
                <dt>Command</dt><dd>{{ $voter->commandLabel() ?? '-' }}</dd>
                <dt>Formation</dt><dd>{{ $voter->formation ?? '-' }}</dd>
                <dt>Phone</dt><dd>{{ \App\Support\PhoneNumber::display($voter->phone) ?: '-' }}</dd>
                <dt>Email</dt><dd>{{ $voter->email ?? '-' }}</dd>
                <dt>Membership</dt><dd><x-status-badge :status="$voter->membership_status" /></dd>
                <dt>Register eligibility</dt><dd><x-status-badge :status="$voter->eligibility_status" /></dd>
                <dt>Verification</dt><dd><x-status-badge :status="$voter->verification_status" /> @if ($voter->verified_at)<span class="small muted">{{ display_time($voter->verified_at) }}</span>@endif</dd>
                <dt>Account</dt><dd><x-status-badge :status="$voter->account_status" /></dd>
                @if ($voter->status_reason)<dt>Status note</dt><dd>{{ $voter->status_reason }}</dd>@endif
                <dt>Registered</dt><dd>{{ display_time($voter->registered_at) }}</dd>
            </dl>
        </div></div>

        <div class="panel">
            <div class="panel-head"><h3>Election participation</h3></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Election</th><th>Status</th><th>Voted at</th></tr></thead>
                    <tbody>
                    @forelse ($participations as $ev)
                        <tr><td>{{ $ev->election->name }}</td><td><x-status-badge :status="$ev->eligibility_status" /></td><td>{{ $ev->voted_at ? display_time($ev->voted_at) : '-' }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="table-empty">Not on any election roll.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-body small muted">Only whether and when a voter voted is recorded against them. Ballot choices are stored anonymously and cannot be linked to this record.</div>
        </div>
    </div>

    <div class="stack">
        @if (auth()->user()->hasPermission('verify_voters'))
            <div class="panel"><div class="panel-body">
                <h3>Verification</h3>
                <form method="POST" action="{{ route('admin.voters.verify', $voter) }}">
                    @csrf
                    <div class="field"><label for="decision">Decision</label>
                        <select class="input" id="decision" name="decision"><option value="VERIFIED">Verified against service records</option><option value="REJECTED">Rejected</option></select></div>
                    <div class="field"><label for="vr">Note</label><input class="input" id="vr" name="reason" maxlength="500"></div>
                    <button class="btn btn-primary" type="submit">Record decision</button>
                </form>
            </div></div>
        @endif

        <div class="panel"><div class="panel-body">
            <h3>Account</h3>
            @if ($voter->account_status->value === 'ACTIVE')
                <form method="POST" action="{{ route('admin.voters.suspend', $voter) }}">
                    @csrf
                    <div class="field"><label for="sr">Reason for suspension</label><input class="input" id="sr" name="reason" required maxlength="500"></div>
                    <button class="btn btn-danger" type="submit">Suspend voter</button>
                    <div class="help mt-1">Blocks sign-in and suspends the voter on every roll where they have not yet voted.</div>
                </form>
            @else
                <form method="POST" action="{{ route('admin.voters.reinstate', $voter) }}">
                    @csrf
                    <div class="field"><label for="rr">Reason for reinstatement</label><input class="input" id="rr" name="reason" required maxlength="500"></div>
                    <button class="btn btn-primary" type="submit">Reinstate voter</button>
                </form>
            @endif
        </div></div>

        @if (auth()->user()->hasPermission('view_audit_logs') && $history->isNotEmpty())
            <div class="panel">
                <div class="panel-head"><h3>Record history</h3></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @foreach ($history as $log)
                        <tr><td class="small nowrap">{{ display_time($log->created_at, 'j M H:i') }}</td><td class="small">{{ $log->action }}</td><td class="small muted">{{ $log->actor_label }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </div>
        @endif
    </div>
</div>
@endsection
