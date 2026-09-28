@extends('layouts.voter')

@section('title', 'Vote submitted')

@section('content')
<div class="receipt maxw-md">
    <div class="receipt-head">
        <div class="tick" aria-hidden="true">✓</div>
        <h1>VOTE SUCCESSFULLY SUBMITTED</h1>
    </div>
    <div class="receipt-body">
        <p>Your ballot for <strong>{{ $election->name }}</strong> has been recorded.</p>

        @if ($receipt)
            <div class="label">Ballot reference</div>
            <div class="reference mb-3" aria-label="Ballot reference {{ $receipt->reference }}">{{ $receipt->reference }}</div>
        @else
            <div class="alert alert-info">Your vote was recorded. The ballot reference is only available on the device used to vote.</div>
        @endif

        <dl class="dl mb-3">
            <dt>Date</dt><dd>{{ display_time($votedAt, 'j F Y') }}</dd>
            <dt>Time</dt><dd>{{ display_time($votedAt, 'H:i') }} (WAT)</dd>
        </dl>

        <p class="small muted">Keep this reference for your records. It confirms that your ballot was counted; it does not show, and cannot be used to prove, how you voted. You can check it at any time on the <a href="{{ route('public.receipt') }}">receipt verification page</a>.</p>

        <div class="btn-row mt-3">
            <button type="button" class="btn btn-secondary" data-print>Print receipt</button>
            <form method="POST" action="{{ route('voter.finish') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Finish and sign out</button>
            </form>
        </div>
    </div>
</div>
@endsection
