<?php

namespace Tests;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionVoter;
use App\Models\Position;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Never let the suite touch a non-test database.
        $database = DB::connection()->getDatabaseName();
        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against database \"{$database}\".");
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PositionSeeder::class);
    }

    protected function admin(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    /** Sign in as an administrator with the MFA step satisfied. */
    protected function actingAsAdmin(User $user): static
    {
        return $this->actingAs($user, 'web')->withSession(['admin.mfa_passed' => true]);
    }

    /**
     * Build an OPEN election with N positions (1 seat each by default), 3 active
     * candidates per position and the given voters authorised.
     *
     * @param  list<Voter>  $voters
     */
    protected function openElection(int $positions = 3, array $voters = [], array $seats = [], ElectionStatus $status = ElectionStatus::OPEN): Election
    {
        $election = Election::factory()->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(8),
        ]);

        foreach (Position::query()->orderBy('display_order')->limit($positions)->get() as $i => $position) {
            $ep = new ElectionPosition;
            $ep->election()->associate($election);
            $ep->fill(['position_id' => $position->id, 'seats' => $seats[$i] ?? 1, 'display_order' => $i, 'is_required' => true])->save();

            for ($c = 1; $c <= 3; $c++) {
                $candidate = new Candidate;
                $candidate->fill([
                    'election_position_id' => $ep->id,
                    'candidate_number' => $i * 10 + $c,
                    'surname' => 'CANDIDATE',
                    'first_name' => 'Pos'.$i.'Cand'.$c,
                    'display_order' => $c,
                ]);
                $candidate->forceFill(['election_id' => $election->id, 'status' => CandidateStatus::ACTIVE, 'is_test_data' => true])->save();
            }
        }

        foreach ($voters as $voter) {
            $this->authorise($election, $voter);
        }

        if ($status !== ElectionStatus::DRAFT) {
            $election->forceFill(['status' => ElectionStatus::SCHEDULED])->save();
        }
        if (in_array($status, [ElectionStatus::OPEN, ElectionStatus::CLOSED], true)) {
            $election->forceFill(['status' => ElectionStatus::OPEN, 'opened_at' => now()])->save();
        }

        return $election->fresh();
    }

    protected function authorise(Election $election, Voter $voter, EligibilityStatus $status = EligibilityStatus::ELIGIBLE): ElectionVoter
    {
        $ev = new ElectionVoter;
        $ev->forceFill([
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'eligibility_status' => $status,
            'authorized_at' => now(),
        ])->save();

        return $ev;
    }

    /** Sign a voter in (as if OTP had succeeded). */
    protected function actingAsVoter(Voter $voter): static
    {
        return $this->actingAs($voter, 'voter');
    }

    /**
     * Start a ballot session for the voter and return the ballot-token cookie value.
     */
    protected function startBallot(Election $election): string
    {
        $response = $this->post(route('voter.election.start', $election->code));
        $response->assertRedirect(route('voter.ballot'));

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === config('nimcos.voting_session.ballot_cookie'));
        $this->assertNotNull($cookie, 'Ballot token cookie was not issued.');

        return $cookie->getValue();
    }

    /** One valid selection per position (first active candidate for each). */
    protected function validSelections(Election $election, int $candidateIndex = 0): array
    {
        $out = [];
        foreach ($election->electionPositions()->with('activeCandidates')->get() as $ep) {
            $out[$ep->id] = [$ep->activeCandidates[$candidateIndex]->id];
        }

        return $out;
    }

    protected function submitBallot(string $ballotToken, array $selections): TestResponse
    {
        return $this->withUnencryptedCookie(config('nimcos.voting_session.ballot_cookie'), $ballotToken)
            ->post(route('voter.ballot.submit'), ['selections' => $selections]);
    }

    /** Complete voting for a fresh voter in one call (used to build result scenarios). */
    protected function castVote(Election $election, Voter $voter, array $selections): TestResponse
    {
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $response = $this->submitBallot($token, $selections);
        // Clear the voting session keys so the next voter starts clean.
        $this->flushSession();

        return $response;
    }
}
