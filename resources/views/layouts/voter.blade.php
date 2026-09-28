<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#207027">
    <title>@yield('title', 'Vote') · {{ config('nimcos.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/app.css') }}">
    <script src="{{ asset_v('assets/app.js') }}" defer></script>
</head>
<body class="voter-body">
<a class="skip-link" href="#main">Skip to main content</a>
@if (config('nimcos.demo_mode'))
    <div class="demo-banner" role="note">DEMONSTRATION ENVIRONMENT: test data only. Votes cast here are not real.</div>
@endif
<div class="band" aria-hidden="true"></div>
<header class="voter-header">
    <div class="inner">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS seal" width="44" height="44">
            <span>
                <span class="brand-name">{{ config('nimcos.name') }}</span><br>
                <span class="brand-sub">{{ config('nimcos.short_org') }}</span>
            </span>
        </a>
        @auth('voter')
            <form method="POST" action="{{ route('voter.logout') }}" class="no-print">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
            </form>
        @endauth
    </div>
</header>
{{-- No pop-ups or moving text while a voter is filling in or reviewing their ballot. --}}
@include('partials.announcements', [
    'showTicker' => ! request()->routeIs('voter.ballot', 'voter.ballot.*'),
    'showPopups' => ! request()->routeIs('voter.ballot', 'voter.ballot.*', 'voter.receipt', 'voter.otp'),
])
@yield('ballot-top')
<main id="main" class="voter-main @yield('main-class')" tabindex="-1">
    @include('partials.flash')
    @yield('content')
</main>
<footer class="voter-footer">
    <div class="inner">
        <span>© {{ now()->year }} {{ config('nimcos.short_org') }}. Your vote is confidential.</span>
        <span>
            <a href="{{ route('public.receipt') }}">Verify a ballot receipt</a>
            · <a href="{{ route('public.results') }}">Published results</a>
        </span>
    </div>
</footer>
</body>
</html>
