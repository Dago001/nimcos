<?php

namespace App\Services\Voters;

use App\Enums\AuditResult;
use App\Enums\ImportStatus;
use App\Enums\VerificationStatus;
use App\Jobs\ProcessVoterImport;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoterImport;
use App\Models\VoterImportError;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-step register import (spec §6):
 *   1. preview(): validate the whole file, record errors, show what would change.
 *   2. confirm(): administrator approves; ProcessVoterImport applies it in ONE transaction.
 */
class VoterImportService
{
    private const DISK = 'local';

    private const TRACKED_FIELDS = ['surname', 'first_name', 'other_names', 'rank', 'command', 'formation', 'phone', 'email', 'membership_status'];

    public function __construct(
        private readonly VoterFileParser $parser,
        private readonly AuditLogger $audit,
    ) {}

    public function preview(UploadedFile $file, User $by, bool $updateExisting): VoterImport
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs('imports', Str::uuid().'.'.$extension, self::DISK);
        $absolute = Storage::disk(self::DISK)->path($storedPath);
        $hash = hash_file('sha256', $absolute);

        $parsed = $this->parser->parse($absolute, $extension);

        $import = new VoterImport;
        $import->forceFill([
            'uploaded_by' => $by->getKey(),
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_path' => $storedPath,
            'file_hash' => $hash,
            'status' => ImportStatus::PREVIEWED,
            'update_existing' => $updateExisting,
        ]);

        if ($parsed['missing_columns'] !== []) {
            Storage::disk(self::DISK)->delete($storedPath);
            throw ValidationException::withMessages([
                'file' => 'The file is missing required column(s): '.implode(', ', $parsed['missing_columns']).'. Expected headings: '.implode(', ', array_keys(VoterFileParser::COLUMNS)).'.',
            ]);
        }

        DB::transaction(function () use ($import, $parsed) {
            $existingEmailConflicts = $this->emailConflictsWithRegister($parsed['valid']);
            foreach ($existingEmailConflicts as $rowNumber => $message) {
                $row = $parsed['valid'][$rowNumber];
                unset($parsed['valid'][$rowNumber]);
                $parsed['errors'][] = ['row' => $rowNumber, 'service_number' => $row['service_number'], 'type' => 'INVALID', 'messages' => [$message], 'raw' => $row];
                $parsed['invalid_count']++;
            }

            $diff = $this->diff($parsed['valid'], $import->update_existing);

            $import->forceFill([
                'total_rows' => $parsed['total'],
                'valid_rows' => count($parsed['valid']),
                'imported_count' => $diff['new'],
                'updated_count' => $diff['updated'],
                'unchanged_count' => $diff['unchanged'],
                'duplicate_count' => $parsed['duplicate_count'],
                'invalid_count' => $parsed['invalid_count'],
                'rejected_count' => $parsed['duplicate_count'] + $parsed['invalid_count'],
            ])->save();

            usort($parsed['errors'], fn ($a, $b) => $a['row'] <=> $b['row']);
            foreach (array_chunk($parsed['errors'], 500) as $chunk) {
                VoterImportError::query()->insert(array_map(fn ($e) => [
                    'id' => (string) Str::orderedUuid(),
                    'voter_import_id' => $import->getKey(),
                    'row_number' => $e['row'],
                    'service_number' => $e['service_number'] ? mb_substr($e['service_number'], 0, 50) : null,
                    'error_type' => $e['type'],
                    'messages' => json_encode($e['messages']),
                    'raw' => json_encode($e['raw']),
                    'created_at' => now(),
                ], $chunk));
            }
        });

        $this->audit->log(AuditAction::VOTER_IMPORT_UPLOADED, AuditResult::SUCCESS, $import, [
            'file' => $import->original_filename,
            'sha256' => $hash,
            'total' => $import->total_rows,
            'invalid' => $import->invalid_count,
            'duplicates' => $import->duplicate_count,
        ]);

