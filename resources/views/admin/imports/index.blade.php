@extends('layouts.admin')

@section('title', 'Import history')

@section('content')
<div class="page-head"><div><h1>Import history</h1></div><a class="btn btn-primary" href="{{ route('admin.imports.create') }}">New import</a></div>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Uploaded</th><th>File</th><th>By</th><th>Status</th><th class="num">Total</th><th class="num">New</th><th class="num">Updated</th><th class="num">Rejected</th><th class="actions"></th></tr></thead>
        <tbody>
        @forelse ($imports as $import)
            <tr>
                <td class="nowrap small">{{ display_time($import->created_at) }}</td>
                <td>{{ $import->original_filename }}</td>
                <td class="small">{{ $import->uploader->name }}</td>
                <td><x-status-badge :status="$import->status" /></td>
                <td class="num">{{ number_format($import->total_rows) }}</td>
                <td class="num">{{ number_format($import->imported_count) }}</td>
                <td class="num">{{ number_format($import->updated_count) }}</td>
                <td class="num">{{ number_format($import->rejected_count) }}</td>
                <td class="actions">
                    <div class="actions-row">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.imports.show', $import) }}">View</a>
                        @if (auth()->user()->hasPermission('import_voters'))
                            <form method="POST" action="{{ route('admin.imports.destroy', $import) }}" class="inline-form" data-confirm="Are you sure you want to permanently delete this import record and remove all onboarded voters associated with it? This action cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="table-empty">No imports yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $imports->links() }}
@endsection
