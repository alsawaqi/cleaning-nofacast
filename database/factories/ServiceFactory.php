<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->randomElement(['Office Cleaning', 'Home Deep Clean', 'Glass Facade Cleaning']);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(100, 999),
            'category' => 'cleaning',
            'description' => $this->faker->sentence(),
            'pricing_type' => 'fixed',
            'base_price_halalas' => 45000,
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'materials_included' => true,
            'minimum_billable_minutes' => 60,
            'default_workers' => 1,
            'default_duration_minutes' => 120,
            'default_material_cost_halalas' => 0,
            'material_policy' => 'company_supplied_included',
            'included_materials' => [],
            'extra_hour_rate_halalas' => 0,
            'overtime_policy' => 'none',
            'allowed_frequencies' => ['once', 'weekly', 'monthly'],
            'required_certificates' => [],
            'checklist_template' => [
                ['label' => 'Sweep floors'],
                ['label' => 'Mop floors'],
                ['label' => 'Remove waste'],
            ],
            'sla_kpi_template' => [],
            'is_active' => true,
        ];
    }
}
