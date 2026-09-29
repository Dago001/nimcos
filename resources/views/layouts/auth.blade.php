<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('nimcos.name') }} Administration</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/app.css') }}">
    <script src="{{ asset_v('assets/app.js') }}" defer></script>
</head>
<body>
<div class="band" aria-hidden="true"></div>
<div class="auth-page">
    <div class="auth-visual" role="img" aria-label="Interior of the Nigeria Immigration Service Headquarters">
        <div class="caption">
            <strong>NIMCOS E-VOTING</strong><br>
            <span class="small">Election administration · authorised officials only</span>
        </div>
    </div>
    <main class="auth-panel" id="main">
        <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS seal" width="72" height="72" class="entry-seal">
        @if (config('nimcos.show_demo_banner'))
            <div class="alert alert-warning">Demonstration environment: fictitious data only.</div>
        @endif
        @include('partials.flash')
        @yield('content')
        <p class="small muted mt-4">All access to this system is logged and monitored.</p>
    </main>
</div>
</body>
</html>
