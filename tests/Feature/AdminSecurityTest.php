<?php

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Http\Controllers\Admin\AuthController;
use App\Models\Role;
use App\Models\User;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Auth\Totp;
use Database\Factories\UserFactory;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    public function test_guests_are_redirected_to_admin_login(): void
    {
        foreach (['/admin', '/admin/voters', '/admin/elections', '/admin/audit', '/admin/users'] as $uri) {
            $this->get($uri)->assertRedirect(route('admin.login'));
        }
        $this->getJson('/admin/dashboard/stats')->assertUnauthorized();
    }

    public function test_voter_session_grants_no_admin_access(): void
    {
        $this->actingAsVoter(Voter::factory()->create())->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_role_restrictions_are_enforced_by_permission(): void
    {
        $matrix = [
            Role::AUDITOR => ['/admin/audit' => 200, '/admin/voters' => 403, '/admin/imports/create' => 403, '/admin/users' => 403, '/admin/settings' => 403, '/admin/positions' => 403],
            Role::RETURNING_OFFICER => ['/admin/audit' => 200, '/admin/voters' => 403, '/admin/users' => 403, '/admin/elections/create' => 403],
            Role::ELECTION_ADMINISTRATOR => ['/admin/voters' => 200, '/admin/elections/create' => 200, '/admin/audit' => 403, '/admin/users' => 403, '/admin/settings' => 403],
            Role::SUPER_ADMIN => ['/admin/users' => 200, '/admin/settings' => 200, '/admin/roles' => 200, '/admin/audit' => 200],
        ];

        foreach ($matrix as $role => $pages) {
            $user = $this->admin($role);
            foreach ($pages as $uri => $status) {
                $this->actingAsAdmin($user)->get($uri)->assertStatus($status);
            }
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.access_denied', 'result' => 'DENIED']);
    }

    public function test_election_administrator_cannot_publish_results_or_manage_admins(): void
    {
        $ea = $this->admin(Role::ELECTION_ADMINISTRATOR);
        $election = $this->openElection(1);
        $election->forceFill(['status' => ElectionStatus::CLOSED])->save();

        $this->actingAsAdmin($ea)->post(route('admin.results.publish', $election), ['confirm_password' => 'x'])->assertForbidden();
        $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->assertDatabaseHas('security_alerts', ['type' => 'UNAUTHORIZED_ADMIN_ACTION']);
    }

    public function test_csrf_token_is_required_for_state_changing_requests(): void
    {
        $this->app['env'] = 'local'; // the CSRF middleware is bypassed only in the testing environment
        $this->withoutExceptionHandling();

        $this->expectException(TokenMismatchException::class);
        $this->post(route('admin.login.attempt'), ['email' => 'a@b.c', 'password' => 'x']);
    }

    public function test_account_locks_after_repeated_failed_logins(): void
    {
        $user = User::factory()->create(['email' => 'lock@nimcos.test']);
        for ($i = 0; $i < config('nimcos.admin.max_failed_logins'); $i++) {
            $this->post(route('admin.login.attempt'), ['email' => 'lock@nimcos.test', 'password' => 'wrong-password']);
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->travel(2)->minutes(); // past the per-minute rate limit, still inside the 15-minute lockout
        $this->post(route('admin.login.attempt'), ['email' => 'lock@nimcos.test', 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertDatabaseHas('security_alerts', ['type' => 'ADMIN_ACCOUNT_LOCKED']);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->post(route('admin.login.attempt'), ['email' => "user{$i}@nimcos.test", 'password' => 'x'])->status();
        }
        $this->assertContains(429, $statuses);
    }

    public function test_successful_login_regenerates_the_session(): void
    {
        $user = $this->admin(Role::AUDITOR);
        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($before, session()->getId());
    }

    public function test_mfa_is_required_after_password_when_enabled(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $user = $this->admin(Role::AUDITOR);
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect(route('admin.mfa.challenge'));
        $this->get('/admin')->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.verify'), ['mfa_code' => '000000'])->assertSessionHasErrors('mfa_code');
        $this->post(route('admin.mfa.verify'), ['mfa_code' => $totp->code($secret)])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk();
    }

    public function test_mfa_enrolment_is_forced_when_policy_requires_it(): void
    {
        config(['nimcos.admin.require_mfa' => true]);
        $this->actingAsAdmin($this->admin(Role::AUDITOR))->get('/admin')->assertRedirect(route('admin.profile.mfa'));
    }

    public function test_privileged_actions_require_reauthentication(): void
    {
        $ro = $this->admin(Role::RETURNING_OFFICER);
        $election = $this->openElection(1, [Voter::factory()->create()]);

        $this->actingAsAdmin($ro)->post(route('admin.elections.close', $election), ['confirm_password' => 'not-my-password'])
            ->assertSessionHasErrors('confirm_password');
        $this->assertSame(ElectionStatus::OPEN, $election->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.reauth_failed']);

        $this->post(route('admin.elections.close', $election), ['confirm_password' => UserFactory::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertSame(ElectionStatus::CLOSED, $election->fresh()->status);
    }

    public function test_malformed_identifiers_in_urls_are_not_found_not_errors(): void
    {
        $this->actingAsAdmin($this->admin(Role::SUPER_ADMIN));
        $this->get('/admin/voters/35562')->assertNotFound();
        $this->get('/admin/elections/1%27%20OR%201=1')->assertNotFound();
        $this->get('/admin/voters/'.Str::uuid())->assertNotFound();
    }

    public function test_voter_urls_carry_no_identifiers(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'voter.')) {
                $this->assertDoesNotMatchRegularExpression('/\{(voter|id|voter_id|session|ballot)\}/', $route->uri());
            }
        }
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get(route('voter.entry'));
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_error_pages_do_not_leak_internals(): void
    {
        $this->actingAsAdmin($this->admin(Role::SUPER_ADMIN));
        Route::get('/__boom', fn () => DB::select('SELECT * FROM no_such_table'))->middleware('web');

        $response = $this->get('/__boom')->assertStatus(500);
        $response->assertSee('We could not complete your request');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('no_such_table');
    }

    public function test_admin_cannot_remove_the_last_administrator_manager(): void
    {
        $sa = $this->admin(Role::SUPER_ADMIN);
        $auditorRole = Role::query()->where('name', Role::AUDITOR)->first();

        $this->actingAsAdmin($sa)->put(route('admin.users.update', $sa), [
            'name' => $sa->name, 'status' => 'ACTIVE', 'roles' => [$auditorRole->id],
            'confirm_password' => UserFactory::PASSWORD,
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($sa->fresh()->hasPermission('manage_admins'));
    }

    public function test_first_time_login_shows_qr_code_and_enrols_google_authenticator(): void
    {
        config(['nimcos.admin.require_mfa' => true]);
        $user = $this->admin(Role::AUDITOR);
        $this->assertFalse($user->hasMfa());

        // 1. First time: user inputs email & password
        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => UserFactory::PASSWORD,
        ])->assertRedirect(route('admin.mfa.challenge'));

        // 2. User sees the QR code section to scan into Google Authenticator
        $challenge = $this->get(route('admin.mfa.challenge'))->assertOk();
        $challenge->assertSee('Set up Google Authenticator');
        $challenge->assertSee('data:image/svg+xml;base64');
        $challenge->assertSee('Enter 6-digit code from Google Authenticator');

        // 3. User submits incorrect code
        $this->post(route('admin.mfa.verify'), ['mfa_code' => '000000'])
            ->assertSessionHasErrors('mfa_code');

        // 4. User inputs the correct code generated by Google Authenticator
        $pendingSecret = session(AuthController::PENDING_SECRET);
        $this->assertNotEmpty($pendingSecret);
        $totp = new Totp;
        $validCode = $totp->code($pendingSecret);

        $this->post(route('admin.mfa.verify'), ['mfa_code' => $validCode])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue($user->fresh()->hasMfa());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ADMIN_MFA_ENABLED,
            'entity_id' => $user->id,
            'result' => 'SUCCESS',
        ]);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_next_time_login_shows_code_input_only(): void
    {
        config(['nimcos.admin.require_mfa' => true]);
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $user = $this->admin(Role::ELECTION_ADMINISTRATOR);
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        $this->assertTrue($user->hasMfa());

        // 1. Next time: user inputs email & password
        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => UserFactory::PASSWORD,
        ])->assertRedirect(route('admin.mfa.challenge'));

        // 2. User sees the section to input code (no QR code)
        $challenge = $this->get(route('admin.mfa.challenge'))->assertOk();
        $challenge->assertSee('Two-step verification');
        $challenge->assertSee('Enter the 6-digit code generated from Google Authenticator');
        $challenge->assertDontSee('data:image/svg+xml;base64');

        // 3. Input code and sign in successfully
        $this->post(route('admin.mfa.verify'), ['mfa_code' => $totp->code($secret)])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }
}
