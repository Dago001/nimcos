<?php

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Role;
use App\Models\Voter;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EligibilityAndCandidateTest extends TestCase
{
    public function test_authorise_all_adds_only_verified_active_eligible_members(): void
    {
        Voter::factory()->count(3)->create();
        Voter::factory()->unverified()->create();
        Voter::factory()->create(['account_status' => 'SUSPENDED']);
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);

        $this->actingAsAdmin($this->admin(Role::ELECTION_ADMINISTRATOR))
            ->post(route('admin.eligibility.authorise-all', $election))->assertSessionHasNoErrors();

        $this->assertSame(3, ElectionVoter::query()->where('election_id', $election->id)->count());
    }

    public function test_eligibility_cannot_be_granted_while_voting_is_open_but_can_be_suspended(): void
    {
        $onRoll = Voter::factory()->create();
        $late = Voter::factory()->create();
        $election = $this->openElection(1, [$onRoll]);
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->post(route('admin.eligibility.store', $election), ['service_number' => $late->service_number])
            ->assertSessionHasErrors('election');

        $ev = ElectionVoter::query()->where('voter_id', $onRoll->id)->first();
        $this->patch(route('admin.eligibility.update', [$election, $ev]), ['status' => 'SUSPENDED', 'reason' => 'Membership under review'])
            ->assertSessionHasNoErrors();
        $this->assertSame(EligibilityStatus::SUSPENDED, $ev->fresh()->eligibility_status);
    }

    public function test_voted_status_cannot_be_changed_by_an_administrator(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->castVote($election, $voter, $this->validSelections($election));
        $this->app['auth']->forgetGuards();
        $ev = ElectionVoter::query()->first();

        $this->actingAsAdmin($this->admin(Role::ELECTION_ADMINISTRATOR))
            ->patch(route('admin.eligibility.update', [$election, $ev]), ['status' => 'INELIGIBLE', 'reason' => 'attempt'])
            ->assertSessionHasErrors('status');
        $this->assertSame(EligibilityStatus::VOTED, $ev->fresh()->eligibility_status);
    }

    public function test_eligibility_is_per_election_so_a_voter_can_vote_again_in_a_later_election(): void
    {
        $voter = Voter::factory()->create();
        $first = $this->openElection(1, [$voter]);
        $second = $this->openElection(1, [$voter]);

        $this->castVote($first, $voter, $this->validSelections($first))->assertRedirect(route('voter.receipt'));
        $this->castVote($second, $voter, $this->validSelections($second))->assertRedirect(route('voter.receipt'));

        $this->assertSame(2, ElectionVoter::query()->where('voter_id', $voter->id)->where('eligibility_status', 'VOTED')->count());
    }

    public function test_candidate_can_be_created_with_a_photo_that_is_reencoded(): void
    {
        Storage::fake('local');
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);
        $ep = $election->electionPositions()->first();

        $this->actingAsAdmin($this->admin(Role::ELECTION_ADMINISTRATOR))->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id,
            'surname' => "O'NEIL",
            'first_name' => 'Ada',
            'rank' => 'CSI',
            'photo' => UploadedFile::fake()->image('ada.png', 400, 500),
        ])->assertSessionHasNoErrors();

        $candidate = Candidate::query()->where('surname', "O'NEIL")->firstOrFail();
        $this->assertStringEndsWith('.jpg', $candidate->photo_path);
        Storage::disk('local')->assertExists($candidate->photo_path);
        [$w, $h] = getimagesize(Storage::disk('local')->path($candidate->photo_path));
        $this->assertSame([600, 600], [$w, $h]);
        $this->assertSame(4, $candidate->candidate_number, 'Next number after candidates 1 to 3 is allocated automatically.');
    }

    public function test_candidate_create_redirects_to_positions_when_election_has_none(): void
    {
        $election = Election::factory()->create();
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->get(route('admin.candidates.create', $election))
            ->assertRedirect(route('admin.election-positions.index', $election))
            ->assertSessionHas('error');

        $this->actingAsAdmin($ea)->get(route('admin.candidates.index', ['election' => $election->id]))
            ->assertOk()->assertSee('has no positions yet');
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);
        $ep = $election->electionPositions()->first();

        $this->actingAsAdmin($this->admin(Role::ELECTION_ADMINISTRATOR))->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id, 'surname' => 'X', 'first_name' => 'Y',
            'photo' => UploadedFile::fake()->createWithContent('shell.php.jpg', '<?php system($_GET["c"]); ?>'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_draft_candidate_photos_are_not_public(): void
    {
        Storage::fake('local');
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);
        $candidate = Candidate::query()->where('election_id', $election->id)->first();
        Storage::disk('local')->put('candidates/x.jpg', 'x');
        $candidate->forceFill(['photo_path' => 'candidates/x.jpg'])->save();

        $this->get(route('candidate.photo', $candidate))->assertNotFound();
    }

    public function test_audit_chain_detects_tampering(): void
    {
        $audit = app(AuditLogger::class);
        $audit->log('test.one', metadata: ['n' => 1, 'f' => 1.5]);
        $audit->log('test.two', metadata: ['z' => ['b' => 2, 'a' => 1], 'list' => [3, 1, 2]]);
        $audit->log('test.three');

        $this->assertTrue(app(AuditChainVerifier::class)->verify()['ok']);

        // Tamper by bypassing the trigger (superuser-style), then verify.
        DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_immutable');
        DB::table('audit_logs')->where('action', 'test.two')->update(['actor_label' => 'someone else']);
        DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_immutable');

        $result = app(AuditChainVerifier::class)->verify();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('altered', $result['reason']);
    }
}
