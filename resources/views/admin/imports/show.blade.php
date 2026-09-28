@extends('layouts.admin')

@section('title', 'Import · '.$import->original_filename)

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.imports.index') }}">Import history</a> / {{ $import->original_filename }}</div>
<div class="page-head">
    <div><h1>{{ $import->status->value === 'PREVIEWED' ? 'Review import' : 'Import result' }}</h1>
        <div class="sub">{{ $import->original_filename }} · uploaded by {{ $import->uploader->name }} {{ display_time($import->created_at) }} · <x-status-badge :status="$import->status" /></div></div>
    @if ($errorTotal)
        <a class="btn btn-secondary" href="{{ route('admin.imports.errors', $import) }}">Download error report ({{ $errorTotal }})</a>
    @endif
</div>

@if ($import->status->value === 'PROCESSING')
    <div class="alert alert-info">The import is being applied. Refresh this page in a moment to see the final totals. (A queue worker must be running.)</div>
@elseif ($import->status->value === 'FAILED')
    <div class="alert alert-error"><strong>The import failed and was rolled back. No records were changed.</strong> {{ $import->failure_reason }}</div>
@elseif ($import->status->value === 'COMPLETED')
    <div class="alert alert-success">Import completed {{ display_time($import->completed_at) }}, confirmed by {{ $import->confirmer?->name }}.</div>
@endif

<div class="stats">
    <div class="stat"><div class="stat-label">Total records</div><div class="stat-value">{{ number_format($import->total_rows) }}</div></div>
    <div class="stat"><div class="stat-label">{{ $import->status->value === 'COMPLETED' ? 'Successfully imported' : 'Will be imported (new)' }}</div><div class="stat-value green">{{ number_format($import->imported_count) }}</div></div>
    <div class="stat"><div class="stat-label">{{ $import->status->value === 'COMPLETED' ? 'Updated records' : 'Will be updated' }}</div><div class="stat-value">{{ number_format($import->updated_count) }}</div><div class="stat-note">Unchanged: {{ number_format($import->unchanged_count) }}</div></div>
    <div class="stat"><div class="stat-label">Rejected records</div><div class="stat-value">{{ number_format($import->rejected_count) }}</div><div class="stat-note">Duplicates {{ number_format($import->duplicate_count) }} · Invalid {{ number_format($import->invalid_count) }}</div></div>
</div>

@if ($import->status->value === 'PREVIEWED')
    <div class="panel mb-3"><div class="panel-body">
        <h2>Confirm import</h2>
        <p class="muted">Rejected rows are skipped. All valid rows are applied together in one transaction: if anything fails, nothing is changed.</p>
        <form method="POST" action="{{ route('admin.imports.confirm', $import) }}" class="btn-row" data-submit-once>
            @csrf
            <label class="check"><input type="checkbox" name="acknowledge" value="1" required> I have reviewed the preview and confirm this is the approved NIMCOS register.</label>
            @error('acknowledge')<div class="error-text">{{ $message }}</div>@enderror
            <button class="btn btn-primary" type="submit" @disabled($import->valid_rows === 0)>Confirm and import {{ number_format($import->valid_rows) }} row(s)</button>
        </form>
        <form method="POST" action="{{ route('admin.imports.cancel', $import) }}" class="mt-2">
            @csrf
            <button class="btn btn-secondary btn-sm" type="submit">Cancel import</button>
        </form>
    </div></div>

    @if ($sample)
        <h2>Preview (first {{ count($sample) }} valid rows)</h2>
        <div class="table-wrap mb-3">
            <table class="table">
                <thead><tr><th>Row</th><th>Service No.</th><th>Name</th><th>Rank</th><th>Command</th><th>Formation</th><th>Phone</th><th>Membership</th></tr></thead>
                <tbody>
                @foreach ($sample as $row => $data)
                    <tr><td>{{ $row }}</td><td class="mono">{{ $data['service_number'] }}</td><td>{{ $data['surname'] }}, {{ $data['first_name'] }} {{ $data['other_names'] }}</td>
                        <td class="small">{{ $data['rank'] }}</td><td class="small">{{ $data['command'] }}</td><td class="small">{{ $data['formation'] }}</td>
                        <td class="small">{{ \App\Support\PhoneNumber::display($data['phone']) }}</td><td class="small">{{ $data['membership_status'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@if ($errorTotal)
    <h2>Rejected rows @if ($errorTotal > 200)<span class="muted small">(first 200 of {{ $errorTotal }}; download the full report)</span>@endif</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Row</th><th>Type</th><th>Service No.</th><th>Problems</th></tr></thead>
            <tbody>
            @foreach ($rowErrors as $error)
                <tr><td>{{ $error->row_number }}</td><td><span class="badge {{ $error->error_type === 'DUPLICATE' ? 'badge-warning' : 'badge-error' }}">{{ $error->error_type }}</span></td>
                    <td class="mono">{{ $error->service_number }}</td><td class="small">{{ implode(' ', $error->messages) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
