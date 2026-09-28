@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')
<div class="page-head"><div><h1>Announcements</h1><div class="sub">Notices shown to voters on the home, sign-in and results pages, as a pop-up, a scrolling ticker, or both. Nothing is shown while a voter is filling in their ballot.</div></div>
    <a class="btn btn-primary" href="{{ route('admin.announcements.create') }}">Post announcement</a></div>

@if ($announcements->isEmpty())
    <div class="panel"><div class="panel-body">
        <h2>No announcements yet</h2>
        <p class="muted mb-0">Post a notice to tell voters about voting hours, changes to the programme, or where to get help.</p>
    </div></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Announcement</th><th>Shown as</th><th>Level</th><th>Showing</th><th>Status</th><th class="actions"></th></tr></thead>
            <tbody>
            @foreach ($announcements as $a)
                @php($state = $a->state())
                <tr>
                    <td>
                        <strong>{{ $a->title }}</strong>
                        <div class="small muted">{{ \Illuminate\Support\Str::limit($a->body, 110) }}</div>
                        <div class="small muted">Posted by {{ $a->author?->name ?? 'a former administrator' }}, {{ display_time($a->created_at, 'j M Y, H:i') }}</div>
                    </td>
                    <td class="small">{{ $a->display->label() }}</td>
                    <td><span class="badge {{ ['INFO' => 'badge-info', 'IMPORTANT' => 'badge-warning', 'URGENT' => 'badge-error'][$a->level->value] }}">{{ $a->level->label() }}</span></td>
                    <td class="small nowrap">
                        {{ $a->starts_at ? display_time($a->starts_at, 'j M, H:i') : 'Immediately' }}<br>
                        to {{ $a->ends_at ? display_time($a->ends_at, 'j M, H:i') : 'until switched off' }}
                    </td>
                    <td>
                        <span class="badge {{ ['LIVE' => 'badge-live', 'SCHEDULED' => 'badge-info', 'EXPIRED' => 'badge-muted', 'OFF' => 'badge-neutral'][$state] }}">{{ ['LIVE' => 'Live', 'SCHEDULED' => 'Scheduled', 'EXPIRED' => 'Ended', 'OFF' => 'Off'][$state] }}</span>
                    </td>
                    <td class="actions nowrap">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.announcements.edit', $a) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.announcements.toggle', $a) }}" class="inline-form" data-submit-once>
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">{{ $a->is_active ? 'Switch off' : 'Switch on' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $announcements->links() }}
@endif
@endsection
