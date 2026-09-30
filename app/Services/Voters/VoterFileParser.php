<?php

namespace App\Services\Voters;

use App\Enums\MembershipStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Support\PhoneNumber;
use App\Support\ServiceNumber;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;

/**
 * Reads a CSV/XLSX voter register and validates every row (spec §6).
 * Pure: it reads the file and returns results; it never writes to the database.
 */
class VoterFileParser
{
    public const COLUMNS = [
        'SERVICE NUMBER' => 'service_number',
        'MEMBERSHIP ID' => 'membership_id',
        'SURNAME' => 'surname',
        'FIRST NAME' => 'first_name',
        'OTHER NAMES' => 'other_names',
        'GENDER' => 'gender',
        'DOB' => 'dob',
        'RANK' => 'rank',
        'COMMAND' => 'command',
        'FORMATION' => 'formation',
        'PHONE NUMBER' => 'phone',
        'EMAIL' => 'email',
        'MEMBERSHIP STATUS' => 'membership_status',
    ];

    public const REQUIRED_COLUMNS = ['SERVICE NUMBER', 'SURNAME', 'FIRST NAME', 'EMAIL'];

    private const HEADER_ALIASES = [
        'SERVICE NO' => 'SERVICE NUMBER', 'SERVICE NO.' => 'SERVICE NUMBER', 'SVC NO' => 'SERVICE NUMBER',
        'MEMBERSHIP NO' => 'MEMBERSHIP ID', 'MEMBERSHIP NO.' => 'MEMBERSHIP ID', 'MEMBER ID' => 'MEMBERSHIP ID', 'MEMBER NO' => 'MEMBERSHIP ID', 'MEMBER NO.' => 'MEMBERSHIP ID',
        'FIRSTNAME' => 'FIRST NAME', 'OTHER NAME' => 'OTHER NAMES', 'OTHERNAMES' => 'OTHER NAMES',
        'SEX' => 'GENDER',
        'DATE OF BIRTH' => 'DOB', 'BIRTH DATE' => 'DOB', 'BIRTHDATE' => 'DOB',
        'PHONE' => 'PHONE NUMBER', 'PHONE NO' => 'PHONE NUMBER', 'MOBILE' => 'PHONE NUMBER', 'GSM' => 'PHONE NUMBER',
        'EMAIL ADDRESS' => 'EMAIL', 'E-MAIL' => 'EMAIL', 'MEMBERSHIP' => 'MEMBERSHIP STATUS', 'STATUS' => 'MEMBERSHIP STATUS',
    ];

    private const MAX_ROWS = 100000;

