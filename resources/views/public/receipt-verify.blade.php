@extends('layouts.voter')

@section('title', 'Verify a ballot receipt')

@section('content')
<div class="panel maxw-md">
    <div class="panel-body">
        <h1 class="page-title">Verify a ballot receipt</h1>
        <p class="lede">Enter the ballot reference from your receipt to confirm it is among the recorded ballots. This check never reveals how anyone voted.</p>

        <form method="POST" action="{{ route('public.receipt.verify') }}">
            @csrf
            <div class="field">
                <label for="reference">Ballot reference</label>
                <input class="input input-lg mono" id="reference" name="reference" value="{{ old('reference', $reference ?? '') }}"
                       placeholder="NIM-2026-XXXXXXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" required
                       @error('reference') aria-invalid="true" aria-describedby="ref-error" @enderror>
                @error('reference')<div class="error-text" id="ref-error">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary" type="submit">Verify</button>
        </form>

        @if ($result)
            <div class="mt-3" role="status">
                @if ($result['found'])
                    <div class="alert alert-success mb-0"><strong>Recorded.</strong> Ballot {{ $reference }} is recorded in {{ $result['election']->name }}.</div>
                @else
                    <div class="alert alert-warning mb-0"><strong>Not found.</strong> No ballot with reference {{ $reference }} was found. Check the reference and try again, or contact the election administrator.</div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
