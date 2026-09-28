@extends('layouts.admin')

@section('title', 'Positions · '.$election->name)

@section('content')
@include('admin.elections.partials.tabs')

@unless ($editable)
    <div class="alert alert-info">The ballot is locked because the election is {{ $election->status->label() }}. Positions can only be changed in draft.</div>
@endunless

<div class="grid-2">
    <div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Order</th><th>Position</th><th class="num">Seats</th><th>Required</th><th class="num">Candidates</th>@if ($editable)<th class="actions"></th>@endif</tr></thead>
                <tbody>
                @forelse ($attached as $ep)
                    <tr>
                        @if ($editable)
                            <td colspan="4">
                                <form method="POST" action="{{ route('admin.election-positions.update', [$election, $ep]) }}" class="btn-row">
                                    @csrf @method('PUT')
                                    <label class="sr-only" for="o-{{ $ep->id }}">Display order</label>
                                    <input class="input w-auto" id="o-{{ $ep->id }}" type="number" name="display_order" value="{{ $ep->display_order }}" min="0" max="10000">
                                    <strong class="grow">{{ $ep->position->name }}</strong>
                                    <label class="sr-only" for="s-{{ $ep->id }}">Seats</label>
                                    <input class="input w-auto" id="s-{{ $ep->id }}" type="number" name="seats" value="{{ $ep->seats }}" min="1" max="50">
                                    <label class="check small"><input type="checkbox" name="is_required" value="1" @checked($ep->is_required)> Required</label>
                                    <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                                </form>
                            </td>
                            <td class="num">{{ $ep->candidates_count }}</td>
                            <td class="actions">
                                @if ($ep->candidates_count === 0)
                                    <form method="POST" action="{{ route('admin.election-positions.destroy', [$election, $ep]) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                                    </form>
                                @endif
                            </td>
                        @else
                            <td>{{ $ep->display_order }}</td>
                            <td><strong>{{ $ep->position->name }}</strong></td>
                            <td class="num">{{ $ep->seats }}</td>
                            <td>{{ $ep->is_required ? 'Yes' : 'No' }}</td>
                            <td class="num">{{ $ep->candidates_count }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">No positions on this ballot yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($editable)
        <div class="stack">
            <div class="panel">
                <div class="panel-head"><h3>Add positions</h3></div>
                <div class="panel-body">
                    @if ($available->isNotEmpty())
                        <form method="POST" action="{{ route('admin.election-positions.attach-all', $election) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-primary btn-block" type="submit">Add all {{ $available->count() }} active positions</button>
                            <div class="help mt-1">Uses each position's default seats and display order.</div>
                        </form>
                        <form method="POST" action="{{ route('admin.election-positions.store', $election) }}">
                            @csrf
                            <div class="field">
                                <label for="position_id">Position</label>
                                <select class="input" id="position_id" name="position_id" required>
                                    @foreach ($available as $position)
                                        <option value="{{ $position->id }}">{{ $position->name }}</option>
                                    @endforeach
                                </select>
                                @error('position_id')<div class="error-text">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="seats">Seats</label>
                                <input class="input" id="seats" type="number" name="seats" value="1" min="1" max="50" required>
                            </div>
                            <label class="check mb-2"><input type="checkbox" name="is_required" value="1" checked> Voters must make a selection</label>
                            <button class="btn btn-secondary" type="submit">Add position</button>
                        </form>
                    @else
                        <p class="muted mb-0">All active positions are on this ballot. Manage the catalogue under <a href="{{ route('admin.positions.index') }}">Positions</a>.</p>
                    @endif
                </div>
            </div>
            @if ($attached->isNotEmpty() && auth()->user()->hasPermission('manage_candidates'))
                <a class="btn btn-secondary btn-block" href="{{ route('admin.candidates.create', $election) }}">Next: add candidates</a>
            @endif
        </div>
    @endif
</div>
@endsection
