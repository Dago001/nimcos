@extends('layouts.admin')

@section('title', 'Import voters')

@section('content')
<div class="page-head"><div><h1>Import voter register</h1><div class="sub">Upload the approved register as CSV or Excel. Nothing changes until you review the preview and confirm.</div></div>
    <a class="btn btn-secondary" href="{{ route('admin.imports.template') }}">Download template (CSV)</a></div>

<div class="grid-2">
    <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="panel" data-submit-once>
        @csrf
        <div class="panel-body">
            <div class="field">
                <label for="file">Register file <span class="req">*</span></label>
                <input class="input" type="file" id="file" name="file" accept=".csv,.xlsx,.xls,text/csv" required>
                <div class="help">CSV (.csv) or Excel (.xlsx) · maximum {{ (int) (config('nimcos.uploads.import_max_kb') / 1024) }} MB · first row must contain column headings.</div>
                @error('file')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <label class="check mb-2"><input type="checkbox" name="update_existing" value="1" checked>
                <span>Update existing voters whose Service Number is already on the register (name, rank, command, formation, contact details, membership).</span></label>
            <button class="btn btn-primary" type="submit" data-busy-text="Validating file…">Upload and validate</button>
        </div>
    </form>

    <div class="panel"><div class="panel-body">
        <h3>Expected columns</h3>
        <ul class="small">
            @foreach ($columns as $column)
                <li><span class="mono">{{ $column }}</span>@if (in_array($column, $required, true)) <span class="req">(required)</span>@endif</li>
            @endforeach
        </ul>
        <h3 class="mt-2">Checks performed</h3>
        <ul class="small muted mb-0">
            <li>File format, required columns and required values</li>
            <li>Service Number format (4 or 5 digits); duplicate Service Numbers and duplicate records</li>
            <li>Email required and valid; one email address per voter (verification codes are emailed)</li>
            <li>Nigerian mobile number format (optional column) and membership status values</li>
        </ul>
    </div></div>
</div>
@endsection
