@php
    $settings = app(\App\Services\Settings\SettingsService::class);
    $contact = [
        'address' => $settings->get('contact_address'),
        'phone' => $settings->get('contact_phone'),
        'email' => $settings->get('contact_email'),
        'hours' => $settings->get('contact_hours'),
        'support' => $settings->get('support_contact'),
    ];
    $hasContact = collect($contact)->filter()->isNotEmpty();
    $current = $openElections->first();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#207027">
    <meta name="description" content="Official electronic voting platform of the Nigeria Immigration Multi-Purpose Cooperative Society (NIMCOS).">
    <title>{{ config('nimcos.name') }} · {{ config('nimcos.short_org') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/app.css') }}">
    <script src="{{ asset_v('assets/app.js') }}" defer></script>
</head>
<body class="home-body">
<a class="skip-link" href="#main">Skip to main content</a>
@if (config('nimcos.show_demo_banner'))
    <div class="demo-banner" role="note">DEMONSTRATION ENVIRONMENT: test data only. Votes cast here are not real.</div>
@endif

<header class="site-header">
    <nav class="site-nav" aria-label="Main">
        <a class="site-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="" width="48" height="48">
            <span><span class="site-brand-name">NIMCOS</span><span class="site-brand-sub">E-VOTING</span></span>
        </a>
        <div class="site-links" id="site-links" data-nav-panel>
            <a href="{{ route('home') }}" aria-current="page">Home</a>
            <a href="#how-to-vote">How to vote</a>
            <a href="#requirements">Requirements</a>
            <a href="{{ route('public.results') }}">Results</a>
            <a href="#contact">Contact</a>
            <div class="site-links-actions">
                <button type="button" class="btn btn-danger btn-sm" data-dialog-open="contact-support">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contact support
                </button>
                <a class="btn btn-primary btn-sm" href="{{ route('voter.entry') }}">Vote now</a>
            </div>
        </div>
        <button type="button" class="nav-toggle" data-nav-toggle aria-controls="site-links" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            <span class="sr-only">Menu</span>
        </button>
    </nav>
</header>

@include('partials.announcements', ['showTicker' => true, 'showPopups' => true])

<main id="main" tabindex="-1">
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-inner">
            @if ($current)
                <p class="hero-status"><span class="live-dot" aria-hidden="true"></span> Voting is open: {{ $current->name }}, until {{ display_time($current->ends_at, 'H:i, j M Y') }} (WAT)</p>
            @elseif ($nextElection)
                <p class="hero-status">Next election: {{ $nextElection->name }}, opens {{ display_time($nextElection->starts_at, 'H:i, j M Y') }} (WAT)</p>
            @endif
            <h1 id="hero-title">Elect your NIMCOS officers<span class="hero-accent">Vote Now!</span></h1>
            <div class="hero-actions">
                <div class="hero-action">
                    <span>Ready to vote?</span>
                    <a class="btn btn-primary btn-lg" href="{{ route('voter.entry') }}">Vote now</a>
                </div>
                <div class="hero-action">
                    <span>Want to see the outcome?</span>
                    <a class="btn btn-light btn-lg" href="{{ $latestResults ? route('public.results.show', $latestResults->code) : route('public.results') }}">View results</a>
                </div>
            </div>
            <div class="hero-action hero-action-small">
                <span>Already voted?</span>
                <a class="btn btn-outline-light" href="{{ route('public.receipt') }}">Verify your receipt
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></a>
            </div>
        </div>
    </section>

    <section class="home-section" id="how-to-vote" aria-labelledby="steps-title">
        <h2 class="home-title" id="steps-title">Steps to Vote</h2>
        <ol class="step-cards">
            <li class="step-card">
                <span class="step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h3M15 12h3M6 16h12"/></svg></span>
                <h3>Enter your Service Number.</h3>
                <p>Type your Service Number exactly as it appears on the NIMCOS register.</p>
            </li>
            <li class="step-card">
                <span class="step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
                <h3>Enter the emailed code.</h3>
                <p>A 6 digit code is sent to your registered email address. It works once and expires after a few minutes.</p>
            </li>
            <li class="step-card">
                <span class="step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg></span>
                <h3>Choose your candidates.</h3>
                <p>Make your choice for each position, then review everything before you submit.</p>
            </li>
            <li class="step-card">
                <span class="step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 14l2 2 4-4"/></svg></span>
                <h3>Confirm and keep your receipt.</h3>
                <p>Submit once. Your receipt reference proves your ballot was counted, never how you voted.</p>
            </li>
        </ol>
    </section>

    <section class="home-band" id="requirements" aria-labelledby="req-title">
        <h2 class="home-title" id="req-title">Requirements</h2>
        <div class="req-grid">
            <div class="req-item">
                <span class="req-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="52" height="52"><rect x="6" y="10" width="36" height="28" rx="4" fill="#207027"/><rect x="10" y="16" width="12" height="14" rx="2" fill="#fff"/><circle cx="16" cy="21" r="3" fill="#207027"/><path d="M11 29c1-3 3-4 5-4s4 1 5 4" fill="#207027"/><rect x="26" y="17" width="12" height="3" rx="1.5" fill="#f5b041"/><rect x="26" y="23" width="9" height="3" rx="1.5" fill="#fff"/><rect x="26" y="29" width="11" height="3" rx="1.5" fill="#fff"/></svg></span>
                <h3>Service Number</h3>
                <p>Enter your Service Number exactly as it appears on the NIMCOS register to avoid being turned away.</p>
            </div>
            <div class="req-item">
                <span class="req-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="52" height="52"><rect x="5" y="11" width="38" height="27" rx="4" fill="#f5b041"/><path d="m6 14 18 13 18-13" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round"/><circle cx="38" cy="12" r="7" fill="#207027"/><path d="m35 12 2 2 4-4" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <h3>Registered email address</h3>
                <p>Your verification code goes to the email NIMCOS holds for you. Check your spam folder if it does not arrive.</p>
            </div>
            <div class="req-item">
                <span class="req-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="52" height="52"><path d="M24 4 8 10v12c0 10 7 18 16 22 9-4 16-12 16-22V10z" fill="#207027"/><path d="m17 24 5 5 10-11" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <h3>Active, eligible member</h3>
                <p>You must be an active NIMCOS member authorised on the roll for this election. Each member votes once.</p>
            </div>
        </div>
    </section>

    <section class="home-section" aria-labelledby="who-title">
        <h2 class="home-title" id="who-title">Who Can Vote</h2>
        <p class="home-note"><strong>Note:</strong> Make sure your details on the NIMCOS register are correct before election day.</p>
        <div class="photo-cards">
            <article class="photo-card">
                <img src="{{ asset('images/nimcos-office.jpg') }}" alt="The NIMCOS office building" loading="lazy" width="548" height="364">
                <h3>Active members of the Nigeria Immigration Multi-Purpose Cooperative Society.</h3>
            </article>
            <article class="photo-card">
                <img src="{{ asset('images/hq-atrium.jpg') }}" alt="Interior of the Nigeria Immigration Service Headquarters" loading="lazy" width="1056" height="800">
                <h3>Members authorised on the voter roll by the Electoral Committee.</h3>
            </article>
            <article class="photo-card">
                <img src="{{ asset('images/hq-rotunda.jpg') }}" alt="The Nigeria Immigration Service Headquarters building" loading="lazy" width="1080" height="607">
                <h3>One member, one secret ballot, counted exactly once.</h3>
            </article>
        </div>
    </section>
</main>

<footer class="site-footer" id="contact">
    <div class="site-footer-main">
        <div class="site-footer-org">
            <a class="site-brand" href="{{ route('home') }}">
                <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="" width="44" height="44">
                <span><span class="site-brand-name">NIMCOS</span><span class="site-brand-sub">E-VOTING</span></span>
            </a>
            <h2>{{ config('nimcos.short_org') }}</h2>
            @if ($contact['address'])<p class="muted">{{ $contact['address'] }}</p>@endif
            <ul class="contact-list">
                @if ($contact['phone'])
                    <li><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>
                @endif
                @if ($contact['hours'])
                    <li><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> {{ $contact['hours'] }}</li>
                @endif
                @if ($contact['email'])
                    <li><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
                @endif
                @if (! $hasContact)
                    <li>Contact the NIMCOS Electoral Committee for help with voting.</li>
                @endif
            </ul>
        </div>
        <div class="site-footer-links">
            <h2>Voting and Results</h2>
            <ul>
                <li><a href="{{ route('voter.entry') }}">Sign in to vote</a></li>
                <li><a href="#how-to-vote">How to vote</a></li>
                <li><a href="{{ route('public.results') }}">Published results</a></li>
                <li><a href="{{ route('public.receipt') }}">Verify a ballot receipt</a></li>
                <li><a href="{{ route('admin.login') }}">Election officials</a></li>
            </ul>
        </div>
    </div>
    <div class="site-footer-bar">
        <span>Your vote is secret. Officials can see that you voted, never how.</span>
    </div>
    @include('partials.legal-footer')
</footer>

<dialog class="modal" id="contact-support" aria-labelledby="contact-support-title">
    <div class="modal-head"><h2 id="contact-support-title">Contact support</h2></div>
    <div class="modal-body">
        <p>Having trouble signing in or did not receive your code? Before contacting us, check that you are using your Service Number and look in your email spam folder.</p>
        <dl class="dl">
            @if ($contact['support'])<dt>Election support</dt><dd>{{ $contact['support'] }}</dd>@endif
            @if ($contact['phone'])<dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></dd>@endif
            @if ($contact['email'])<dt>Email</dt><dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd>@endif
            @if ($contact['hours'])<dt>Hours</dt><dd>{{ $contact['hours'] }}</dd>@endif
            @if ($contact['address'])<dt>Office</dt><dd>{{ $contact['address'] }}</dd>@endif
        </dl>
        @if (! $hasContact)
            <p class="muted mb-0">Please contact the NIMCOS Electoral Committee at your command.</p>
        @endif
        <p class="small muted">Never share your verification code with anyone, including NIMCOS officials.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-primary" data-dialog-close>Close</button></div>
</dialog>
</body>
</html>
