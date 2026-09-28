@extends('layouts.admin')

@section('title', 'Security & MFA')

@section('content')
<div class="page-head"><div><h1>Security &amp; multi-factor authentication</h1><div class="sub">{{ $user->email }}</div></div></div>

<div class="panel maxw-md">
    <div class="panel-body">
        @if ($user->hasMfa())
            <p><span class="badge badge-success">MFA enabled</span> since {{ display_time($user->mfa_confirmed_at) }}.</p>
            <p class="muted">You will be asked for an authenticator code at every sign-in and when confirming privileged actions.</p>
            @unless ($mfaRequired)
                <h2 class="mt-3">Disable MFA</h2>
                <form method="POST" action="{{ route('admin.profile.mfa.disable') }}" class="form-grid two">
                    @csrf @method('DELETE')
                    <div class="field">
                        <label for="dp">Password</label>
                        <input class="input" id="dp" type="password" name="confirm_password" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label for="dc">Authenticator code</label>
                        <input class="input" id="dc" name="mfa_code" inputmode="numeric" maxlength="6" required>
                        @error('mfa_code')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="span-2"><button class="btn btn-danger" type="submit">Disable MFA</button></div>
                </form>
            @endunless
        @else
            @if ($mfaRequired)
                <div class="alert alert-warning">Your organisation requires MFA. Complete this setup to continue using the system.</div>
            @endif
            <h2>Set up an authenticator app</h2>
            <ol class="mb-3">
                <li>Install an authenticator app (Google Authenticator, Microsoft Authenticator, Authy…).</li>
                <li>Scan this QR code, or enter the setup key manually.</li>
                <li>Enter the 6-digit code the app shows, with your password.</li>
            </ol>
            <img src="{{ $qr }}" alt="QR code for authenticator enrolment" width="200" height="200" class="mb-2">
            <p class="small">Setup key: <span class="mono">{{ $secret }}</span></p>

            <form method="POST" action="{{ route('admin.profile.mfa.enable') }}" class="form-grid two mt-2">
                @csrf
                <div class="field">
                    <label for="mfa_code">Code from app</label>
                    <input class="input" id="mfa_code" name="mfa_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
                    @error('mfa_code')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="cp">Your password</label>
                    <input class="input" id="cp" type="password" name="confirm_password" autocomplete="current-password" required>
                </div>
                <div class="span-2"><button class="btn btn-primary" type="submit">Enable MFA</button></div>
            </form>
        @endif
    </div>
</div>
@endsection