        return $import;
    }

    /** First valid rows for the preview table. */
    public function sampleRows(VoterImport $import, int $limit = 25): array
    {
        $absolute = Storage::disk(self::DISK)->path($import->stored_path);
        if (! is_file($absolute)) {
            return [];
        }
        $parsed = $this->parser->parse($absolute, pathinfo($import->stored_path, PATHINFO_EXTENSION));

        return array_slice($parsed['valid'], 0, $limit, true);
    }

    public function confirm(VoterImport $import, User $by): void
    {
        $updated = VoterImport::query()
            ->whereKey($import->getKey())
            ->where('status', ImportStatus::PREVIEWED->value)
            ->update([
                'status' => ImportStatus::PROCESSING->value,
                'confirmed_by' => $by->getKey(),
                'confirmed_at' => now(),
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['import' => 'This import has already been confirmed or cancelled.']);
        }

        $this->audit->log(AuditAction::VOTER_IMPORT_CONFIRMED, AuditResult::SUCCESS, $import);
        ProcessVoterImport::dispatch($import->getKey(), $by->getKey());
    }

    public function cancel(VoterImport $import): void
    {
        $updated = VoterImport::query()->whereKey($import->getKey())
            ->where('status', ImportStatus::PREVIEWED->value)
            ->update(['status' => ImportStatus::CANCELLED->value, 'updated_at' => now()]);
        if ($updated === 1) {
            Storage::disk(self::DISK)->delete($import->stored_path);
            $this->audit->log(AuditAction::VOTER_IMPORT_CANCELLED, AuditResult::SUCCESS, $import);
        }
    }

    /**
     * Apply a confirmed import atomically. Called by the queued job.
     */
    public function process(VoterImport $import, User $by): void
    {
        try {
            $absolute = Storage::disk(self::DISK)->path($import->stored_path);
            if (! is_file($absolute) || hash_file('sha256', $absolute) !== $import->file_hash) {
                throw new \RuntimeException('The uploaded file changed or is missing since it was previewed.');
            }

            $parsed = $this->parser->parse($absolute, pathinfo($import->stored_path, PATHINFO_EXTENSION));

            $counts = DB::transaction(function () use ($parsed, $import, $by) {
                $valid = $parsed['valid'];
                foreach (array_keys($this->emailConflictsWithRegister($valid)) as $rowNumber) {
                    unset($valid[$rowNumber]);
                }

                $counts = ['new' => 0, 'updated' => 0, 'unchanged' => 0];
                foreach (array_chunk($valid, 500, true) as $chunk) {
                    $existing = Voter::query()
                        ->whereIn('service_number', array_column($chunk, 'service_number'))
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('service_number');

                    foreach ($chunk as $row) {
                        /** @var Voter|null $voter */
                        $voter = $existing->get($row['service_number']);
                        if (! $voter) {
                            $voter = new Voter;
                            $voter->fill($row);
                            // The register file is the source of truth for onboarding: a voter
                            // from an approved import is verified on arrival, not left pending
                            // for a separate manual step.
                            $voter->forceFill([
                                'created_by' => $by->getKey(), 'updated_by' => $by->getKey(), 'registered_at' => now(),
                                'verification_status' => VerificationStatus::VERIFIED, 'verified_at' => now(), 'verified_by' => $by->getKey(),
                            ])->save();
                            $counts['new']++;

                            continue;
                        }
                        if (! $import->update_existing) {
                            $counts['unchanged']++;

                            continue;
                        }
                        $voter->fill(array_intersect_key($row, array_flip(self::TRACKED_FIELDS)));
                        if ($voter->isDirty()) {
                            $voter->updated_by = $by->getKey();
                            $voter->save();
                            $counts['updated']++;
                        } else {
                            $counts['unchanged']++;
                        }
                    }
                }

                $import->forceFill([
                    'status' => ImportStatus::COMPLETED,
                    'imported_count' => $counts['new'],
                    'updated_count' => $counts['updated'],
                    'unchanged_count' => $counts['unchanged'],
                    'completed_at' => now(),
                ])->save();

                return $counts;
            });

            $this->audit->log(AuditAction::VOTER_IMPORT_COMPLETED, AuditResult::SUCCESS, $import, $counts,
                ['type' => 'ADMIN', 'id' => $by->getKey(), 'label' => $by->email]);
        } catch (\Throwable $e) {
            // The transaction rolled back: the register is exactly as it was before.
            VoterImport::query()->whereKey($import->getKey())->update([
                'status' => ImportStatus::FAILED->value,
                'failure_reason' => mb_substr($e->getMessage(), 0, 500),
                'updated_at' => now(),
            ]);
            $this->audit->failure(AuditAction::VOTER_IMPORT_FAILED, $import, ['error' => mb_substr($e->getMessage(), 0, 250)],
                ['type' => 'ADMIN', 'id' => $by->getKey(), 'label' => $by->email]);
            report($e);
        }
    }

    /**
     * @param  array<int, array<string, ?string>>  $valid
     * @return array{new:int, updated:int, unchanged:int}
     */
    private function diff(array $valid, bool $updateExisting): array
    {
        $out = ['new' => 0, 'updated' => 0, 'unchanged' => 0];
        foreach (array_chunk($valid, 1000) as $chunk) {
            $existing = Voter::query()->whereIn('service_number', array_column($chunk, 'service_number'))->get()->keyBy('service_number');
            foreach ($chunk as $row) {
                $voter = $existing->get($row['service_number']);
                if (! $voter) {
                    $out['new']++;
                } elseif (! $updateExisting) {
                    $out['unchanged']++;
                } else {
                    $voter->fill(array_intersect_key($row, array_flip(self::TRACKED_FIELDS)));
                    $voter->isDirty() ? $out['updated']++ : $out['unchanged']++;
                }
            }
        }

        return $out;
    }

    /**
     * Rows whose email already belongs to a DIFFERENT registered voter.
     *
     * @param  array<int, array<string, ?string>>  $valid
     * @return array<int, string> rowNumber => message
     */
    private function emailConflictsWithRegister(array $valid): array
    {
        $conflicts = [];
        foreach (array_chunk($valid, 1000, true) as $chunk) {
            $emails = array_filter(array_column($chunk, 'email'));
            $owners = Voter::query()->whereIn('email', $emails)->pluck('service_number', 'email');
            foreach ($chunk as $rowNumber => $row) {
                $owner = $row['email'] ? $owners->get($row['email']) : null;
                if ($owner && $owner !== $row['service_number']) {
                    $conflicts[$rowNumber] = "Email is already registered to Service Number {$owner}.";
                }
            }
        }

        return $conflicts;
    }
}
