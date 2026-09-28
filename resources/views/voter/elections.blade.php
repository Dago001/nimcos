@extends('layouts.voter')

@section('title', 'Your elections')

@section('content')
<h1 class="page-title">Welcome, {{ $voter->first_name }}</h1>
<p class="lede">Select the election you wish to take part in.</p>

@forelse ($participations as $ev)
    <div class="election-card">
        <div class="btn-row spread">
            <div>
                <h2 class="mb-0">{{ $ev->election->name }}</h2>
                <div class="muted small">Voting closes {{ display_time($ev->election->ends_at, 'H:i, j M Y') }} (WAT)</div>
            </div>
            @if ($ev->hasVoted())
                <span class="badge badge-success">✓ You have voted</span>
            @else
                <a class="btn btn-primary" href="{{ route('voter.election', $ev->election->code) }}">Continue</a>
            @endif
        </div>
    </div>
@empty
    <div class="alert alert-info" role="status">
        <strong>There is no election for you to vote in right now.</strong>
        Voting may have closed, or you may not be on the roll for the current election. If you believe this is an error, contact the election administrator.
    </div>
@endforelse
@endsection
