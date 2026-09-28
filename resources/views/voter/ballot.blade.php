@extends('layouts.voter')

@section('title', 'Ballot')
@section('main-class', 'wide')

@section('ballot-top')
<div class="ballot-top" aria-label="Ballot progress">
    <div class="inner">
        <div>
            <strong>{{ $election->name }}</strong><br>
            <span class="muted" data-step-text>{{ $ballot->count() }} positions</span>
        </div>
        <div class="right" data-expires-at="{{ $expiresAt->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}">
            <span class="muted small">Session time left</span><br>
            <strong class="mono" data-countdown>{{ now()->diff($expiresAt)->format('%i:%S') }}</strong>
        </div>
    </div>
    <div class="progress" aria-hidden="true"><span data-progress-bar></span></div>
</div>
@endsection

@section('content')
@php($total = $ballot->count())

@if ($message)
    <div class="alert alert-error" role="alert">
        <strong>{{ $message }}</strong>
        @if (count($fieldErrors))
            <ul>
                @foreach ($fieldErrors as $posId => $err)
                    <li>@if ($ballot->position($posId))<a href="#pos-{{ $posId }}">{{ $err }}</a>@else{{ $err }}@endif</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

<form method="POST" action="{{ route('voter.ballot.review') }}" data-ballot novalidate>
    @csrf
    <div class="ballot-layout">
        <aside class="ballot-index" aria-label="Positions on this ballot">
            <ol>
                @foreach ($ballot->positions as $i => $ep)
                    <li class="{{ ! empty($selected[$ep->id]) ? 'done' : '' }} {{ isset($fieldErrors[$ep->id]) ? 'has-error' : '' }}">
                        <a href="#pos-{{ $ep->id }}"><span class="dot" aria-hidden="true"></span>{{ $ep->position->name }}</a>
                    </li>
                @endforeach
            </ol>
        </aside>

        <div>
            @foreach ($ballot->positions as $i => $ep)
                @php($multi = $ep->seats > 1)
                @php($chosen = $selected[$ep->id] ?? [])
                <fieldset class="position {{ isset($fieldErrors[$ep->id]) ? 'has-error' : '' }}" id="pos-{{ $ep->id }}" data-seats="{{ $ep->seats }}"
                          @if (isset($fieldErrors[$ep->id])) aria-describedby="err-{{ $ep->id }}" @endif>
                    <legend>
                        <span class="position-count">Position {{ $i + 1 }} of {{ $total }}</span>
                        <span class="position-name">{{ $ep->position->name }}</span>
                    </legend>
                    <p class="position-instruction">
                        @if ($multi)
                            Select up to {{ $ep->seats }} candidates.
                        @else
                            Select one candidate.
                        @endif
                        @unless ($ep->is_required) This position is optional. @endunless
                    </p>
                    @if (isset($fieldErrors[$ep->id]))
                        <div class="error-text mb-2" id="err-{{ $ep->id }}" role="alert">{{ $fieldErrors[$ep->id] }}</div>
                    @endif

                    <div class="options">
                        @foreach ($ep->candidates as $candidate)
                            <label class="option">
                                <input type="{{ $multi ? 'checkbox' : 'radio' }}"
                                       name="selections[{{ $ep->id }}]{{ $multi ? '[]' : '' }}"
                                       value="{{ $candidate->id }}"
                                       @checked(in_array($candidate->id, $chosen, true))>
                                <span class="option-card">
                                    <x-candidate-avatar :candidate="$candidate" />
                                    <span class="candidate-text">
                                        <span class="candidate-no">CANDIDATE {{ $candidate->candidate_number }}</span>
                                        <span class="candidate-name">{{ $candidate->displayName() }}</span>
                                        <span class="candidate-meta">{{ $candidate->rank }}@if ($candidate->rank && $candidate->command) · @endif{{ $candidate->command }}</span>
                                    </span>
                                    <span class="select-indicator" aria-hidden="true">
                                        <span class="ring"></span>
                                        <span class="when-off">Select</span>
                                        <span class="when-on">Selected</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button type="button" class="link-button clear-choice small" data-clear>Clear selection for {{ $ep->position->name }}</button>
                </fieldset>
            @endforeach
        </div>
    </div>

    <div class="ballot-nav">
        <div class="inner">
            <button type="button" class="btn btn-secondary hidden" data-step-prev>Back</button>
            <div class="status" aria-live="polite"><span data-progress-text></span></div>
            <button type="button" class="btn btn-primary hidden" data-step-next>Next</button>
            <button type="submit" class="btn btn-primary" data-review>Review ballot</button>
        </div>
    </div>
</form>
@endsection
