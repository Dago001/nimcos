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
                        <div class="help" id="sn-help">Enter your Service Number</div>
                    @enderror
                </div>

                <div class="human-check @error('human_check') is-invalid @enderror" data-human-check>
                    <label class="human-check-row">
                        <input type="checkbox" id="human_check" name="human_check" value="1" required
                               @error('human_check') aria-invalid="true" aria-describedby="human-check-error" @enderror>
                        <span>I am not a robot</span>
                    </label>
                    <div class="human-check-question" data-human-check-question>
                        <label for="human_check_answer">{{ $challenge['question'] }}</label>
                        <input class="input" type="text" inputmode="numeric" pattern="[0-9]*" id="human_check_answer" name="human_check_answer"
                               value="{{ old('human_check_answer') }}" autocomplete="off" maxlength="3">
                    </div>
                    <input type="hidden" name="human_check_token" value="{{ $challenge['token'] }}">
                    @error('human_check')
                        <div class="error-text" id="human-check-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-text="Checking…">Continue</button>
            </form>

            <ul class="notice-list small mt-3">
                <li>A one-time code will be sent to the email address registered for you with NIMCOS.</li>
                <li>Your Choices are Secret. Election Officials can see that you voted, But not who you voted.</li>
                @if ($support)
                    <li>Need help or have complaints? Contact support: <a href="mailto:{{ $support }}">{{ $support }}</a></li>
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
