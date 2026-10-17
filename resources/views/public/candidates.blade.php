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
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#207027">
    <meta name="description" content="Official screened and cleared candidates for the Nigeria Immigration Multi-Purpose Cooperative Society (NIMCOS) Elections.">
    <title>Candidates · {{ config('nimcos.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/app.css') }}">
    <script src="{{ asset_v('assets/app.js') }}" defer></script>
</head>
<body class="candidates-body">
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
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('public.candidates') }}" aria-current="page">Candidates</a>
            <a href="{{ route('home') }}#requirements">Requirements</a>
            <a href="{{ route('home') }}#how-to-vote">How to vote</a>
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

@include('partials.announcements', ['showTicker' => true, 'showPopups' => false])

<main id="main" tabindex="-1">
    <section class="candidates-header-band" aria-labelledby="candidates-title">
        <div class="candidates-header-inner">
            <span class="candidates-badge">
                <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                Screened &amp; Cleared Candidates
            </span>
            <h1 class="candidates-header-title" id="candidates-title">Meet the Candidates</h1>
            <p class="candidates-header-sub">
                Review the profiles, ranks, commands, and manifestos of all candidates who have successfully undergone and passed the official screening process.
            </p>

            @if ($elections->count() > 1)
                <div class="candidates-election-pills" role="navigation" aria-label="Select election">
                    @foreach ($elections as $elec)
                        <a href="{{ route('public.candidates', ['election' => $elec->code]) }}"
                           class="candidates-election-pill {{ $selectedElection && $selectedElection->id === $elec->id ? 'is-active' : '' }}">
                            {{ $elec->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($selectedElection && $selectedElection->status === \App\Enums\ElectionStatus::DRAFT)
                <div class="badge badge-warning mt-2">
                    Administrator Preview: This election is in DRAFT status and is not yet publicly live.
                </div>
            @endif
        </div>
    </section>

    <div class="candidates-content">
        @if (! $selectedElection || $selectedElection->electionPositions->isEmpty())
            <div class="candidates-empty-card">
                <div class="candidates-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h2>No Screened Candidates Published Yet</h2>
                <p class="muted">Candidate profiles will appear here once the NIMCOS Electoral Screening Committee concludes screening and verification of contestants.</p>
                <div class="btn-row center mt-3">
                    <a href="{{ route('home') }}" class="btn btn-secondary">Return to Home</a>
                    <a href="{{ route('voter.entry') }}" class="btn btn-primary">Sign in to Vote</a>
                </div>
            </div>
        @else
            @php($hasAnyCandidates = false)
            @foreach ($selectedElection->electionPositions as $positionIndex => $ep)
                @php($activeCandidates = $ep->activeCandidates)
                @if ($activeCandidates->isNotEmpty())
                    @php($hasAnyCandidates = true)
                @endif
                <section class="position-showcase-card" id="pos-{{ $ep->id }}" aria-labelledby="pos-title-{{ $ep->id }}">
                    <div class="position-showcase-header">
                        <div class="position-showcase-title">
                            <span class="position-badge-number" aria-hidden="true">{{ $positionIndex + 1 }}</span>
                            <div>
                                <h2 class="mb-0" id="pos-title-{{ $ep->id }}">{{ $ep->position->name }}</h2>
                                <div class="position-showcase-subtitle">
                                    {{ $ep->seats }} {{ \Illuminate\Support\Str::plural('Seat', $ep->seats) }} available
                                    @unless ($ep->is_required) · <em>Optional position</em> @endunless
                                </div>
                            </div>
                        </div>
                        <div class="position-showcase-meta">
                            <span class="badge badge-info">{{ $activeCandidates->count() }} Cleared {{ \Illuminate\Support\Str::plural('Candidate', $activeCandidates->count()) }}</span>
                        </div>
                    </div>

                    <div class="position-showcase-body">
                        @if ($activeCandidates->isEmpty())
                            <div class="position-empty-notice">
                                <p class="muted mb-0">No cleared candidates currently listed for this position.</p>
                            </div>
                        @else
                            <div class="candidates-grid">
                                @foreach ($activeCandidates as $candidate)
                                    <article class="candidate-card" id="candidate-{{ $candidate->id }}">
                                        <div class="candidate-card-top">
                                            @if ($candidate->candidate_number)
                                                <span class="candidate-num-tag">Candidate {{ $candidate->candidate_number }}</span>
                                            @endif
                                            <div class="candidate-card-photo-wrap">
                                                <x-candidate-avatar :candidate="$candidate" size="large" />
                                            </div>
                                            <span class="candidate-verified-chip">
                                                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                                Screened &amp; Cleared
                                            </span>
                                            <h3 class="candidate-card-name">{{ $candidate->displayName() }}</h3>
                                            <p class="candidate-card-position">{{ $ep->position->name }}</p>
                                        </div>

                                        <div class="candidate-card-info">
                                            <div class="candidate-info-table">
                                                <div class="candidate-info-row">
                                                    <span class="candidate-info-label">Rank</span>
                                                    <span class="candidate-info-value">{{ $candidate->rankLabel() ?? 'Not specified' }}</span>
                                                </div>
                                                <div class="candidate-info-row">
                                                    <span class="candidate-info-label">Command</span>
                                                    <span class="candidate-info-value">{{ $candidate->commandLabel() ?? 'Not specified' }}</span>
                                                </div>
                                            </div>

                                            <div class="candidate-bio-wrap">
                                                <h4 class="candidate-bio-title">
                                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                                    Profile &amp; Brief Bio
                                                </h4>
                                                <div class="candidate-bio-box">
                                                    @if ($candidate->biography)
                                                        {!! nl2br(e($candidate->biography)) !!}
                                                    @else
                                                        <span class="candidate-bio-empty">No biography statement submitted.</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach

            @unless ($hasAnyCandidates)
                <div class="candidates-empty-card">
                    <div class="candidates-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h2>Candidate Screening in Progress</h2>
                    <p class="muted">Contestants are currently undergoing verification by the Electoral Screening Committee. Cleared candidates will be published here shortly.</p>
                </div>
            @endunless
        @endif

        <div class="candidates-cta-band">
            <div class="candidates-cta-inner">
                <div>
                    <h2>Ready to exercise your democratic right?</h2>
                    <p class="muted mb-0">Cast your vote securely using your Service Number and registered email verification code.</p>
                </div>
                <div class="candidates-cta-btn">
                    <a class="btn btn-primary btn-lg" href="{{ route('voter.entry') }}">Vote Now</a>
                </div>
            </div>
        </div>
    </div>
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
                <li><a href="{{ route('public.candidates') }}">Screened candidates</a></li>
                <li><a href="{{ route('home') }}#how-to-vote">How to vote</a></li>
                <li><a href="{{ route('public.results') }}">Published results</a></li>
                <li><a href="{{ route('public.receipt') }}">Verify a ballot receipt</a></li>
            </ul>
        </div>
    </div>
    <div class="site-footer-bar">
        <span>Your vote is secret. Officials can see that you voted, not who you voted.</span>
    </div>
    @include('partials.legal-footer')
</footer>

<dialog class="modal" id="contact-support" aria-labelledby="contact-support-title">
    <div class="modal-head"><h2 id="contact-support-title">Contact support</h2></div>
    <div class="modal-body">
        <p>Having trouble signing in or did not receive your code? Before contacting us, check that you are using your Service Number and look in your email spam folder.</p>
        <dl class="dl">
            @if ($contact['email'])
                <dt>Support email</dt><dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd>
            @endif
            @if ($contact['support'] && $contact['support'] !== $contact['email'])
                <dt>Election support</dt><dd>@if (filter_var($contact['support'], FILTER_VALIDATE_EMAIL))<a href="mailto:{{ $contact['support'] }}">{{ $contact['support'] }}</a>@else{{ $contact['support'] }}@endif</dd>
            @endif
            @if ($contact['phone'])<dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></dd>@endif
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
