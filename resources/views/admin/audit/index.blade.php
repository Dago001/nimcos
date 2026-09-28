@extends('layouts.admin')

@section('title', 'Audit logs')

@section('content')
<div class="page-head">
    <div><h1>Audit logs</h1><div class="sub">Append-only and hash-chained. Entries cannot be edited or deleted. OTPs, passwords and ballot choices are never recorded.</div></div>
    <form method="POST" action="{{ route('admin.audit.verify') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Verify integrity of the log</button>
    </form>
</div>

<form class="filters" method="GET">
    <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="field"><label for="actor">User / actor</label><input class="input" id="actor" name="actor" value="{{ $filters['actor'] ?? '' }}"></div>
    <div class="field"><label for="action">Action</label><select class="input" id="action" name="action"><option value="">All</option>@foreach ($actions as $a)<option @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
    <div class="field"><label for="entity">Entity</label><select class="input" id="entity" name="entity"><option value="">All</option>@foreach ($entities as $en)<option @selected(($filters['entity'] ?? '') === $en)>{{ $en }}</option>@endforeach</select></div>
    <div class="field"><label for="result">Result</label><select class="input" id="result" name="result"><option value="">All</option>@foreach (\App\Enums\AuditResult::cases() as $r)<option value="{{ $r->value }}" @selected(($filters['result'] ?? '') === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
    <div class="field"><label for="ip">IP address</label><input class="input" id="ip" name="ip" value="{{ $filters['ip'] ?? '' }}"></div>
    <button class="btn btn-secondary" type="submit">Filter</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>#</th><th>Time (WAT)</th><th>Actor</th><th>Action</th><th>Entity</th><th>Result</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="small muted">{{ $log->id }}</td>
                <td class="nowrap small">{{ display_time($log->created_at, 'j M Y H:i:s') }}</td>
                <td class="small"><span class="badge badge-neutral">{{ $log->actor_type }}</span><br>{{ $log->actor_label }}</td>
                <td class="small mono">{{ $log->action }}</td>
                <td class="small">{{ $log->entity_type }}<br><span class="muted mono">{{ \Illuminate\Support\Str::limit((string) $log->entity_id, 13) }}</span></td>
                <td><x-status-badge :status="$log->result" /></td>
                <td class="small mono">{{ $log->ip }}</td>
                <td>@if ($log->metadata)<div class="meta-json">{{ json_encode($log->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="table-empty">No audit entries match.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection
