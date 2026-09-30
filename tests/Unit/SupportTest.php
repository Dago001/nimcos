<?php

namespace Tests\Unit;

use App\Enums\ElectionStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Services\Auth\Totp;
use App\Support\PhoneNumber;
use App\Support\PhpIniSize;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_totp_matches_rfc_6238_reference_vectors(): void
    {
        // RFC 6238 Appendix B, SHA-1 secret "12345678901234567890"; 6-digit truncation of the 8-digit values.
        $secret = Totp::base32Encode('12345678901234567890');
        $totp = new Totp;
        $this->assertSame('287082', $totp->code($secret, 59));
        $this->assertSame('081804', $totp->code($secret, 1111111109));
        $this->assertSame('050471', $totp->code($secret, 1111111111));
        $this->assertSame('005924', $totp->code($secret, 1234567890));
        $this->assertSame('279037', $totp->code($secret, 2000000000));
    }

    public function test_totp_verification_window_and_format(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $now = 1_800_000_000;
        $this->assertTrue($totp->verify($secret, $totp->code($secret, $now - 30), 1, $now));
        $this->assertFalse($totp->verify($secret, $totp->code($secret, $now - 120), 1, $now));
        $this->assertFalse($totp->verify($secret, 'abcdef', 1, $now));
        $this->assertSame('12345678901234567890', Totp::base32Decode(Totp::base32Encode('12345678901234567890')));
    }

    public function test_nigerian_phone_numbers_are_normalised_to_e164(): void
    {
        foreach (['08031234567', '8031234567', '2348031234567', '+234 803 123 4567', '0803-123-4567', '07061234567', '09121234567'] as $input) {
            $this->assertMatchesRegularExpression('/^\+234[789][01]\d{8}$/', (string) PhoneNumber::normalise($input), $input);
        }
        foreach (['12345', '0803123456', '06031234567', '+1 202 555 0100', '', null] as $input) {
            $this->assertNull(PhoneNumber::normalise($input));
        }
        $this->assertSame('0803 123 4567', PhoneNumber::display('+2348031234567'));
    }

    public function test_election_state_machine_allows_only_approved_transitions(): void
    {
        $this->assertTrue(ElectionStatus::DRAFT->canTransitionTo(ElectionStatus::SCHEDULED));
        $this->assertTrue(ElectionStatus::SCHEDULED->canTransitionTo(ElectionStatus::OPEN));
        $this->assertTrue(ElectionStatus::OPEN->canTransitionTo(ElectionStatus::CLOSED));
        $this->assertFalse(ElectionStatus::CLOSED->canTransitionTo(ElectionStatus::OPEN));
        $this->assertFalse(ElectionStatus::DRAFT->canTransitionTo(ElectionStatus::OPEN));
        $this->assertFalse(ElectionStatus::OPEN->canTransitionTo(ElectionStatus::RESULTS_PUBLISHED));
        $this->assertSame([], ElectionStatus::ARCHIVED->allowedTransitions());
    }

    public function test_nis_rank_list_runs_from_comptroller_general_to_immigration_assistant_3(): void
    {
        $this->assertSame([
            'CGI', 'DCG', 'ACG', 'CIS', 'DCI', 'ACI', 'CSI', 'SI', 'DSI', 'ASI1', 'ASI2',
            'II', 'AII', 'CIA', 'SIA', 'IA1', 'IA2', 'IA3',
        ], NisRank::values());
        $this->assertSame('Comptroller General of Immigration (CGI)', NisRank::CGI->label());
        $this->assertSame('Deputy Comptroller General of Immigration (DCG)', NisRank::DCG->label());
        $this->assertSame('Assistant Comptroller General of Immigration (ACG)', NisRank::ACG->label());
        $this->assertSame('Deputy Comptroller of Immigration (DCI)', NisRank::DCI->label());
        $this->assertSame('Immigration Assistant 3 (IA3)', NisRank::IA3->label());
        $this->assertSame('DCI', NisRank::DCI->shortLabel());
    }

    public function test_nis_rank_from_text_accepts_full_title_or_abbreviation(): void
    {
        $this->assertSame(NisRank::DCI, NisRank::fromText('DCI'));
        $this->assertSame(NisRank::DCI, NisRank::fromText('dci'));
        $this->assertSame(NisRank::DCI, NisRank::fromText('Deputy Comptroller of Immigration (DCI)'));
        $this->assertSame(NisRank::DCI, NisRank::fromText('Deputy Comptroller of Immigration'));
        $this->assertSame(NisRank::IA3, NisRank::fromText(' ia3 '));
        $this->assertNull(NisRank::fromText('DSP'));
        $this->assertNull(NisRank::fromText(''));
        $this->assertNull(NisRank::fromText(null));
    }

    public function test_php_ini_size_parses_upload_shorthand_to_kilobytes(): void
    {
        $this->assertSame(20 * 1024, PhpIniSize::toKb('20M'));
        $this->assertSame(2 * 1024, PhpIniSize::toKb('2M'));
        $this->assertSame(1024 * 1024, PhpIniSize::toKb('1G'));
        $this->assertSame(512, PhpIniSize::toKb('512K'));
        $this->assertSame(0, PhpIniSize::toKb('0'));
        $this->assertSame(0, PhpIniSize::toKb(''));
        // Bare number, no unit, is bytes per the php.ini convention.
        $this->assertSame(1, PhpIniSize::toKb('1024'));
    }

    public function test_php_ini_effective_upload_limit_is_the_smaller_of_the_two_directives(): void
    {
        // A regression guard for the exact bug this was written to catch: PHP's stock
        // 2M/8M defaults silently reject a voter-register upload the app allows up to
        // 20 MB, before Laravel's own validation error can ever be shown.
        $this->assertSame(2 * 1024, min(PhpIniSize::toKb('2M'), PhpIniSize::toKb('8M')));
        $this->assertSame(20 * 1024, min(PhpIniSize::toKb('20M'), PhpIniSize::toKb('25M')));
    }

    public function test_nis_command_list_has_every_command_exactly_once(): void
    {
        $this->assertCount(78, NisCommand::cases());
        $this->assertSame(78, count(array_unique(NisCommand::values())));
        $labels = array_map(fn ($c) => $c->label(), NisCommand::cases());
        $this->assertSame(78, count(array_unique($labels)), 'Every command must have a distinct label.');

        $grouped = NisCommand::grouped();
        $this->assertSame(
            ['Headquarters', 'State Commands', 'Zonal Commands', 'Land Border Control Posts', 'Airport Commands', 'Marine & Seaport Commands', 'Training Institutions'],
            array_keys($grouped)
        );
        $flat = array_merge(...array_values($grouped));
        $this->assertSame(78, count($flat), 'grouped() must place every command in exactly one category.');
        $this->assertSame(78, count(array_unique(array_map(fn ($c) => $c->value, $flat))));

        $this->assertSame('Service Headquarters (Abuja)', NisCommand::SERVICE_HEADQUARTERS_ABUJA->label());
        $this->assertSame('FCT Command', NisCommand::FCT_COMMAND->label());
        $this->assertSame('Lagos State Command', NisCommand::LAGOS_STATE_COMMAND->label());
    }

    public function test_nis_command_from_text_is_tolerant_of_case_spacing_and_missing_state_word(): void
    {
        // Regression guard: str_replace() on the label must uppercase FIRST, or a
        // title-case label like "Lagos State Command" never matches "STATE COMMAND"
        // and the short-form fallback (e.g. bare "Lagos") silently fails.
        $this->assertSame(NisCommand::LAGOS_STATE_COMMAND, NisCommand::fromText('Lagos State Command'));
        $this->assertSame(NisCommand::LAGOS_STATE_COMMAND, NisCommand::fromText('LAGOS STATE COMMAND'));
        $this->assertSame(NisCommand::LAGOS_STATE_COMMAND, NisCommand::fromText('LAGOS_STATE_COMMAND'));
        $this->assertSame(NisCommand::LAGOS_STATE_COMMAND, NisCommand::fromText('lagos command'));
        $this->assertSame(NisCommand::LAGOS_STATE_COMMAND, NisCommand::fromText('  Lagos   Command  '));
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA, NisCommand::fromText('Service Headquarters (Abuja)'));
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA, NisCommand::fromText('SERVICE HEADQUARTERS ABUJA'));
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA, NisCommand::fromText('SERVICE_HEADQUARTERS_ABUJA'));
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA, NisCommand::fromText('SHQ'));
        $this->assertSame(NisCommand::FCT_COMMAND, NisCommand::fromText('FCT Command'));
        $this->assertSame(NisCommand::MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT, NisCommand::fromText('Murtala Muhammed International Airport'));
        $this->assertNull(NisCommand::fromText('Not A Real Command'));
        $this->assertNull(NisCommand::fromText(''));
        $this->assertNull(NisCommand::fromText(null));
    }

    public function test_nis_command_from_text_accepts_known_aliases_seen_in_real_registers(): void
    {
        // Found from a real 20,000-row voter register import: "Abuja" and "FCT Area
        // Command" both mean FCT Command; "Murtala Mohammed" is a common misspelling
        // of "Murtala Muhammed" International Airport.
        $this->assertSame(NisCommand::FCT_COMMAND, NisCommand::fromText('Abuja'));
        $this->assertSame(NisCommand::FCT_COMMAND, NisCommand::fromText('FCT Area Command'));
        $this->assertSame(NisCommand::MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT, NisCommand::fromText('Murtala Mohammed International Airport'));
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA, NisCommand::fromText('Service Headquarters'));
        $this->assertSame(NisCommand::SERVICE_HEADQUARTERS_ABUJA, NisCommand::fromText('NIS SHQ'));

        // Genuinely not in the official directory: never silently guessed.
        $this->assertNull(NisCommand::fromText('Sam Mbakwe Airport'));
        $this->assertNull(NisCommand::fromText('Border Post Command'));
    }
}
