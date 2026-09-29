<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\AnnouncementDisplay;
use App\Enums\AnnouncementLevel;
use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\EligibilityStatus;
use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\ResultStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VoterEligibility;
use App\Models\Announcement;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionVoter;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ============================ DEVELOPMENT / DEMO DATA ONLY ============================
 * Every record is fictitious and flagged is_test_data = true. Service Numbers are
 * in the reserved range 90001 to 90024 and emails end in ".test". This seeder refuses to run in production.
 * =====================================================================================
 */
class DemoSeeder extends Seeder
{
    public const ADMIN_PASSWORD = 'Demo-Only-2026!';

    private const COMMANDS = ['FCT COMMAND', 'LAGOS COMMAND', 'KANO COMMAND', 'RIVERS COMMAND', 'ENUGU COMMAND', 'SERVICE HEADQUARTERS'];

    private const RANKS = ['ASSISTANT INSPECTOR', 'INSPECTOR OF IMMIGRATION', 'ASSISTANT SUPERINTENDENT', 'DEPUTY SUPERINTENDENT', 'SUPERINTENDENT', 'CHIEF SUPERINTENDENT'];

    private const SURNAMES = ['ADEBAYO', 'OKONKWO', 'MUSA', 'EZE', 'BELLO', 'OKAFOR', 'IBRAHIM', 'OGUNDIPE', 'NWOSU', 'ABUBAKAR', 'ADEYEMI', 'CHUKWU', 'DANJUMA', 'EFFIONG', 'FASHOLA', 'GARBA', 'IHEANACHO', 'JIMOH', 'KALU', 'LAWAL', 'MOHAMMED', 'NNAMDI', 'OLATUNJI', 'SANI'];

    private const FIRST_NAMES = ['Aisha', 'Chinedu', 'Fatima', 'Emeka', 'Halima', 'Tunde', 'Ngozi', 'Yusuf', 'Adaeze', 'Babatunde', 'Zainab', 'Ifeanyi', 'Hauwa', 'Segun', 'Amaka', 'Umar', 'Kemi', 'Obinna', 'Hadiza', 'Femi', 'Chioma', 'Aliyu', 'Bisi', 'Kelechi'];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        DB::transaction(function () {
            $this->admins();
            $voters = $this->voters();
            $this->election($voters);
            $this->announcements();
        });

