<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Voter;
use App\Services\Settings\SettingsService;
use Database\Factories\UserFactory;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    public function test_public_pages_accessible_when_maintenance_mode_is_off(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('system_under_maintenance', false);

        $response = $this->get('/');
        $response->assertOk();
    }

    public function test_public_pages_return_503_when_maintenance_mode_is_on(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('system_under_maintenance', true);
        $settings->set('maintenance_message', 'Scheduled server upgrade in progress.');

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertSee('System Under Maintenance');
        $response->assertSee('Scheduled server upgrade in progress.');
        $response->assertDontSee('Administrator Sign In');

        // Voter endpoints also blocked
        $this->get('/vote')->assertStatus(503);
        $this->get('/results')->assertStatus(503);
    }

    public function test_json_requests_receive_503_json_in_maintenance_mode(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('system_under_maintenance', true);

        $response = $this->getJson('/');
        $response->assertStatus(503);
        $response->assertJsonStructure(['message']);
    }

    public function test_admin_routes_remain_accessible_in_maintenance_mode(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('system_under_maintenance', true);

        // Admin login page is accessible
        $this->get('/admin/login')->assertOk();

        // Admin dashboard is accessible to signed-in administrators
        $superAdmin = $this->admin(Role::SUPER_ADMIN);
        $this->actingAsAdmin($superAdmin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAsAdmin($superAdmin)->get(route('admin.settings.edit'))->assertOk();
    }

    public function test_super_admin_can_toggle_maintenance_mode_via_settings(): void
    {
        $superAdmin = $this->admin(Role::SUPER_ADMIN);

        // Enable maintenance mode
        $response = $this->actingAsAdmin($superAdmin)->put('/admin/settings', [
            'system_under_maintenance' => '1',
            'maintenance_message' => 'Platform maintenance ongoing',
            'otp_ttl_minutes' => 5,
            'otp_max_attempts' => 3,
            'voting_session_minutes' => 10,
            'confirm_password' => UserFactory::PASSWORD,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertTrue((bool) app(SettingsService::class)->get('system_under_maintenance'));

        // Public unauthenticated visitor is now blocked by maintenance mode
        auth('web')->logout();
        $this->flushSession();
        $this->get('/')->assertStatus(503);

        // Disable maintenance mode
        $response = $this->actingAsAdmin($superAdmin)->put('/admin/settings', [
            'system_under_maintenance' => '0',
            'maintenance_message' => '',
            'otp_ttl_minutes' => 5,
            'otp_max_attempts' => 3,
            'voting_session_minutes' => 10,
            'confirm_password' => UserFactory::PASSWORD,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertFalse((bool) app(SettingsService::class)->get('system_under_maintenance'));

        // Public site is now accessible again
        $this->get('/')->assertOk();
    }

    public function test_voter_session_is_blocked_in_maintenance_mode(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('system_under_maintenance', true);

        $voter = Voter::factory()->create();

        $response = $this->actingAs($voter, 'voter')->get('/elections');
        $response->assertStatus(503);
    }
}
