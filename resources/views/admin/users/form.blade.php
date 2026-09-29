@extends('layouts.admin')

@section('title', $user->exists ? 'Manage administrator' : 'Add administrator')

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.users.index') }}">Administrators</a> / {{ $user->exists ? $user->email : 'New' }}</div>
<div class="page-head"><div><h1>{{ $user->exists ? $user->name : 'Add administrator' }}</h1>
    @if ($user->exists)<div class="sub">{{ $user->email }} · last sign-in {{ $user->last_login_at ? display_time($user->last_login_at).' from '.$user->last_login_ip : 'never' }}</div>@endif</div></div>

@if (session('temporary_password'))
    <div class="alert alert-warning" role="alert">
        <strong>Temporary password (shown once):</strong>
        <span class="mono">{{ session('temporary_password') }}</span><br>
        It is also emailed to the administrator. They must change it at first sign-in.
    </div>
@endif

<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="panel" data-submit-once>
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="panel-body form-grid two">
        <div class="field">
            <label for="name">Full name <span class="req">*</span></label>
            <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="150">
            @error('name')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="email">Email <span class="req">*</span></label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" @disabled($user->exists) required>
            @error('email')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="phone">Phone</label>
            <input class="input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20">
        </div>
        @if ($user->exists)
            <div class="field">
                <label for="status">Account status</label>
                <select class="input" id="status" name="status">
                    @foreach (\App\Enums\UserStatus::cases() as $s)<option value="{{ $s->value }}" @selected(old('status', $user->status->value) === $s->value)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
        @endif
        <fieldset class="field span-2">
            <legend class="label">Roles <span class="req">*</span></legend>
            @foreach ($roles as $role)
                <label class="check mb-1"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $assigned), true))>
                    <span><strong>{{ $role->label }}</strong> <span class="small muted">({{ $role->description }})</span></span></label>
            @endforeach
            @error('roles')<div class="error-text">{{ $message }}</div>@enderror
        </fieldset>
        @if ($user->exists && $user->isLocked())
            <label class="check span-2"><input type="checkbox" name="unlock" value="1"> Unlock account (locked until {{ display_time($user->locked_until, 'H:i') }})</label>
        @endif
        <div class="field">
            <label for="confirm_password">Your password (to confirm) <span class="req">*</span></label>
            <input class="input" id="confirm_password" name="confirm_password" type="password" autocomplete="current-password" required>
        </div>
        @if (auth()->user()->hasMfa())
            <div class="field">
                <label for="confirm_mfa">Your authenticator code <span class="req">*</span></label>
                <input class="input" id="confirm_mfa" name="confirm_mfa" inputmode="numeric" maxlength="6" required>
            </div>
        @endif
    </div>
    <div class="modal-foot">
        <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Back</a>
        <button class="btn btn-primary" type="submit">{{ $user->exists ? 'Save changes' : 'Create administrator' }}</button>
    </div>
</form>

@if ($user->exists)
    <div class="panel mt-3"><div class="panel-body">
        <h3>Reset password</h3>
        <p class="small muted">Generates a new temporary password, ends the user's active sessions and requires a change at next sign-in.</p>
        <x-reauth-dialog id="dlg-reset" :action="route('admin.users.reset-password', $user)" title="Reset password" button="Reset password" trigger-class="btn btn-secondary">
            <p>Reset the password for <strong>{{ $user->email }}</strong>?</p>
        </x-reauth-dialog>
    </div></div>
@endif
@endsection
