<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Nigeria Immigration Service Commands: every state command, zonal command,
 * land border control post, airport command, marine/seaport command and
 * training institution. Source: the NIS Command Directory (state_command,
 * zonal, border, airport, marine and training tabs), compiled 2026-09-29.
 * A handful of commands (e.g. Lagos Border Patrol, Seme State Command) are
 * cross-listed under more than one tab on the source directory; each appears
 * here exactly once, under the category it is most associated with.
 */
enum NisCommand: string
{
    use EnumHelpers;

    // Headquarters
    case SERVICE_HEADQUARTERS_ABUJA = 'SERVICE_HEADQUARTERS_ABUJA';

    // State Commands
    case ABIA_STATE_COMMAND = 'ABIA_STATE_COMMAND';
    case ADAMAWA_STATE_COMMAND = 'ADAMAWA_STATE_COMMAND';
    case AKWA_IBOM_STATE_COMMAND = 'AKWA_IBOM_STATE_COMMAND';
    case ANAMBRA_STATE_COMMAND = 'ANAMBRA_STATE_COMMAND';
    case BAUCHI_STATE_COMMAND = 'BAUCHI_STATE_COMMAND';
    case BAYELSA_STATE_COMMAND = 'BAYELSA_STATE_COMMAND';
    case BENUE_STATE_COMMAND = 'BENUE_STATE_COMMAND';
    case BORNO_STATE_COMMAND = 'BORNO_STATE_COMMAND';
    case CROSS_RIVER_STATE_COMMAND = 'CROSS_RIVER_STATE_COMMAND';
    case DELTA_STATE_COMMAND = 'DELTA_STATE_COMMAND';
    case EBONYI_STATE_COMMAND = 'EBONYI_STATE_COMMAND';
    case EDO_STATE_COMMAND = 'EDO_STATE_COMMAND';
    case EKITI_STATE_COMMAND = 'EKITI_STATE_COMMAND';
    case ENUGU_STATE_COMMAND = 'ENUGU_STATE_COMMAND';
    case FCT_COMMAND = 'FCT_COMMAND';
    case GOMBE_STATE_COMMAND = 'GOMBE_STATE_COMMAND';
    case IMO_STATE_COMMAND = 'IMO_STATE_COMMAND';
    case JIGAWA_STATE_COMMAND = 'JIGAWA_STATE_COMMAND';
    case KADUNA_STATE_COMMAND = 'KADUNA_STATE_COMMAND';
    case KANO_STATE_COMMAND = 'KANO_STATE_COMMAND';
    case KATSINA_STATE_COMMAND = 'KATSINA_STATE_COMMAND';
    case KEBBI_STATE_COMMAND = 'KEBBI_STATE_COMMAND';
    case KOGI_STATE_COMMAND = 'KOGI_STATE_COMMAND';
    case KWARA_STATE_COMMAND = 'KWARA_STATE_COMMAND';
    case LAGOS_STATE_COMMAND = 'LAGOS_STATE_COMMAND';
    case NASARAWA_STATE_COMMAND = 'NASARAWA_STATE_COMMAND';
    case NIGER_STATE_COMMAND = 'NIGER_STATE_COMMAND';
    case OGUN_STATE_COMMAND = 'OGUN_STATE_COMMAND';
    case ONDO_STATE_COMMAND = 'ONDO_STATE_COMMAND';
    case OSUN_STATE_COMMAND = 'OSUN_STATE_COMMAND';
    case OYO_STATE_COMMAND = 'OYO_STATE_COMMAND';
    case PLATEAU_STATE_COMMAND = 'PLATEAU_STATE_COMMAND';
    case RIVERS_STATE_COMMAND = 'RIVERS_STATE_COMMAND';
    case SOKOTO_STATE_COMMAND = 'SOKOTO_STATE_COMMAND';
    case TARABA_STATE_COMMAND = 'TARABA_STATE_COMMAND';
    case YOBE_STATE_COMMAND = 'YOBE_STATE_COMMAND';
    case ZAMFARA_STATE_COMMAND = 'ZAMFARA_STATE_COMMAND';

