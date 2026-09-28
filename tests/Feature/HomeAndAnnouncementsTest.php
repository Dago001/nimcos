<?php

namespace Tests\Feature;

use App\Enums\AnnouncementDisplay;
use App\Enums\AnnouncementLevel;
use App\Models\Announcement;
use App\Models\Role;
use App\Models\Voter;
use App\Services\Announcements\AnnouncementFeed;
use Tests\TestCase;

class HomeAndAnnouncementsTest extends TestCase
{
    private function post_(array $overrides = []): Announcement
    {
        $a = new Announcement(array_merge([
            'title' => 'Voting hours extended',
            'body' => 'Voting now closes at 18:00 (WAT).',
            'display' => AnnouncementDisplay::BOTH,
            'level' => AnnouncementLevel::INFO,
            'is_active' => true,
        ], $overrides));
        $a->save();
        app(AnnouncementFeed::class)->forget();

        return $a;
    }

    public function test_home_page_is_the_landing_page_and_links_to_sign_in(): void
    {
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);

        $this->get('/')->assertOk()
            ->assertSee('Steps to Vote')
            ->assertSee('Requirements')
            ->assertSee('Voting is open: '.$election->name, false)
            ->assertSee(route('voter.entry'));

        $this->get('/vote')->assertOk()->assertSee('name="service_number"', false);
    }

    public function test_super_admin_posts_an_announcement_that_voters_see_as_ticker_and_popup(): void
    {
        $this->actingAsAdmin($this->admin(Role::SUPER_ADMIN));

        $this->post(route('admin.announcements.store'), [
            'title' => 'Voting hours extended',
            'body' => 'Voting now closes at 18:00 (WAT).',
            'display' => 'BOTH',
            'level' => 'IMPORTANT',
            'link_url' => '/results',
            'link_label' => 'See results',
            'is_active' => '1',
            'confirm_password' => 'Test-Password-123!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.created']);
        $this->app['auth']->forgetGuards();

        foreach (['/', '/vote', '/results'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('data-ticker', false)
                ->assertSee('data-announcement-popup', false)
                ->assertSee('Voting hours extended');
        }
    }

    public function test_ticker_only_and_popup_only_are_respected(): void
    {
        $this->post_(['title' => 'Ticker only', 'display' => AnnouncementDisplay::TICKER]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-ticker', $html);
        $this->assertStringNotContainsString('data-announcement-popup', $html);

        Announcement::query()->delete();
        $this->post_(['title' => 'Popup only', 'display' => AnnouncementDisplay::POPUP]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-ticker', $html);
        $this->assertStringContainsString('data-announcement-popup', $html);
    }

    public function test_announcements_survive_a_real_cache_round_trip(): void
    {
        // The production cache store refuses to unserialize objects; the feed must cache plain data.
        config(['cache.default' => 'file']);
        $this->post_(['title' => 'Cached notice']);

        $this->get('/')->assertOk()->assertSee('Cached notice'); // fills the cache
        $this->get('/')->assertOk()->assertSee('Cached notice'); // served from the cache

        app(AnnouncementFeed::class)->forget();
    }

    public function test_switched_off_scheduled_and_expired_announcements_are_hidden(): void
    {
        $this->post_(['title' => 'Switched off', 'is_active' => false]);
        $this->post_(['title' => 'Not yet', 'starts_at' => now()->addHour()]);
        $this->post_(['title' => 'Already over', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

        $this->get('/')->assertOk()->assertDontSee('Switched off')->assertDontSee('Not yet')->assertDontSee('Already over');
    }

    public function test_announcement_text_is_escaped(): void
    {
        $this->post_(['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_no_announcements_are_shown_on_the_ballot(): void
    {
        $this->post_(['title' => 'Distracting notice']);
        $voter = Voter::factory()->create();
        $election = $this->openElection(1, [$voter]);
        $this->actingAsVoter($voter);
        $this->startBallot($election);

        $this->get(route('voter.ballot'))->assertOk()->assertDontSee('Distracting notice');
    }

    public function test_only_holders_of_the_permission_can_manage_announcements(): void
    {
        foreach ([Role::ELECTION_ADMINISTRATOR, Role::RETURNING_OFFICER, Role::AUDITOR] as $role) {
            $this->actingAsAdmin($this->admin($role))->get(route('admin.announcements.index'))->assertForbidden();
        }
        $this->actingAsAdmin($this->admin(Role::SUPER_ADMIN))->get(route('admin.announcements.index'))->assertOk();
    }

    public function test_links_must_be_https_or_on_this_site_and_password_is_required(): void
    {
        $this->actingAsAdmin($this->admin(Role::SUPER_ADMIN));
        $base = ['title' => 'T', 'body' => 'B', 'display' => 'TICKER', 'level' => 'INFO', 'link_label' => 'Open'];

        foreach (['javascript:alert(1)', 'http://example.com', 'data:text/html,x', '//evil.example', '/\evil.example'] as $bad) {
            $this->post(route('admin.announcements.store'), $base + ['link_url' => $bad, 'confirm_password' => 'Test-Password-123!'])
                ->assertSessionHasErrors('link_url');
        }
        $this->post(route('admin.announcements.store'), $base + ['confirm_password' => 'wrong-password'])->assertSessionHasErrors();
        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_switching_off_and_deleting_are_audited(): void
    {
        $a = $this->post_();
        $this->actingAsAdmin($this->admin(Role::SUPER_ADMIN));

        $this->post(route('admin.announcements.toggle', $a))->assertRedirect();
        $this->assertFalse($a->fresh()->is_active);

        $this->delete(route('admin.announcements.destroy', $a), ['confirm_password' => 'Test-Password-123!'])->assertRedirect();
        $this->assertModelMissing($a);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.deleted']);
    }
}
