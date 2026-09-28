@extends('layouts.admin')

@section('title', 'Change password')

@section('content')
<div class="page-head"><div><h1>Change password</h1><div class="sub">At least {{ config('nimcos.admin.password_min_length') }} characters with upper- and lower-case letters, a number and a symbol.</div></div></div>

<div class="panel maxw-sm">
    <form class="panel-body" method="POST" action="{{ route('admin.profile.password.update') }}" data-submit-once>
        @csrf @method('PUT')
        <div class="field">
            <label for="current_password">Current password</label>
            <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            @error('current_password')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password">New password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary" type="submit">Change password</button>
    </form>
</div>
@endsection
