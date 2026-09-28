@php($u = auth()->user())
<div class="breadcrumb"><a href="{{ route('admin.elections.index') }}">Elections</a> / {{ $election->code }}</div>
<div class="page-head">
    <div>
        <h1>{{ $election->name }}</h1>
        <div class="sub"><x-status-badge :status="$election->status" /> &nbsp; {{ display_time($election->starts_at, 'j M Y, H:i') }} – {{ display_time($election->ends_at, 'H:i') }} WAT</div>
    </div>
    @isset($actions){{ $actions }}@endisset
</div>
<nav class="tabs" aria-label="Election sections">
    @if ($u->hasPermission('manage_elections') || $u->hasPermission('view_live_statistics') || $u->hasPermission('view_results'))
        <a href="{{ route('admin.elections.show', $election) }}" class="{{ request()->routeIs('admin.elections.show') ? 'active' : '' }}">Overview</a>
    @endif
    @if ($u->hasPermission('manage_positions'))
        <a href="{{ route('admin.election-positions.index', $election) }}" class="{{ request()->routeIs('admin.election-positions.*') ? 'active' : '' }}">Positions</a>
    @endif
    @if ($u->hasPermission('manage_candidates'))
        <a href="{{ route('admin.candidates.index', ['election' => $election->id]) }}">Candidates</a>
    @endif
    @if ($u->hasPermission('manage_elections'))
        <a href="{{ route('admin.eligibility.index', $election) }}" class="{{ request()->routeIs('admin.eligibility.*') ? 'active' : '' }}">Eligibility</a>
    @endif
    @if ($u->hasPermission('view_live_statistics'))
        <a href="{{ route('admin.monitor.show', $election) }}" class="{{ request()->routeIs('admin.monitor.*') ? 'active' : '' }}">Live monitor</a>
    @endif
    @if ($u->hasPermission('view_results'))
        <a href="{{ route('admin.results.show', $election) }}" class="{{ request()->routeIs('admin.results.*') ? 'active' : '' }}">Results</a>
    @endif
</nav>
