@extends('layouts.admin')

@section('title', 'Positions')

@section('content')
<div class="page-head"><div><h1>Positions</h1><div class="sub">The catalogue of NIMCOS elective offices. Each election chooses which positions appear on its ballot, and how many seats each has.</div></div></div>

<div class="grid-2">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Order</th><th>Position</th><th class="num">Default seats</th><th>Status</th><th class="num">Used in</th><th class="actions"></th></tr></thead>
            <tbody>
            @foreach ($positions as $position)
                <tr>
                    <td>{{ $position->display_order }}</td>
                    <td><strong>{{ $position->name }}</strong><div class="small muted">{{ $position->description }}</div></td>
                    <td class="num">{{ $position->default_seats }}</td>
                    <td><x-status-badge :status="$position->status" /></td>
                    <td class="num">{{ $position->election_positions_count }} election(s)</td>
                    <td class="actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="edit-{{ $position->id }}">Edit</button>
                        <form method="POST" action="{{ route('admin.positions.toggle', $position) }}" class="inline-form">
                            @csrf
                            <button class="btn btn-ghost btn-sm" type="submit">{{ $position->status->value === 'ACTIVE' ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                        <dialog class="modal" id="edit-{{ $position->id }}" aria-label="Edit {{ $position->name }}">
                            <form method="POST" action="{{ route('admin.positions.update', $position) }}">
                                @csrf @method('PUT')
                                <div class="modal-head"><h2>Edit position</h2></div>
                                <div class="modal-body">@include('admin.positions.fields', ['position' => $position, 'prefix' => 'e'.$loop->index])</div>
                                <div class="modal-foot">
                                    <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
                                    <button class="btn btn-primary" type="submit">Save</button>
                                </div>
                            </form>
                        </dialog>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-head"><h3>New position</h3></div>
        <form class="panel-body" method="POST" action="{{ route('admin.positions.store') }}">
            @csrf
            @include('admin.positions.fields', ['position' => new \App\Models\Position(['default_seats' => 1, 'display_order' => ($positions->max('display_order') ?? 0) + 10]), 'prefix' => 'n'])
            <button class="btn btn-primary" type="submit">Create position</button>
        </form>
    </div>
</div>
@endsection
