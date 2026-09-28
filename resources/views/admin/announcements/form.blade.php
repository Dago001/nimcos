@extends('layouts.admin')

@section('title', $announcement->exists ? 'Edit announcement' : 'Post announcement')

@section('content')
<div class="breadcrumb"><a href="{{ route('admin.announcements.index') }}">Announcements</a> / {{ $announcement->exists ? 'Edit' : 'New' }}</div>
<div class="page-head"><div><h1>{{ $announcement->exists ? 'Edit announcement' : 'Post announcement' }}</h1>
    <div class="sub">Plain text only. Never ask voters for their verification code, password or payment in an announcement.</div></div></div>

<form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" class="panel" data-submit-once>
    @csrf
    @if ($announcement->exists) @method('PUT') @endif
    <div class="panel-body form-grid two">
        <div class="field span-2">
            <label for="title">Title <span class="req">*</span></label>
            <input class="input" id="title" name="title" maxlength="150" required value="{{ old('title', $announcement->title) }}" placeholder="e.g. Voting closes at 17:00 today">
            @error('title')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field span-2">
            <label for="body">Message <span class="req">*</span></label>
            <textarea class="input" id="body" name="body" maxlength="1000" rows="5" required>{{ old('body', $announcement->body) }}</textarea>
            <div class="help">Up to 1,000 characters. In the scrolling ticker the message is shown on one line; keep it short.</div>
            @error('body')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <fieldset class="field">
            <legend>Show as <span class="req">*</span></legend>
            @foreach (\App\Enums\AnnouncementDisplay::cases() as $d)
                <label class="check"><input type="radio" name="display" value="{{ $d->value }}" @checked(old('display', $announcement->display?->value) === $d->value)> <span>{{ $d->label() }}</span></label>
            @endforeach
            <div class="help">A pop-up opens once for each visitor (and again if you edit it). The ticker scrolls across the top of the page.</div>
            @error('display')<div class="error-text">{{ $message }}</div>@enderror
        </fieldset>
        <fieldset class="field">
            <legend>Level <span class="req">*</span></legend>
            @foreach (\App\Enums\AnnouncementLevel::cases() as $l)
                <label class="check"><input type="radio" name="level" value="{{ $l->value }}" @checked(old('level', $announcement->level?->value) === $l->value)> <span>{{ $l->label() }}</span></label>
            @endforeach
            <div class="help">Important notices use an amber ticker; urgent ones use red and are listed first.</div>
            @error('level')<div class="error-text">{{ $message }}</div>@enderror
        </fieldset>

        <div class="field">
            <label for="starts_at">Show from (WAT)</label>
            <input class="input" type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', $announcement->starts_at ? display_time($announcement->starts_at, 'Y-m-d\TH:i') : '') }}">
            <div class="help">Leave empty to show it immediately.</div>
            @error('starts_at')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="ends_at">Show until (WAT)</label>
            <input class="input" type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', $announcement->ends_at ? display_time($announcement->ends_at, 'Y-m-d\TH:i') : '') }}">
            <div class="help">Leave empty to show it until you switch it off.</div>
            @error('ends_at')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label for="link_url">Link (optional)</label>
            <input class="input" id="link_url" name="link_url" maxlength="500" value="{{ old('link_url', $announcement->link_url) }}" placeholder="https://… or /results">
            @error('link_url')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="link_label">Link text</label>
            <input class="input" id="link_label" name="link_label" maxlength="60" value="{{ old('link_label', $announcement->link_label) }}" placeholder="e.g. View results">
            @error('link_label')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <label class="check span-2 mb-1"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->is_active))>
            <span><strong>Switched on</strong>: voters see it during the times above.</span></label>

        <div class="field">
            <label for="confirm_password">Your password (to confirm) <span class="req">*</span></label>
            <input class="input" id="confirm_password" name="confirm_password" type="password" autocomplete="current-password" required>
        </div>
        @if (auth()->user()->hasMfa())
            <div class="field">
                <label for="confirm_mfa">Your authenticator code <span class="req">*</span></label>
                <input class="input" id="confirm_mfa" name="confirm_mfa" inputmode="numeric" maxlength="6" required>
            </div>
        @endif
    </div>
    <div class="modal-foot">
        <a class="btn btn-secondary" href="{{ route('admin.announcements.index') }}">Back</a>
        <button class="btn btn-primary" type="submit">{{ $announcement->exists ? 'Save changes' : 'Post announcement' }}</button>
    </div>
</form>

@if ($announcement->exists)
    <div class="panel mt-3"><div class="panel-body btn-row spread">
        <div><h2 class="mb-0">Delete announcement</h2><p class="small muted mb-0">Removes it permanently. The audit log keeps a record that it existed.</p></div>
        <x-reauth-dialog id="delete-announcement" :action="route('admin.announcements.destroy', $announcement)" method="DELETE"
                         title="Delete this announcement?" button="Delete" variant="danger" trigger-class="btn btn-danger">
            <p class="modal-summary"><strong>{{ $announcement->title }}</strong></p>
        </x-reauth-dialog>
    </div></div>
@endif
@endsection
