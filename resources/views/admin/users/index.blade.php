@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<div class="page-head"><div><h1>Administrators</h1><div class="sub">Accounts that can sign in to election administration.</div></div>
    <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Add administrator</a></div>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>MFA</th><th>Status</th><th>Last sign-in</th><th class="actions"></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td><strong>{{ $u->name }}</strong> @if ($u->is_test_data)<span class="badge badge-warning">Test</span>@endif</td>
                <td class="small">{{ $u->email }}</td>
                <td class="small">{{ $u->roleLabels() }}</td>
                <td>@if ($u->hasMfa())<span class="badge badge-success">On</span>@else<span class="badge badge-warning">Off</span>@endif</td>
                <td><x-status-badge :status="$u->status" /> @if ($u->isLocked())<span class="badge badge-error">Locked</span>@endif</td>
                <td class="small nowrap">{{ $u->last_login_at ? display_time($u->last_login_at) : 'Never' }}</td>
                <td class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('admin.users.edit', $u) }}">Manage</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $users->links() }}
@endsection
