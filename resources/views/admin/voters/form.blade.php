@extends('layouts.admin')

@section('title', $voter->exists ? 'Edit voter' : 'Add voter')

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.voters.index') }}">Voter register</a> / {{ $voter->exists ? $voter->service_number : 'New' }}</div>
<div class="page-head"><div><h1>{{ $voter->exists ? 'Edit voter' : 'Add voter' }}</h1>
    @if ($voter->exists)<div class="sub">Changing identity or contact details resets verification.</div>@endif</div></div>

<form method="POST" action="{{ $voter->exists ? route('admin.voters.update', $voter) : route('admin.voters.store') }}" class="panel" data-submit-once>
    @csrf
    @if ($voter->exists) @method('PUT') @endif
    <div class="panel-body form-grid two">
        @foreach ([
            ['service_number', 'Service Number', true, 'mono'],
            ['surname', 'Surname', true, ''],
            ['first_name', 'First name', true, ''],
            ['other_names', 'Other names', false, ''],
            ['command', 'Command', false, ''],
            ['formation', 'Formation', false, ''],
        ] as [$field, $label, $required, $class])
            <div class="field">
                <label for="{{ $field }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
                <input class="input {{ $class }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $voter->{$field}) }}" @required($required)
                       @error($field) aria-invalid="true" @enderror>
                @error($field)<div class="error-text">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="field">
            <label for="rank">Rank</label>
            <select class="input" id="rank" name="rank" @error('rank') aria-invalid="true" @enderror>
                <option value="">Select rank</option>
                @foreach (\App\Enums\NisRank::cases() as $r)
                    <option value="{{ $r->value }}" @selected(old('rank', $voter->rank) === $r->value)>{{ $r->label() }}</option>
                @endforeach
            </select>
            @error('rank')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="phone">Mobile phone</label>
            <input class="input" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $voter->phone ? \App\Support\PhoneNumber::display($voter->phone) : '') }}" placeholder="0803 123 4567">
            <div class="help">Optional contact number.</div>
            @error('phone')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="email">Email <span class="req">*</span></label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email', $voter->email) }}" required>
            <div class="help">Verification codes are sent to this address. Must be unique to this voter.</div>
            @error('email')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="membership_status">NIMCOS membership status <span class="req">*</span></label>
            <select class="input" id="membership_status" name="membership_status">
                @foreach (\App\Enums\MembershipStatus::cases() as $m)
                    <option value="{{ $m->value }}" @selected(old('membership_status', $voter->membership_status?->value ?? 'ACTIVE') === $m->value)>{{ $m->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="modal-foot">
        <a class="btn btn-secondary" href="{{ $voter->exists ? route('admin.voters.show', $voter) : route('admin.voters.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $voter->exists ? 'Save' : 'Add voter' }}</button>
    </div>
</form>
@endsection
