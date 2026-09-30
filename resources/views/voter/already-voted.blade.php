@extends('layouts.voter')

@section('title', 'Already voted')
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

        <h1 class="page-title">You have already voted</h1>
        <p class="lede">Our records show that your ballot for <strong>{{ $election->name }}</strong> was recorded on {{ display_time($votedAt, 'j F Y') }} at {{ display_time($votedAt, 'H:i') }} (WAT). Each member may vote only once.</p>

        @if ($receipt)
            <div class="label">Ballot reference</div>
            <div class="reference mb-3">{{ $receipt->reference }}</div>
        @endif

        <p class="small muted">If you did not cast this vote, contact the election administrator immediately.</p>
        <form method="POST" action="{{ route('voter.finish') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg">Sign out</button>
        </form>
    </div>
</div>
@endsection
