<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\MembershipStatus;
use App\Enums\NisRank;
use App\Enums\VerificationStatus;
use App\Enums\VoterEligibility;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A register entry. Voters authenticate with Service Number + OTP only, so the
 * password-related Authenticatable methods intentionally return nothing.
 */
class Voter extends Model implements Authenticatable
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'service_number', 'surname', 'first_name', 'other_names', 'rank',
        'command', 'formation', 'phone', 'email', 'membership_status',
    ];

    protected function casts(): array
    {
        return [
            'membership_status' => MembershipStatus::class,
            'eligibility_status' => VoterEligibility::class,
            'verification_status' => VerificationStatus::class,
            'account_status' => AccountStatus::class,
            'registered_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_test_data' => 'boolean',
        ];
    }

    public function electionVoters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    public function fullName(): string
    {
        return trim($this->surname.', '.$this->first_name.' '.($this->other_names ?? ''));
    }

    /** Full rank title for display, e.g. "Deputy Comptroller of Immigration (DCI)". Falls back to the raw value for legacy/imported data that predates the fixed rank list. */
    public function rankLabel(): ?string
    {
        return $this->rank ? (NisRank::tryFrom($this->rank)?->label() ?? $this->rank) : null;
    }

    /** Can this register entry be authorised for elections at all? */
    public function isEligibleForAuthorisation(): bool
    {
        return $this->account_status === AccountStatus::ACTIVE
            && $this->membership_status === MembershipStatus::ACTIVE
            && $this->eligibility_status === VoterEligibility::ELIGIBLE
            && $this->verification_status === VerificationStatus::VERIFIED;
    }

    public function scopeAuthorisable(Builder $query): Builder
    {
        return $query->where('account_status', AccountStatus::ACTIVE->value)
            ->where('membership_status', MembershipStatus::ACTIVE->value)
            ->where('eligibility_status', VoterEligibility::ELIGIBLE->value)
            ->where('verification_status', VerificationStatus::VERIFIED->value);
    }

    public function maskedPhone(): ?string
    {
        return $this->phone ? '•••• '.substr($this->phone, -4) : null;
    }

    public function maskedEmail(): ?string
    {
        if (! $this->email || ! str_contains($this->email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $this->email, 2);

        return mb_substr($local, 0, 1).'•••@'.$domain;
    }

    // --- Authenticatable (session guard only; there is no voter password) ---

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Voters never receive remember-me tokens.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
