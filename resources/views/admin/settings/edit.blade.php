@extends('layouts.admin')

@section('title', 'System settings')

@section('content')
<div class="page-head"><div><h1>System settings</h1><div class="sub">Runtime settings. Secrets (database, mail server password) are configured only in the server environment file.</div></div></div>

<div class="grid-2">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="panel">
        @csrf @method('PUT')
        <div class="panel-body">
            @foreach ($definitions as $key => [$configPath, $type, $label, $help])
                <div class="field">
                    @if ($type === 'bool')
                        <label class="check"><input type="checkbox" name="{{ $key }}" value="1" @checked($values[$key])> <span><strong>{{ $label }}</strong></span></label>
                    @else
                        <label for="{{ $key }}">{{ $label }}</label>
                        <input class="input" id="{{ $key }}" name="{{ $key }}" type="{{ $type === 'int' ? 'number' : 'text' }}" value="{{ old($key, $values[$key]) }}">
                    @endif
                    <div class="help">{{ $help }}</div>
                    @error($key)<div class="error-text">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="form-grid two">
                <div class="field"><label for="cp">Your password <span class="req">*</span></label><input class="input" type="password" id="cp" name="confirm_password" required autocomplete="current-password"></div>
                @if (auth()->user()->hasMfa())
                    <div class="field"><label for="cm">Authenticator code <span class="req">*</span></label><input class="input" id="cm" name="confirm_mfa" inputmode="numeric" maxlength="6" required></div>
                @endif
            </div>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </div>
    </form>

    <div class="panel">
        <div class="panel-head"><h3>Environment (read-only)</h3></div>
        <div class="panel-body">
            <dl class="dl">
                @foreach ($environment as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach
            </dl>
        </div>
    </div>
</div>
@endsection
