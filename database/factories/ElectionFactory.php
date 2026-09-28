<?php

namespace Database\Factories;

use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\ResultStatus;
use App\Models\Election;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST DATA ONLY.
 *
 * @extends Factory<Election>
 */
class ElectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'TEST ELECTION '.fake()->unique()->numberBetween(1000, 9999),
            'code' => 'TEST-'.fake()->unique()->numberBetween(10000, 99999),
            'description' => 'Automated test election.',
            'election_type' => ElectionType::GENERAL,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(10),
            'status' => ElectionStatus::DRAFT,
            'result_status' => ResultStatus::NOT_CALCULATED,
            'auto_open' => true,
            'auto_close' => true,
            'interim_results_enabled' => false,
            'receipt_year' => (string) now()->year,
            'is_test_data' => true,
        ];
    }
}