        $this->command?->warn('DEMO DATA loaded (fictitious). Admin password for all demo accounts: '.self::ADMIN_PASSWORD);
        $this->command?->table(['Role', 'Email'], [
            ['Super Admin', 'superadmin@nimcos.test'],
            ['Election Administrator', 'electionadmin@nimcos.test'],
            ['Returning Officer', 'returningofficer@nimcos.test'],
            ['Auditor', 'auditor@nimcos.test'],
        ]);
        $this->command?->info('Demo voters: Service Numbers 90001 to 90024 (the emailed code is also shown on screen in demo mode).');
    }

    private function admins(): void
    {
        $accounts = [
            'superadmin@nimcos.test' => ['Demo Super Admin', Role::SUPER_ADMIN],
            'electionadmin@nimcos.test' => ['Demo Election Administrator', Role::ELECTION_ADMINISTRATOR],
            'returningofficer@nimcos.test' => ['Demo Returning Officer', Role::RETURNING_OFFICER],
            'auditor@nimcos.test' => ['Demo Auditor', Role::AUDITOR],
        ];
        foreach ($accounts as $email => [$name, $roleName]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            if ($user->exists) {
                continue;
            }
            $user->name = $name;
            $user->password = self::ADMIN_PASSWORD;
            $user->forceFill(['status' => UserStatus::ACTIVE, 'is_test_data' => true, 'password_changed_at' => now()])->save();
            $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'), ['assigned_at' => now()]);
        }
    }

    /** @return list<Voter> */
    private function voters(): array
    {
        $voters = [];
        for ($i = 1; $i <= 24; $i++) {
            $sn = (string) (90000 + $i);
            $voter = Voter::query()->firstOrNew(['service_number' => $sn]);
            if (! $voter->exists) {
                $voter->fill([
                    'surname' => self::SURNAMES[$i - 1],
                    'first_name' => self::FIRST_NAMES[$i - 1],
                    'rank' => self::RANKS[$i % count(self::RANKS)],
                    'command' => self::COMMANDS[$i % count(self::COMMANDS)],
                    'formation' => 'DEMO FORMATION',
                    'phone' => sprintf('+2347000000%03d', $i),
                    'email' => sprintf('voter%02d@nimcos.test', $i),
                    'membership_status' => MembershipStatus::ACTIVE,
                ]);
                $voter->forceFill([
                    'eligibility_status' => $i === 24 ? VoterEligibility::INELIGIBLE : VoterEligibility::ELIGIBLE,
                    'verification_status' => $i === 23 ? VerificationStatus::UNVERIFIED : VerificationStatus::VERIFIED,
                    'account_status' => AccountStatus::ACTIVE,
                    'verified_at' => $i === 23 ? null : now(),
                    'is_test_data' => true,
                ])->save();
            }
            $voters[] = $voter;
        }

        return $voters;
    }

    private function announcements(): void
    {
        if (Announcement::query()->exists()) {
            return;
        }
        $author = User::query()->where('email', 'superadmin@nimcos.test')->value('id');

        foreach ([
            ['Voting is now open', "Voting for the NIMCOS 2026 Elective Congress is open until 17:00 today (WAT).\nSign in with your Service Number and the code sent to your registered email.", AnnouncementDisplay::BOTH, AnnouncementLevel::IMPORTANT, '/vote', 'Vote now'],
            ['Never share your code', 'NIMCOS officials will never ask for your verification code. Report anyone who does to the Electoral Committee.', AnnouncementDisplay::TICKER, AnnouncementLevel::INFO, null, null],
        ] as [$title, $body, $display, $level, $url, $label]) {
            $a = new Announcement(['title' => $title, 'body' => $body, 'display' => $display, 'level' => $level, 'link_url' => $url, 'link_label' => $label, 'is_active' => true]);
            $a->forceFill(['created_by' => $author, 'updated_by' => $author])->save();
        }
    }

    /** @param list<Voter> $voters */
    private function election(array $voters): void
    {
        if (Election::query()->where('code', 'NIMCOS-2026-EC')->exists()) {
            return;
        }

        $election = new Election;
        $election->fill([
            'name' => 'NIMCOS 2026 ELECTIVE CONGRESS',
            'code' => 'NIMCOS-2026-EC',
            'description' => 'Election of officers of the Nigeria Immigration Multi-Purpose Cooperative Society for the 2026 to 2028 tenure.',
            'election_type' => ElectionType::GENERAL,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHours(12),
            'auto_open' => true,
            'auto_close' => true,
            'interim_results_enabled' => true,
        ]);
        $election->forceFill([
            'status' => ElectionStatus::DRAFT,
            'result_status' => ResultStatus::NOT_CALCULATED,
            'receipt_year' => '2026',
            'is_test_data' => true,
        ])->save();

        $candidateNumber = 0;
        $nameIndex = 0;
        foreach (Position::query()->where('status', RecordStatus::ACTIVE->value)->orderBy('display_order')->get() as $position) {
            $ep = new ElectionPosition;
            $ep->election()->associate($election);
            $ep->fill(['position_id' => $position->id, 'seats' => $position->default_seats, 'display_order' => $position->display_order, 'is_required' => true])->save();

            for ($c = 0; $c < 3; $c++) {
                $candidateNumber++;
                $candidate = new Candidate;
                $candidate->fill([
                    'election_position_id' => $ep->id,
                    'candidate_number' => $candidateNumber,
                    'surname' => self::SURNAMES[($nameIndex + 7) % 24],
                    'first_name' => self::FIRST_NAMES[($nameIndex + 11) % 24],
                    'rank' => self::RANKS[$nameIndex % count(self::RANKS)],
                    'command' => self::COMMANDS[$nameIndex % count(self::COMMANDS)],
                    'biography' => 'Demo candidate profile (fictitious).',
                    'display_order' => $c + 1,
                ]);
                $candidate->forceFill(['election_id' => $election->id, 'status' => CandidateStatus::ACTIVE, 'is_test_data' => true])->save();
                $nameIndex++;
            }
        }

        foreach ($voters as $voter) {
            if (! $voter->isEligibleForAuthorisation()) {
                continue;
            }
            $ev = new ElectionVoter;
            $ev->forceFill([
                'election_id' => $election->id,
                'voter_id' => $voter->id,
                'eligibility_status' => EligibilityStatus::ELIGIBLE,
                'authorized_at' => now(),
            ])->save();
        }

        // Follow the permitted state path so the demo election is live immediately.
        $election->forceFill(['status' => ElectionStatus::SCHEDULED, 'scheduled_at' => now()])->save();
        $election->forceFill(['status' => ElectionStatus::OPEN, 'opened_at' => now()])->save();
    }
}
