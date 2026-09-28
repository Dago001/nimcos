<?php

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Models\Election;
use App\Models\Role;
use App\Models\Voter;
use App\Services\Elections\ElectionLifecycle;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ElectionLifecycleTest extends TestCase
{
    public function test_administrator_can_create_an_election_and_attach_all_fourteen_positions(): void
    {
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->post(route('admin.elections.store'), [
            'name' => 'NIMCOS 2026 ELECTIVE CONGRESS',
            'code' => 'nimcos-2026-ec',
            'election_type' => 'GENERAL',
            'starts_at' => now('Africa/Lagos')->addDay()->format('Y-m-d\T08:00'),
            'ends_at' => now('Africa/Lagos')->addDay()->format('Y-m-d\T18:00'),
            'auto_open' => '1',
            'auto_close' => '1',
        ])->assertRedirect();

        $election = Election::query()->where('code', 'NIMCOS-2026-EC')->firstOrFail();
        $this->assertSame(ElectionStatus::DRAFT, $election->status);
        // 08:00 WAT is stored as 07:00 UTC.
        $this->assertSame('07:00', $election->starts_at->utc()->format('H:i'));

        $this->post(route('admin.election-positions.attach-all', $election))->assertSessionHasNoErrors();
        $this->assertSame(14, $election->electionPositions()->count());
        $this->assertSame('PRESIDENT', $election->electionPositions()->with('position')->first()->position->name);
    }

    public function test_election_cannot_be_scheduled_until_it_is_ready(): void
    {
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);
        $election = Election::factory()->create();

        $this->actingAsAdmin($ea)->post(route('admin.elections.schedule', $election), ['confirm_password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors('election');
        $this->assertSame(ElectionStatus::DRAFT, $election->fresh()->status);
    }

    public function test_ready_election_can_be_scheduled_opened_and_closed(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter], status: ElectionStatus::DRAFT);
        $election->forceFill(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(9)])->save();
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);
        $pw = ['confirm_password' => UserFactory::PASSWORD];

        $this->actingAsAdmin($ea)->post(route('admin.elections.schedule', $election), $pw)->assertSessionHasNoErrors();
        $this->assertSame(ElectionStatus::SCHEDULED, $election->fresh()->status);

        $this->post(route('admin.elections.open', $election), $pw)->assertSessionHasNoErrors();
        $this->assertSame(ElectionStatus::OPEN, $election->fresh()->status);
        $this->assertTrue($election->fresh()->isAcceptingVotes());

        $this->post(route('admin.elections.close', $election), $pw)->assertSessionHasNoErrors();
        $this->assertSame(ElectionStatus::CLOSED, $election->fresh()->status);
    }

    public function test_structure_is_locked_once_scheduled(): void
    {
        $election = $this->openElection(1, [Voter::factory()->create()], status: ElectionStatus::SCHEDULED);
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);
        $ep = $election->electionPositions()->first();

        $this->actingAsAdmin($ea)->put(route('admin.election-positions.update', [$election, $ep]), ['seats' => 3, 'display_order' => 1])
            ->assertSessionHasErrors('position');
        $this->post(route('admin.candidates.store', $election), ['election_position_id' => $ep->id, 'surname' => 'X', 'first_name' => 'Y'])
            ->assertSessionHasErrors('candidate');
        $this->assertSame(1, $ep->fresh()->seats);
    }

    public function test_closed_election_cannot_be_reopened_even_by_direct_sql(): void
    {
        $election = $this->openElection(1);
        app(ElectionLifecycle::class)->close($election, null);

        try {
            app(ElectionLifecycle::class)->open($election->fresh(), $this->admin(Role::SUPER_ADMIN));
            $this->fail('Reopening must be refused.');
        } catch (ValidationException) {
            // expected
        }

        $this->expectException(QueryException::class);
        DB::table('elections')->where('id', $election->id)->update(['status' => 'OPEN']);
    }

    public function test_scheduler_opens_and_closes_elections_on_time(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter], status: ElectionStatus::SCHEDULED);
        $election->forceFill(['starts_at' => now()->addMinutes(5), 'ends_at' => now()->addHours(2)])->save();

        Artisan::call('nimcos:tick');
        $this->assertSame(ElectionStatus::SCHEDULED, $election->fresh()->status);

        $this->travel(6)->minutes();
        Artisan::call('nimcos:tick');
        $this->assertSame(ElectionStatus::OPEN, $election->fresh()->status);

        $this->travel(2)->hours();
        Artisan::call('nimcos:tick');
        $this->assertSame(ElectionStatus::CLOSED, $election->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'election.closed', 'actor_type' => 'SYSTEM']);
    }

    public function test_ballots_and_votes_are_immutable_at_database_level(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->castVote($election, $voter, $this->validSelections($election));

        foreach (['UPDATE votes SET candidate_id = candidate_id', 'DELETE FROM votes', 'DELETE FROM ballots', "UPDATE ballots SET reference = 'X'", 'DELETE FROM audit_logs', "UPDATE election_voters SET eligibility_status = 'ELIGIBLE', voted_at = NULL"] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail("Statement should have been blocked: {$sql}");
            } catch (QueryException $e) {
                $this->assertStringContainsString('NIMCOS integrity', $e->getMessage());
            }
        }
    }

    public function test_ballots_cannot_be_inserted_into_a_closed_election(): void
    {
        $election = $this->openElection(1);
        $election->forceFill(['status' => ElectionStatus::CLOSED])->save();
        $tokenId = (string) Str::uuid();
        DB::table('ballot_tokens')->insert(['id' => $tokenId, 'election_id' => $election->id, 'token_hash' => str_repeat('a', 64), 'is_consumed' => true]);

        $this->expectException(QueryException::class);
        DB::table('ballots')->insert(['id' => (string) Str::uuid(), 'election_id' => $election->id, 'ballot_token_id' => $tokenId, 'reference' => 'NIM-TEST-1']);
    }
}
