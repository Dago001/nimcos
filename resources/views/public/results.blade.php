@extends('layouts.voter')

@section('title', 'Results · '.$election->name)
@section('main-class', 'wide')

@section('content')
<div class="public-results">
    <div class="step-label">Official results</div>
    <h1 class="page-title">{{ $election->name }}</h1>
    <p class="lede">Published {{ display_time($election->published_at) }} (WAT).</p>

    <div class="stats">
        <div class="stat"><div class="stat-label">Eligible voters</div><div class="stat-value">{{ number_format($results['eligible']) }}</div></div>
        <div class="stat"><div class="stat-label">Ballots cast</div><div class="stat-value">{{ number_format($results['ballots']) }}</div></div>
        <div class="stat"><div class="stat-label">Turnout</div><div class="stat-value green">{{ number_format($results['turnout'], 2) }}%</div></div>
        <div class="stat"><div class="stat-label">Positions</div><div class="stat-value">{{ count($results['positions']) }}</div></div>
    </div>

    @foreach ($results['positions'] as $p)
        @include('partials.position-result', ['p' => $p])
    @endforeach

    <p class="small muted">Verification hash: <span class="mono">{{ $results['hash'] }}</span></p>
</div>
@endsection
