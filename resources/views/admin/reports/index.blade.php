@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<div class="page-head"><div><h1>Reports</h1><div class="sub">Download reports as PDF, Excel or CSV. Every download is recorded in the audit log. No report links voters to their choices.</div></div></div>

<div class="grid-2 even">
    @foreach ($types as $type => $label)
        @php($needsElection = in_array($type, ['turnout', 'results'], true))
        <form class="panel" method="GET" action="{{ route('admin.reports.download') }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="panel-head"><h3>{{ $label }}</h3></div>
            <div class="panel-body">
                <p class="small muted">
                    @switch($type)
                        @case('voter-register') Service Number, name, rank, command, eligibility and voting status (voted / not voted) for an election roll, or the full register. @break
                        @case('turnout') Eligible voters, votes cast, not voted and turnout, overall and by command. @break
                        @case('results') Votes and percentage per candidate and position. Available after the election closes; marked provisional until published. @break
                        @case('audit') Timestamp, actor, action, entity, result and IP address. @break
                    @endswitch
                </p>
                @if ($type !== 'audit')
                    <div class="field">
                        <label for="e-{{ $type }}">Election @if ($needsElection)<span class="req">*</span>@endif</label>
                        <select class="input" id="e-{{ $type }}" name="election" @required($needsElection)>
                            @unless ($needsElection)<option value="">Full register (all members)</option>@endunless
                            @foreach ($elections as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                        </select>
                    </div>
                @else
                    <div class="form-grid two">
                        <div class="field"><label for="af">From</label><input class="input" type="date" id="af" name="from"></div>
                        <div class="field"><label for="at">To</label><input class="input" type="date" id="at" name="to"></div>
                    </div>
                @endif
                <div class="btn-row">
                    <button class="btn btn-primary btn-sm" name="format" value="pdf" type="submit">PDF</button>
                    <button class="btn btn-secondary btn-sm" name="format" value="xlsx" type="submit">Excel</button>
                    <button class="btn btn-secondary btn-sm" name="format" value="csv" type="submit">CSV</button>
                </div>
            </div>
        </form>
    @endforeach
</div>
@endsection