    /**
     * @return array{
     *   missing_columns: list<string>,
     *   total: int,
     *   valid: array<int, array<string, ?string>>,
     *   errors: list<array{row:int, service_number:?string, type:string, messages:list<string>, raw:array}>,
     *   duplicate_count: int,
     *   invalid_count: int
     * }
     */
    public function parse(string $path, string $extension): array
    {
        $rows = $this->readRows($path, strtolower($extension));
        $header = array_shift($rows) ?? [];

        $map = [];
        foreach ($header as $index => $name) {
            $key = $this->normaliseHeader((string) $name);
            if (isset(self::COLUMNS[$key]) && ! in_array($key, $map, true)) {
                $map[$index] = $key;
            }
        }
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $map));

        $result = ['missing_columns' => $missing, 'total' => 0, 'valid' => [], 'errors' => [], 'duplicate_count' => 0, 'invalid_count' => 0];
        if ($missing !== []) {
            return $result;
        }

        $seenServiceNumbers = [];
        $seenRecords = [];
        $seenEmails = [];
        $seenMembershipIds = [];

        foreach ($rows as $i => $cells) {
            $rowNumber = $i + 2; // 1-based, after header
            $raw = [];
            foreach ($map as $index => $column) {
                $raw[self::COLUMNS[$column]] = isset($cells[$index]) ? trim((string) $cells[$index]) : '';
            }
            if (implode('', $raw) === '') {
                continue; // blank line
            }
            $result['total']++;
            if ($result['total'] > self::MAX_ROWS) {
                $result['missing_columns'] = ['File exceeds '.self::MAX_ROWS.' rows. Split it into smaller files.'];

                return $result;
            }

            [$data, $messages] = $this->validateRow($raw);
            $sn = $data['service_number'];

            if ($messages === [] && isset($seenServiceNumbers[$sn])) {
                $fingerprint = md5(json_encode($data));
                $exact = isset($seenRecords[$fingerprint]);
                $result['errors'][] = [
                    'row' => $rowNumber, 'service_number' => $sn, 'type' => 'DUPLICATE',
                    'messages' => [$exact
                        ? "Exact duplicate of row {$seenServiceNumbers[$sn]}."
                        : "Service Number already appears on row {$seenServiceNumbers[$sn]} with different details."],
                    'raw' => $raw,
                ];
                $result['duplicate_count']++;

                continue;
            }

            if ($messages === [] && isset($seenEmails[$data['email']])) {
                $messages[] = "Email is already used by Service Number {$seenEmails[$data['email']]} in this file. Each voter needs their own email address for verification codes.";
            }

            if ($messages === [] && ! empty($data['membership_id']) && isset($seenMembershipIds[$data['membership_id']])) {
                $messages[] = "Membership ID \"{$data['membership_id']}\" is already used by row {$seenMembershipIds[$data['membership_id']]} in this file.";
            }

            if ($messages !== []) {
                $result['errors'][] = ['row' => $rowNumber, 'service_number' => $sn ?: null, 'type' => 'INVALID', 'messages' => $messages, 'raw' => $raw];
                $result['invalid_count']++;

                continue;
            }

            $seenServiceNumbers[$sn] = $rowNumber;
            $seenRecords[md5(json_encode($data))] = true;
            $seenEmails[$data['email']] = $sn;
            if (! empty($data['membership_id'])) {
                $seenMembershipIds[$data['membership_id']] = $rowNumber;
            }
            $result['valid'][$rowNumber] = $data;
        }

        return $result;
    }

    /** @return array{0: array<string, ?string>, 1: list<string>} */
    public function validateRow(array $raw): array
    {
        $messages = [];
        $clean = fn (?string $v, int $max) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '')) === '' ? null : mb_substr($v, 0, $max);

        $sn = ServiceNumber::normalise($raw['service_number'] ?? '');
        if ($sn === '') {
            $messages[] = 'Service Number is required.';
        } elseif (! ServiceNumber::isValid($sn)) {
            $messages[] = "Service Number \"{$sn}\" is not in a valid format.";
        }

        $membershipId = $clean($raw['membership_id'] ?? null, 50);
        if ($membershipId !== null) {
            $membershipId = mb_strtoupper($membershipId);
        }

        $surname = $clean($raw['surname'] ?? null, 100);
        $first = $clean($raw['first_name'] ?? null, 100);
        if ($surname === null) {
            $messages[] = 'Surname is required.';
        }
        if ($first === null) {
            $messages[] = 'First name is required.';
        }
        foreach (['surname' => $surname, 'first name' => $first] as $label => $value) {
            if ($value !== null && ! preg_match("/^[\p{L}\p{M}' .\-]+$/u", $value)) {
                $messages[] = ucfirst($label).' contains invalid characters.';
            }
        }

        $rawGender = $clean($raw['gender'] ?? null, 20);
        $gender = null;
        if ($rawGender !== null) {
            $upperGender = mb_strtoupper($rawGender);
            if (in_array($upperGender, ['M', 'MALE'], true)) {
                $gender = 'MALE';
            } elseif (in_array($upperGender, ['F', 'FEMALE'], true)) {
                $gender = 'FEMALE';
            } else {
                $gender = $upperGender;
            }
        }

        $rawDob = $clean($raw['dob'] ?? null, 30);
        $dob = null;
        if ($rawDob !== null) {
            $parsedDob = null;
            if (is_numeric($rawDob) && (float) $rawDob > 1000 && (float) $rawDob < 100000) {
                $excelTimestamp = ((float) $rawDob - 25569) * 86400;
                $parsedDob = (new \DateTimeImmutable)->setTimestamp((int) $excelTimestamp);
            } else {
                foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d'] as $format) {
                    $d = \DateTimeImmutable::createFromFormat($format, $rawDob);
                    if ($d && $d->format($format) === $rawDob) {
                        $parsedDob = $d;
                        break;
                    }
                }
                if (! $parsedDob) {
                    try {
                        $parsedDob = new \DateTimeImmutable($rawDob);
                    } catch (\Throwable) {
                        $parsedDob = null;
                    }
                }
            }
            if ($parsedDob) {
                $dob = $parsedDob->format('Y-m-d');
            } else {
                $messages[] = "Date of birth \"{$rawDob}\" is not a valid date (use YYYY-MM-DD or DD/MM/YYYY).";
            }
        }

        $rawPhone = trim((string) ($raw['phone'] ?? ''));
        $phone = PhoneNumber::normalise($rawPhone);
        if ($rawPhone !== '' && $phone === null) {
            $messages[] = "Phone number \"{$rawPhone}\" is not a valid Nigerian mobile number.";
        }

        $email = $clean($raw['email'] ?? null, 191);
        if ($email === null) {
            $messages[] = 'Email is required: verification codes are sent by email.';
        } else {
            $email = mb_strtolower($email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $messages[] = "Email \"{$email}\" is not valid.";
            }
        }

        $membership = strtoupper($clean($raw['membership_status'] ?? null, 20) ?? MembershipStatus::ACTIVE->value);
        if (! in_array($membership, MembershipStatus::values(), true)) {
            $messages[] = 'Membership status must be one of: '.implode(', ', MembershipStatus::values()).'.';
        }

        // Rank, Command and Formation never block onboarding: what matters is the
        // Service Number, names, email and phone. An unrecognised Rank or Command
        // is simply left blank (correctable later from the voter's record) rather
        // than keeping the officer off the register.
        $rawRank = $clean($raw['rank'] ?? null, 80);
        $rank = $rawRank !== null ? NisRank::fromText($rawRank) : null;

        $rawCommand = $clean($raw['command'] ?? null, 120);
        $command = $rawCommand !== null ? NisCommand::fromText($rawCommand) : null;

        return [[
            'service_number' => $sn,
            'membership_id' => $membershipId,
            'surname' => $surname !== null ? mb_strtoupper($surname) : null,
            'first_name' => $first !== null ? mb_convert_case($first, MB_CASE_TITLE) : null,
            'other_names' => ($o = $clean($raw['other_names'] ?? null, 150)) !== null ? mb_convert_case($o, MB_CASE_TITLE) : null,
            'gender' => $gender,
            'dob' => $dob,
            'rank' => $rank?->value,
            'command' => $command?->value,
            'formation' => ($f = $clean($raw['formation'] ?? null, 120)) !== null ? mb_strtoupper($f) : null,
            'phone' => $phone,
            'email' => $email,
            'membership_status' => $membership,
        ], $messages];
    }

    private function normaliseHeader(string $name): string
    {
        $name = preg_replace('/^\x{FEFF}/u', '', $name) ?? $name;
        $name = strtoupper(trim(preg_replace('/[\s_]+/', ' ', $name) ?? ''));

        return self::HEADER_ALIASES[$name] ?? $name;
    }

    /** @return list<list<mixed>> */
    private function readRows(string $path, string $extension): array
    {
        if (in_array($extension, ['csv', 'txt'], true)) {
            $rows = [];
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to read the uploaded file.');
            }
            $first = fgets($handle);
            $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
            rewind($handle);
            while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $rows[] = array_map(fn ($v) => $v === null ? '' : mb_convert_encoding((string) $v, 'UTF-8', 'UTF-8, Windows-1252'), $data);
            }
            fclose($handle);

            return $rows;
        }

        $reader = IOFactory::createReader($extension === 'xls' ? 'Xls' : 'Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($path, IReader::READ_DATA_ONLY);

        // Values only: formulas are returned as their text, never evaluated.
        return $spreadsheet->getSheet(0)->toArray(null, false, false, false);
    }
}
