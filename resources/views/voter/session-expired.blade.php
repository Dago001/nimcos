@extends('layouts.voter')

@section('title', 'Session expired')

@section('content')
<div class="panel maxw-md">
    <div class="panel-body">
        <h1 class="page-title">Your ballot session has ended</h1>
        <p class="lede">For your security, ballot sessions end after a period of inactivity, when you sign in on another device, or when voting closes. <strong>Nothing was submitted from the expired session.</strong></p>
        <p>If voting is still open, sign in again to start a new ballot.</p>
        <a class="btn btn-primary" href="{{ route('voter.entry') }}">Return to sign in</a>
    </div>
</div>
@endsection
