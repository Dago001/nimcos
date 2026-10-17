<?php

namespace Tests\Feature;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Models\Role;
use Tests\TestCase;

class PublicCandidatesTest extends TestCase
{
    public function test_candidates_page_is_accessible_and_linked_from_homepage(): void
    {
        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee(route('public.candidates'));
        $response->assertSee('Candidates');

        $candidatesResponse = $this->get(route('public.candidates'));
        $candidatesResponse->assertOk();
        $candidatesResponse->assertSee('Candidates');
        $candidatesResponse->assertSee('Official Candidates Directory');
    }

    public function test_candicates_spelling_redirects_to_candidates(): void
    {
        $response = $this->get('/candicates');
        $response->assertRedirect('/candidates');
    }

    public function test_screened_candidates_are_displayed_with_position_rank_command_and_bio(): void
    {
        $election = $this->openElection(1, status: ElectionStatus::SCHEDULED);
        $candidate = $election->candidates()->first();

        $candidate->update([
            'surname' => 'Babatunde',
            'first_name' => 'Adewale',
            'other_names' => 'O.',
            'rank' => NisRank::CIS->value,
            'command' => NisCommand::SERVICE_HEADQUARTERS_ABUJA->value,
            'biography' => 'Experienced immigration officer with over 20 years of meritorious service.',
        ]);
        $candidate->forceFill(['status' => CandidateStatus::ACTIVE])->save();

        $response = $this->get(route('public.candidates'));
        $response->assertOk();
        $response->assertSee('Adewale O. Babatunde');
        $response->assertSee($candidate->electionPosition->position->name);
        $response->assertSee(NisRank::CIS->label());
        $response->assertSee(NisCommand::SERVICE_HEADQUARTERS_ABUJA->label());
        $response->assertSee('Experienced immigration officer with over 20 years of meritorious service.');
        $response->assertSee('Screened Contestant');
    }

    public function test_inactive_and_disqualified_candidates_are_excluded(): void
    {
        $election = $this->openElection(1, status: ElectionStatus::SCHEDULED);
        $candidates = $election->candidates()->get();

        $active = $candidates[0];
        $active->forceFill(['first_name' => 'ActiveCandidate', 'status' => CandidateStatus::ACTIVE])->save();

        $disqualified = $candidates[1];
        $disqualified->forceFill(['first_name' => 'DisqualifiedCandidate', 'status' => CandidateStatus::DISQUALIFIED])->save();

        $withdrawn = $candidates[2];
        $withdrawn->forceFill(['first_name' => 'WithdrawnCandidate', 'status' => CandidateStatus::WITHDRAWN])->save();

        $response = $this->get(route('public.candidates'));
        $response->assertOk();
        $response->assertSee('ActiveCandidate');
        $response->assertDontSee('DisqualifiedCandidate');
        $response->assertDontSee('WithdrawnCandidate');
    }

    public function test_draft_election_without_screened_candidates_is_hidden_from_public_but_visible_to_admin(): void
    {
        $draftElection = $this->openElection(1, status: ElectionStatus::DRAFT);
        $draftElection->candidates()->update(['status' => CandidateStatus::INACTIVE->value]);

        // Public visitor should not see candidates from unfinalized draft election
        $publicResponse = $this->get(route('public.candidates'));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Candidate Screening &amp; Vetting Underway', false);

        // Signed-in administrator can view the draft election directory
        $admin = $this->admin(Role::SUPER_ADMIN);
        $adminResponse = $this->actingAsAdmin($admin)->get(route('public.candidates'));
        $adminResponse->assertOk();
        $adminResponse->assertSee($draftElection->name);
    }

    public function test_draft_election_with_screened_candidates_is_visible_to_public(): void
    {
        $draftElection = $this->openElection(1, status: ElectionStatus::DRAFT);
        $candidate = $draftElection->candidates()->first();
        $candidate->update(['first_name' => 'ScreenedDraftCandidate']);
        $candidate->forceFill(['status' => CandidateStatus::ACTIVE])->save();

        $publicResponse = $this->get(route('public.candidates'));
        $publicResponse->assertOk();
        $publicResponse->assertSee('ScreenedDraftCandidate');
    }
}
