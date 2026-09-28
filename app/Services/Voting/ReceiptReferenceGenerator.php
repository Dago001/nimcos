<?php

namespace App\Services\Voting;

use App\Models\Ballot;
use App\Models\Election;

/**
 * Ballot references such as NIM-2026-7KQ4M2XD: random (Crockford base32, no
 * ambiguous characters), unrelated to the voter, the time, or the selections.
 */
class ReceiptReferenceGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(Election $election): string
    {
        for ($i = 0; $i < 10; $i++) {
            $reference = sprintf('%s-%s-%s', config('nimcos.receipt_prefix', 'NIM'), $election->receipt_year, $this->randomString(8));
            if (! Ballot::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique ballot reference.');
    }

    private function randomString(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, 31)];
        }

        return $out;
    }
}
