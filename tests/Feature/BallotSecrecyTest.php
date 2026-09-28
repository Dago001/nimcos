<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Role;
use App\Models\Voter;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ballot secrecy (spec §16): the system records THAT an officer voted, never WHAT they chose.
 */
class BallotSecrecyTest extends TestCase
{
    public function test_anonymous_tables_have_no_link_to_voters_sessions_or_time(): void
    {
        $forbidden = ['voter_id', 'election_voter_id', 'voting_session_id', 'user_id', 'service_number', 'created_at', 'updated_at', 'cast_at', 'ip', 'user_agent'];
        foreach (['ballot_tokens', 'ballots', 'votes'] as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertEmpty(array_intersect($forbidden, $columns), "{$table} must not contain identifying or time columns.");
        }

        $foreignTables = collect(Schema::getForeignKeys('votes'))->pluck('foreign_table')
            ->merge(collect(Schema::getForeignKeys('ballots'))->pluck('foreign_table'))
            ->merge(collect(Schema::getForeignKeys('ballot_tokens'))->pluck('foreign_table'))
            ->unique()->values()->all();
        sort($foreignTables);
        $this->assertSame(['ballot_tokens', 'ballots', 'candidates', 'election_positions', 'elections'], $foreignTables);
    }

    public function test_ballot_keys_are_random_uuids_not_time_ordered(): void
    {
        $voters = Voter::factory()->count(3)->create();
        $election = $this->openElection(1, $voters->all());
        foreach ($voters as $voter) {
            $this->castVote($election, $voter, $this->validSelections($election));
        }

        foreach (Ballot::query()->pluck('id') as $id) {
            $this->assertSame('4', $id[14], "Ballot id {$id} must be a random (version 4) UUID.");
        }
        foreach (DB::table('votes')->pluck('id') as $id) {
            $this->assertSame('4', $id[14]);
        }
    }

    public function test_raw_ballot_token_is_never_stored(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $this->startBallot($election);

        $row = DB::table('ballot_tokens')->first();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->token_hash);
        // Nothing on the identity side stores the token hash either.
        $this->assertSame(0, DB::table('voting_sessions')->where('token_hash', $row->token_hash)->count());
        $this->assertStringNotContainsString($row->token_hash, AuditLog::query()->get()->toJson());
    }

    public function test_audit_log_records_that_a_voter_voted_but_not_the_reference_or_choices(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter]);
        $selections = $this->validSelections($election);
        $this->castVote($election, $voter, $selections);

        $cast = AuditLog::query()->where('action', 'voting.ballot_cast')->firstOrFail();
        $this->assertSame($voter->id, $cast->actor_id);

        $everything = AuditLog::query()->get()->toJson();
        $this->assertStringNotContainsString(Ballot::query()->value('reference'), $everything);
        $this->assertStringNotContainsString(Ballot::query()->value('id'), $everything);
        foreach (array_merge(...array_values($selections)) as $candidateId) {
            $this->assertStringNotContainsString($candidateId, $everything);
        }
    }

    public function test_audit_logger_scrubs_secret_keys_even_if_a_caller_passes_them(): void
    {
        $log = app(AuditLogger::class)->log('test.event', metadata: [
            'otp' => '123456', 'password' => 'secret', 'selections' => ['x'], 'nested' => ['token' => 'abc', 'ok' => 1], 'kept' => 'yes',
        ]);

        $this->assertSame(['kept' => 'yes', 'nested' => ['ok' => 1]], $log->metadata);
    }

    public function test_monitoring_statistics_expose_no_candidate_figures(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->castVote($election, $voter, $this->validSelections($election));
        $this->app['auth']->forgetGuards();

        $json = $this->actingAsAdmin($this->admin(Role::RETURNING_OFFICER))
            ->getJson(route('admin.monitor.stats', $election))->assertOk()->json();

        $flat = json_encode($json);
        foreach (Candidate::query()->pluck('id') as $candidateId) {
            $this->assertStringNotContainsString($candidateId, $flat);
        }
        $this->assertSame(1, $json['summary']['voted']);
    }
}
