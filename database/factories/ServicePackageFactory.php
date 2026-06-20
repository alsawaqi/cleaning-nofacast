<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePackage>
 */
class ServicePackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => $this->faker->randomElement(['Monthly Care', 'One-Time Deep Clean', 'Facility Standard']),
            'description' => $this->faker->sentence(),
            'billing_cycle' => 'monthly',
            'visit_frequency' => 'monthly',
            'visits_per_week' => 1,
            'hours_per_visit' => '3.00',
            'worker_count' => 2,
            'duration_minutes' => 180,
            'expected_labor_minutes' => 360,
            'material_cost_halalas' => 0,
            'price_halalas' => 120000,
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'checklist_template' => [
                ['label' => 'Site arrival photo', 'is_required' => true],
                ['label' => 'Supervisor completion note', 'is_required' => false],
            ],
            'sla_kpi_template' => [],
            'is_active' => true,
        ];
    }
}
