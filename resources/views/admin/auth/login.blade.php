@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
<h1>Administrator sign in</h1>
<p class="muted mb-3">NIMCOS election officials only.</p>

<form method="POST" action="{{ route('admin.login.attempt') }}" data-submit-once novalidate>
    @csrf
    <div class="field">
        <label for="email">Email address</label>
        <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<div class="error-text" id="email-error" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<div class="error-text">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg" data-busy-text="Signing in…">Sign in</button>
</form>
@endsection
