<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

/**
 * The demo/sample data must never read as visibly fictitious to a real user:
 * no "Demo", "DEMO" or "(fictitious)" wording in anything a voter or an
 * administrator sees on screen. is_test_data flags the record instead.
 */
class SeededDataWordingTest extends TestCase
{
    public function test_seeded_voters_admins_and_candidates_contain_no_demo_wording(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(0, Voter::query()->where('formation', 'ilike', '%demo%')->count());
        $this->assertSame(0, Voter::query()->where('command', 'ilike', '%demo%')->count());
        $this->assertSame(0, User::query()->where('name', 'ilike', '%demo%')->count());
        $this->assertSame(0, Candidate::query()->where('biography', 'ilike', '%demo%')->count());
        $this->assertSame(0, Candidate::query()->where('biography', 'ilike', '%fictitious%')->count());
    }

    public function test_footer_is_present_and_correctly_worded_on_every_page_type(): void
    {
        $expected = 'Nigeria Immigration Service. All rights reserved.';
        $developer = 'Developed by ICT/Cybersecurity Directorate';

        $this->get('/')->assertOk()->assertSee($expected)->assertSee($developer);
        $this->get('/vote')->assertOk()->assertSee($expected)->assertSee($developer);
        $this->get(route('admin.login'))->assertOk()->assertSee($expected)->assertSee($developer);
    }
}
