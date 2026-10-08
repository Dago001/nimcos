@extends('layouts.voter')

@section('title', 'Results · '.$election->name)
@section('main-class', 'wide')

@section('content')
<div class="public-results">
    <div class="results-header-center">
        <div class="results-logo-wrap">
            <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS seal" width="88" height="88" class="results-logo">
        </div>
        <div class="step-label">Official results</div>
        <h1 class="page-title">{{ $election->name }}</h1>
        <p class="lede">Published {{ display_time($election->published_at) }} (WAT).</p>
    </div>

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
        <div class="endorsement-card">
            <div class="endorsement-header">
                <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="Seal" width="28" height="28" class="endorsement-seal-img">
                <span class="endorsement-header-title">OFFICIAL RESULTS CERTIFICATION</span>
            </div>

            <div class="endorsement-body">
                <div class="endorsement-item">
                    <span class="endorsement-label">NAME:</span>
                    <strong class="endorsement-value">{{ $election->returning_officer_display_name }}</strong>
                </div>

                <div class="endorsement-item">
                    <span class="endorsement-label">POSITION:</span>
                    <strong class="endorsement-value">RETURNING OFFICER</strong>
                </div>

                <div class="endorsement-signature-section">
                    <span class="endorsement-label">SIGNATURE:</span>
                    <div class="endorsement-signature-frame">
                        <img src="{{ route('election.signature', $election) }}" alt="Returning Officer Signature" width="140" height="48" class="signature-img" loading="eager">
                    </div>
                </div>

                <div class="endorsement-item endorsement-certified-item">
                    <span class="endorsement-label">Certified &amp; Published:</span>
                    <span class="endorsement-date">{{ display_time($election->published_at, 'j F Y, H:i') }} (WAT)</span>
                </div>

                <div class="endorsement-badge">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Verified Authentic Result Sheet</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
