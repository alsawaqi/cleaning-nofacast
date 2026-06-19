<?php

namespace Database\Factories;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_code' => 'W-'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->name(),
            'phone' => '+9665'.$this->faker->numerify('########'),
            'hired_on' => now()->subYear()->toDateString(),
            'nationality' => 'Saudi Arabia',
            'role_language' => 'ar',
            'job_role' => 'cleaner',
            'status' => 'available',
            'cost_rate_halalas' => 150000,
            'skills' => ['general_cleaning'],
            'certifications' => [],
            'availability_notes' => null,
        ];
    }
}
