<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoterImport;
use App\Services\Reports\ReportRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoterImportTest extends TestCase
{
    private User $ea;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->ea = $this->admin(Role::ELECTION_ADMINISTRATOR);
    }

    private function csv(array $rows): UploadedFile
    {
        $lines = ['SERVICE NUMBER,SURNAME,FIRST NAME,OTHER NAMES,RANK,COMMAND,FORMATION,PHONE NUMBER,EMAIL,MEMBERSHIP STATUS'];
        foreach ($rows as $r) {
            $lines[] = implode(',', $r);
        }

        return UploadedFile::fake()->createWithContent('register.csv', implode("\n", $lines)."\n");
    }

    private function upload(UploadedFile $file): VoterImport
    {
        $this->actingAsAdmin($this->ea)->post(route('admin.imports.store'), ['file' => $file, 'update_existing' => '1'])->assertRedirect();

        return VoterImport::query()->latest()->firstOrFail();
    }

    public function test_preview_validates_and_reports_without_changing_the_register(): void
    {
        $import = $this->upload($this->csv([
            ['1001', 'ADEYEMI', 'Tunde', '', 'ASI1', 'LAGOS STATE COMMAND', 'MMIA', '08031234567', 'a@example.com', 'ACTIVE'],
            [' 1002 ', 'bello', 'aisha', '', 'DSI', 'FCT COMMAND', 'HQ', '+234 803 123 4568', 'B@Example.com', ''],
            ['1001', 'ADEYEMI', 'Tunde', '', 'ASI1', 'LAGOS STATE COMMAND', 'MMIA', '08031234567', 'a@example.com', 'ACTIVE'],
            ['1003', '', 'Musa', '', '', '', '', '08031234569', 'c@example.com', 'ACTIVE'],
            ['1004', 'EZE', 'Ngozi', '', '', '', '', '12345', 'd@example.com', 'ACTIVE'],
            ['1005', 'OKAFOR', 'Emeka', '', '', '', '', '', 'a@example.com', 'ACTIVE'],
            ['1006', 'SANI', 'Umar', '', '', '', '', '08031234570', 'not-an-email', 'ACTIVE'],
            ['NIS/1007', 'GARBA', 'Hauwa', '', '', '', '', '', 'g@example.com', 'ACTIVE'],
            ['1008', 'JIMOH', 'Segun', '', '', '', '', '08031234571', '', 'ACTIVE'],
        ]));

        $this->assertSame(ImportStatus::PREVIEWED, $import->status);
        $this->assertSame(9, $import->total_rows);
        $this->assertSame(2, $import->valid_rows);
        $this->assertSame(2, $import->imported_count);
        $this->assertSame(1, $import->duplicate_count);
        $this->assertSame(6, $import->invalid_count);
        $this->assertSame(7, $import->rejected_count);
        $this->assertSame(0, Voter::query()->count(), 'Nothing is imported before confirmation.');

        $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Rejected rows');
        $report = $this->get(route('admin.imports.errors', $import))->assertOk()->streamedContent();
        $this->assertStringContainsString('DUPLICATE', $report);
        $this->assertStringContainsString('not a valid Nigerian mobile number', $report);
        $this->assertStringContainsString('Email is already used by Service Number 1001', $report);
        $this->assertStringContainsString('NIS/1007', $report);
        $this->assertStringContainsString('Email is required', $report);
    }

    public function test_confirmed_import_creates_and_updates_voters(): void
    {
        $existing = Voter::factory()->unverified()->create(['service_number' => '2001', 'surname' => 'OLDNAME', 'phone' => '+2348031111111', 'email' => 'kemi@example.com']);

        $import = $this->upload($this->csv([
            ['2001', 'NEWNAME', 'Kemi', '', 'CSI', 'KANO STATE COMMAND', '', '08031111111', 'kemi@example.com', 'ACTIVE'],
            ['2002', 'LAWAL', 'Femi', '', 'ASI1', 'KANO STATE COMMAND', '', '08032222222', 'femi@example.com', 'ACTIVE'],
        ]));
        $this->assertSame(1, $import->imported_count);
        $this->assertSame(1, $import->updated_count);

        $this->post(route('admin.imports.confirm', $import), ['acknowledge' => '1'])->assertRedirect();

        $import->refresh();
        $this->assertSame(ImportStatus::COMPLETED, $import->status);
        $this->assertSame('NEWNAME', Voter::query()->where('service_number', '2001')->value('surname'));
        $new = Voter::query()->where('service_number', '2002')->firstOrFail();
        $this->assertSame('+2348032222222', $new->phone);
        $this->assertSame('femi@example.com', $new->email);
        // A voter created by an approved import is verified on arrival (the register
        // file is the authorised source); this does not apply to hand-added voters.
        $this->assertSame('VERIFIED', $new->verification_status->value);
        $this->assertNotNull($new->verified_at);
        // Updating an existing record does not change their verification: import
        // auto-verify only applies to brand-new rows, never re-verifies on update.
        $this->assertSame('UNVERIFIED', $existing->fresh()->verification_status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voter_import.completed']);
    }

    public function test_import_cannot_be_confirmed_twice(): void
    {
        $import = $this->upload($this->csv([['3001', 'KALU', 'Obinna', '', '', '', '', '08033333333', 'obinna@example.com', 'ACTIVE']]));
        $this->post(route('admin.imports.confirm', $import), ['acknowledge' => '1']);
        $this->post(route('admin.imports.confirm', $import), ['acknowledge' => '1'])->assertSessionHasErrors('import');

        $this->assertSame(1, Voter::query()->count());
    }

    public function test_import_page_warns_when_server_php_limit_is_below_the_app_configured_max(): void
    {
        // The app's own limit set far above whatever this box's real php.ini allows,
        // so the page must show the mismatch instead of a silent later failure.
        config(['nimcos.uploads.import_max_kb' => 999 * 1024]);

        $this->actingAsAdmin($this->ea)->get(route('admin.imports.create'))
            ->assertOk()
            ->assertSee("This server's PHP configuration only allows uploads up to", false);
    }

    public function test_import_page_shows_no_warning_when_app_limit_is_within_the_server_php_limit(): void
    {
        config(['nimcos.uploads.import_max_kb' => 1]); // 1 KB: certainly not above any real server limit

        $this->actingAsAdmin($this->ea)->get(route('admin.imports.create'))
            ->assertOk()
            ->assertDontSee("This server's PHP configuration only allows uploads up to", false);
    }

    public function test_file_missing_required_columns_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('bad.csv', "NAME,PHONE\nX,080\n");

        $this->actingAsAdmin($this->ea)->post(route('admin.imports.store'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertSame(0, VoterImport::query()->count());
    }

    public function test_non_spreadsheet_files_are_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;');

        $this->actingAsAdmin($this->ea)->post(route('admin.imports.store'), ['file' => $file])->assertSessionHasErrors('file');
    }

    public function test_duplicate_service_number_is_rejected_on_manual_entry(): void
    {
        Voter::factory()->create(['service_number' => '4001']);

        $this->actingAsAdmin($this->ea)->post(route('admin.voters.store'), [
            'service_number' => ' 4001 ', 'surname' => 'X', 'first_name' => 'Y', 'email' => 'x@example.com', 'membership_status' => 'ACTIVE',
        ])->assertSessionHasErrors('service_number');
    }

    public function test_manual_entry_requires_a_4_or_5_digit_service_number_and_a_unique_email(): void
    {
        Voter::factory()->create(['service_number' => '5001', 'email' => 'taken@example.com']);
        $this->actingAsAdmin($this->ea);
        $base = ['surname' => 'EZE', 'first_name' => 'Ngozi', 'membership_status' => 'ACTIVE'];

        foreach (['NIS/5002', '123', '123456', 'AB123'] as $bad) {
            $this->post(route('admin.voters.store'), $base + ['service_number' => $bad, 'email' => 'new@example.com'])
                ->assertSessionHasErrors('service_number');
        }
        $this->post(route('admin.voters.store'), $base + ['service_number' => '5002'])->assertSessionHasErrors('email');
        $this->post(route('admin.voters.store'), $base + ['service_number' => '5002', 'email' => 'Taken@example.com'])->assertSessionHasErrors('email');

        $this->post(route('admin.voters.store'), $base + ['service_number' => '05002', 'email' => 'new@example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('voters', ['service_number' => '05002', 'email' => 'new@example.com', 'phone' => null]);
    }

    public function test_spreadsheet_formula_injection_is_neutralised_in_exports(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", ReportRenderer::safeCell('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", ReportRenderer::safeCell('@SUM(A1)'));
        $this->assertSame('-5', ReportRenderer::safeCell('-5'));
        $this->assertSame(42, ReportRenderer::safeCell(42));
    }
}
