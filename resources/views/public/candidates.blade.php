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
    <meta name="description" content="Official screened and certified candidates for the Nigeria Immigration Multi-Purpose Cooperative Society (NIMCOS) Elections.">
    <title>Screened Candidates · {{ config('nimcos.name') }}</title>
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
            <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS Crest" width="48" height="48">
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
    {{-- Executive Hero Header --}}
    <section class="candidates-hero" aria-labelledby="candidates-hero-title">
        <div class="candidates-hero-inner">
            <div class="candidates-hero-seal-wrap">
                <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS Official Seal" class="candidates-hero-seal" width="86" height="86">
            </div>

            <span class="candidates-hero-masthead">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Nigeria Immigration Service · NIMCOS Electoral Directory
            </span>

            <h1 id="candidates-hero-title">Official Candidates Directory</h1>
            <p class="candidates-hero-sub">
                Official register of screened, vetted, and cleared contestants eligible for election into the Executive Committee of the Nigeria Immigration Multi-Purpose Cooperative Society.
            </p>

            {{-- Metrics Ribbon --}}
            <div class="candidates-metrics-ribbon" role="region" aria-label="Election directory summary">
                <div class="candidates-metric-item">
                    <div class="candidates-metric-label">Contested Offices</div>
                    <div class="candidates-metric-val">{{ $totalPositions }} {{ \Illuminate\Support\Str::plural('Position', $totalPositions) }}</div>
                </div>
                <div class="candidates-metric-item">
                    <div class="candidates-metric-label">Screened Contestants</div>
                    <div class="candidates-metric-val">{{ $totalClearedCandidates }} {{ \Illuminate\Support\Str::plural('Candidate', $totalClearedCandidates) }}</div>
                </div>
                <div class="candidates-metric-item">
                    <div class="candidates-metric-label">Screening Status</div>
                    <div class="candidates-metric-val">
                        <svg viewBox="0 0 20 20" fill="#4ade80" width="18" height="18" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Certified &amp; Cleared
                    </div>
                </div>
                <div class="candidates-metric-item">
                    <div class="candidates-metric-label">Voting Channel</div>
                    <div class="candidates-metric-val">Electronic Ballot</div>
                </div>
            </div>

            @if ($elections->isNotEmpty())
                <div class="candidates-election-pills" role="navigation" aria-label="Select election directory">
                    @foreach ($elections as $elec)
                        <a href="{{ route('public.candidates', ['election' => $elec->code]) }}"
                           class="candidates-election-pill {{ $selectedElection && $selectedElection->id === $elec->id ? 'is-active' : '' }}">
                            {{ $elec->name }}
                        </a>
                    @endforeach
                </div>
            @endif

        </div>
    </section>

    {{-- Main Directory Body --}}
    <div class="candidates-main-content">
        @if ($totalClearedCandidates > 0 && $selectedElection)
            {{-- Interactive Search and Filter Toolbar --}}
            <div class="candidates-toolbar" role="search" aria-label="Filter candidates">
                <div class="candidates-search-wrap">
                    <svg class="candidates-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="candidates-search-input" placeholder="Search candidate by name, rank, or command..." data-candidate-search aria-label="Search candidates">
                </div>

                <div class="candidates-filter-pills" role="tablist" aria-label="Position filter">
                    <button type="button" class="candidate-filter-btn active" data-filter-pos="all">All Offices</button>
                    @foreach ($selectedElection->electionPositions as $ep)
                        @if ($ep->activeCandidates->isNotEmpty())
                            <button type="button" class="candidate-filter-btn" data-filter-pos="{{ $ep->id }}">
                                {{ $ep->position->name }}
                            </button>
                        @endif
                    @endforeach
                    <span class="candidates-count-tag"><strong data-candidate-count>{{ $totalClearedCandidates }}</strong> candidates shown</span>
                </div>
            </div>

            {{-- Fallback Card for when Search Finds Nothing --}}
            <div class="position-showcase-panel hidden" data-no-search-results>
                <div class="position-showcase-body center">
                    <p class="lede mb-1">No cleared candidates matched your search criteria.</p>
                    <p class="muted small mb-0">Try typing a different name, command, or reset the office filter above.</p>
                </div>
            </div>

            {{-- Positions and Candidate Profiles --}}
            @foreach ($selectedElection->electionPositions as $positionIndex => $ep)
                @php($activeCandidates = $ep->activeCandidates)
                @if ($activeCandidates->isNotEmpty())
                    <section class="position-showcase-panel" id="pos-{{ $ep->id }}" data-position-section data-position-id="{{ $ep->id }}" aria-labelledby="pos-title-{{ $ep->id }}">
                        <div class="position-showcase-header">
                            <div class="position-showcase-title-left">
                                <span class="position-index-badge" aria-hidden="true">{{ $positionIndex + 1 }}</span>
                                <div>
                                    <h2 id="pos-title-{{ $ep->id }}">{{ $ep->position->name }}</h2>
                                    <div class="position-showcase-sub">
                                        {{ $ep->seats }} {{ \Illuminate\Support\Str::plural('Seat', $ep->seats) }} to be filled
                                        @unless ($ep->is_required) · <em>Optional vote</em> @endunless
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-success">{{ $activeCandidates->count() }} Cleared Contestants</span>
                            </div>
                        </div>

                        <div class="position-showcase-body">
                            <div class="candidates-showcase-grid">
                                @foreach ($activeCandidates as $candidate)
                                    <article class="candidate-profile-card" id="candidate-{{ $candidate->id }}"
                                             data-candidate-card
                                             data-candidate-search-text="{{ strtolower($candidate->displayName().' '.$candidate->rankLabel().' '.$candidate->commandLabel().' '.$ep->position->name) }}">
                                        
                                        <div class="candidate-profile-top">
                                            <div class="candidate-badges-strip">
                                                @if ($candidate->candidate_number)
                                                    <span class="candidate-number-tag">No. {{ $candidate->candidate_number }}</span>
                                                @else
                                                    <span></span>
                                                @endif
                                                <span class="candidate-clearance-badge">
                                                    <svg viewBox="0 0 16 16" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M13.485 1.431a1.473 1.473 0 0 1 2.104 2.062l-7.84 9.801a1.473 1.473 0 0 1-2.12.175l-4.2-3.733a1.473 1.473 0 0 1 1.958-2.202l3.047 2.709 6.84-8.552a.207.207 0 0 0 .211-.26z"/></svg>
                                                    Screened Contestant
                                                </span>
                                            </div>

                                            <div class="candidate-photo-frame">
                                                <x-candidate-avatar :candidate="$candidate" size="large" />
                                            </div>

                                            <h3 class="candidate-profile-name">{{ $candidate->displayName() }}</h3>
                                            <p class="candidate-contested-tag">Contesting for {{ $ep->position->name }}</p>
                                        </div>

                                        <div class="candidate-profile-body">
                                            <div class="candidate-meta-strip">
                                                <div class="candidate-meta-cell">
                                                    <span class="candidate-meta-label">NIS Rank</span>
                                                    <span class="candidate-meta-val" title="{{ $candidate->rankLabel() ?? 'Not specified' }}">{{ $candidate->rankLabel() ?? 'Not specified' }}</span>
                                                </div>
                                                <div class="candidate-meta-cell">
                                                    <span class="candidate-meta-label">Command</span>
                                                    <span class="candidate-meta-val" title="{{ $candidate->commandLabel() ?? 'Not specified' }}">{{ $candidate->commandLabel() ?? 'Not specified' }}</span>
                                                </div>
                                            </div>

                                            @if ($candidate->biography)
                                                <details class="candidate-bio-accordion">
                                                    <summary class="candidate-bio-summary">
                                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                                        <span>Read Manifesto / Statement</span>
                                                    </summary>
                                                    <div class="candidate-bio-content">
                                                        {!! nl2br(e($candidate->biography)) !!}
                                                    </div>
                                                </details>
                                            @else
                                                <div class="candidate-bio-placeholder">
                                                    <span>Official profile on file with Electoral Committee</span>
                                                </div>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
            @endforeach

        @else
            {{-- Executive Electoral Screening Notice (Shown when screening is in progress or no candidates published yet) --}}
            <div class="electoral-notice-panel">
                <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS Official Seal" class="electoral-notice-seal" width="88" height="88">
                
                <span class="electoral-notice-badge">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Official Notice · Electoral Screening in Progress
                </span>

                <h2>Candidate Screening &amp; Vetting Underway</h2>
                <p class="electoral-notice-desc">
                    The NIMCOS Electoral Screening Committee is actively reviewing nomination dossiers, service qualifications, and statutory eligibility for all aspirants. Official certified candidates will be rostered here immediately following sign-off.
                </p>

                {{-- 3-Step Process Timeline --}}
                <div class="electoral-timeline" role="region" aria-label="Screening progress">
                    <div class="electoral-step done">
                        <span class="electoral-step-dot" aria-hidden="true">✓</span>
                        <div>
                            <div class="electoral-step-title">Phase 1: Nominations</div>
                            <div class="electoral-step-sub">Aspirant submissions closed</div>
                        </div>
                    </div>
                    <div class="electoral-step active">
                        <span class="electoral-step-dot" aria-hidden="true">●</span>
                        <div>
                            <div class="electoral-step-title">Phase 2: Screening</div>
                            <div class="electoral-step-sub">Vetting &amp; clearance in progress</div>
                        </div>
                    </div>
                    <div class="electoral-step">
                        <span class="electoral-step-dot" aria-hidden="true">3</span>
                        <div>
                            <div class="electoral-step-title">Phase 3: Directory</div>
                            <div class="electoral-step-sub">Publishing verified profiles</div>
                        </div>
                    </div>
                </div>

                <div class="btn-row center">
                    <a href="{{ route('home') }}" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        Return to Homepage
                    </a>
                    <a href="{{ route('voter.entry') }}" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Voter Login Portal
                    </a>
                </div>
            </div>
        @endif

        {{-- Call to Action Banner --}}
        <section class="candidates-cta-section" aria-labelledby="cta-heading">
            <div class="candidates-cta-flex">
                <div>
                    <h2 id="cta-heading">Ready to cast your ballot?</h2>
                    <p>Make sure you have your Service Number and registered email address on hand to receive your verification code.</p>
                </div>
                <div>
                    <a class="btn btn-primary btn-lg" href="{{ route('voter.entry') }}">
                        Proceed to Vote
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </div>
            </div>
        </section>
    </div>
</main>

<footer class="site-footer" id="contact">
    <div class="site-footer-main">
        <div class="site-footer-org">
            <a class="site-brand" href="{{ route('home') }}">
                <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="NIMCOS Seal" width="44" height="44">
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
