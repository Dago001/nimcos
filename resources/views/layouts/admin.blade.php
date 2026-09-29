@php
    /** @var \App\Models\User $admin */
    $admin = auth('web')->user();
    $can = fn (string ...$p) => collect($p)->contains(fn ($x) => $admin->hasPermission($x));
    $openAlerts = $can('view_audit_logs') ? \App\Models\SecurityAlert::query()->where('status', 'OPEN')->count() : 0;
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'active' : '';
    // A group starts open only when the current page is inside it, so the sidebar
    // reflects where you are without hiding every other section by default.
    $inGroup = fn (string ...$patterns) => request()->routeIs(...$patterns);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Administration') · {{ config('nimcos.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/app.css') }}">
    <script src="{{ asset_v('assets/app.js') }}" defer></script>
</head>
<body class="admin-body">
<a class="skip-link" href="#main">Skip to main content</a>
@if (config('nimcos.show_demo_banner'))
    <div class="demo-banner" role="note">DEMONSTRATION ENVIRONMENT: fictitious test data only.</div>
@endif
<div class="admin-shell">
    <nav class="sidebar" aria-label="Administration">
        <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
            <img src="{{ asset('images/nimcos-seal-sq.jpg') }}" alt="" width="40" height="40">
            <span><strong>NIMCOS E-VOTING</strong><span>Election Administration</span></span>
        </a>

        <div class="nav-group">
            <a class="nav-link {{ $is('admin.dashboard') }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
        </div>

        @if ($can('manage_elections', 'view_live_statistics', 'view_results', 'manage_positions', 'manage_candidates'))
            <details class="nav-group nav-accordion" @if ($inGroup('admin.elections.*', 'admin.eligibility.*', 'admin.election-positions.*', 'admin.monitor.*', 'admin.results.*', 'admin.positions.*', 'admin.candidates.*')) open @endif>
                <summary class="nav-group-title">Elections</summary>
                <div class="nav-accordion-body">
                    @if ($can('manage_elections', 'view_live_statistics', 'view_results'))
                        <a class="nav-link {{ $is('admin.elections.*', 'admin.eligibility.*', 'admin.election-positions.*', 'admin.monitor.*', 'admin.results.*') }}" href="{{ route('admin.elections.index') }}">Elections</a>
                    @endif
                    @if ($can('manage_positions'))
                        <a class="nav-link {{ $is('admin.positions.*') }}" href="{{ route('admin.positions.index') }}">Positions</a>
                    @endif
                    @if ($can('manage_candidates'))
                        <a class="nav-link {{ $is('admin.candidates.*') }}" href="{{ route('admin.candidates.index') }}">Candidates</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($can('manage_voters', 'import_voters'))
            <details class="nav-group nav-accordion" @if ($inGroup('admin.voters.*', 'admin.imports.*')) open @endif>
                <summary class="nav-group-title">Voter register</summary>
                <div class="nav-accordion-body">
                    @if ($can('manage_voters'))
                        <a class="nav-link {{ $is('admin.voters.index', 'admin.voters.show', 'admin.voters.edit', 'admin.voters.create') }}" href="{{ route('admin.voters.index') }}">Voter list</a>
                        <a class="nav-link {{ $is('admin.voters.suspended') }}" href="{{ route('admin.voters.suspended') }}">Suspended voters</a>
                    @endif
                    @if ($can('import_voters'))
                        <a class="nav-link {{ $is('admin.imports.index', 'admin.imports.show', 'admin.imports.create') }}" href="{{ route('admin.imports.index') }}">Import history</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($can('generate_reports', 'view_audit_logs'))
            <details class="nav-group nav-accordion" @if ($inGroup('admin.reports.*', 'admin.audit.*', 'admin.alerts.*')) open @endif>
                <summary class="nav-group-title">Oversight</summary>
                <div class="nav-accordion-body">
                    @if ($can('generate_reports'))
                        <a class="nav-link {{ $is('admin.reports.*') }}" href="{{ route('admin.reports.index') }}">Reports</a>
                    @endif
                    @if ($can('view_audit_logs'))
                        <a class="nav-link {{ $is('admin.audit.*') }}" href="{{ route('admin.audit.index') }}">Audit logs</a>
                        <a class="nav-link {{ $is('admin.alerts.*') }}" href="{{ route('admin.alerts.index') }}">
                            Security alerts
                            @if ($openAlerts > 0)<span class="nav-count" aria-label="{{ $openAlerts }} open">{{ $openAlerts }}</span>@endif
                        </a>
                    @endif
                </div>
            </details>
        @endif

        @if ($can('manage_admins', 'manage_system_settings', 'manage_announcements'))
            <details class="nav-group nav-accordion" @if ($inGroup('admin.announcements.*', 'admin.users.*', 'admin.roles.*', 'admin.settings.*')) open @endif>
                <summary class="nav-group-title">Administration</summary>
                <div class="nav-accordion-body">
                    @if ($can('manage_announcements'))
                        <a class="nav-link {{ $is('admin.announcements.*') }}" href="{{ route('admin.announcements.index') }}">Announcements</a>
                    @endif
                    @if ($can('manage_admins'))
                        <a class="nav-link {{ $is('admin.users.*') }}" href="{{ route('admin.users.index') }}">Users</a>
                        <a class="nav-link {{ $is('admin.roles.*') }}" href="{{ route('admin.roles.index') }}">Roles &amp; permissions</a>
                    @endif
                    @if ($can('manage_system_settings'))
                        <a class="nav-link {{ $is('admin.settings.*') }}" href="{{ route('admin.settings.edit') }}">System settings</a>
                    @endif
                </div>
            </details>
        @endif

        <div class="sidebar-foot">
            Server time {{ display_time(now(), 'H:i') }} WAT<br>
            {{ config('nimcos.short_org') }}
        </div>
    </nav>

    <div class="admin-main">
        <header class="topbar">
            <div class="inner">
                <button type="button" class="btn btn-secondary btn-sm menu-toggle" data-menu-toggle aria-expanded="false" aria-label="Open navigation menu">☰ Menu</button>
                <div class="topbar-title">@yield('title', 'Administration')</div>
                <div class="topbar-actions">
                    @if ($openAlerts > 0)
                        <a class="badge badge-error" href="{{ route('admin.alerts.index') }}">{{ $openAlerts }} alert{{ $openAlerts === 1 ? '' : 's' }}</a>
                    @endif
                    <details class="user-menu">
                        <summary>
                            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
                            <span class="user-name-label">{{ $admin->name }}</span>
                        </summary>
                        <div class="menu">
                            <div class="menu-meta">{{ $admin->email }}<br>{{ $admin->roleLabels() }}</div>
                            <a href="{{ route('admin.profile.mfa') }}">Security &amp; MFA</a>
                            <a href="{{ route('admin.profile.password') }}">Change password</a>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit">Sign out</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </header>
        <main id="main" class="content" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
        @include('partials.legal-footer')
    </div>
</div>
</body>
</html>
