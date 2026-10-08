<?php

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Enums\ResultStatus;
use App\Enums\TieResolutionMethod;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ResultTally;
use App\Models\Role;
use App\Models\User;
use App\Models\Voter;
use App\Services\Results\ResultsCalculator;
use App\Services\Results\ResultsPresenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    private User $ro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ro = $this->admin(Role::RETURNING_OFFICER);
    }

    /**
     * Cast ballots choosing candidate index $pattern[i] for voter i in the single position.
     *
     * @param  list<int|list<int>>  $pattern
     */
    private function electionWithVotes(array $pattern, array $seats = [1]): Election
    {
        $voters = Voter::factory()->count(count($pattern))->create();
        $election = $this->openElection(count($seats), $voters->all(), $seats);
        $ep = $election->electionPositions()->with('activeCandidates')->first();

        foreach ($voters as $i => $voter) {
            $choice = (array) $pattern[$i];
            $this->castVote($election, $voter, [$ep->id => array_map(fn ($idx) => $ep->activeCandidates[$idx]->id, $choice)])
                ->assertRedirect(route('voter.receipt'));
        }
        $this->app['auth']->forgetGuards();

        $election->forceFill(['status' => ElectionStatus::CLOSED, 'closed_at' => now()])->save();

        return $election->fresh();
    }

    public function test_vote_totals_and_percentages_are_correct(): void
    {
        // 4 votes for A, 3 for B, 1 for C.
        $election = $this->electionWithVotes([0, 0, 0, 0, 1, 1, 1, 2]);

        app(ResultsCalculator::class)->calculate($election, $this->ro);
        $results = app(ResultsPresenter::class)->stored($election->fresh());
        $rows = $results['positions'][0]['candidates'];

        $this->assertSame([4, 3, 1], array_column($rows, 'votes'));
        $this->assertSame([50.0, 37.5, 12.5], array_column($rows, 'percentage'));
        $this->assertSame(8, $results['positions'][0]['total_valid_votes']);
        $this->assertSame(8, $results['ballots']);
        $this->assertSame(100.0, $results['turnout']);
        $this->assertTrue($rows[0]['is_winner']);
        $this->assertFalse($results['positions'][0]['has_tie']);
    }

    public function test_tie_is_detected_and_no_winner_is_invented(): void
    {
        $election = $this->electionWithVotes([0, 0, 1, 1, 2]);

        $computed = app(ResultsCalculator::class)->calculate($election, $this->ro);
        $position = array_values($computed['positions'])[0];

        $this->assertTrue($position['has_tie']);
        $this->assertSame([true, true, false], array_column($position['candidates'], 'is_tied'));
        $this->assertSame([false, false, false], array_column($position['candidates'], 'is_provisional_winner'));
    }

    public function test_multi_seat_tie_only_matters_at_the_seat_boundary(): void
    {
        // 2 seats: A=3, B=2, C=2 → B and C tie for the last seat.
        $election = $this->electionWithVotes([[0, 1], [0, 2], [0, 1], [2]], [2]);
        $position = array_values(app(ResultsCalculator::class)->calculate($election, $this->ro)['positions'])[0];

        $this->assertTrue($position['has_tie']);
        $this->assertTrue($position['candidates'][0]['is_provisional_winner']);
        $this->assertSame([false, true, true], array_column($position['candidates'], 'is_tied'));
    }

    public function test_results_cannot_be_calculated_or_published_before_the_election_closes(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);

        $this->actingAsAdmin($this->ro);
        $this->post(route('admin.results.calculate', $election), ['confirm_password' => 'Test-Password-123!'])->assertSessionHasErrors('results');
        $this->post(route('admin.results.publish', $election), ['confirm_password' => 'Test-Password-123!'])->assertSessionHasErrors('results');

        $this->assertSame(ElectionStatus::OPEN, $election->fresh()->status);
        $this->assertSame(0, ResultTally::query()->count());
    }

    public function test_full_workflow_calculate_verify_resolve_tie_and_publish(): void
    {
        $election = $this->electionWithVotes([0, 1]);
        $this->actingAsAdmin($this->ro);
        $pw = ['confirm_password' => 'Test-Password-123!'];

        $this->post(route('admin.results.calculate', $election), $pw)->assertSessionHasNoErrors();
        $this->post(route('admin.results.publish', $election), $pw)->assertSessionHasErrors('results'); // not verified
        $this->post(route('admin.results.verify', $election), $pw)->assertSessionHas('success');
        $this->post(route('admin.results.publish', $election), $pw)->assertSessionHasErrors('results'); // tie unresolved

        $ep = $election->electionPositions()->first();
        $tied = ResultTally::query()->where('is_tied', true)->pluck('candidate_id');
        $this->post(route('admin.results.tie', [$election, $ep]), $pw + [
            'method' => TieResolutionMethod::DRAW_OF_LOTS->value,
            'winning_candidate_id' => $tied[0],
            'notes' => 'Drawn by the Electoral Committee, minutes ref EC/2026/14.',
        ])->assertSessionHasNoErrors();

        $this->post(route('admin.results.publish', $election), $pw)->assertSessionHasNoErrors();

        $election->refresh();
        $this->assertSame(ElectionStatus::RESULTS_PUBLISHED, $election->status);
        $this->assertSame(ResultStatus::PUBLISHED, $election->result_status);
        $this->get(route('public.results.show', $election->code))
            ->assertOk()
            ->assertSee('Elected')
            ->assertSee('NAME:')
            ->assertSee($this->ro->name)
            ->assertSee('POSITION:')
            ->assertSee('RETURNING OFFICER')
            ->assertSee('Certified &amp; Published:', false);
        $this->get(route('election.signature', $election))->assertOk();
        // Counts were not changed by the tie resolution.
        $this->assertSame([1, 1], ResultTally::query()->orderBy('rank')->limit(2)->pluck('votes')->all());
    }

    public function test_verification_detects_a_tampered_tally(): void
    {
        $election = $this->electionWithVotes([0, 0, 1]);
        $calc = app(ResultsCalculator::class);
        $calc->calculate($election, $this->ro);

        DB::table('result_tallies')->where('rank', 1)->update(['votes' => 999]);

        $problems = $calc->verify($election->fresh(), $this->ro);
        $this->assertNotEmpty($problems);
        $this->assertSame(ResultStatus::CALCULATED, $election->fresh()->result_status);
        $this->assertDatabaseHas('security_alerts', ['type' => 'RESULTS_VERIFICATION_FAILED']);
    }

    public function test_published_results_are_frozen_at_database_level(): void
    {
        $election = $this->electionWithVotes([0]);
        $calc = app(ResultsCalculator::class);
        $calc->calculate($election, $this->ro);
        $calc->verify($election->fresh(), $this->ro);
        $calc->publish($election->fresh(), $this->ro);

        $this->expectException(QueryException::class);
        DB::table('result_tallies')->update(['votes' => 5]);
    }

    public function test_no_route_accepts_manual_vote_counts(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $writes = array_intersect($route->methods(), ['PUT', 'PATCH', 'POST']);
            if ($writes && str_contains($uri, 'results')) {
                $this->assertMatchesRegularExpression('#results/(calculate|verify|publish|ties/\{electionPosition\})$|receipt/verify$#', $uri, "Unexpected results write route: {$uri}");
            }
            $this->assertStringNotContainsString('tallies', $uri);
        }
    }

    public function test_only_the_publish_permission_can_process_results(): void
    {
        $election = $this->electionWithVotes([0]);
        foreach ([Role::SUPER_ADMIN, Role::ELECTION_ADMINISTRATOR, Role::AUDITOR] as $role) {
            $this->actingAsAdmin($this->admin($role))
                ->post(route('admin.results.calculate', $election), ['confirm_password' => 'Test-Password-123!'])
                ->assertForbidden();
        }
    }

    public function test_results_are_sealed_from_admins_while_voting_is_open(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->castVote($election, $voter, $this->validSelections($election));
        $this->app['auth']->forgetGuards();
        $name = Candidate::query()->where('election_id', $election->id)->orderBy('display_order')->first()->displayName();

        $this->actingAsAdmin($this->ro)->get(route('admin.results.show', $election))
            ->assertOk()->assertSee('Results are sealed')->assertDontSee($name);
    }
}
