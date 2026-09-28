@extends('layouts.voter')

@section('title', 'Verify your identity')

@section('content')
<div class="panel maxw-sm">
    <div class="panel-body">
        <div class="step-label">Step 2 of 3 · Verify</div>
        <h1 class="page-title">Verify your identity</h1>
        <p class="lede">We have sent a {{ config('nimcos.otp.length') }}-digit code to your registered {{ $destination }}. It expires in {{ $ttl }} minutes.</p>

        @if ($demoCode)
            <div class="alert alert-warning" role="note">
                <strong>Demo environment</strong>
                Your test code is <span class="mono">{{ $demoCode }}</span>. (Codes are never shown on screen in production.)
            </div>
        @endif

        <form method="POST" action="{{ route('voter.otp.verify') }}" data-submit-once novalidate>
            @csrf
            <div class="field">
                <label for="code">One-time code</label>
                <input class="input input-lg otp-input" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="one-time-code" maxlength="{{ config('nimcos.otp.length') }}" required autofocus data-otp
                       @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')
                    <div class="error-text" id="code-error" role="alert">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-text="Verifying…">Verify</button>
        </form>

        <div class="btn-row spread mt-3">
            <form method="POST" action="{{ route('voter.otp.resend') }}">
                @csrf
                <button type="submit" class="link-button">Resend code</button>
            </form>
            <a href="{{ route('voter.entry') }}" class="small">Use a different Service Number</a>
        </div>
        <p class="small muted mt-3 mb-0">Never share this code. NIMCOS officials will never ask you for it.</p>
    </div>
</div>
@endsection
