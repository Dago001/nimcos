@extends('layouts.auth')

@section('title', 'Verify')

@section('content')
<h1>Two-step verification</h1>
<p class="muted mb-3">Enter the 6-digit code from your authenticator app.</p>

<form method="POST" action="{{ route('admin.mfa.verify') }}" data-submit-once>
    @csrf
    <div class="field">
        <label for="mfa_code">Authenticator code</label>
        <input class="input input-lg otp-input" id="mfa_code" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus data-otp
               @error('mfa_code') aria-invalid="true" aria-describedby="mfa-error" @enderror>
        @error('mfa_code')<div class="error-text" id="mfa-error" role="alert">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Verify</button>
</form>
<form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
    @csrf
    <button type="submit" class="link-button">Cancel and sign out</button>
</form>
@endsection
