<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Production-safe and idempotent: the 14 NIMCOS elective offices (spec §3).
 * Positions live in the database and can be edited, reordered or deactivated.
 */
class PositionSeeder extends Seeder
{
    public const POSITIONS = [
        ['PRESIDENT', 'Chief executive of the Society; presides over general meetings and the Management Committee.'],
        ['VICE PRESIDENT', 'Deputises for the President and performs duties assigned by the Committee.'],
        ['TREASURER', 'Custodian of the Society\'s funds and financial instruments.'],
        ['GENERAL SECRETARY', 'Keeps records and minutes and conducts the Society\'s correspondence.'],
        ['FINANCIAL SECRETARY', 'Maintains members\' accounts, contributions and financial records.'],
        ['ASSISTANT GENERAL SECRETARY', 'Assists the General Secretary and acts in their absence.'],
        ['LEGAL ADVISOR', 'Advises the Society on legal and regulatory matters.'],
        ['PRO', 'Public Relations Officer: communications and member engagement.'],
        ['CREDIT COMMITTEE 1', 'Member of the Credit Committee overseeing loan applications.'],
        ['CREDIT COMMITTEE 2', 'Member of the Credit Committee overseeing loan applications.'],
        ['CREDIT COMMITTEE 3', 'Member of the Credit Committee overseeing loan applications.'],
        ['SUPERVISORY AUDIT 1', 'Member of the Supervisory/Audit Committee.'],
        ['SUPERVISORY AUDIT 2', 'Member of the Supervisory/Audit Committee.'],
        ['SUPERVISORY AUDIT 3', 'Member of the Supervisory/Audit Committee.'],
    ];

    public function run(): void
    {
        foreach (self::POSITIONS as $i => [$name, $description]) {
            $position = Position::query()->firstOrNew(['name' => $name]);
            if (! $position->exists) {
                $position->fill(['description' => $description, 'display_order' => ($i + 1) * 10, 'default_seats' => 1]);
                $position->status = RecordStatus::ACTIVE;
                $position->save();
            }
        }
    }
}
