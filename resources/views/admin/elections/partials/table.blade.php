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
                <td class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('admin.elections.show', $election) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="table-empty">No elections found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
