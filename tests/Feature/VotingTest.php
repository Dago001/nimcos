<?php

namespace Tests\Feature;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\ElectionVoter;
use App\Models\Vote;
use App\Models\Voter;
use App\Services\Voting\ReceiptReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VotingTest extends TestCase
{
    public function test_eligible_voter_can_vote_and_is_marked_as_voted(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(3, [$voter]);

        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $this->get(route('voter.ballot'))->assertOk()->assertSee('Position 1 of 3');

        $this->submitBallot($token, $this->validSelections($election))->assertRedirect(route('voter.receipt'));

        $this->assertSame(1, Ballot::query()->where('election_id', $election->id)->count());
        $this->assertSame(3, Vote::query()->where('election_id', $election->id)->count());
        $ev = ElectionVoter::query()->where('voter_id', $voter->id)->first();
        $this->assertSame(EligibilityStatus::VOTED, $ev->eligibility_status);
        $this->assertNotNull($ev->voted_at);

        $this->get(route('voter.receipt'))->assertOk()
            ->assertSee('VOTE SUCCESSFULLY SUBMITTED')
            ->assertSee(Ballot::query()->value('reference'));
    }

    public function test_review_page_lists_every_selection_and_receipt_reveals_none(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $selections = $this->validSelections($election, 1);
        $names = Candidate::query()->whereIn('id', array_merge(...array_values($selections)))->get()->map->displayName();

        $review = $this->post(route('voter.ballot.review'), ['selections' => $selections])->assertOk()->assertSee('Review your ballot');
        foreach ($names as $name) {
            $review->assertSee($name);
        }

        $this->submitBallot($token, $selections);
        $receipt = $this->get(route('voter.receipt'))->assertOk();
        foreach ($names as $name) {
            $receipt->assertDontSee($name);
        }
    }

    public function test_ineligible_voter_cannot_start_a_ballot(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1);
        $this->authorise($election, $voter, EligibilityStatus::SUSPENDED);

        $this->actingAsVoter($voter)->post(route('voter.election.start', $election->code))->assertRedirect(route('voter.election', $election->code));
        $this->assertSame(0, DB::table('voting_sessions')->count());
    }

    public function test_voter_not_on_the_roll_cannot_access_the_election(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [Voter::factory()->create()]);

        $this->actingAsVoter($voter)->get(route('voter.election', $election->code))->assertNotFound();
        $this->post(route('voter.election.start', $election->code))->assertNotFound();
    }

    public function test_voter_cannot_vote_before_the_election_opens(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter], status: ElectionStatus::SCHEDULED);

        $this->actingAsVoter($voter)->post(route('voter.election.start', $election->code))
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('voting_sessions')->count());
    }

    public function test_voter_cannot_vote_after_the_end_time_even_if_not_yet_closed(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        // The scheduler has not run: status is still OPEN, but the server clock is past ends_at.
        $this->travelTo($election->ends_at->copy()->addSecond());

        $this->submitBallot($token, $this->validSelections($election))->assertRedirect();
        $this->assertSame(0, Ballot::query()->count());
        $this->assertSame(EligibilityStatus::ELIGIBLE, ElectionVoter::query()->first()->eligibility_status);
    }

    public function test_voter_cannot_vote_after_the_election_is_closed(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        $election->forceFill(['status' => ElectionStatus::CLOSED, 'closed_at' => now()])->save();

        $this->submitBallot($token, $this->validSelections($election))->assertRedirect();
        $this->assertSame(0, Ballot::query()->count());
    }

    public function test_voter_cannot_vote_twice(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $this->submitBallot($token, $this->validSelections($election))->assertRedirect(route('voter.receipt'));

        // A new ballot session is refused...
        $this->post(route('voter.election.start', $election->code))->assertRedirect(route('voter.already-voted', $election->code));
        // ...and so is a submission from a new browser without the original token.
        $this->withUnencryptedCookie(config('nimcos.voting_session.ballot_cookie'), 'forged')
            ->post(route('voter.ballot.submit'), ['selections' => $this->validSelections($election, 1)]);

        $this->assertSame(1, Ballot::query()->count());
        $this->assertSame(2, Vote::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'voting.duplicate_attempt']);
    }

    public function test_retrying_a_submission_returns_the_same_receipt_without_a_second_ballot(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $selections = $this->validSelections($election);

        $this->submitBallot($token, $selections)->assertRedirect(route('voter.receipt'));
        $first = $this->get(route('voter.receipt'))->getContent();

        // Browser retries after a timeout: same cookie, same session.
        $this->submitBallot($token, $selections)->assertRedirect(route('voter.receipt'));
        $second = $this->get(route('voter.receipt'))->getContent();

        $this->assertSame(1, Ballot::query()->count());
        $reference = Ballot::query()->value('reference');
        $this->assertStringContainsString($reference, $first);
        $this->assertStringContainsString($reference, $second);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voting.idempotent_replay']);
    }

    public function test_compare_and_set_blocks_a_concurrent_second_ballot(): void
    {
        // Simulates the losing side of a race: another request marked the voter as voted
        // after this request's session was loaded.
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        DB::table('election_voters')->update(['eligibility_status' => 'VOTED', 'voted_at' => now()]);

        $this->submitBallot($token, $this->validSelections($election));

        $this->assertSame(0, Ballot::query()->count());
    }

    public function test_candidate_from_another_election_is_rejected(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $other = $this->openElection(1);
        $foreign = Candidate::query()->where('election_id', $other->id)->first();

        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $ep = $election->electionPositions()->first();

        $this->submitBallot($token, [$ep->id => [$foreign->id]])->assertOk()->assertSee('invalid selection');
        $this->assertSame(0, Ballot::query()->count());
        $this->assertDatabaseHas('security_alerts', ['type' => 'TAMPERED_BALLOT_SUBMISSION']);
    }

    public function test_candidate_for_a_different_position_is_rejected(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter]);
        [$ep1, $ep2] = $election->electionPositions()->with('activeCandidates')->get()->all();

        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        $this->submitBallot($token, [
            $ep1->id => [$ep2->activeCandidates[0]->id],
            $ep2->id => [$ep2->activeCandidates[1]->id],
        ])->assertOk();
        $this->assertSame(0, Ballot::query()->count());
    }

    public function test_inactive_candidate_is_rejected(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $candidate = Candidate::query()->where('election_id', $election->id)->first();
        $candidate->forceFill(['status' => CandidateStatus::WITHDRAWN])->save();

        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        $this->submitBallot($token, [$candidate->election_position_id => [$candidate->id]])->assertOk();
        $this->assertSame(0, Ballot::query()->count());
    }

    public function test_every_required_position_must_be_answered(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(3, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);
        $selections = $this->validSelections($election);
        array_pop($selections);

        $this->submitBallot($token, $selections)->assertOk()->assertSee('Please make a selection');
        $this->assertSame(0, Ballot::query()->count());
    }

    public function test_multi_seat_position_accepts_up_to_seats_and_rejects_more_or_duplicates(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter], seats: [2]);
        $ep = $election->electionPositions()->with('activeCandidates')->first();
        $c = $ep->activeCandidates;

        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        $this->submitBallot($token, [$ep->id => [$c[0]->id, $c[1]->id, $c[2]->id]])->assertOk();
        $this->submitBallot($token, [$ep->id => [$c[0]->id, $c[0]->id]])->assertOk();
        $this->assertSame(0, Ballot::query()->count());

        $this->submitBallot($token, [$ep->id => [$c[0]->id, $c[2]->id]])->assertRedirect(route('voter.receipt'));
        $this->assertSame(2, Vote::query()->count());
    }

    public function test_ballot_submission_is_atomic_and_rolls_back_on_failure(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(2, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        // Fail after the voter has been marked VOTED inside the transaction.
        $this->mock(ReceiptReferenceGenerator::class, fn ($m) => $m->shouldReceive('generate')->andThrow(new \RuntimeException('simulated failure')));

        $this->submitBallot($token, $this->validSelections($election))->assertStatus(500);

        $this->assertSame(0, Ballot::query()->count());
        $this->assertSame(0, Vote::query()->count());
        $this->assertSame(EligibilityStatus::ELIGIBLE, ElectionVoter::query()->first()->eligibility_status);
        $this->assertSame(0, DB::table('ballot_tokens')->where('is_consumed', true)->count());
    }

    public function test_session_expires_after_inactivity(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $token = $this->startBallot($election);

        $this->travel(config('nimcos.voting_session.idle_minutes') + 1)->minutes();

        $this->get(route('voter.ballot'))->assertRedirect(route('voter.session-expired'));
        $this->submitBallot($token, $this->validSelections($election));
        $this->assertSame(0, Ballot::query()->count());
    }

    public function test_starting_a_new_session_revokes_the_previous_one(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $this->startBallot($election);
        $this->startBallot($election);

        $this->assertSame(1, DB::table('voting_sessions')->where('status', 'ACTIVE')->count());
        $this->assertSame(1, DB::table('voting_sessions')->where('status', 'REVOKED')->count());
        $this->assertDatabaseHas('security_alerts', ['type' => 'MULTIPLE_VOTING_SESSIONS']);
    }

    public function test_voter_cannot_use_another_voters_voting_session(): void
    {
        [$alice, $bob] = Voter::factory()->count(2)->create()->all();
        $election = $this->openElection(1, [$alice, $bob]);

        $this->actingAsVoter($alice);
        $this->startBallot($election);
        $aliceSession = session()->only(['voting.session_id', 'voting.session_secret']);

        // Bob signs in and replays Alice's session identifiers.
        $this->flushSession();
        $this->actingAsVoter($bob)->withSession($aliceSession);

        $this->get(route('voter.ballot'))->assertRedirect(route('voter.elections'));
    }
}
