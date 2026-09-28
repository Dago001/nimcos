<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EligibilityStatus;
use App\Jobs\SendVoterOtp;
use App\Models\AuditLog;
use App\Models\OtpVerification;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Voting\Exceptions\OtpException;
use App\Services\Voting\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VoterAuthenticationTest extends TestCase
{
    private function requestOtp(string $serviceNumber)
    {
        return $this->post(route('voter.access'), ['service_number' => $serviceNumber]);
    }

    /** Issue an OTP directly so the test knows the plain code. */
    private function issueKnownOtp(Voter $voter): array
    {
        $issued = app(OtpService::class)->issue($voter, request());
        $this->withSession(['voter.pending_otp' => [
            'otp_id' => $issued['otp']->id,
            'voter_id' => $voter->id,
            'destination' => $issued['otp']->destination_masked,
        ]]);

        return $issued;
    }

    public function test_valid_service_number_of_eligible_voter_receives_an_otp(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create(['service_number' => '1001']);
        $this->openElection(1, [$voter]);

        $this->requestOtp(' 1001 ')->assertRedirect(route('voter.otp'));

        $this->assertSame(1, OtpVerification::query()->where('voter_id', $voter->id)->count());
        Queue::assertPushed(SendVoterOtp::class, fn (SendVoterOtp $job) => $job->destination === $voter->email && $job->channel === 'EMAIL');
    }

    public function test_unknown_service_number_is_refused_with_a_generic_message(): void
    {
        Queue::fake();
        $this->openElection(1, [Voter::factory()->create()]);

        $this->requestOtp('99999')
            ->assertRedirect()
            ->assertSessionHasErrors('service_number');

        $this->assertSame(0, OtpVerification::query()->count());
        Queue::assertNothingPushed();
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::VOTER_LOOKUP_FAILED)->exists());
    }

    public function test_registered_but_ineligible_voter_gets_the_same_refusal_and_no_otp(): void
    {
        Queue::fake();
        Voter::factory()->create(['service_number' => '2001']);
        $suspended = Voter::factory()->create(['service_number' => '2002']);
        $election = $this->openElection(1, [Voter::factory()->create()]);
        $this->authorise($election, $suspended, EligibilityStatus::SUSPENDED);

        $messages = [];
        foreach (['2001', '2002', '0000'] as $sn) {
            $this->requestOtp($sn)->assertSessionHasErrors('service_number');
            $messages[] = session('errors')->first('service_number');
        }

        // Identical wording: the response does not reveal whether a number is registered.
        $this->assertCount(1, array_unique($messages));
        $this->assertSame(0, OtpVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_suspended_register_account_cannot_request_an_otp(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create(['service_number' => '3001', 'account_status' => AccountStatus::SUSPENDED]);
        $this->openElection(1, [$voter]);

        $this->requestOtp('3001')->assertSessionHasErrors('service_number');
        Queue::assertNothingPushed();
    }

    public function test_no_otp_is_sent_when_no_election_is_open(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create(['service_number' => '4001']);
        $election = $this->openElection(1, [$voter]);
        $election->forceFill(['status' => 'CLOSED'])->save();

        $this->requestOtp('4001')->assertSessionHasErrors('service_number');
        Queue::assertNothingPushed();
    }

    public function test_correct_otp_signs_the_voter_in_and_regenerates_the_session(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $issued = $this->issueKnownOtp($voter);
        $before = session()->getId();

        $this->post(route('voter.otp.verify'), ['code' => $issued['code']])->assertRedirect(route('voter.elections'));

        $this->assertAuthenticatedAs($voter, 'voter');
        $this->assertNotSame($before, session()->getId(), 'Session id must change on sign-in (fixation defence).');
        $this->assertNotNull($issued['otp']->fresh()->consumed_at);
    }

    public function test_otp_cannot_be_reused(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $issued = $this->issueKnownOtp($voter);

        $this->post(route('voter.otp.verify'), ['code' => $issued['code']]);
        auth('voter')->logout();
        $this->withSession(['voter.pending_otp' => ['otp_id' => $issued['otp']->id, 'voter_id' => $voter->id, 'destination' => 'x']]);

        $this->post(route('voter.otp.verify'), ['code' => $issued['code']])->assertSessionHasErrors('code');
        $this->assertGuest('voter');
    }

    public function test_invalid_otp_is_rejected(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $issued = $this->issueKnownOtp($voter);
        $wrong = $issued['code'] === '000000' ? '111111' : '000000';

        $this->post(route('voter.otp.verify'), ['code' => $wrong])->assertSessionHasErrors('code');

        $this->assertGuest('voter');
        $this->assertSame(1, $issued['otp']->fresh()->attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $issued = $this->issueKnownOtp($voter);

        $this->travel(config('nimcos.otp.ttl_minutes') + 1)->minutes();

        $this->post(route('voter.otp.verify'), ['code' => $issued['code']])
            ->assertSessionHasErrors(['code' => 'This code has expired. Request a new code.']);
        $this->assertGuest('voter');
    }

    public function test_otp_is_invalidated_after_maximum_failed_attempts_even_if_later_correct(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $issued = $this->issueKnownOtp($voter);
        $wrong = $issued['code'] === '000000' ? '111111' : '000000';

        for ($i = 0; $i < config('nimcos.otp.max_attempts'); $i++) {
            $this->post(route('voter.otp.verify'), ['code' => $wrong]);
        }
        $this->post(route('voter.otp.verify'), ['code' => $issued['code']])->assertSessionHasErrors('code');

        $this->assertGuest('voter');
        $this->assertNotNull($issued['otp']->fresh()->invalidated_at);
    }

    public function test_otp_is_stored_only_as_a_keyed_hash(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $issued = $this->issueKnownOtp($voter);

        $row = (array) DB::table('otp_verifications')->where('id', $issued['otp']->id)->first();
        foreach ($row as $value) {
            $this->assertNotSame($issued['code'], (string) $value);
        }
        $this->assertSame(OtpService::hashCode($issued['otp']->id, $issued['code']), $row['code_hash']);
        $this->assertStringNotContainsString($issued['code'], (string) AuditLog::query()->pluck('metadata')->toJson());
    }

    public function test_issuing_a_new_otp_invalidates_the_previous_one(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $first = app(OtpService::class)->issue($voter, request());

        $this->travel(61)->seconds();
        app(OtpService::class)->issue($voter, request());

        $this->assertNotNull($first['otp']->fresh()->invalidated_at);
    }

    public function test_otp_resend_is_subject_to_cooldown_and_request_limits(): void
    {
        Queue::fake();
        $voter = Voter::factory()->create();
        $this->openElection(1, [$voter]);
        $service = app(OtpService::class);

        $service->issue($voter, request());
        try {
            $service->issue($voter, request());
            $this->fail('Immediate resend should be refused.');
        } catch (OtpException $e) {
            $this->assertSame('cooldown', $e->reason);
        }

        $this->travel(61)->seconds();
        $service->issue($voter, request());
        $this->travel(61)->seconds();
        $service->issue($voter, request());
        $this->travel(61)->seconds();
        $this->expectException(OtpException::class);
        $service->issue($voter, request()); // 4th within the window
    }

    public function test_service_number_entry_is_rate_limited(): void
    {
        Queue::fake();
        $this->openElection(1, [Voter::factory()->create()]);

        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->requestOtp((string) (90000 + $i))->status();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_repeated_unknown_lookups_from_one_address_raise_a_security_alert(): void
    {
        Queue::fake();
        config(['nimcos.alerts.failed_lookups_per_ip' => 3]);
        $this->openElection(1, [Voter::factory()->create()]);

        foreach (['99991', '99992', '99993'] as $sn) {
            $this->requestOtp($sn);
        }

        $this->assertDatabaseHas('security_alerts', ['type' => 'REPEATED_SERVICE_NUMBER_LOOKUPS', 'status' => 'OPEN']);
    }
}
