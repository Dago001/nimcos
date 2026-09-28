@extends('layouts.admin')

@section('title', 'Elections')

@section('content')
<div class="page-head">
    <div><h1>Elections</h1><div class="sub">All elections, past and present.</div></div>
    @if (auth()->user()->hasPermission('manage_elections'))
        <a class="btn btn-primary" href="{{ route('admin.elections.create') }}">Create election</a>
    @endif
</div>

<form class="filters" method="GET">
    <div class="field">
        <label for="status">Status</label>
        <select class="input" id="status" name="status" data-autosubmit>
            <option value="">All statuses</option>
            @foreach (\App\Enums\ElectionStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>
    <noscript><button class="btn btn-secondary" type="submit">Filter</button></noscript>
</form>

@include('admin.elections.partials.table', ['elections' => $elections])
{{ $elections->links() }}
@endsection
