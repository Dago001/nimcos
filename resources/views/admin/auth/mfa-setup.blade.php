@extends('layouts.auth')

@section('title', 'Set up Google Authenticator')
@section('hide-logo', true)

@section('content')
<h1 class="h2 mb-1">Set up Google Authenticator</h1>
<p class="muted small mb-2">Scan this QR code using Google Authenticator on your phone to link your account.</p>

<div class="qr-box">
    <img src="{{ $qr }}" alt="Google Authenticator QR code" width="140" height="140">
    <div class="small muted mt-2">Can't scan? Enter this key manually:</div>
    <div class="mono small font-semibold mt-1">{{ $secret }}</div>
</div>

<form method="POST" action="{{ route('admin.mfa.verify') }}" data-submit-once novalidate>
    @csrf
    <div class="mfa-field">
        <label for="mfa_code">Enter 6-digit code from Google Authenticator</label>
        <input class="input mfa-input" id="mfa_code" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus data-otp
               placeholder="123456"
               @error('mfa_code') aria-invalid="true" aria-describedby="mfa-error" @enderror>
        @error('mfa_code')<div class="error-text" id="mfa-error" role="alert">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary btn-block" data-busy-text="Verifying…">Verify &amp; Sign in</button>
</form>
<form method="POST" action="{{ route('admin.logout') }}" class="mt-2 text-center">
    @csrf
    <button type="submit" class="link-button small">Cancel and sign out</button>
</form>
@endsection
