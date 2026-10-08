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

    <div class="results-endorsement">
        <div class="endorsement-box">
            <div class="endorsement-header">Official Certification</div>
            @if ($election->returning_officer_signature)
                <div class="endorsement-signature">
                    <img src="{{ asset('storage/'.$election->returning_officer_signature) }}" alt="Returning Officer Signature" loading="lazy">
                </div>
            @endif
            <div class="endorsement-name">{{ $election->returning_officer_name ?: ($election->publisher?->name ?? 'Returning Officer') }}</div>
            <div class="endorsement-title">Returning Officer, NIMCOS Electoral Committee</div>
            <div class="endorsement-date">Certified & Published: {{ display_time($election->published_at, 'j F Y, H:i') }} (WAT)</div>
            <div class="endorsement-seal">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Verified Authentic Result Sheet
            </div>
        </div>
    </div>
</div>
@endsection
