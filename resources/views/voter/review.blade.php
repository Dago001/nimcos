@extends('layouts.voter')

@section('title', 'Review your ballot')

@section('content')
<div class="step-label">Review</div>
<h1 class="page-title">Review your ballot</h1>
<p class="lede">Check every selection carefully. You can go back and change any choice. Once you submit, your ballot cannot be changed.</p>

<ol class="review-list mb-3">
    @foreach ($ballot->positions as $ep)
        <li class="review-item">
            <div class="review-pos">{{ $ep->position->name }}</div>
            <div class="review-choice">
                @forelse ($selections[$ep->id] ?? [] as $candidateId)
                    @php($c = $ballot->candidate($ep->id, $candidateId))
                    <div>{{ $c->displayName() }} <span class="muted small">· Candidate {{ $c->candidate_number }}</span></div>
                @empty
                    <span class="review-none">No selection (optional position)</span>
                @endforelse
            </div>
        </li>
    @endforeach
</ol>

<div class="btn-row spread">
    <form method="POST" action="{{ route('voter.ballot.edit') }}">
        @csrf
        @include('voter.partials.selection-inputs', ['selections' => $selections])
        <button type="submit" class="btn btn-secondary btn-lg">Back and edit</button>
    </form>

    <form method="POST" action="{{ route('voter.ballot.submit') }}" data-final-ballot="confirm-ballot">
        @csrf
        @include('voter.partials.selection-inputs', ['selections' => $selections])
        <button type="submit" class="btn btn-primary btn-lg">Submit final ballot</button>
    </form>
</div>

<dialog class="modal" id="confirm-ballot" aria-labelledby="confirm-ballot-title" aria-describedby="confirm-ballot-desc">
    <div class="modal-head"><h2 id="confirm-ballot-title">Are you sure?</h2></div>
    <div class="modal-body">
        <p id="confirm-ballot-desc"><strong>Your ballot cannot be changed after submission.</strong> You are about to cast your vote in {{ $election->name }}.</p>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
        <button type="button" class="btn btn-primary" data-confirm-submit data-busy-text="Submitting…">Submit ballot</button>
    </div>
</dialog>
@endsection