    // Zonal Commands
    case ZONE_A_LAGOS = 'ZONE_A_LAGOS';
    case ZONE_B_KADUNA = 'ZONE_B_KADUNA';
    case ZONE_C_BAUCHI = 'ZONE_C_BAUCHI';
    case ZONE_D_MINNA = 'ZONE_D_MINNA';
    case ZONE_E_OWERRI = 'ZONE_E_OWERRI';
    case ZONE_F_IBADAN = 'ZONE_F_IBADAN';
    case ZONE_G_BENIN_CITY = 'ZONE_G_BENIN_CITY';
    case ZONE_H_MAKURDI = 'ZONE_H_MAKURDI';

    // Land Border Control Posts
    case BABAN_MUTUM_CONTROL_POST = 'BABAN_MUTUM_CONTROL_POST';
    case BANKI_CONTROL_POST = 'BANKI_CONTROL_POST';
    case BELEL_CONTROL_POST = 'BELEL_CONTROL_POST';
    case CHIKANDA_CONTROL_POST = 'CHIKANDA_CONTROL_POST';
    case EKANG_CONTROL_POST = 'EKANG_CONTROL_POST';
    case GAMBORU_NGALA_BORDER_PATROL = 'GAMBORU_NGALA_BORDER_PATROL';
    case IDI_IROKO_BORDER_PATROL = 'IDI_IROKO_BORDER_PATROL';
    case IKANG_CONTROL_POST = 'IKANG_CONTROL_POST';
    case ILLELA_CONTROL_POST = 'ILLELA_CONTROL_POST';
    case IMEKO_CONTROL_POST = 'IMEKO_CONTROL_POST';
    case JATO_AKAA_CONTROL_POST = 'JATO_AKAA_CONTROL_POST';
    case JIBIYA_CONTROL_POST = 'JIBIYA_CONTROL_POST';
    case KAMBA_CONTROL_POST = 'KAMBA_CONTROL_POST';
    case KONGOLAN_CONTROL_POST = 'KONGOLAN_CONTROL_POST';
    case MAITAGARI_CONTROL_POST = 'MAITAGARI_CONTROL_POST';
    case MAMBILA_PLATEAU_CONTROL_POST = 'MAMBILA_PLATEAU_CONTROL_POST';
    case MFUM_BORDER_PATROL = 'MFUM_BORDER_PATROL';
    case YUSUFARI_CONTROL_POST = 'YUSUFARI_CONTROL_POST';
    case ZANGON_DAURA_CONTROL_POST = 'ZANGON_DAURA_CONTROL_POST';

    // Airport Commands
    case AKANU_IBIAM_INTERNATIONAL_AIRPORT = 'AKANU_IBIAM_INTERNATIONAL_AIRPORT';
    case MALLAM_AMINU_KANO_INTERNATIONAL_AIRPORT = 'MALLAM_AMINU_KANO_INTERNATIONAL_AIRPORT';
    case MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT = 'MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT';
    case NNAMDI_AZIKIWE_INTERNATIONAL_AIRPORT = 'NNAMDI_AZIKIWE_INTERNATIONAL_AIRPORT';
    case PORT_HARCOURT_INTERNATIONAL_AIRPORT = 'PORT_HARCOURT_INTERNATIONAL_AIRPORT';

    // Marine & Seaport Commands
    case LAGOS_BORDER_PATROL = 'LAGOS_BORDER_PATROL';
    case ONNE_MARINE_COMMAND = 'ONNE_MARINE_COMMAND';
    case SEAPORT_STATE_COMMAND = 'SEAPORT_STATE_COMMAND';
    case SEME_STATE_COMMAND = 'SEME_STATE_COMMAND';

