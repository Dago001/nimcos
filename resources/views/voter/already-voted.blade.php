@extends('layouts.voter')

@section('title', 'Already voted')

@section('content')
<div class="panel maxw-md">
    <div class="panel-body">
        <h1 class="page-title">You have already voted</h1>
        <p class="lede">Our records show that your ballot for <strong>{{ $election->name }}</strong> was recorded on {{ display_time($votedAt, 'j F Y') }} at {{ display_time($votedAt, 'H:i') }} (WAT). Each member may vote only once.</p>

        @if ($receipt)
            <div class="label">Ballot reference</div>
            <div class="reference mb-3">{{ $receipt->reference }}</div>
        @endif

        <p class="small muted">If you did not cast this vote, contact the election administrator immediately.</p>
        <form method="POST" action="{{ route('voter.finish') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Sign out</button>
        </form>
    </div>
</div>
@endsection
