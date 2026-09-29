@extends('layouts.admin')

@section('title', $suspendedOnly ? 'Suspended voters' : 'Voter register')

@section('content')
@php($canVerify = auth()->user()->hasPermission('verify_voters'))
<div class="page-head">
    <div><h1>{{ $suspendedOnly ? 'Suspended voters' : 'Voter register' }}</h1>
        <div class="sub">{{ number_format($voters->total()) }} record(s){{ $election ? ' · voting status for '.$election->name : '' }}</div></div>
    <div class="btn-row">
        @if (auth()->user()->hasPermission('import_voters'))<a class="btn btn-secondary" href="{{ route('admin.imports.create') }}">Import</a>@endif
        <a class="btn btn-primary" href="{{ route('admin.voters.create') }}">Add voter</a>
    </div>
</div>

<form class="filters" method="GET">
    <div class="field grow"><label for="q">Service Number or name</label><input class="input" id="q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
    <div class="field"><label for="rank">Rank</label><select class="input" id="rank" name="rank"><option value="">All</option>@foreach ($ranks as $r)<option value="{{ $r->value }}" @selected(($filters['rank'] ?? '') === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
    <div class="field"><label for="command">Command</label><select class="input" id="command" name="command"><option value="">All</option>@foreach ($commands as $c)<option @selected(($filters['command'] ?? '') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div class="field"><label for="formation">Formation</label><select class="input" id="formation" name="formation"><option value="">All</option>@foreach ($formations as $f)<option @selected(($filters['formation'] ?? '') === $f)>{{ $f }}</option>@endforeach</select></div>
    <div class="field"><label for="eligibility">Eligibility</label><select class="input" id="eligibility" name="eligibility"><option value="">All</option>@foreach (\App\Enums\VoterEligibility::cases() as $e)<option value="{{ $e->value }}" @selected(($filters['eligibility'] ?? '') === $e->value)>{{ $e->label() }}</option>@endforeach</select></div>
    <div class="field"><label for="verification">Verification</label><select class="input" id="verification" name="verification"><option value="">All</option>@foreach (\App\Enums\VerificationStatus::cases() as $v)<option value="{{ $v->value }}" @selected(($filters['verification'] ?? '') === $v->value)>{{ $v->label() }}</option>@endforeach</select></div>
    <div class="field"><label for="election">Election</label><select class="input" id="election" name="election"><option value="">All elections</option>@foreach ($elections as $e)<option value="{{ $e->id }}" @selected($election?->id === $e->id)>{{ $e->code }}</option>@endforeach</select></div>
    <div class="field"><label for="voting">Voting status</label><select class="input" id="voting" name="voting"><option value="">All</option>
        <option value="voted" @selected(($filters['voting'] ?? '') === 'voted')>Voted</option>
        <option value="not_voted" @selected(($filters['voting'] ?? '') === 'not_voted')>Not yet voted</option>
        <option value="not_on_roll" @selected(($filters['voting'] ?? '') === 'not_on_roll')>Not on roll</option></select></div>
    <button class="btn btn-secondary" type="submit">Filter</button>
</form>

<form method="POST" action="{{ route('admin.voters.verify-bulk') }}">
    @csrf
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                @if ($canVerify)<th><label class="sr-only" for="all">Select all</label><input type="checkbox" id="all" data-select-all="voter_ids[]"></th>@endif
                <th>Service No.</th><th>Name</th><th>Rank</th><th>Command / Formation</th><th>Register</th>@if ($election)<th>Voting status</th>@endif<th class="actions"></th>
            </tr></thead>
            <tbody>
            @forelse ($voters as $voter)
                @php($ev = $roll->get($voter->id))
                <tr>
                    @if ($canVerify)<td>@if ($voter->verification_status->value === 'UNVERIFIED')<input type="checkbox" name="voter_ids[]" value="{{ $voter->id }}" aria-label="Select {{ $voter->service_number }}">@endif</td>@endif
                    <td class="mono">{{ $voter->service_number }} @if ($voter->is_test_data)<span class="badge badge-warning">Test</span>@endif</td>
                    <td><a href="{{ route('admin.voters.show', $voter) }}">{{ $voter->fullName() }}</a></td>
                    <td class="small">{{ $voter->rankLabel() }}</td>
                    <td class="small">{{ $voter->command }}<br><span class="muted">{{ $voter->formation }}</span></td>
                    <td>
                        <x-status-badge :status="$voter->verification_status" />
                        @if ($voter->account_status->value !== 'ACTIVE')<x-status-badge :status="$voter->account_status" />@endif
                        @if ($voter->eligibility_status->value !== 'ELIGIBLE')<x-status-badge :status="$voter->eligibility_status" />@endif
                    </td>
                    @if ($election)
                        <td>@if ($ev)<x-status-badge :status="$ev->eligibility_status" />@else<span class="muted small">Not on roll</span>@endif</td>
                    @endif
                    <td class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('admin.voters.show', $voter) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No voters match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($canVerify && $voters->isNotEmpty())
        <div class="btn-row mt-2"><button class="btn btn-secondary btn-sm" type="submit">Verify selected records</button>
            <span class="small muted">Confirm each record against official service records before verifying.</span></div>
    @endif
</form>
{{ $voters->links() }}
@endsection
