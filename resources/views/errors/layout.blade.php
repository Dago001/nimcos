<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · NIMCOS E-VOTING</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="voter-body">
<div class="band" aria-hidden="true"></div>
<header class="voter-header"><div class="inner">
    <a class="brand" href="{{ url('/') }}"><img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS seal" width="44" height="44">
        <span><span class="brand-name">NIMCOS E-VOTING</span><br><span class="brand-sub">Nigeria Immigration Multi-Purpose Cooperative Society</span></span></a>
</div></header>
<main class="voter-main" id="main">
    <div class="panel maxw-md"><div class="panel-body">
        <div class="step-label">Error @yield('code')</div>
        <h1>@yield('heading')</h1>
        <p class="lede">@yield('message')</p>
        @hasSection('reference')<p class="small muted">Reference: <span class="mono">@yield('reference')</span></p>@endif
        <a class="btn btn-primary" href="{{ url('/') }}">Return to the start</a>
    </div></div>
</main>
</body>
</html>
