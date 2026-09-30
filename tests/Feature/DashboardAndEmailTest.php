<?php

namespace Tests\Feature;

use App\Enums\AlertSeverity;
use App\Mail\AdminCredentialsMail;
use App\Mail\SecurityAlertMail;
use App\Mail\VoterOtpMail;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voter;
use App\Services\Notifications\NotificationService;
use App\Services\Security\SecurityAlertService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DashboardAndEmailTest extends TestCase
{
    public function test_dashboard_shows_every_contestant_with_the_live_count(): void
    {
        $voters = Voter::factory()->count(3)->create();
        $election = $this->openElection(2, $voters->all());
        $election->forceFill(['interim_results_enabled' => true])->save();

        $ep = $election->electionPositions()->with('activeCandidates')->orderBy('display_order')->first();
        $second = $election->electionPositions()->with('activeCandidates')->orderBy('display_order')->skip(1)->first();
        $favourite = $ep->activeCandidates[1];
        foreach ($voters as $voter) {
            $this->castVote($election, $voter, [$ep->id => [$favourite->id], $second->id => [$second->activeCandidates[0]->id]]);
        }
        $this->app['auth']->forgetGuards();

        $response = $this->actingAsAdmin($this->admin(Role::RETURNING_OFFICER))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Live vote count');
        foreach (Candidate::query()->where('election_id', $election->id)->get() as $candidate) {
            $response->assertSee($candidate->displayName());
        }

        $json = $this->getJson(route('admin.dashboard.stats'))->assertOk()->json('tally');
        $this->assertSame(3, $json['ballots']);
        $this->assertSame(3, $json['candidates'][$favourite->id]['votes']);
        $this->assertTrue($json['candidates'][$favourite->id]['leading']);
        $this->assertSame(0, $json['candidates'][$ep->activeCandidates[0]->id]['votes']);
        $this->assertFalse($json['candidates'][$ep->activeCandidates[0]->id]['leading']);
        $this->assertSame(3, $json['totals'][$ep->id]);
    }

    public function test_live_count_is_hidden_when_switched_off_for_the_election(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $election->forceFill(['interim_results_enabled' => false])->save();

        $this->actingAsAdmin($this->admin(Role::RETURNING_OFFICER))->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('Live vote count');
        $this->assertNull($this->getJson(route('admin.dashboard.stats'))->json('tally'));
    }

    public function test_live_count_requires_the_view_results_permission(): void
    {
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $role = Role::query()->create(['name' => 'OBSERVER', 'label' => 'Observer', 'description' => 'Dashboard only']);
        $role->permissions()->sync(Permission::query()->where('name', 'view_dashboard')->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->actingAsAdmin($user)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Live vote count');
        $this->assertNull($this->getJson(route('admin.dashboard.stats'))->json('tally'));
    }

    public function test_new_elections_show_the_live_count_by_default(): void
    {
        $this->assertTrue((new Election)->interim_results_enabled);
    }

    public function test_voter_code_is_sent_by_email(): void
    {
        Mail::fake();
        app(NotificationService::class)->sendOtp('officer@example.com', '123456', 10);

        Mail::assertSent(VoterOtpMail::class, fn (VoterOtpMail $m) => $m->hasTo('officer@example.com') && $m->code === '123456');
    }

    public function test_voter_without_email_cannot_request_a_code(): void
    {
        $voter = Voter::factory()->create(['email' => null]);
        $this->openElection(1, [$voter]);

        $this->post(route('voter.access'), ['service_number' => $voter->service_number] + $this->humanCheckFields())->assertSessionHasErrors();
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_new_administrator_and_password_reset_are_emailed(): void
    {
        Mail::fake();
        $super = $this->admin(Role::SUPER_ADMIN);
        $this->actingAsAdmin($super);

        $this->post(route('admin.users.store'), [
            'name' => 'New Officer', 'email' => 'new.officer@example.com',
            'roles' => [Role::query()->where('name', Role::AUDITOR)->value('id')],
            'confirm_password' => 'Test-Password-123!',
        ])->assertSessionHasNoErrors();

        Mail::assertQueued(AdminCredentialsMail::class, fn (AdminCredentialsMail $m) => $m->hasTo('new.officer@example.com') && $m->isNewAccount);

        $created = User::query()->where('email', 'new.officer@example.com')->firstOrFail();
        $this->post(route('admin.users.reset-password', $created), ['confirm_password' => 'Test-Password-123!'])->assertSessionHasNoErrors();
        Mail::assertQueued(AdminCredentialsMail::class, fn (AdminCredentialsMail $m) => $m->hasTo('new.officer@example.com') && ! $m->isNewAccount);
    }

    public function test_high_severity_alerts_are_emailed_to_alert_reviewers_only(): void
    {
        Mail::fake();
        $auditor = $this->admin(Role::AUDITOR);
        $electionAdmin = $this->admin(Role::ELECTION_ADMINISTRATOR);
        $alerts = app(SecurityAlertService::class);

        $alerts->raise('TEST_LOW', AlertSeverity::LOW, 'Low severity');
        Mail::assertNothingQueued();

        $alerts->raise('TEST_HIGH', AlertSeverity::HIGH, 'High severity');
        Mail::assertQueued(SecurityAlertMail::class, fn ($m) => $m->hasTo($auditor->email));
        Mail::assertNotQueued(SecurityAlertMail::class, fn ($m) => $m->hasTo($electionAdmin->email));
    }
}
