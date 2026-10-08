<?php

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Enums\ImportStatus;
use App\Models\Election;
use App\Models\Role;
use App\Models\Voter;
use App\Models\VoterImport;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDeleteActionsTest extends TestCase
{
    public function test_admin_can_delete_election_with_associated_records(): void
    {
        $admin = $this->admin(Role::SUPER_ADMIN);
        $election = $this->openElection(1, [Voter::factory()->create()]);

        $this->assertDatabaseHas('elections', ['id' => $election->id]);
        $this->assertDatabaseHas('election_positions', ['election_id' => $election->id]);

        $response = $this->actingAsAdmin($admin)->delete(route('admin.elections.destroy', $election));
        $response->assertRedirect(route('admin.elections.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('elections', ['id' => $election->id]);
        $this->assertDatabaseMissing('election_positions', ['election_id' => $election->id]);
    }

    public function test_admin_can_delete_import_and_onboarded_voters(): void
    {
        Storage::fake('local');
        $admin = $this->admin(Role::SUPER_ADMIN);

        // Create an import file in storage
        $csvContent = "service_number,membership_id,surname,first_name,other_names,gender,dob,rank,command,formation,phone,email,membership_status\n";
        $csvContent .= "99001,NMC-99001,DELETE_ME,John,Test,MALE,1990-01-01,ACI,LAGOS COMMAND,MMIA,+2348011112222,del1@example.com,ACTIVE\n";
        $csvContent .= "99002,NMC-99002,DELETE_TOO,Jane,Test,FEMALE,1992-02-02,DSI,ABUJA HQ,HQ,+2348033334444,del2@example.com,ACTIVE\n";

        $storedPath = 'imports/test_import.csv';
        Storage::disk('local')->put($storedPath, $csvContent);

        // Pre-create the voters representing onboarded voters from this file
        $voter1 = Voter::factory()->create(['service_number' => '99001', 'created_by' => $admin->id]);
        $voter2 = Voter::factory()->create(['service_number' => '99002', 'created_by' => $admin->id]);

        $import = new VoterImport;
        $import->forceFill([
            'uploaded_by' => $admin->id,
            'original_filename' => 'test_import.csv',
            'stored_path' => $storedPath,
            'file_hash' => hash('sha256', $csvContent),
            'status' => ImportStatus::COMPLETED,
            'total_rows' => 2,
            'valid_rows' => 2,
            'imported_count' => 2,
            'updated_count' => 0,
            'rejected_count' => 0,
            'completed_at' => now(),
        ])->save();

        $this->assertDatabaseHas('voters', ['service_number' => '99001']);
        $this->assertDatabaseHas('voters', ['service_number' => '99002']);
        $this->assertDatabaseHas('voter_imports', ['id' => $import->id]);

        $response = $this->actingAsAdmin($admin)->delete(route('admin.imports.destroy', $import));
        $response->assertRedirect(route('admin.imports.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('voters', ['service_number' => '99001']);
        $this->assertDatabaseMissing('voters', ['service_number' => '99002']);
        $this->assertDatabaseMissing('voter_imports', ['id' => $import->id]);
        $this->assertFalse(Storage::disk('local')->exists($storedPath));
    }

    public function test_admin_can_delete_closed_election_with_results(): void
    {
        $admin = $this->admin(Role::SUPER_ADMIN);
        $election = $this->openElection(1, [Voter::factory()->create()]);

        // Close the election
        $election->forceFill([
            'status' => ElectionStatus::CLOSED,
            'closed_at' => now(),
        ])->save();

        $response = $this->actingAsAdmin($admin)->delete(route('admin.elections.destroy', $election));
        $response->assertRedirect(route('admin.elections.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('elections', ['id' => $election->id]);
    }

    public function test_admin_can_delete_cancelled_import(): void
    {
        $admin = $this->admin(Role::SUPER_ADMIN);
        $import = new VoterImport;
        $import->forceFill([
            'uploaded_by' => $admin->id,
            'original_filename' => 'cancelled.csv',
            'stored_path' => 'imports/cancelled.csv',
            'file_hash' => 'dummy',
            'status' => ImportStatus::CANCELLED,
            'total_rows' => 0,
            'valid_rows' => 0,
            'imported_count' => 0,
            'updated_count' => 0,
            'rejected_count' => 0,
        ])->save();

        $response = $this->actingAsAdmin($admin)->delete(route('admin.imports.destroy', $import));
        $response->assertRedirect(route('admin.imports.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('voter_imports', ['id' => $import->id]);
    }
}
