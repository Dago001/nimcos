@if (session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if (session('status'))
    <div class="alert alert-info" role="status">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
@endif
@php($summaryKeys = ['election', 'results', 'import', 'position', 'candidate', 'tie', 'roles', 'permissions', 'file', 'status'])
@if ($errors->hasAny($summaryKeys))
    <div class="alert alert-error" role="alert">
        <strong>This action could not be completed.</strong>
        <ul>
            @foreach ($summaryKeys as $key)
                @foreach ($errors->get($key) as $message)
                    @foreach ((array) $message as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
@if ($errors->has('confirm_password') || $errors->has('confirm_mfa'))
    <div class="alert alert-error" role="alert">{{ $errors->first('confirm_password') ?: $errors->first('confirm_mfa') }}</div>
@endif
