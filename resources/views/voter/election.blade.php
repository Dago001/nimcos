@extends('layouts.voter')

@section('title', $election->name)

@section('content')
<div class="step-label">Step 3 of 3 · Vote</div>
<h1 class="page-title">{{ $election->name }}</h1>

@if ($acceptingVotes)
    <p class="lede"><span class="badge badge-live">Voting is open</span></p>
@else
    <div class="alert alert-warning" role="status">
        <strong>Voting is not open for this election.</strong>
        Ballots can be cast between {{ display_time($election->starts_at, 'H:i, j M Y') }} and {{ display_time($election->ends_at, 'H:i, j M Y') }} (WAT).
    </div>
@endif

<div class="facts">
    <div class="fact"><span>Election date</span><strong>{{ display_time($election->starts_at, 'j F Y') }}</strong></div>
    <div class="fact"><span>Voting period (WAT)</span><strong>{{ display_time($election->starts_at, 'H:i') }} to {{ display_time($election->ends_at, 'H:i') }}</strong></div>
    <div class="fact"><span>Positions</span><strong>{{ $positionCount }}</strong></div>
    <div class="fact"><span>Voter</span><strong>{{ $voter->surname }}, {{ $voter->first_name }}</strong></div>
</div>

<div class="panel mb-3">
    <div class="panel-body">
        <h2>Before you begin</h2>
        <ul class="notice-list mb-0">
            <li><strong>Your vote is confidential.</strong> Your choices are stored separately from your identity. No official can see how you voted.</li>
            <li>You will choose one candidate for each of the {{ $positionCount }} positions, then review your choices.</li>
            <li>You can go back and change any choice until you press <strong>Submit final ballot</strong>.</li>
            <li><strong>Your ballot cannot be changed after it is submitted.</strong> You can vote only once.</li>
            <li>Vote alone. Do not let anyone watch you vote or take photographs of your ballot.</li>
        </ul>
    </div>
</div>

@if ($acceptingVotes)
    <form method="POST" action="{{ route('voter.election.start', $election->code) }}" data-submit-once>
        @csrf
        <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-text="Preparing your ballot…">Start voting</button>
    </form>
@endif
@endsection
