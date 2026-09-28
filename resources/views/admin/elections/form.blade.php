@extends('layouts.admin')

@section('title', $election->exists ? 'Edit election' : 'Create election')

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.elections.index') }}">Elections</a> / {{ $election->exists ? $election->code : 'New' }}</div>
<div class="page-head"><div><h1>{{ $election->exists ? 'Edit election' : 'Create election' }}</h1>
    <div class="sub">Times are in West Africa Time (Africa/Lagos). Elections start as drafts; nothing is visible to voters until scheduled.</div></div></div>

<form method="POST" action="{{ $election->exists ? route('admin.elections.update', $election) : route('admin.elections.store') }}" class="panel" data-submit-once>
    @csrf
    @if ($election->exists) @method('PUT') @endif
    <div class="panel-body">
        <div class="form-grid two">
            <div class="field span-2">
                <label for="name">Election name <span class="req">*</span></label>
                <input class="input" id="name" name="name" value="{{ old('name', $election->name) }}" placeholder="NIMCOS 2026 ELECTIVE CONGRESS" required maxlength="200">
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="code">Election code <span class="req">*</span></label>
                <input class="input mono" id="code" name="code" value="{{ old('code', $election->code) }}" placeholder="NIMCOS-2026-EC" required maxlength="40">
                <div class="help">Short unique code used in links and reports. Capital letters, numbers and hyphens.</div>
                @error('code')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="election_type">Election type <span class="req">*</span></label>
                <select class="input" id="election_type" name="election_type" required>
                    @foreach (\App\Enums\ElectionType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('election_type', $election->election_type?->value ?? 'GENERAL') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="starts_at">Voting opens (WAT) <span class="req">*</span></label>
                <input class="input" type="datetime-local" id="starts_at" name="starts_at" required
                       value="{{ old('starts_at', $election->starts_at ? display_time($election->starts_at, 'Y-m-d\TH:i') : '') }}">
                @error('starts_at')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="ends_at">Voting closes (WAT) <span class="req">*</span></label>
                <input class="input" type="datetime-local" id="ends_at" name="ends_at" required
                       value="{{ old('ends_at', $election->ends_at ? display_time($election->ends_at, 'Y-m-d\TH:i') : '') }}">
                @error('ends_at')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field span-2">
                <label for="description">Description</label>
                <textarea class="input" id="description" name="description" maxlength="5000">{{ old('description', $election->description) }}</textarea>
            </div>
            <div class="field span-2">
                <label class="check"><input type="checkbox" name="auto_open" value="1" @checked(old('auto_open', $election->auto_open ?? true))>
                    <span><strong>Open automatically</strong> at the start time once scheduled.</span></label>
                <label class="check mt-1"><input type="checkbox" name="auto_close" value="1" @checked(old('auto_close', $election->auto_close ?? true))>
                    <span><strong>Close automatically</strong> at the end time. (Ballots are refused after the end time regardless.)</span></label>
                <label class="check mt-1"><input type="checkbox" name="interim_results_enabled" value="1" @checked(old('interim_results_enabled', $election->interim_results_enabled))>
                    <span><strong>Show live vote counts</strong> for every contestant on the dashboard while voting is open (officials with the "View results" permission only). Untick to keep results sealed until the election closes.</span></label>
            </div>
        </div>
    </div>
    <div class="modal-foot">
        <a class="btn btn-secondary" href="{{ $election->exists ? route('admin.elections.show', $election) : route('admin.elections.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $election->exists ? 'Save changes' : 'Create election' }}</button>
    </div>
</form>
@endsection
