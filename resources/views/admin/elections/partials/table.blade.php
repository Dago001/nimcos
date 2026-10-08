<div class="table-wrap">
    <table class="table">
        <thead>
        <tr><th>Election</th><th>Code</th><th>Voting period (WAT)</th><th>Status</th><th>Results</th><th class="actions"></th></tr>
        </thead>
        <tbody>
        @forelse ($elections as $election)
            <tr>
                <td><a href="{{ route('admin.elections.show', $election) }}"><strong>{{ $election->name }}</strong></a>
                    @if ($election->is_test_data) <span class="badge badge-warning">Test</span>@endif</td>
                <td class="mono small">{{ $election->code }}</td>
                <td class="nowrap">{{ display_time($election->starts_at, 'j M Y H:i') }} to {{ display_time($election->ends_at, 'H:i') }}</td>
                <td><x-status-badge :status="$election->status" /></td>
                <td><x-status-badge :status="$election->result_status" /></td>
                <td class="actions">
                    <div class="actions-row">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.elections.show', $election) }}">Open</a>
                        @if (auth()->user()->hasPermission('manage_elections'))
                            <form method="POST" action="{{ route('admin.elections.destroy', $election) }}" class="inline-form" data-confirm="Are you sure you want to permanently delete '{{ $election->name }}' and all its associated data? This cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="table-empty">No elections found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
