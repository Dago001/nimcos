@extends('layouts.admin')

@section('title', 'Roles & permissions')

@section('content')
<div class="page-head"><div><h1>Roles &amp; permissions</h1><div class="sub">Access is granted by permission, never by role name. Changes are audited and require your password.</div></div></div>

@foreach ($roles as $role)
    @php($has = $role->permissions->pluck('id')->all())
    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="panel">
        @csrf @method('PUT')
        <div class="panel-head"><div><h3>{{ $role->label }}</h3><div class="small muted">{{ $role->description }} · {{ $role->users_count }} user(s)</div></div></div>
        <div class="panel-body">
            <div class="form-grid three">
                @foreach ($permissions as $group => $perms)
                    <fieldset class="mb-2">
                        <legend class="label">{{ $group }}</legend>
                        @foreach ($perms as $perm)
                            <label class="check mb-1 small"><input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $has, true))> <span>{{ $perm->label }} <span class="mono muted">{{ $perm->name }}</span></span></label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            <div class="btn-row mt-2">
                <label class="sr-only" for="pw-{{ $role->id }}">Your password</label>
                <input class="input w-auto" type="password" id="pw-{{ $role->id }}" name="confirm_password" placeholder="Your password" required autocomplete="current-password">
                @if (auth()->user()->hasMfa())
                    <label class="sr-only" for="mf-{{ $role->id }}">Authenticator code</label>
                    <input class="input w-auto" id="mf-{{ $role->id }}" name="confirm_mfa" placeholder="Authenticator code" inputmode="numeric" maxlength="6" required>
                @endif
                <button class="btn btn-primary" type="submit">Save {{ $role->label }}</button>
            </div>
        </div>
    </form>
@endforeach
@endsection
