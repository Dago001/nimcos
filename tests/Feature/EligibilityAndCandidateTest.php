<?php

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Enums\EligibilityStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
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

    public function test_candidate_rank_must_be_a_real_nis_rank(): void
    {
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);
        $ep = $election->electionPositions()->first();
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id, 'surname' => 'BELLO', 'first_name' => 'Musa', 'rank' => 'GENERAL',
        ])->assertSessionHasErrors('rank');

        $this->actingAsAdmin($ea)->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id, 'surname' => 'BELLO', 'first_name' => 'Musa', 'rank' => NisRank::DCI->value,
        ])->assertSessionHasNoErrors();

        $candidate = Candidate::query()->where('surname', 'BELLO')->firstOrFail();
        $this->assertSame(NisRank::DCI->value, $candidate->rank);
        $this->assertSame('Deputy Comptroller of Immigration (DCI)', $candidate->rankLabel());
        $this->assertSame('DCI', $candidate->rankShortLabel());
    }

    public function test_voter_rank_must_be_a_real_nis_rank(): void
    {
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->post(route('admin.voters.store'), [
            'service_number' => '19001', 'surname' => 'YUSUF', 'first_name' => 'Aisha',
            'email' => 'yusuf@example.com', 'membership_status' => 'ACTIVE', 'rank' => 'CAPTAIN',
        ])->assertSessionHasErrors('rank');

        $this->actingAsAdmin($ea)->post(route('admin.voters.store'), [
            'service_number' => '19001', 'surname' => 'YUSUF', 'first_name' => 'Aisha',
            'email' => 'yusuf@example.com', 'membership_status' => 'ACTIVE', 'rank' => NisRank::IA3->value,
        ])->assertSessionHasNoErrors();

        $voter = Voter::query()->where('service_number', '19001')->firstOrFail();
        $this->assertSame(NisRank::IA3->value, $voter->rank);
        $this->assertSame('Immigration Assistant 3 (IA3)', $voter->rankLabel());
    }

    public function test_candidate_command_must_be_a_real_nis_command(): void
    {
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);
        $ep = $election->electionPositions()->first();
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id, 'surname' => 'OKORO', 'first_name' => 'Chidi', 'command' => 'MADE UP COMMAND',
        ])->assertSessionHasErrors('command');

        $this->actingAsAdmin($ea)->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id, 'surname' => 'OKORO', 'first_name' => 'Chidi', 'command' => NisCommand::RIVERS_STATE_COMMAND->value,
        ])->assertSessionHasNoErrors();

        $candidate = Candidate::query()->where('surname', 'OKORO')->firstOrFail();
        $this->assertSame(NisCommand::RIVERS_STATE_COMMAND->value, $candidate->command);
        $this->assertSame('Rivers State Command', $candidate->commandLabel());

        // Service Headquarters (Abuja) is accepted and satisfies the DB check constraint
        $this->actingAsAdmin($ea)->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id, 'surname' => 'DAGOGO', 'first_name' => 'Gift', 'command' => NisCommand::SERVICE_HEADQUARTERS_ABUJA->value,
        ])->assertSessionHasNoErrors();

        $shqCandidate = Candidate::query()->where('surname', 'DAGOGO')->firstOrFail();
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA->value, $shqCandidate->command);
        $this->assertSame('Service Headquarters (Abuja)', $shqCandidate->commandLabel());
    }

    public function test_voter_command_must_be_a_real_nis_command(): void
    {
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        $this->actingAsAdmin($ea)->post(route('admin.voters.store'), [
            'service_number' => '19002', 'surname' => 'BALA', 'first_name' => 'Amina',
            'email' => 'bala@example.com', 'membership_status' => 'ACTIVE', 'command' => 'MADE UP COMMAND',
        ])->assertSessionHasErrors('command');

        $this->actingAsAdmin($ea)->post(route('admin.voters.store'), [
            'service_number' => '19002', 'surname' => 'BALA', 'first_name' => 'Amina',
            'email' => 'bala@example.com', 'membership_status' => 'ACTIVE', 'command' => NisCommand::SERVICE_HEADQUARTERS_ABUJA->value,
        ])->assertSessionHasNoErrors();

        $voter = Voter::query()->where('service_number', '19002')->firstOrFail();
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA->value, $voter->command);
        $this->assertSame('Service Headquarters (Abuja)', $voter->commandLabel());
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

    public function test_candidate_voter_lookup_returns_voter_details(): void
    {
        $voter = Voter::factory()->create([
            'service_number' => '54321',
            'membership_id' => 'NMC-54321',
            'surname' => 'IBRAHIM',
            'first_name' => 'Fatima',
            'other_names' => 'Zainab',
            'rank' => NisRank::DCI->value,
            'command' => NisCommand::LAGOS_STATE_COMMAND->value,
        ]);

        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);

        // Found voter by service_number (returns membership_id too)
        $this->actingAsAdmin($ea)
            ->getJson(route('admin.candidates.lookup-voter', ['service_number' => '54321']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'voter' => [
                    'service_number' => '54321',
                    'membership_id' => 'NMC-54321',
                    'surname' => 'IBRAHIM',
                    'first_name' => 'Fatima',
                    'other_names' => 'Zainab',
                    'rank' => NisRank::DCI->value,
                    'command' => NisCommand::LAGOS_STATE_COMMAND->value,
                ],
            ]);

        // Found voter by membership_id (returns service_number and all officer details)
        $this->actingAsAdmin($ea)
            ->getJson(route('admin.candidates.lookup-voter', ['membership_id' => 'NMC-54321']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'voter' => [
                    'service_number' => '54321',
                    'membership_id' => 'NMC-54321',
                    'surname' => 'IBRAHIM',
                    'first_name' => 'Fatima',
                    'other_names' => 'Zainab',
                    'rank' => NisRank::DCI->value,
                    'command' => NisCommand::LAGOS_STATE_COMMAND->value,
                ],
            ]);

        // Voter not found
        $this->actingAsAdmin($ea)
            ->getJson(route('admin.candidates.lookup-voter', ['service_number' => '99999']))
            ->assertOk()
            ->assertJson([
                'found' => false,
            ]);

        // Missing both identifiers
        $this->actingAsAdmin($ea)
            ->getJson(route('admin.candidates.lookup-voter'))
            ->assertStatus(400);

        // Admin without manage_candidates permission is forbidden
        $ro = $this->admin(Role::RETURNING_OFFICER);
        $this->actingAsAdmin($ro)
            ->getJson(route('admin.candidates.lookup-voter', ['service_number' => '54321']))
            ->assertForbidden();

        // Guest is unauthorized
        $this->app['auth']->forgetGuards();
        $this->getJson(route('admin.candidates.lookup-voter', ['service_number' => '54321']))
            ->assertUnauthorized();
    }

    public function test_candidate_can_be_created_with_membership_id(): void
    {
        $election = $this->openElection(1, status: ElectionStatus::DRAFT);
        $ep = $election->electionPositions()->first();

        $this->actingAsAdmin($this->admin(Role::ELECTION_ADMINISTRATOR))->post(route('admin.candidates.store', $election), [
            'election_position_id' => $ep->id,
            'service_number' => '33445',
            'membership_id' => 'NMC-33445',
            'surname' => 'MOHAMMED',
            'first_name' => 'Ali',
            'rank' => 'CSI',
        ])->assertSessionHasNoErrors();

        $candidate = Candidate::query()->where('membership_id', 'NMC-33445')->firstOrFail();
        $this->assertSame('33445', $candidate->service_number);
        $this->assertSame('MOHAMMED', $candidate->surname);
    }
}
