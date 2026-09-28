@extends('layouts.admin')

@section('title', $candidate->exists ? 'Edit candidate' : 'Add candidate')

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.candidates.index', ['election' => $election->id]) }}">Candidates</a> / {{ $election->code }}</div>
<div class="page-head"><div><h1>{{ $candidate->exists ? 'Edit candidate' : 'Add candidate' }}</h1><div class="sub">{{ $election->name }}</div></div></div>

@unless ($election->status->isStructureEditable())
    <div class="alert alert-warning">This election is {{ $election->status->label() }}. Candidates can only be changed in draft.</div>
@endunless

<form method="POST" enctype="multipart/form-data" class="panel" data-submit-once
      action="{{ $candidate->exists ? route('admin.candidates.update', $candidate) : route('admin.candidates.store', $election) }}">
    @csrf
    @if ($candidate->exists) @method('PUT') @endif
    <div class="panel-body">
        <div class="form-grid two">
            <div class="field">
                <label for="election_position_id">Position <span class="req">*</span></label>
                <select class="input" id="election_position_id" name="election_position_id" required>
                    @foreach ($positions as $ep)
                        <option value="{{ $ep->id }}" @selected(old('election_position_id', $candidate->election_position_id) === $ep->id)>{{ $ep->position->name }}</option>
                    @endforeach
                </select>
                @error('election_position_id')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="candidate_number">Candidate number</label>
                <input class="input" type="number" id="candidate_number" name="candidate_number" min="1" max="9999" value="{{ old('candidate_number', $candidate->candidate_number) }}">
                <div class="help">Leave blank to assign the next number automatically.</div>
                @error('candidate_number')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="surname">Surname <span class="req">*</span></label>
                <input class="input" id="surname" name="surname" value="{{ old('surname', $candidate->surname) }}" required maxlength="100">
                @error('surname')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="first_name">First name <span class="req">*</span></label>
                <input class="input" id="first_name" name="first_name" value="{{ old('first_name', $candidate->first_name) }}" required maxlength="100">
                @error('first_name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="other_names">Other names</label>
                <input class="input" id="other_names" name="other_names" value="{{ old('other_names', $candidate->other_names) }}" maxlength="150">
                @error('other_names')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="service_number">Service Number</label>
                <input class="input mono" id="service_number" name="service_number" value="{{ old('service_number', $candidate->service_number) }}" maxlength="20">
                <div class="help">Only if the NIMCOS election rules permit it to be recorded. Never shown to voters.</div>
                @error('service_number')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="rank">Rank</label>
                <input class="input" id="rank" name="rank" value="{{ old('rank', $candidate->rank) }}" maxlength="80">
            </div>
            <div class="field">
                <label for="command">Command</label>
                <input class="input" id="command" name="command" value="{{ old('command', $candidate->command) }}" maxlength="120">
            </div>
            <div class="field span-2">
                <label for="biography">Profile / biography</label>
                <textarea class="input" id="biography" name="biography" maxlength="3000">{{ old('biography', $candidate->biography) }}</textarea>
            </div>
            <div class="field">
                <label for="display_order">Display order on ballot</label>
                <input class="input" type="number" id="display_order" name="display_order" min="0" max="10000" value="{{ old('display_order', $candidate->display_order) }}">
            </div>
            <div class="field">
                <label for="photo">Photograph</label>
                @if ($candidate->exists && $candidate->photo_path)
                    <div class="mb-1"><x-candidate-avatar :candidate="$candidate" size="small" /></div>
                @endif
                <input class="input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="help">JPEG, PNG or WebP · at least 200×200 px · max {{ (int) (config('nimcos.uploads.photo_max_kb') / 1024) }} MB. The image is re-processed and cropped square.</div>
                @error('photo')<div class="error-text">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="modal-foot">
        <a class="btn btn-secondary" href="{{ route('admin.candidates.index', ['election' => $election->id]) }}">Cancel</a>
        <button class="btn btn-primary" type="submit" @disabled(! $election->status->isStructureEditable())>{{ $candidate->exists ? 'Save candidate' : 'Add candidate' }}</button>
    </div>
</form>
@endsection
