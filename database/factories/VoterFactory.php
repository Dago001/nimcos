<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Enums\VoterEligibility;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST DATA ONLY: fictitious officers.
 *
 * @extends Factory<Voter>
 */
class VoterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'surname' => mb_strtoupper(fake()->lastName()),
            'first_name' => fake()->firstName(),
            'other_names' => null,
            'rank' => 'INSPECTOR OF IMMIGRATION',
            'command' => 'TEST COMMAND',
            'formation' => 'TEST FORMATION',
            'phone' => '+23470'.str_pad((string) fake()->unique()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => fake()->unique()->safeEmail(),
            'membership_status' => MembershipStatus::ACTIVE,
            'eligibility_status' => VoterEligibility::ELIGIBLE,
            'verification_status' => VerificationStatus::VERIFIED,
            'account_status' => AccountStatus::ACTIVE,
            'is_test_data' => true,
            'registered_at' => now(),
            'verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['verification_status' => VerificationStatus::UNVERIFIED, 'verified_at' => null]);
    }
}
