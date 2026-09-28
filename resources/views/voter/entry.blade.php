@extends('layouts.voter')

@section('title', 'Sign in to vote')
@section('main-class', 'entry-wrap')

@section('content')
<div class="entry">
    <div class="entry-photo hq" role="img" aria-label="Nigeria Immigration Service Headquarters building at dusk">
        <div class="caption">
            <strong>Nigeria Immigration Multi-Purpose Cooperative Society</strong>
            <span>Electronic Voting Platform</span>
        </div>
    </div>

    <div class="entry-form">
        <img class="entry-seal" src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="Seal of the NIS Staff Multi-Purpose Co-operative Society Limited" width="88" height="88">

        @if ($openElections->isNotEmpty())
            <div class="step-label">Step 1 of 3 · Identify yourself</div>
            <h1 class="page-title">NIMCOS E-VOTING</h1>
            <p class="lede">
                {{ $openElections->pluck('name')->join(', ') }}.
                Voting is open until {{ display_time($openElections->first()->ends_at, 'H:i, j M Y') }} (WAT).
            </p>

            <form method="POST" action="{{ route('voter.access') }}" data-submit-once novalidate>
                @csrf
                <div class="field">
                    <label for="service_number">Service Number</label>
                    <input class="input input-lg" id="service_number" name="service_number" type="text" inputmode="numeric" pattern="[0-9]{4,5}"
                           value="{{ old('service_number') }}" autocomplete="off" spellcheck="false"
                           maxlength="5" required autofocus
                           @error('service_number') aria-invalid="true" aria-describedby="sn-error" @else aria-describedby="sn-help" @enderror>
                    @error('service_number')
                        <div class="error-text" id="sn-error" role="alert">{{ $message }}</div>
                    @else
                        <div class="help" id="sn-help">Enter your Service Number as it appears on the NIMCOS register.</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-text="Checking…">Continue</button>
            </form>

            <ul class="notice-list small mt-3">
                <li>A one-time code will be sent to the email address registered for you with NIMCOS.</li>
                <li>Your choices are secret. Election officials can see <em>that</em> you voted, never <em>how</em>.</li>
                @if ($support)
                    <li>Need help? Contact the election administrator: {{ $support }}</li>
                @endif
            </ul>
        @else
            <h1 class="page-title">NIMCOS E-VOTING</h1>
            <div class="alert alert-info" role="status">
                <strong>Voting is not open at this time.</strong>
                Ballots can only be cast during the official voting period.
            </div>
            @if ($upcoming->isNotEmpty())
                <h2>Upcoming</h2>
                @foreach ($upcoming as $e)
                    <div class="election-card">
                        <strong>{{ $e->name }}</strong>
                        <div class="muted small">
                            {{ display_time($e->starts_at, 'l, j F Y') }} ·
                            {{ display_time($e->starts_at, 'H:i') }} to {{ display_time($e->ends_at, 'H:i') }} (WAT)
                        </div>
                    </div>
                @endforeach
            @endif
            <p class="small muted mt-2"><a href="{{ route('public.results') }}">View published results</a></p>
        @endif
    </div>
</div>
@endsection
