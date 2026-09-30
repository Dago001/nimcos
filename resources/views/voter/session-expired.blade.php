@extends('layouts.voter')

@section('title', 'Session expired')
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

        <h1 class="page-title">Your ballot session has ended</h1>
        <p class="lede">For your security, ballot sessions end after a period of inactivity, when you sign in on another device, or when voting closes. <strong>Nothing was submitted from the expired session.</strong></p>
        <p>If voting is still open, sign in again to start a new ballot.</p>
        <a class="btn btn-primary btn-lg" href="{{ route('voter.entry') }}">Return to sign in</a>
    </div>
</div>
@endsection