    // Training Institutions
    case COMMAND_STAFF_COLLEGE_SOKOTO = 'COMMAND_STAFF_COLLEGE_SOKOTO';
    case IMMIGRATION_TRAINING_SCHOOL_AHOADA = 'IMMIGRATION_TRAINING_SCHOOL_AHOADA';
    case IMMIGRATION_TRAINING_SCHOOL_KANO = 'IMMIGRATION_TRAINING_SCHOOL_KANO';
    case IMMIGRATION_TRAINING_SCHOOL_ORLU = 'IMMIGRATION_TRAINING_SCHOOL_ORLU';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE_HEADQUARTERS_ABUJA => 'Service Headquarters (Abuja)',
            self::ABIA_STATE_COMMAND => 'Abia State Command',
            self::ADAMAWA_STATE_COMMAND => 'Adamawa State Command',
            self::AKWA_IBOM_STATE_COMMAND => 'Akwa Ibom State Command',
            self::ANAMBRA_STATE_COMMAND => 'Anambra State Command',
            self::BAUCHI_STATE_COMMAND => 'Bauchi State Command',
            self::BAYELSA_STATE_COMMAND => 'Bayelsa State Command',
            self::BENUE_STATE_COMMAND => 'Benue State Command',
            self::BORNO_STATE_COMMAND => 'Borno State Command',
            self::CROSS_RIVER_STATE_COMMAND => 'Cross River State Command',
            self::DELTA_STATE_COMMAND => 'Delta State Command',
            self::EBONYI_STATE_COMMAND => 'Ebonyi State Command',
            self::EDO_STATE_COMMAND => 'Edo State Command',
            self::EKITI_STATE_COMMAND => 'Ekiti State Command',
            self::ENUGU_STATE_COMMAND => 'Enugu State Command',
            self::FCT_COMMAND => 'FCT Command',
            self::GOMBE_STATE_COMMAND => 'Gombe State Command',
            self::IMO_STATE_COMMAND => 'Imo State Command',
            self::JIGAWA_STATE_COMMAND => 'Jigawa State Command',
            self::KADUNA_STATE_COMMAND => 'Kaduna State Command',
            self::KANO_STATE_COMMAND => 'Kano State Command',
            self::KATSINA_STATE_COMMAND => 'Katsina State Command',
            self::KEBBI_STATE_COMMAND => 'Kebbi State Command',
            self::KOGI_STATE_COMMAND => 'Kogi State Command',
            self::KWARA_STATE_COMMAND => 'Kwara State Command',
            self::LAGOS_STATE_COMMAND => 'Lagos State Command',
            self::NASARAWA_STATE_COMMAND => 'Nasarawa State Command',
            self::NIGER_STATE_COMMAND => 'Niger State Command',
            self::OGUN_STATE_COMMAND => 'Ogun State Command',
            self::ONDO_STATE_COMMAND => 'Ondo State Command',
            self::OSUN_STATE_COMMAND => 'Osun State Command',
            self::OYO_STATE_COMMAND => 'Oyo State Command',
            self::PLATEAU_STATE_COMMAND => 'Plateau State Command',
            self::RIVERS_STATE_COMMAND => 'Rivers State Command',
            self::SOKOTO_STATE_COMMAND => 'Sokoto State Command',
            self::TARABA_STATE_COMMAND => 'Taraba State Command',
            self::YOBE_STATE_COMMAND => 'Yobe State Command',
            self::ZAMFARA_STATE_COMMAND => 'Zamfara State Command',
            self::ZONE_A_LAGOS => "Zone 'A' (Lagos)",
            self::ZONE_B_KADUNA => "Zone 'B' (Kaduna)",
            self::ZONE_C_BAUCHI => "Zone 'C' (Bauchi)",
            self::ZONE_D_MINNA => "Zone 'D' (Minna)",
            self::ZONE_E_OWERRI => "Zone 'E' (Owerri)",
            self::ZONE_F_IBADAN => "Zone 'F' (Ibadan)",
            self::ZONE_G_BENIN_CITY => "Zone 'G' (Benin City)",
            self::ZONE_H_MAKURDI => "Zone 'H' (Makurdi)",
            self::BABAN_MUTUM_CONTROL_POST => 'Baban Mutum Control Post',
            self::BANKI_CONTROL_POST => 'Banki Control Post',
            self::BELEL_CONTROL_POST => 'Belel Control Post',
            self::CHIKANDA_CONTROL_POST => 'Chikanda Control Post',
            self::EKANG_CONTROL_POST => 'Ekang Control Post',
            self::GAMBORU_NGALA_BORDER_PATROL => 'Gamboru-Ngala Border Patrol',
            self::IDI_IROKO_BORDER_PATROL => 'Idi-Iroko Border Patrol',
            self::IKANG_CONTROL_POST => 'Ikang Control Post',
            self::ILLELA_CONTROL_POST => 'Illela Control Post',
            self::IMEKO_CONTROL_POST => 'Imeko Control Post',
            self::JATO_AKAA_CONTROL_POST => 'Jato Akaa Control Post',
            self::JIBIYA_CONTROL_POST => 'Jibiya Control Post',
            self::KAMBA_CONTROL_POST => 'Kamba Control Post',
            self::KONGOLAN_CONTROL_POST => 'Kongolan Control Post',
            self::MAITAGARI_CONTROL_POST => 'Maitagari Control Post',
            self::MAMBILA_PLATEAU_CONTROL_POST => 'Mambila Plateau Control Post',
            self::MFUM_BORDER_PATROL => 'Mfum Border Patrol',
            self::YUSUFARI_CONTROL_POST => 'Yusufari Control Post',
            self::ZANGON_DAURA_CONTROL_POST => 'Zangon Daura Control Post',
            self::AKANU_IBIAM_INTERNATIONAL_AIRPORT => 'Akanu Ibiam International Airport',
            self::MALLAM_AMINU_KANO_INTERNATIONAL_AIRPORT => 'Mallam Aminu Kano International Airport',
            self::MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT => 'Murtala Muhammed International Airport',
            self::NNAMDI_AZIKIWE_INTERNATIONAL_AIRPORT => 'Nnamdi Azikiwe International Airport',
            self::PORT_HARCOURT_INTERNATIONAL_AIRPORT => 'Port Harcourt International Airport',
            self::LAGOS_BORDER_PATROL => 'Lagos Border Patrol',
            self::ONNE_MARINE_COMMAND => 'Onne Marine Command',
            self::SEAPORT_STATE_COMMAND => 'Seaport State Command',
            self::SEME_STATE_COMMAND => 'Seme State Command',
            self::COMMAND_STAFF_COLLEGE_SOKOTO => 'Command & Staff College, Sokoto',
            self::IMMIGRATION_TRAINING_SCHOOL_AHOADA => 'Immigration Training School, Ahoada',
            self::IMMIGRATION_TRAINING_SCHOOL_KANO => 'Immigration Training School, Kano',
            self::IMMIGRATION_TRAINING_SCHOOL_ORLU => 'Immigration Training School, Orlu',
        };
    }

    /**
     * Known real-world aliases and common misspellings seen in NIS registers
     * that do not otherwise match the official directory text, mapped to the
     * command they unambiguously refer to. Deliberately short: only additions
     * confirmed against a real voter-register import, not guessed matches.
     */
    private const ALIASES = [
        'ABUJA' => self::FCT_COMMAND,
        'FCT AREA COMMAND' => self::FCT_COMMAND,
        'MURTALA MOHAMMED INTERNATIONAL AIRPORT' => self::MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT,
        'SERVICE HEADQUARTERS' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SERVICE HEADQUARTERS ABUJA' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SERVICE HEADQUARTERS (ABUJA)' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SERVICE HEADQUARTERS, ABUJA' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SERVICE HQ' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SERVICE HQ ABUJA' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SERVICE HQ (ABUJA)' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SHQ' => self::SERVICE_HEADQUARTERS_ABUJA,
        'SHQ ABUJA' => self::SERVICE_HEADQUARTERS_ABUJA,
        'NIS SHQ' => self::SERVICE_HEADQUARTERS_ABUJA,
        'NIS SERVICE HEADQUARTERS' => self::SERVICE_HEADQUARTERS_ABUJA,
        'NIS HEADQUARTERS' => self::SERVICE_HEADQUARTERS_ABUJA,
        'HEADQUARTERS' => self::SERVICE_HEADQUARTERS_ABUJA,
        'HEADQUARTERS ABUJA' => self::SERVICE_HEADQUARTERS_ABUJA,
        'HEADQUARTERS (ABUJA)' => self::SERVICE_HEADQUARTERS_ABUJA,
    ];

    /**
     * Best-effort match from free text (imports, legacy data), tolerant of case,
     * extra whitespace and (for state commands) a missing "State" word, e.g.
     * "lagos command" or "LAGOS STATE COMMAND" both match Lagos State Command.
     */
    public static function fromText(?string $value): ?self
    {
        $value = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
        if ($value === '') {
            return null;
        }

        foreach (self::cases() as $command) {
            if ($value === $command->value || $value === mb_strtoupper($command->label())) {
                return $command;
            }
        }

        // "LAGOS COMMAND" / "LAGOS" for a state command whose full title is "Lagos State Command".
        foreach (self::cases() as $command) {
            $short = str_replace(' STATE COMMAND', '', mb_strtoupper($command->label()));
            if ($value === $short.' COMMAND' || $value === $short) {
                return $command;
            }
        }

        if (isset(self::ALIASES[$value])) {
            return self::ALIASES[$value];
        }

        return null;
    }

    /** @return array<string, list<self>> Commands grouped by category, in directory order. */
    public static function grouped(): array
    {
        return [
            'Headquarters' => [
                self::SERVICE_HEADQUARTERS_ABUJA,
            ],
            'State Commands' => [
                self::ABIA_STATE_COMMAND,
                self::ADAMAWA_STATE_COMMAND,
                self::AKWA_IBOM_STATE_COMMAND,
                self::ANAMBRA_STATE_COMMAND,
                self::BAUCHI_STATE_COMMAND,
                self::BAYELSA_STATE_COMMAND,
                self::BENUE_STATE_COMMAND,
                self::BORNO_STATE_COMMAND,
                self::CROSS_RIVER_STATE_COMMAND,
                self::DELTA_STATE_COMMAND,
                self::EBONYI_STATE_COMMAND,
                self::EDO_STATE_COMMAND,
                self::EKITI_STATE_COMMAND,
                self::ENUGU_STATE_COMMAND,
                self::FCT_COMMAND,
                self::GOMBE_STATE_COMMAND,
                self::IMO_STATE_COMMAND,
                self::JIGAWA_STATE_COMMAND,
                self::KADUNA_STATE_COMMAND,
                self::KANO_STATE_COMMAND,
                self::KATSINA_STATE_COMMAND,
                self::KEBBI_STATE_COMMAND,
                self::KOGI_STATE_COMMAND,
                self::KWARA_STATE_COMMAND,
                self::LAGOS_STATE_COMMAND,
                self::NASARAWA_STATE_COMMAND,
                self::NIGER_STATE_COMMAND,
                self::OGUN_STATE_COMMAND,
                self::ONDO_STATE_COMMAND,
                self::OSUN_STATE_COMMAND,
                self::OYO_STATE_COMMAND,
                self::PLATEAU_STATE_COMMAND,
                self::RIVERS_STATE_COMMAND,
                self::SOKOTO_STATE_COMMAND,
                self::TARABA_STATE_COMMAND,
                self::YOBE_STATE_COMMAND,
                self::ZAMFARA_STATE_COMMAND,
            ],
            'Zonal Commands' => [
                self::ZONE_A_LAGOS,
                self::ZONE_B_KADUNA,
                self::ZONE_C_BAUCHI,
                self::ZONE_D_MINNA,
                self::ZONE_E_OWERRI,
                self::ZONE_F_IBADAN,
                self::ZONE_G_BENIN_CITY,
                self::ZONE_H_MAKURDI,
            ],
            'Land Border Control Posts' => [
                self::BABAN_MUTUM_CONTROL_POST,
                self::BANKI_CONTROL_POST,
                self::BELEL_CONTROL_POST,
                self::CHIKANDA_CONTROL_POST,
                self::EKANG_CONTROL_POST,
                self::GAMBORU_NGALA_BORDER_PATROL,
                self::IDI_IROKO_BORDER_PATROL,
                self::IKANG_CONTROL_POST,
                self::ILLELA_CONTROL_POST,
                self::IMEKO_CONTROL_POST,
                self::JATO_AKAA_CONTROL_POST,
                self::JIBIYA_CONTROL_POST,
                self::KAMBA_CONTROL_POST,
                self::KONGOLAN_CONTROL_POST,
                self::MAITAGARI_CONTROL_POST,
                self::MAMBILA_PLATEAU_CONTROL_POST,
                self::MFUM_BORDER_PATROL,
                self::YUSUFARI_CONTROL_POST,
                self::ZANGON_DAURA_CONTROL_POST,
            ],
            'Airport Commands' => [
                self::AKANU_IBIAM_INTERNATIONAL_AIRPORT,
                self::MALLAM_AMINU_KANO_INTERNATIONAL_AIRPORT,
                self::MURTALA_MUHAMMED_INTERNATIONAL_AIRPORT,
                self::NNAMDI_AZIKIWE_INTERNATIONAL_AIRPORT,
                self::PORT_HARCOURT_INTERNATIONAL_AIRPORT,
            ],
            'Marine & Seaport Commands' => [
                self::LAGOS_BORDER_PATROL,
                self::ONNE_MARINE_COMMAND,
                self::SEAPORT_STATE_COMMAND,
                self::SEME_STATE_COMMAND,
            ],
            'Training Institutions' => [
                self::COMMAND_STAFF_COLLEGE_SOKOTO,
                self::IMMIGRATION_TRAINING_SCHOOL_AHOADA,
                self::IMMIGRATION_TRAINING_SCHOOL_KANO,
                self::IMMIGRATION_TRAINING_SCHOOL_ORLU,
            ],
        ];
    }
}
