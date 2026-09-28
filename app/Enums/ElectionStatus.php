<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ElectionStatus: string
{
    use EnumHelpers;

    case DRAFT = 'DRAFT';
    case SCHEDULED = 'SCHEDULED';
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
    case RESULTS_PUBLISHED = 'RESULTS_PUBLISHED';
    case ARCHIVED = 'ARCHIVED';

    /**
     * The only permitted transitions (spec §4). CLOSED can never return to OPEN.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SCHEDULED],
            self::SCHEDULED => [self::DRAFT, self::OPEN],
            self::OPEN => [self::CLOSED],
            self::CLOSED => [self::RESULTS_PUBLISHED],
            self::RESULTS_PUBLISHED => [self::ARCHIVED],
            self::ARCHIVED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Positions, candidates and dates may only be edited while in draft. */
    public function isStructureEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /** Voter eligibility may be granted until the election opens. */
    public function isEligibilityEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED], true);
    }

    public function hasClosed(): bool
    {
        return in_array($this, [self::CLOSED, self::RESULTS_PUBLISHED, self::ARCHIVED], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::SCHEDULED => 'info',
            self::OPEN => 'live',
            self::CLOSED => 'warning',
            self::RESULTS_PUBLISHED => 'success',
            self::ARCHIVED => 'muted',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open (Live)',
            self::RESULTS_PUBLISHED => 'Results Published',
            default => ucwords(strtolower($this->value)),
        };
    }
}
