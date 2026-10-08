<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#207027">
    <meta name="robots" content="noindex, nofollow">
    <title>System Under Maintenance · {{ config('nimcos.name', 'NIMCOS E-VOTING') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/app.css') }}">
</head>
<body class="voter-body">
<div class="band" aria-hidden="true"></div>
<header class="voter-header">
    <div class="inner">
        <div class="brand">
            <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS seal" width="44" height="44">
            <span>
                <span class="brand-name">{{ config('nimcos.name', 'NIMCOS E-VOTING') }}</span><br>
                <span class="brand-sub">{{ config('nimcos.org', 'Nigeria Immigration Multi-Purpose Cooperative Society') }}</span>
            </span>
        </div>
    </div>
</header>

<main class="voter-main" id="main">
    <div class="panel maxw-md">
        <div class="panel-body text-center">
            <div class="maintenance-badge-wrap">
                <span class="badge badge-warning">System Maintenance</span>
            </div>
            <h1>System Under Maintenance</h1>
            <p class="lede">
                {{ !empty($message) ? $message : 'The NIMCOS E-Voting Portal is currently undergoing scheduled maintenance. Please check back shortly.' }}
            </p>

            @if (!empty($supportContact) || !empty($phone))
                <div class="alert alert-info">
                    <strong>Need assistance?</strong>
                    @if (!empty($supportContact))
                        <div>{{ $supportContact }}</div>
                    @endif
                    @if (!empty($phone))
                        <div>Phone: {{ $phone }}</div>
                    @endif
                </div>
            @endif

            <div class="maintenance-actions">
                <a class="btn btn-primary" href="{{ url()->current() }}">Refresh page</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
