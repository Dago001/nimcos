{{-- One position's result table. Expects $p from ResultsPresenter. --}}
<section class="result-position" aria-labelledby="res-{{ $p['election_position']->id }}">
    <div class="btn-row spread mb-1">
        <h2 id="res-{{ $p['election_position']->id }}" class="mb-0">{{ $p['name'] }}@if ($p['seats'] > 1) <span class="muted small">({{ $p['seats'] }} seats)</span>@endif</h2>
        @if ($p['has_tie'] && ! $p['resolution'])
            <span class="tie-flag">TIE DETECTED</span>
        @elseif ($p['has_tie'] && $p['resolution'])
            <span class="badge badge-info">Tie resolved: {{ $p['resolution']->method->label() }}</span>
        @endif
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Candidate</th><th class="num">Votes</th><th class="num">%</th><th>Outcome</th></tr>
            </thead>
            <tbody>
            @foreach ($p['candidates'] as $row)
                <tr class="{{ $row['is_winner'] ? 'winner' : '' }}">
                    <td>
                        <div class="person">
                            <x-candidate-avatar :candidate="$row['candidate']" size="small" />
                            <div>
                                <strong>{{ $row['candidate']->displayName() }}</strong>
                                <div class="small muted">Candidate {{ $row['candidate']->candidate_number }}@if ($row['candidate']->rank) · {{ $row['candidate']->rank }}@endif</div>
                            </div>
                        </div>
                    </td>
                    <td class="num"><strong>{{ number_format($row['votes']) }}</strong></td>
                    <td class="num">{{ number_format($row['percentage'], 2) }}</td>
                    <td>
                        @if ($row['is_tied'] && ! $p['resolution'])
                            <span class="badge badge-warning">Tied</span>
                        @elseif ($row['is_winner'])
                            <span class="badge badge-success">✓ Elected</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td><strong>Total valid votes</strong></td>
                <td class="num"><strong>{{ number_format($p['total_valid_votes']) }}</strong></td>
                <td class="num">100.00</td>
                <td class="small muted">Invalid: {{ $p['invalid_votes'] }}@if ($p['abstentions'] !== null) · Abstained: {{ number_format($p['abstentions']) }}@endif</td>
            </tr>
            </tfoot>
        </table>
    </div>
    @if ($p['resolution'])
        <p class="small muted mt-1">Tie resolution ({{ $p['resolution']->method->label() }}): {{ $p['resolution']->notes }}</p>
    @endif
</section>
