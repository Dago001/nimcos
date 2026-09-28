<?php

namespace App\Services\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Re-computes the audit hash chain to detect edits, deletions or insertions made outside the app. */
class AuditChainVerifier
{
    /** @return array{ok: bool, checked: int, broken_at: ?int, reason: ?string} */
    public function verify(): array
    {
        $prev = AuditLogger::GENESIS;
        $checked = 0;

        foreach (DB::table('audit_logs')->orderBy('id')->lazyById(2000) as $row) {
            $checked++;
            if ($row->prev_hash !== $prev) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row->id, 'reason' => 'Previous-hash link does not match (a row may have been deleted or inserted).'];
            }
            $computed = AuditLogger::computeHash((array) $row, CarbonImmutable::parse($row->created_at), $row->prev_hash);
            if (! hash_equals($row->hash, $computed)) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row->id, 'reason' => 'Row content does not match its hash (the row may have been altered).'];
            }
            $prev = $row->hash;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null, 'reason' => null];
    }
}
